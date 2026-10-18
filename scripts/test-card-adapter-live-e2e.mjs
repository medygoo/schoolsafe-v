import assert from "node:assert/strict";
import fs from "node:fs";
import http from "node:http";
import os from "node:os";
import path from "node:path";
import { createHmac, randomBytes, randomUUID } from "node:crypto";
import { createRequire } from "node:module";
import { pathToFileURL } from "node:url";
import pg from "pg";
import { hash as argonHash } from "@node-rs/argon2";
import { tsImport } from "tsx/esm/api";
import { installSchoolDatabase } from "./install-school-db.mjs";
import { repositoryRoot } from "./installation-plan.mjs";

const require = createRequire(import.meta.url);
const { adaptRosarioCard } = require("../app/modules/cards/card-adapter.js");

const digest = (value) => createHmac("sha256", "schoolsafe-card-e2e").update(value).digest("hex");
const asObject = (value) => (typeof value === "string" ? JSON.parse(value) : value);
const secret = process.env.CARD_HMAC_SECRET;
assert.ok(secret && secret.length >= 32, "CARD_HMAC_SECRET required");

function databaseUrl(base, database) {
  const url = new URL(base);
  url.pathname = "/" + database;
  return url;
}

function roleUrl(base, name, role, password) {
  const url = databaseUrl(base, name);
  url.username = "schoolsafe_" + role;
  url.password = password;
  return url.toString();
}

async function recreate(adminUrl, name) {
  const client = new pg.Client({ connectionString: adminUrl, connectionTimeoutMillis: 5000 });
  await client.connect();
  await client.query("select pg_terminate_backend(pid) from pg_stat_activity where datname=$1 and pid<>pg_backend_pid()", [name]);
  await client.query('drop database if exists "' + name + '"');
  await client.query('create database "' + name + '"');
  await client.end();
}

function sqlIdent(value) {
  if (!/^[a-z_]+$/.test(value)) throw new Error("Unsafe SQL identifier");
  return value;
}

function startCardFacade(pool, context) {
  const tables = {
    student_cards: "app.student_cards",
    students: "app.students",
    classes: "app.classes",
    student_guardians: "app.student_guardians",
    school_settings: "app.school_settings",
    security_events: "app.security_events",
  };
  const server = http.createServer(async (req, res) => {
    const url = new URL(req.url || "/", "http://127.0.0.1");
    const chunks = [];
    for await (const chunk of req) chunks.push(chunk);
    const raw = Buffer.concat(chunks).toString("utf8");
    const body = raw ? JSON.parse(raw) : {};
    const client = await pool.connect();
    try {
      await client.query("begin");
      await client.query("select api.set_request_context($1,$2,$3,$4)", [
        context.userId,
        context.profileId,
        context.schoolId,
        randomUUID(),
      ]);
      if (url.pathname === "/rest/v1/rpc/is_student_operational") {
        const result = await client.query("select app.is_student_operational($1) as ok", [body.student_id]);
        await client.query("commit");
        res.writeHead(200, { "content-type": "application/json" });
        res.end(JSON.stringify(result.rows[0].ok === true));
        return;
      }
      const name = decodeURIComponent(url.pathname.replace(/^\/rest\/v1\//, ""));
      const table = tables[name];
      if (!table) throw new Error("Unmapped table " + name);
      const object = String(req.headers.accept || "").includes("application/vnd.pgrst.object+json");
      const selected = (url.searchParams.get("select") || "id")
        .split(",")
        .map((column) => sqlIdent(column.trim()));
      if (req.method === "POST") {
        const columns = Object.keys(body).map(sqlIdent);
        const values = columns.map((column) => (body[column] && typeof body[column] === "object" ? JSON.stringify(body[column]) : body[column]));
        const casts = columns.map((column, index) => (column === "metadata" ? `$${index + 1}::jsonb` : `$${index + 1}`));
        const inserted = await client.query(
          `insert into ${table} (${columns.join(",")}) values (${casts.join(",")}) returning ${selected.join(",")}`,
          values,
        );
        await client.query("commit");
        sendRow(res, inserted.rows, object);
        return;
      }
      const filters = [];
      const params = [];
      for (const [key, value] of url.searchParams) {
        if (key === "select" || key === "order") continue;
        const match = /^eq\.(.*)$/.exec(value);
        if (!match) continue;
        params.push(match[1] === "true" ? true : match[1] === "false" ? false : match[1]);
        filters.push(`${sqlIdent(key)} = $${params.length}`);
      }
      let order = "";
      const ordering = url.searchParams.get("order");
      if (ordering) {
        const [column, direction] = ordering.split(".");
        order = ` order by ${sqlIdent(column)} ${direction === "desc" ? "desc" : "asc"}`;
      }
      const where = filters.length ? ` where ${filters.join(" and ")}` : "";
      const found = await client.query(`select ${selected.join(",")} from ${table}${where}${order}`, params);
      await client.query("commit");
      if (object && found.rows.length !== 1) {
        res.writeHead(406, { "content-type": "application/json" });
        res.end(JSON.stringify({ code: "PGRST116", message: "JSON object requested, multiple or no rows" }));
        return;
      }
      sendRow(res, found.rows, object);
    } catch (error) {
      await client.query("rollback").catch(() => {});
      res.writeHead(400, { "content-type": "application/json" });
      res.end(JSON.stringify({ message: error.message, code: error.code || "FACADE" }));
    } finally {
      client.release();
    }
  });
  return new Promise((resolve) => {
    server.listen(0, "127.0.0.1", () => {
      const address = server.address();
      resolve({ server, url: `http://127.0.0.1:${address.port}` });
    });
  });
}

function sendRow(res, rows, object) {
  const normalized = rows.map((row) => ({
    ...row,
    occurred_at: row.occurred_at instanceof Date ? row.occurred_at.toISOString() : row.occurred_at,
  }));
  res.writeHead(200, { "content-type": "application/json" });
  res.end(JSON.stringify(object ? normalized[0] : normalized));
}

async function prove(base, name, passwords) {
  const admin = new pg.Client({ connectionString: databaseUrl(base, name).toString() });
  const auth = new pg.Client({ connectionString: roleUrl(base, name, "auth", passwords.auth) });
  const migrator = new pg.Client({ connectionString: roleUrl(base, name, "migrator", passwords.migrator) });
  const pool = new pg.Pool({ connectionString: roleUrl(base, name, "api", passwords.api), max: 2 });
  const scanPool = new pg.Pool({ connectionString: databaseUrl(base, name).toString(), max: 2 });
  await Promise.all([admin.connect(), auth.connect(), migrator.connect()]);
  let facade;
  try {
    const tokenHash = digest(randomBytes(32).toString("hex"));
    const email = "cards-" + randomUUID() + "@example.test";
    const passwordHash = await argonHash(randomBytes(32).toString("hex"), { memoryCost: 19456, timeCost: 2, parallelism: 1 });
    const payload = {
      identity: { name_fr: "École Carte Réelle" },
      cycles: ["primary"],
      academic_year: { label: "2026-2027", starts_on: "2026-09-01", ends_on: "2027-07-31", periods: "Trimestres" },
      contact: {},
      brand: {},
    };
    await migrator.query("select ops.authorize_school_setup($1,$2)", [tokenHash, 3600]);
    const staged = asObject((await auth.query("select api.setup_stage_school($1,$2::jsonb) r", [tokenHash, JSON.stringify(payload)])).rows[0].r);
    const created = asObject((await auth.query("select api.setup_complete_school($1,$2,$3,$4,$5,$6) r", [tokenHash, email, passwordHash, "Carte", "Admin", null])).rows[0].r);
    const schoolId = staged.school_id;
    const yearId = staged.academic_year_id;
    const classId = randomUUID();
    const subjectId = randomUUID();
    const studentId = randomUUID();
    const locationId = randomUUID();
    const motto = "Grandir ensemble";
    const phone = "+243810009001";
    const photoPath = `/uploads/students/${schoolId}/${studentId}.jpg`;
    const photoFile = path.join(os.tmpdir(), `schoolsafe-card-${studentId}.jpg`);
    fs.writeFileSync(photoFile, Buffer.from([0xff, 0xd8, 0xff, 0xd9]));

    await admin.query("update app.schools set motto = $2 where id = $1", [schoolId, motto]);
    await admin.query(
      "insert into app.classes(id,school_id,academic_year_id,cycle_key,name,option,teacher_profile_id,card_color,card_color_soft,card_color_dark,card_pat,card_family) values($1,$2,$3,'primary','3e Primaire','Générale',$4,'#1446aa','#d9e4ff','#0d2f78','rdc-okapi','A')",
      [classId, schoolId, yearId, created.profile_id],
    );
    await admin.query("insert into app.subjects(id,school_id,academic_year_id,code,name) values($1,$2,$3,'MATH','Math')", [subjectId, schoolId, yearId]);
    await admin.query(
      "insert into app.students(id,school_id,class_id,matricule,first_name,middle_name,last_name,date_of_birth,lifecycle_status) values($1,$2,$3,'MAT-9','Amina','Wa','Kabila','2016-04-02','active')",
      [studentId, schoolId, classId],
    );
    await admin.query(
      "insert into app.student_enrollments(school_id,student_id,academic_year_id,class_id,status,starts_on) values($1,$2,$3,$4,'active','2026-09-01')",
      [schoolId, studentId, yearId, classId],
    );
    await admin.query(
      "insert into app.student_guardians(school_id,student_id,guardian_type,full_name,phone,is_primary,is_authorized_pickup,is_active) values($1,$2,'mere','Marie Kabila',$3,true,true,true)",
      [schoolId, studentId, phone],
    );
    await admin.query(
      "insert into app.locations(id,school_id,code,label) values($1,$2,'PORTAIL','Portail test')",
      [locationId, schoolId],
    );
    await admin.query(
      "insert into app.school_settings(school_id,lockdown_active) values($1,false) on conflict (school_id) do update set lockdown_active = false",
      [schoolId],
    );

    const context = { userId: created.user_id, profileId: created.profile_id, schoolId, requestId: randomUUID() };
    const { createPedagogyNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/pedagogynative/service.ts")).href, import.meta.url);
    const { createStudentRecordNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/studentrecordnative/service.ts")).href, import.meta.url);
    const { createCardsNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/cardsnative/service.ts")).href, import.meta.url);
    const { createSecurityService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/security/service.ts")).href, import.meta.url);
    const { buildApp } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/app.ts")).href, import.meta.url);

    const pedagogy = createPedagogyNativeService(pool);
    await pedagogy.setHomeroom(context, created.profile_id, classId, subjectId, yearId);
    const assignments = await pedagogy.listTeacherAssignments(context);
    const tutor = assignments.find((row) => row.class_id === classId && row.is_tutor === true);
    assert.ok(tutor, "real homeroom tutor");

    const records = createStudentRecordNativeService(pool);
    const cards = createCardsNativeService(pool);
    const recorded = await records.setPhoto(context, studentId, photoPath);
    assert.equal(recorded.photo_path, photoPath);
    const family = await records.getFamily(context, studentId);
    assert.equal(Object.hasOwn(family[0], "phone"), false);
    const phones = await cards.studentCardGuardianPhones(context, studentId);
    assert.equal(phones.find((item) => item.id === family[0].id).phone, phone);
    const visuals = await cards.classCardVisualList(context);
    const visual = visuals.find((item) => item.id === classId);
    assert.equal(visual.teacher_profile_id, created.profile_id);
    assert.equal(visual.card_pat, "rdc-okapi");
    const mottoRow = await cards.schoolCardMotto(context);
    assert.equal(mottoRow.motto, motto);
    await admin.query(
      "insert into app.scan_control_grants(school_id, profile_id, capability, granted_by, reason) values($1,$2,'CARD_CREATE',$2,'Emission adaptateur')",
      [schoolId, created.profile_id],
    );
    const credential = await cards.issueCardCredential(context, studentId);
    assert.match(credential.qr_payload, /^schoolsafe:\/\/card\/[^/]+\/[^/]+$/);
    assert.equal(credential.signature.length, 32);
    assert.notEqual(createHmac("sha256", secret).update(credential.card_number).digest("hex"), credential.signature);

    const familyDef = (await admin.query("select pg_get_functiondef('api.student_family_read(uuid)'::regprocedure) def")).rows[0].def;
    const oldConfigDef = (await admin.query("select pg_get_functiondef('api.class_card_config_list()'::regprocedure) def")).rows[0].def;
    assert.equal(familyDef.includes("'phone'"), false);
    assert.equal(oldConfigDef.includes("c.teacher_id"), true);
    assert.equal(oldConfigDef.includes("c.teacher_profile_id"), false);

    const years = (await admin.query("select id,label from app.academic_years where id=$1", [yearId])).rows;
    const view = adaptRosarioCard({
      record: recorded,
      family,
      guardianPhones: phones,
      cardMotto: mottoRow.motto,
      schoolSettings: { identity: { name: "École Carte Réelle" }, brand: {}, contact: {} },
      academicYears: years,
      assignments,
      classRow: visual,
      cardConfig: visual,
      credential,
      selectedAuthorizedId: "",
    });
    assert.equal(view.student.last_name, "Kabila");
    assert.equal(view.student.parent_phone, phone);
    assert.equal(view.school.motto, motto);
    assert.equal(view.teacher.name, tutor.teacher_name);
    assert.equal(view.year, "2026-2027");
    assert.equal(view.student.qr, credential.qr_payload);

    globalThis.document = {
      createElement() {
        const node = { textContent: "" };
        Object.defineProperty(node, "innerHTML", { get() { return node.textContent; } });
        return node;
      },
    };
    const renderer = await import(pathToFileURL(path.join(repositoryRoot, "app/modules/cards/card-renderer.js")).href);
    const built = renderer.ssBuildBadge(
      view.student,
      view.classData,
      view.teacher,
      view.year,
      renderer.ssGetClassPat(view.classData, "badge"),
      "both",
      view.school,
      "",
    );
    assert.equal(built.qr, credential.qr_payload);
    assert.equal(built.recto.includes("Kabila"), true);
    assert.equal(built.qr.includes("schoolsafe://student/"), false);

    facade = await startCardFacade(scanPool, context);
    const security = createSecurityService(facade.url, "schoolsafe-card-e2e-service-role", secret);
    const app = buildApp({
      security: {
        service: security,
        resolveProfileAndSchool: async () => ({ profileId: created.profile_id, schoolId }),
        access: { hasPermission: async () => true, hasScope: async () => true },
      },
    });
    const scan = (qr) => app.inject({
      method: "POST",
      url: "/security/scan",
      headers: { authorization: "Bearer valid-token" },
      payload: { qr_payload: qr, event_type: "entry", location_id: locationId },
    });
    const accepted = await scan(built.qr);
    assert.equal(accepted.statusCode, 200, accepted.body);
    assert.equal(accepted.json().data.decision, "allowed");
    assert.equal(accepted.json().data.student.matricule, "MAT-9");
    const stored = await admin.query("select decision from app.security_events where student_id=$1", [studentId]);
    assert.equal(stored.rows[0].decision, "allowed");

    const studentQr = await scan(`schoolsafe://student/${view.student.mat}`);
    assert.equal(studentQr.statusCode >= 400, true);
    const hexQr = await scan(`schoolsafe://card/${credential.card_number}/${createHmac("sha256", secret).update(credential.card_number).digest("hex")}`);
    assert.equal(hexQr.statusCode >= 400, true);
    fs.rmSync(photoFile, { force: true });

    console.log("REAL_SCHOOL_DATA_TEST=PASS");
    console.log("REAL_CARD_CREATED=PASS");
    console.log("REAL_SERVER_SIGNATURE=PASS");
    console.log("REAL_RENDERED_QR=PASS");
    console.log("REAL_HTTP_SCAN=PASS");
    console.log("LEGACY_STUDENT_QR_REFUSED=PASS");
    console.log("LEGACY_HEX_SIGNATURE_REFUSED=PASS");
  } finally {
    if (facade) await new Promise((resolve) => facade.server.close(resolve));
    await Promise.allSettled([admin.end(), auth.end(), migrator.end(), pool.end(), scanPool.end()]);
  }
}

const base = process.env.DATABASE_URL;
const passwords = Object.fromEntries(["api", "auth", "worker", "migrator"].map((role) => [role, process.env["SCHOOLSAFE_" + role.toUpperCase() + "_PASSWORD"]]));
assert.ok(base, "DATABASE_URL required");
for (const role of ["api", "auth", "worker", "migrator"]) assert.ok(passwords[role] && passwords[role].length >= 32, role);

const name = "schoolsafe_test_cardv2";
try {
  await recreate(base, name);
  await installSchoolDatabase({ connectionString: databaseUrl(base, name).toString(), database: name, mode: "apply", passwords, log: () => {} });
  await prove(base, name, passwords);
} catch (error) {
  console.error(error.stack || error.message);
  process.exitCode = 1;
}
