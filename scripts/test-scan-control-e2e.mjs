import assert from "node:assert/strict";
import http from "node:http";
import { randomBytes, randomUUID } from "node:crypto";
import { pathToFileURL } from "node:url";
import path from "node:path";
import pg from "pg";
import { hash as argonHash } from "@node-rs/argon2";
import { tsImport } from "tsx/esm/api";
import { installSchoolDatabase } from "./install-school-db.mjs";
import { repositoryRoot } from "./installation-plan.mjs";

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
function startFacade(pool, context) {
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
      await client.query("select api.set_request_context($1,$2,$3,$4)", [context.userId, context.profileId, context.schoolId, randomUUID()]);
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
      const selected = (url.searchParams.get("select") || "id").split(",").map((column) => sqlIdent(column.trim()));
      if (req.method === "POST") {
        const columns = Object.keys(body).map(sqlIdent);
        const values = columns.map((column) => (body[column] && typeof body[column] === "object" ? JSON.stringify(body[column]) : body[column]));
        const casts = columns.map((column, index) => (column === "metadata" ? `$${index + 1}::jsonb` : `$${index + 1}`));
        const inserted = await client.query(`insert into ${table} (${columns.join(",")}) values (${casts.join(",")}) returning ${selected.join(",")}`, values);
        await client.query("commit");
        const normalized = inserted.rows.map((row) => ({ ...row, occurred_at: row.occurred_at instanceof Date ? row.occurred_at.toISOString() : row.occurred_at }));
        res.writeHead(200, { "content-type": "application/json" });
        res.end(JSON.stringify(object ? normalized[0] : normalized));
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
      const ordering = url.searchParams.get("order");
      let order = "";
      if (ordering) {
        const [column, direction] = ordering.split(".");
        order = ` order by ${sqlIdent(column)} ${direction === "desc" ? "desc" : "asc"}`;
      }
      const where = filters.length ? ` where ${filters.join(" and ")}` : "";
      const found = await client.query(`select ${selected.join(",")} from ${table}${where}${order}`, params);
      await client.query("commit");
      if (object && found.rows.length !== 1) {
        res.writeHead(406, { "content-type": "application/json" });
        res.end(JSON.stringify({ message: "JSON object requested, multiple or no rows" }));
        return;
      }
      const normalized = found.rows.map((row) => ({ ...row, occurred_at: row.occurred_at instanceof Date ? row.occurred_at.toISOString() : row.occurred_at }));
      res.writeHead(200, { "content-type": "application/json" });
      res.end(JSON.stringify(object ? normalized[0] : normalized));
    } catch (error) {
      await client.query("rollback").catch(() => {});
      res.writeHead(400, { "content-type": "application/json" });
      res.end(JSON.stringify({ message: error.message }));
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

async function prove(base, name, passwords) {
  const admin = new pg.Client({ connectionString: databaseUrl(base, name).toString() });
  const auth = new pg.Client({ connectionString: roleUrl(base, name, "auth", passwords.auth) });
  const migrator = new pg.Client({ connectionString: roleUrl(base, name, "migrator", passwords.migrator) });
  const pool = new pg.Pool({ connectionString: roleUrl(base, name, "api", passwords.api), max: 4 });
  const scanPool = new pg.Pool({ connectionString: databaseUrl(base, name).toString(), max: 2 });
  await Promise.all([admin.connect(), auth.connect(), migrator.connect()]);
  let facade;
  try {
    const tokenHash = randomBytes(32).toString("hex");
    const email = "scan-" + randomUUID() + "@example.test";
    const passwordHash = await argonHash(randomBytes(32).toString("hex"), { memoryCost: 19456, timeCost: 2, parallelism: 1 });
    const payload = {
      identity: { name_fr: "École Scan Réelle" },
      cycles: ["primary"],
      academic_year: { label: "2026-2027", starts_on: "2026-09-01", ends_on: "2027-07-31", periods: "Trimestres" },
      contact: {},
      brand: {},
    };
    await migrator.query("select ops.authorize_school_setup($1,$2)", [tokenHash, 3600]);
    const staged = asObject((await auth.query("select api.setup_stage_school($1,$2::jsonb) r", [tokenHash, JSON.stringify(payload)])).rows[0].r);
    const created = asObject((await auth.query("select api.setup_complete_school($1,$2,$3,$4,$5,$6) r", [tokenHash, email, passwordHash, "Jeanne", "Mbayo", null])).rows[0].r);
    const schoolId = staged.school_id;
    const yearId = staged.academic_year_id;
    const jeanne = { userId: created.user_id, profileId: created.profile_id, schoolId, requestId: randomUUID() };
    await admin.query("update iam.profiles set display_name = 'Jeanne Mbayo' where id = $1", [jeanne.profileId]);

    const classId = randomUUID();
    const otherClassId = randomUUID();
    const subjectId = randomUUID();
    const locationId = randomUUID();
    await admin.query(
      "insert into app.classes(id,school_id,academic_year_id,cycle_key,name,option,teacher_profile_id) values($1,$2,$3,'primary','3e Primaire','Générale',$4)",
      [classId, schoolId, yearId, jeanne.profileId],
    );
    await admin.query(
      "insert into app.classes(id,school_id,academic_year_id,cycle_key,name,option) values($1,$2,$3,'primary','4e Primaire','Générale')",
      [otherClassId, schoolId, yearId],
    );
    await admin.query("insert into app.subjects(id,school_id,academic_year_id,code,name) values($1,$2,$3,'MATH','Math')", [subjectId, schoolId, yearId]);
    await admin.query("insert into app.locations(id,school_id,code,label) values($1,$2,'PORTAIL','Portail')", [locationId, schoolId]);
    await admin.query("insert into app.school_settings(school_id,lockdown_active) values($1,false) on conflict (school_id) do update set lockdown_active = false", [schoolId]);

    async function addPerson(displayName) {
      const userId = randomUUID();
      const profileId = randomUUID();
      await admin.query("insert into iam.users(id,email,is_active) values($1,$2,true)", [userId, displayName.toLowerCase().replace(/\s+/g, ".") + "@example.test"]);
      await admin.query(
        "insert into iam.profiles(id,user_id,school_id,display_name,is_active,account_status) values($1,$2,$3,$4,true,'active')",
        [profileId, userId, schoolId, displayName],
      );
      await admin.query("begin");
      try {
        await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, schoolId, randomUUID()]);
        await admin.query(
          "insert into iam.profile_roles(school_id,profile_id,role_id,assigned_by,is_active) select school_id,$1,role_id,$2,true from iam.profile_roles where profile_id = $2",
          [profileId, jeanne.profileId],
        );
        await admin.query("commit");
      } catch (error) {
        await admin.query("rollback");
        throw error;
      }
      return { userId, profileId, schoolId, requestId: randomUUID() };
    }
    const paul = await addPerson("Paul Gardien");
    const staff = await addPerson("Aline Personnel");

    async function addStudent(matricule, first, last, classRef) {
      const studentId = randomUUID();
      await admin.query(
        "insert into app.students(id,school_id,class_id,matricule,first_name,last_name,date_of_birth,lifecycle_status) values($1,$2,$3,$4,$5,$6,'2016-04-02','active')",
        [studentId, schoolId, classRef, matricule, first, last],
      );
      await admin.query(
        "insert into app.student_enrollments(school_id,student_id,academic_year_id,class_id,status,starts_on) values($1,$2,$3,$4,'active','2026-09-01')",
        [schoolId, studentId, yearId, classRef],
      );
      return studentId;
    }
    async function addGuardian(studentId, type, fullName, phone, authorized) {
      const id = randomUUID();
      await admin.query(
        "insert into app.student_guardians(id,school_id,student_id,guardian_type,full_name,phone,is_primary,is_authorized_pickup,is_active) values($1,$2,$3,$4,$5,$6,true,$7,true)",
        [id, schoolId, studentId, type, fullName, phone, authorized],
      );
      return id;
    }

    const amina = await addStudent("MAT-A", "Amina", "Kabila", classId);
    const boris = await addStudent("MAT-B", "Boris", "Ilunga", otherClassId);
    const claire = await addStudent("MAT-C", "Claire", "Mbuyi", classId);
    const diane = await addStudent("MAT-D", "Diane", "Ngoy", classId);
    const elise = await addStudent("MAT-E", "Elise", "Tshibanda", classId);
    const farid = await addStudent("MAT-F", "Farid", "Kasongo", classId);
    const gina = await addStudent("MAT-G", "Gina", "Mwamba", classId);
    const hugo = await addStudent("MAT-H", "Hugo", "Banza", classId);
    const marie = await addGuardian(amina, "mere", "Marie Kabila", "+243810009001", true);
    const dianeMother = await addGuardian(diane, "mere", "Rita Ngoy", "+243810009002", true);
    const refused = await addGuardian(elise, "tuteur", "Oscar Tshibanda", "+243810009003", false);
    await addGuardian(claire, "mere", "Lara Mbuyi", "+243810009004", true);

    const { createPedagogyNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/pedagogynative/service.ts")).href, import.meta.url);
    const { createCardsNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/cardsnative/service.ts")).href, import.meta.url);
    const { createFamilyNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/familynative/service.ts")).href, import.meta.url);
    const { createDeviceHubService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/devicehub/service.ts")).href, import.meta.url);
    const { createFinanceNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/financenative/service.ts")).href, import.meta.url);
    const { createSecurityService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/security/service.ts")).href, import.meta.url);
    const { createScanControlService } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/scancontrol/service.ts")).href, import.meta.url);
    const { buildApp } = await tsImport(pathToFileURL(path.join(repositoryRoot, "server/src/app.ts")).href, import.meta.url);

    const pedagogy = createPedagogyNativeService(pool);
    await pedagogy.setHomeroom(jeanne, jeanne.profileId, classId, subjectId, yearId);
    const cards = createCardsNativeService(pool);
    const family = createFamilyNativeService(pool);
    const deviceHub = createDeviceHubService(pool);
    const finance = createFinanceNativeService(pool);
    facade = await startFacade(scanPool, jeanne);
    const security = createSecurityService(facade.url, "schoolsafe-scan-e2e-service-role", secret);
    const scanControl = createScanControlService(pool, { security, deviceHub, family, cards });

    const caps = ["SCAN_ENTRY", "SCAN_PRE_EXIT", "SCAN_GATE_EXIT", "SCAN_PICKUP", "SCAN_FEES", "SCAN_VERIFY", "CARD_CREATE", "CARD_PRINT", "CARD_LOST", "CARD_REPLACE", "CARD_REPRINT", "CARD_REVOKE"];
    for (const capability of caps) {
      await scanControl.grant(jeanne, { profile_id: jeanne.profileId, capability, action: "assign", reason: "Poste de Jeanne" });
    }
    await scanControl.grant(jeanne, { profile_id: paul.profileId, capability: "SCAN_GATE_EXIT", action: "assign", reason: "Poste de Paul" });

    async function issue(studentId) {
      return cards.issueCardCredential(jeanne, studentId);
    }
    const aminaCard = await issue(amina);
    const claireCard = await issue(claire);
    const dianeCard = await issue(diane);
    const eliseCard = await issue(elise);
    const faridCard = await issue(farid);
    const ginaCard = await issue(gina);
    const hugoCard = await issue(hugo);
    const scan = (actor, mode, qr, extra = {}) => scanControl.scan({ ...actor, requestId: randomUUID() }, {
      mode, qr_payload: qr, location_id: locationId, ...extra,
    });
    const events = (studentId, type) => admin.query("select count(*)::int n from app.security_events where student_id=$1 and event_type=$2", [studentId, type]).then((r) => r.rows[0].n);
    const attendance = (studentId) => admin.query("select arrived_at, left_at from app.attendance_records where student_id=$1 and day=current_date", [studentId]).then((r) => r.rows[0] || null);

    const entered = await scan(jeanne, "entry", aminaCard.qr_payload);
    assert.equal(entered.result, "PRESENT");
    assert.equal(entered.currently_on_site, true);
    assert.equal(await events(amina, "entry"), 1);
    const again = await scan(jeanne, "entry", aminaCard.qr_payload);
    assert.equal(again.result, "ALREADY_PRESENT");
    assert.equal(await events(amina, "entry"), 1);

    const teacher = await scanControl.teacherBoard(jeanne);
    assert.equal(Number(teacher.total), 7);
    assert.equal(Number(teacher.present_today), 1);
    assert.equal(Number(teacher.absent_today), 6);
    assert.equal(Number(teacher.currently_on_site), 1);
    const school = await scanControl.schoolBoard(jeanne);
    assert.equal(Number(school.total_students), 8);
    assert.equal(Number(school.present_today), 1);
    assert.equal(Number(school.absent_today), 7);
    assert.equal(Number(school.currently_on_site), 1);
    assert.equal(Number(school.exited), 0);
    const aminaSite = await scanControl.onSiteStudent(jeanne, amina);
    const borisSite = await scanControl.onSiteStudent(jeanne, boris);
    assert.equal(aminaSite.currently_on_site, true);
    assert.equal(borisSite.currently_on_site, false);
    const classSite = await scanControl.onSiteClass(jeanne, classId);
    assert.equal(Number(classSite.currently_on_site), 1);
    const otherSite = await scanControl.onSiteClass(jeanne, otherClassId);
    assert.equal(Number(otherSite.currently_on_site), 0);

    await assert.rejects(() => scan(jeanne, "pre_exit", aminaCard.qr_payload, { authorized_person_id: marie, human_confirmed: true }), /accès officiel/);
    await assert.rejects(() => scan(jeanne, "gate_exit", aminaCard.qr_payload, { authorized_person_id: marie, human_confirmed: true }), /accès officiel/);
    assert.equal(await events(amina, "exit"), 0);
    assert.equal(await events(amina, "pickup_confirmed"), 0);
    assert.equal((await attendance(amina)).left_at, null);

    const claireIn = await scan(jeanne, "entry", claireCard.qr_payload);
    assert.equal(claireIn.result, "PRESENT");
    const claireGuardian = (await admin.query("select id from app.student_guardians where student_id=$1", [claire])).rows[0].id;
    const claireOut = await scan(jeanne, "pickup", claireCard.qr_payload, { authorized_person_id: claireGuardian });
    assert.equal(claireOut.result, "NEED_HUMAN_CONFIRMATION");
    assert.equal(typeof claireOut.proof_token, "string");
    assert.equal(await events(claire, "exit"), 0);
    assert.equal((await attendance(claire)).left_at, null);
    await admin.query("update app.student_cards set status = 'revoked' where id = $1", [claireCard.card_id]);
    await admin.query("begin");
    try {
      await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, schoolId, randomUUID()]);
      const revokedCard = asObject((await admin.query(
        "select api.pickup_confirm($1,$2,$3,$4,$5,$6) r",
        [claire, claireGuardian, jeanne.profileId, "revoke-" + randomUUID(), null, claireOut.proof_token],
      )).rows[0].r);
      assert.equal(revokedCard.status, "denied");
      assert.equal(revokedCard.code, "CARD_NOT_ACTIVE");
      await admin.query("commit");
    } catch (error) {
      await admin.query("rollback");
      throw error;
    }
    assert.equal(await events(claire, "pickup_confirmed"), 0);
    await admin.query("update app.student_cards set status = 'active' where id = $1", [claireCard.card_id]);

    const portrait = await scanControl.setPortrait(jeanne, dianeMother, `/uploads/authorized-persons/${schoolId}/${dianeMother}.jpg`);
    assert.equal(portrait.photo_path.includes("/uploads/authorized-persons/"), true);
    const guardianColumn = await admin.query("select column_name from information_schema.columns where table_schema='app' and table_name='student_guardians' and column_name='photo_path'");
    assert.equal(guardianColumn.rowCount, 0);
    await admin.query("begin");
    try {
      await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, schoolId, randomUUID()]);
      await admin.query("select api.pickup_photo_review($1,'accepted','Contrôle humain de la photographie')", [dianeMother]);
      await admin.query("commit");
    } catch (error) {
      await admin.query("rollback");
      throw error;
    }
    await scan(jeanne, "entry", dianeCard.qr_payload);
    const choice = await scan(jeanne, "pickup", dianeCard.qr_payload);
    assert.equal(choice.result, "PICKUP_CHOICE");
    const shown = choice.people.find((person) => person.id === dianeMother);
    assert.equal(shown.name, "Rita Ngoy");
    assert.equal(shown.relation, "mere");
    assert.equal(shown.phone, undefined);
    assert.equal(shown.pickup_ready, true);
    assert.equal(shown.photo_path, portrait.photo_path);
    const unconfirmed = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother });
    assert.equal(unconfirmed.result, "NEED_HUMAN_CONFIRMATION");
    assert.equal(typeof unconfirmed.proof_token, "string");
    assert.equal(await events(diane, "pickup_confirmed"), 0);
    assert.equal(await events(diane, "exit"), 0);
    await admin.query("update app.scanner_pickup_proofs set expires_at = now() - interval '1 minute' where token = $1", [unconfirmed.proof_token]);
    const expiredProof = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother, human_confirmed: true, proof_token: unconfirmed.proof_token });
    assert.equal(expiredProof.result, "DENIED");
    assert.equal(expiredProof.reason, "PICKUP_PROOF_EXPIRED");
    const portalProof = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother });
    await admin.query("update app.scanner_pickup_proofs set portal_id = $2 where token = $1", [portalProof.proof_token, randomUUID()]);
    const wrongPortal = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother, human_confirmed: true, proof_token: portalProof.proof_token });
    assert.equal(wrongPortal.result, "DENIED");
    assert.equal(wrongPortal.reason, "PORTAL_DENIED");
    const ready = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother });
    const picked = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother, human_confirmed: true, proof_token: ready.proof_token });
    assert.equal(picked.result, "CONFIRMED");
    assert.equal(picked.decision, "allowed");
    assert.equal(await events(diane, "pickup_confirmed"), 1);
    assert.equal(await events(diane, "exit"), 0);
    assert.ok((await attendance(diane)).left_at);
    const reused = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother, human_confirmed: true, proof_token: ready.proof_token });
    assert.equal(reused.result, "DENIED");
    assert.equal(reused.reason, "PICKUP_PROOF_REUSED");
    const againPrep = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother });
    const secondHandover = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother, human_confirmed: true, proof_token: againPrep.proof_token });
    assert.equal(secondHandover.result, "DENIED");
    assert.equal(secondHandover.reason, "PICKUP_ALREADY_DONE");
    assert.equal(await events(diane, "pickup_confirmed"), 1);
    const withoutProof = await scan(jeanne, "pickup", dianeCard.qr_payload, { authorized_person_id: dianeMother, human_confirmed: true });
    assert.equal(withoutProof.result, "PICKUP_PROOF_REQUIRED");
    const otherSchoolId = randomUUID();
    const foreignToken = randomUUID() + randomUUID();
    await admin.query("insert into app.schools(id, code, name) values($1,$2,'Ecole preuve')", [otherSchoolId, "PV" + otherSchoolId.replace(/-/g, "").slice(0, 10)]);
    await admin.query(
      "insert into app.scanner_pickup_proofs(school_id,student_id,card_id,profile_id,location_id,guardian_id,token,expires_at) values($1,$2,$3,$4,$5,$6,$7,now()+interval '2 minutes')",
      [otherSchoolId, diane, dianeCard.card_id, jeanne.profileId, locationId, dianeMother, foreignToken],
    );
    await admin.query("begin");
    try {
      await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, schoolId, randomUUID()]);
      const bypass = asObject((await admin.query(
        "select api.pickup_confirm($1,$2,$3,$4,$5,$6) r",
        [diane, dianeMother, jeanne.profileId, "bypass-" + randomUUID(), null, null],
      )).rows[0].r);
      assert.equal(bypass.status, "denied");
      assert.equal(bypass.code, "PICKUP_PROOF_REQUIRED");
      const foreignProof = asObject((await admin.query(
        "select api.pickup_confirm($1,$2,$3,$4,$5,$6) r",
        [diane, dianeMother, jeanne.profileId, "foreign-" + randomUUID(), null, foreignToken],
      )).rows[0].r);
      assert.equal(foreignProof.status, "denied");
      assert.equal(foreignProof.code, "PICKUP_PROOF_INVALID");
      await admin.query("commit");
    } catch (error) {
      await admin.query("rollback");
      throw error;
    }
    await assert.rejects(
      () => family.pickupConfirm(jeanne, {
        student_id: diane,
        guardian_id: dianeMother,
        request_key: "family-bypass-" + randomUUID(),
      }),
      (error) => error.statusCode === 403,
    );
    assert.equal(await events(diane, "pickup_confirmed"), 1);

    await scan(jeanne, "entry", eliseCard.qr_payload);
    const pendingDenial = await scan(jeanne, "pickup", eliseCard.qr_payload, { authorized_person_id: refused });
    assert.equal(pendingDenial.result, "NEED_HUMAN_CONFIRMATION");
    const denied = await scan(jeanne, "pickup", eliseCard.qr_payload, { authorized_person_id: refused, human_confirmed: true, proof_token: pendingDenial.proof_token });
    assert.equal(denied.result, "DENIED");
    assert.equal(denied.reason, "PHOTO_NOT_VALIDATED");
    assert.equal((await attendance(elise)).left_at, null);

    const beforeFeesEntry = await events(farid, "entry");
    const beforeFeesAttendance = await attendance(farid);
    const verified = await scan(jeanne, "verify", faridCard.qr_payload);
    assert.equal(verified.result, "VALID");
    assert.equal(verified.status, "active");
    assert.equal(await events(farid, "entry"), beforeFeesEntry);
    assert.equal(await attendance(farid), beforeFeesAttendance);
    const invalid = await scan(jeanne, "verify", "schoolsafe://student/MAT-F");
    assert.equal(invalid.result, "INVALID");
    assert.equal(await events(farid, "entry"), 0);

    const feeStructure = randomUUID();
    await admin.query(
      "insert into app.fee_structures(id,school_id,academic_year_id,label,amount,currency) values($1,$2,$3,'Minerval',100,'USD')",
      [feeStructure, schoolId, yearId],
    );
    await admin.query("begin");
    try {
      await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, schoolId, randomUUID()]);
      await admin.query(
        "insert into app.student_fees(school_id,student_id,fee_structure_id,status,amount_expected,amount_paid,amount_remaining) values($1,$2,$3,'pending',100,25,75)",
        [schoolId, farid, feeStructure],
      );
      await admin.query("commit");
    } catch (error) {
      await admin.query("rollback");
      throw error;
    }
    const disabledFees = await scan(jeanne, "fees", faridCard.qr_payload);
    assert.equal(disabledFees.result, "FEES_DISABLED");
    assert.equal(disabledFees.student_name, undefined);
    await scanControl.feeModuleSet(jeanne, true);
    const missingFees = await scan(jeanne, "fees", (await issue(boris)).qr_payload);
    assert.equal(missingFees.result, "FEE_STATUS");
    assert.equal(missingFees.balance_status, "no_data");
    const feeScan = await scan(jeanne, "fees", faridCard.qr_payload, { student_fee_status: "unpaid", result: "paid" });
    assert.equal(feeScan.result, "FEE_STATUS");
    assert.equal(feeScan.balance_status, "partial");
    assert.equal(Number(feeScan.amount_remaining), 75);
    assert.equal(await events(farid, "entry"), 0);
    assert.equal(await attendance(farid), null);
    const campaignId = await finance.createCampaign(jeanne, { fee_structure_id: feeStructure, label: "Contrôle octobre", classes: [classId] });
    await scanControl.transitionCampaign(jeanne, campaignId, "published");
    const campaignScan = await scan(jeanne, "fees", faridCard.qr_payload, { campaign_id: campaignId });
    assert.equal(campaignScan.result, "FEE_STATUS");
    assert.equal(campaignScan.balance_status, "partial");
    const feeRows = await admin.query("select student_fee_status, result from app.fee_control_scans where student_id=$1", [farid]);
    assert.equal(feeRows.rowCount, 1);
    assert.equal(feeRows.rows[0].student_fee_status, "partial");
    assert.equal(feeRows.rows[0].result, "consulted");
    await admin.query("begin");
    try {
      await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, schoolId, randomUUID()]);
      await admin.query("select api.fee_control_scan_create($1,$2,'paid','note client','unpaid')", [campaignId, farid]);
      await admin.query("commit");
    } catch (error) {
      await admin.query("rollback");
      throw error;
    }
    const forged = await admin.query("select student_fee_status, result from app.fee_control_scans where student_id=$1 order by scanned_at desc", [farid]);
    assert.equal(forged.rows[0].student_fee_status, "partial");
    assert.equal(forged.rows[0].result, "consulted");
    const payments = await admin.query("select count(*)::int n from app.fee_payments where school_id=$1", [schoolId]);
    assert.equal(payments.rows[0].n, 0);
    const agentUser = randomUUID();
    const agentProfile = randomUUID();
    await admin.query("insert into iam.users(id,email,is_active) values($1,$2,true)", [agentUser, "agent-" + agentUser + "@example.test"]);
    await admin.query(
      "insert into iam.profiles(id,user_id,school_id,display_name,is_active,account_status) values($1,$2,$3,'Agent frais',true,'active')",
      [agentProfile, agentUser, schoolId],
    );
    const agent = { userId: agentUser, profileId: agentProfile, schoolId, requestId: randomUUID() };
    const outsider = await scan(agent, "fees", faridCard.qr_payload, { campaign_id: campaignId });
    assert.equal(outsider.result, "NOT_DELEGATED");
    await scanControl.feeDelegationSet(jeanne, {
      profile_id: agent.profileId, capability: "SCAN_FEES", action: "assign", reason: "Contrôle de la 3e",
      class_ids: [otherClassId], campaign_id: campaignId,
    });
    const outsideClass = await scan(agent, "fees", faridCard.qr_payload, { campaign_id: campaignId });
    assert.equal(outsideClass.result, "OUT_OF_SCOPE");
    await scanControl.feeDelegationSet(jeanne, {
      profile_id: agent.profileId, capability: "SCAN_FEES", action: "assign", reason: "Contrôle de la classe de Farid",
      class_ids: [classId], campaign_id: campaignId, expires_at: new Date(Date.now() + 3600000).toISOString(),
    });
    const delegated = await scan(agent, "fees", faridCard.qr_payload, { campaign_id: campaignId });
    assert.equal(delegated.result, "FEE_STATUS");
    assert.equal(delegated.balance_status, "partial");
    await admin.query("update app.scan_control_grants set expires_at = now() - interval '1 minute' where profile_id=$1 and capability='SCAN_FEES' and revoked_at is null", [agent.profileId]);
    const expired = await scan(agent, "fees", faridCard.qr_payload, { campaign_id: campaignId });
    assert.equal(expired.result, "DELEGATION_EXPIRED");
    const delegateUser = randomUUID();
    const delegateProfile = randomUUID();
    await admin.query("insert into iam.users(id,email,is_active) values($1,$2,true)", [delegateUser, "delegate-" + delegateUser + "@example.test"]);
    await admin.query(
      "insert into iam.profiles(id,user_id,school_id,display_name,is_active,account_status) values($1,$2,$3,'Délégué limité',true,'active')",
      [delegateProfile, delegateUser, schoolId],
    );
    const delegate = { userId: delegateUser, profileId: delegateProfile, schoolId, requestId: randomUUID() };
    await scanControl.feeDelegationSet(jeanne, {
      profile_id: delegate.profileId, capability: "SCAN_FEES_DELEGATE", action: "assign", reason: "Délégation limitée à une classe",
      class_ids: [classId], campaign_id: campaignId, expires_at: new Date(Date.now() + 3600000).toISOString(),
    });
    await assert.rejects(
      () => scanControl.feeDelegationSet(delegate, {
        profile_id: agent.profileId, capability: "SCAN_FEES", action: "assign", reason: "Tentative d'élargissement",
      }),
      /delegation scope/,
    );
    const wideUser = randomUUID();
    const wideProfile = randomUUID();
    await admin.query("insert into iam.users(id,email,is_active) values($1,$2,true)", [wideUser, "wide-" + wideUser + "@example.test"]);
    await admin.query(
      "insert into iam.profiles(id,user_id,school_id,display_name,is_active,account_status) values($1,$2,$3,'Agent autre classe',true,'active')",
      [wideProfile, wideUser, schoolId],
    );
    await scanControl.feeDelegationSet(jeanne, {
      profile_id: wideProfile, capability: "SCAN_FEES", action: "assign", reason: "Autre classe",
      class_ids: [otherClassId], campaign_id: campaignId,
    });
    await assert.rejects(
      () => scanControl.feeDelegationSet(delegate, {
        profile_id: wideProfile, capability: "SCAN_FEES", action: "revoke", reason: "Retrait hors plafond",
      }),
      /delegation scope/,
    );
    await assert.rejects(
      () => scanControl.feeDelegationSet(delegate, {
        profile_id: wideProfile, capability: "SCAN_FEES", action: "assign", reason: "Réécriture hors plafond",
        class_ids: [classId], campaign_id: campaignId,
      }),
      /delegation scope/,
    );
    const wideKept = await admin.query(
      "select class_ids, revoked_at from app.scan_control_grants where profile_id = $1 and capability = 'SCAN_FEES'",
      [wideProfile],
    );
    assert.equal(wideKept.rows[0].revoked_at, null);
    assert.deepEqual(wideKept.rows[0].class_ids, [otherClassId]);
    await scanControl.transitionCampaign(jeanne, campaignId, "closed");
    const closed = await scan(jeanne, "fees", faridCard.qr_payload, { campaign_id: campaignId });
    assert.equal(closed.result, "OUT_OF_SCOPE");

    const portalId = randomUUID();
    await admin.query(
      "insert into app.security_portals(id, school_id, location_id, code, label, is_active) values($1,$2,$3,'P1','Portail principal',true)",
      [portalId, schoolId, locationId],
    );
    const guardUser = randomUUID();
    const guardProfile = randomUUID();
    const guardRole = randomUUID();
    await admin.query("insert into iam.users(id,email,is_active) values($1,$2,true)", [guardUser, "guard-" + guardUser + "@example.test"]);
    await admin.query(
      "insert into iam.profiles(id,user_id,school_id,display_name,is_active,account_status) values($1,$2,$3,'Gardien portail',true,'active')",
      [guardProfile, guardUser, schoolId],
    );
    await admin.query("begin");
    try {
      await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, schoolId, randomUUID()]);
      await admin.query("insert into iam.roles(id,school_id,code,label,created_by) values($1,$2,'guard_portal','Gardien',$3)", [guardRole, schoolId, jeanne.profileId]);
      const permission = await admin.query("select id from iam.permissions where code = 'security.scan'");
      const grant = await admin.query(
        "insert into iam.role_permission_grants(school_id,role_id,permission_id,effect,reason,granted_by) values($1,$2,$3,'allow','Portail attribué',$4) returning id",
        [schoolId, guardRole, permission.rows[0].id, jeanne.profileId],
      );
      await admin.query(
        "insert into iam.grant_scopes(school_id,grant_id,scope_code,target_id,assigned_by) values($1,$2,'assigned_portal',null,$3)",
        [schoolId, grant.rows[0].id, jeanne.profileId],
      );
      await admin.query(
        "insert into iam.profile_roles(school_id,profile_id,role_id,assigned_by,is_active) values($1,$2,$3,$4,true)",
        [schoolId, guardProfile, guardRole, jeanne.profileId],
      );
      await admin.query("commit");
    } catch (error) {
      await admin.query("rollback");
      throw error;
    }
    await scanControl.grant(jeanne, { profile_id: guardProfile, capability: "SCAN_ENTRY", action: "assign", reason: "Poste du gardien" });
    const guard = { userId: guardUser, profileId: guardProfile, schoolId, requestId: randomUUID() };
    const portalDenied = await scan(guard, "entry", aminaCard.qr_payload);
    assert.equal(portalDenied.result, "PORTAL_DENIED");
    assert.equal(await events(amina, "entry"), 1);

    await admin.query("update app.students set lifecycle_status = 'archived' where id = $1", [gina]);
    const notOperational = await scan(jeanne, "entry", ginaCard.qr_payload);
    assert.equal(notOperational.result, "STUDENT_NOT_OPERATIONAL");
    assert.equal(await events(gina, "entry"), 0);
    await admin.query("update app.students set lifecycle_status = 'active' where id = $1", [gina]);

    await scanControl.reportLoss(jeanne, { student_id: gina, card_id: ginaCard.card_id, reason: "Carte perdue" });
    const lost = await scan(jeanne, "entry", ginaCard.qr_payload);
    assert.equal(lost.result, "LOST");
    assert.equal(await events(gina, "entry"), 0);
    const lostFees = await scan(jeanne, "fees", ginaCard.qr_payload);
    assert.equal(lostFees.result, "LOST");
    assert.equal(lostFees.student_name, undefined);
    assert.equal(lostFees.student_id, undefined);
    const hiddenLost = await scan(agent, "fees", ginaCard.qr_payload);
    assert.equal(hiddenLost.result, "DELEGATION_EXPIRED");
    assert.equal(hiddenLost.status, undefined);
    assert.equal(hiddenLost.student_id, undefined);

    const replaced = await scanControl.canonicalReplace(jeanne, hugoCard.card_id);
    assert.match(replaced.qr_payload, /^schoolsafe:\/\/card\/[^/]+\/[^/]+$/);
    assert.notEqual(replaced.qr_payload, hugoCard.qr_payload);
    const oldRefused = await scan(jeanne, "entry", hugoCard.qr_payload);
    assert.equal(oldRefused.result, "REPLACED");
    assert.equal(await events(hugo, "entry"), 0);
    const fresh = await scan(jeanne, "entry", replaced.qr_payload);
    assert.equal(fresh.result, "PRESENT");
    await assert.rejects(
      () => security.scan({ qr_payload: hugoCard.qr_payload, event_type: "entry", location_id: locationId, scanned_by: jeanne.profileId }),
      (error) => String(error.message).includes("Card is replaced"),
    );

    await assert.rejects(() => scan(staff, "verify", faridCard.qr_payload), /capability denied|SCAN_VERIFY/);
    await scanControl.grant(jeanne, { profile_id: paul.profileId, capability: "SCAN_VERIFY", action: "assign", reason: "Vérification Paul" });
  await scanControl.grant(jeanne, { profile_id: staff.profileId, capability: "SCAN_VERIFY", action: "assign", reason: "Vérification Aline" });
  const paulOk = await scan(paul, "verify", faridCard.qr_payload);
  const staffOk = await scan(staff, "verify", faridCard.qr_payload);
  assert.equal(paulOk.result, "VALID");
  assert.equal(staffOk.result, "VALID");
  await scanControl.grant(jeanne, { profile_id: paul.profileId, capability: "SCAN_VERIFY", action: "revoke", reason: "Fin de poste Paul" });
  await assert.rejects(() => scan(paul, "verify", faridCard.qr_payload), /capability denied|SCAN_VERIFY/);
  const staffStill = await scan(staff, "verify", faridCard.qr_payload);
  assert.equal(staffStill.result, "VALID");
  const holders = await scanControl.grantList(jeanne);
  assert.equal(holders.some((row) => row.profile_id === staff.profileId && row.capability === "SCAN_VERIFY"), true);
  assert.equal(holders.some((row) => row.profile_id === paul.profileId && row.capability === "SCAN_VERIFY"), false);

  const audit = await scanControl.audit(jeanne, amina);
  assert.equal(audit.some((row) => row.operator_name === "Jeanne Mbayo" && row.student_id === amina), true);

  const session = {
    sessionId: randomUUID(), identityId: randomUUID(), userId: jeanne.userId, profileId: jeanne.profileId,
    schoolId, mustChange: false, expiresAt: new Date(Date.now() + 3600000).toISOString(),
  };
  const app = buildApp({
    scanControl: {
      authService: { resolveSession: async () => session },
      service: scanControl,
    },
  });
  const httpVerify = await app.inject({
    method: "POST",
    url: "/native/scan-control/scan",
    headers: { cookie: "schoolsafe_session=e2e" },
    payload: { mode: "verify", qr_payload: faridCard.qr_payload },
  });
  assert.equal(httpVerify.statusCode, 200, httpVerify.body);
  assert.equal(httpVerify.json().data.result, "VALID");
  await app.close();

    console.log("ENTRY_DOUBLE_PREEXIT_GATE_PICKUP_FEES_VERIFY_LOSS_REPLACE_DELEGATION=PASS");

    async function withActor(actor, sql, params = []) {
      const client = await pool.connect();
      try {
        await client.query("begin");
        if (actor) {
          await client.query("select api.set_request_context($1,$2,$3,$4)", [actor.userId, actor.profileId, actor.schoolId, randomUUID()]);
        }
        return await client.query(sql, params);
      } finally {
        await client.query("rollback").catch(() => {});
        client.release();
      }
    }

    const selectRight = await admin.query("select has_table_privilege('schoolsafe_api', 'app.locations', 'SELECT') as allowed");
    assert.equal(selectRight.rows[0].allowed, false);
    await assert.rejects(() => withActor(jeanne, "select id from app.locations"), /permission denied/i);
    console.log("LOCATION_SELECT_DENIED=PASS");

    const inactiveId = randomUUID();
    const courtId = randomUUID();
    const foreignSchoolId = randomUUID();
    const foreignLocationId = randomUUID();
    const missingId = randomUUID();
    await admin.query("insert into app.schools(id, code, name) values($1,$2,'Autre école')", [foreignSchoolId, "AUTRE" + foreignSchoolId.replace(/-/g, "").slice(0, 12)]);
    await admin.query(
      "insert into app.locations(id, school_id, code, label, kind, is_active) values($1,$2,'ARRIERE','Arrière','door',false)",
      [inactiveId, schoolId],
    );
    await admin.query(
      "insert into app.locations(id, school_id, code, label, kind) values($1,$2,'COUR','Cour','control_point')",
      [courtId, schoolId],
    );
    await admin.query(
      "insert into app.locations(id, school_id, code, label, kind) values($1,$2,'NORD','Portail nord','gate')",
      [foreignLocationId, foreignSchoolId],
    );

    await assert.rejects(
      () => withActor(null, "select api.scan_control_location_check($1) as ok", [locationId]),
      /active identity/,
    );
    await assert.rejects(
      () => withActor(staff, "select api.scan_control_location_check($1) as ok", [locationId]),
      /capability denied/,
    );

    const accepted = await withActor(jeanne, "select api.scan_control_location_check($1) as ok", [locationId]);
    assert.equal(accepted.rows[0].ok, true);
    const inactive = await withActor(jeanne, "select api.scan_control_location_check($1) as ok", [inactiveId]);
    assert.equal(inactive.rows[0].ok, false);
    const missing = await withActor(jeanne, "select api.scan_control_location_check($1) as ok", [missingId]);
    assert.equal(missing.rows[0].ok, false);
    const foreign = await withActor(jeanne, "select api.scan_control_location_check($1) as ok", [foreignLocationId]);
    assert.equal(foreign.rows[0].ok, false);
    const paulBefore = await withActor(paul, "select api.scan_control_location_check($1) as ok", [locationId]);
    assert.equal(paulBefore.rows[0].ok, true);

    await scanControl.grant(jeanne, { profile_id: paul.profileId, capability: "SCAN_GATE_EXIT", action: "revoke", reason: "Fin du poste de Paul" });
    await assert.rejects(
      () => withActor(paul, "select api.scan_control_location_check($1) as ok", [locationId]),
      /capability denied/,
    );

    const listed = await withActor(jeanne, "select id, code, label, kind from api.scan_control_location_list() order by code");
    assert.deepEqual(listed.rows.map((row) => Object.keys(row).sort()), [
      ["code", "id", "kind", "label"],
      ["code", "id", "kind", "label"],
    ]);
    assert.deepEqual(
      listed.rows.map((row) => row.id),
      [courtId, locationId],
    );
    assert.deepEqual(listed.rows.map((row) => [row.code, row.label, row.kind]), [
      ["COUR", "Cour", "control_point"],
      ["PORTAIL", "Portail", "gate"],
    ]);
    assert.equal(listed.rows.some((row) => row.id === inactiveId || row.id === foreignLocationId), false);
    const rights = await admin.query(
      "select p.proname, r.rolname as owner, p.prosecdef as security_definer, has_function_privilege('schoolsafe_api', p.oid, 'EXECUTE') as api_execute, has_function_privilege('public', p.oid, 'EXECUTE') as public_execute, has_function_privilege('schoolsafe_auth', p.oid, 'EXECUTE') as auth_execute from pg_proc p join pg_roles r on r.oid = p.proowner join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'api' and p.proname in ('scan_control_location_list', 'scan_control_location_check') order by p.proname",
    );
    assert.equal(rights.rows.length, 2);
    for (const row of rights.rows) {
      assert.equal(row.owner, "schoolsafe_owner");
      assert.equal(row.security_definer, true);
      assert.equal(row.api_execute, true);
      assert.equal(row.public_execute, false);
      assert.equal(row.auth_execute, false);
    }
    console.log("LOCATION_ACTIVE_FOREIGN_INACTIVE_GRANT=PASS");

    const borisCard = await issue(boris);
    const noprintUser = randomUUID();
    const noprintProfile = randomUUID();
    await admin.query("insert into iam.users(id,email,is_active) values($1,$2,true)", [noprintUser, "noprint-" + noprintUser + "@example.test"]);
    await admin.query(
      "insert into iam.profiles(id,user_id,school_id,display_name,is_active,account_status) values($1,$2,$3,'No Print',true,'active')",
      [noprintProfile, noprintUser, schoolId],
    );
    const noprint = { userId: noprintUser, profileId: noprintProfile, schoolId, requestId: randomUUID() };
    const sessionAuth = {
      resolveSession: async (token) => {
        if (token === "e2e") return session;
        if (token === "staff") {
          return {
            sessionId: randomUUID(), identityId: randomUUID(), userId: staff.userId, profileId: staff.profileId,
            schoolId, mustChange: false, expiresAt: new Date(Date.now() + 3600000).toISOString(),
          };
        }
        if (token === "noprint") {
          return {
            sessionId: randomUUID(), identityId: randomUUID(), userId: noprint.userId, profileId: noprint.profileId,
            schoolId, mustChange: false, expiresAt: new Date(Date.now() + 3600000).toISOString(),
          };
        }
        return null;
      },
    };
    const locationApp = buildApp({
      scanControl: { authService: sessionAuth, service: scanControl },
      cardsNative: { authService: sessionAuth, service: cards },
    });
    const noSession = await locationApp.inject({ method: "GET", url: "/native/scan-control/locations" });
    assert.equal(noSession.statusCode, 401, noSession.body);
    const staffLocations = await locationApp.inject({
      method: "GET",
      url: "/native/scan-control/locations",
      headers: { cookie: "schoolsafe_session=staff" },
    });
    assert.equal(staffLocations.statusCode, 403, staffLocations.body);
    const httpLocations = await locationApp.inject({
      method: "GET",
      url: "/native/scan-control/locations",
      headers: { cookie: "schoolsafe_session=e2e" },
    });
    assert.equal(httpLocations.statusCode, 200, httpLocations.body);
    assert.match(String(httpLocations.headers["cache-control"] || ""), /no-store/);
    const httpRows = httpLocations.json().data.locations;
    assert.deepEqual(httpRows.map((row) => Object.keys(row).sort()), [
      ["code", "id", "kind", "label"],
      ["code", "id", "kind", "label"],
    ]);
    assert.deepEqual(httpRows.map((row) => row.id).sort(), [courtId, locationId].sort());
    assert.equal(httpRows.some((row) => row.id === inactiveId || row.id === foreignLocationId), false);

    async function effects(studentId) {
      const events = await admin.query("select count(*)::int n from app.security_events where student_id=$1", [studentId]);
      const attendance = await admin.query("select count(*)::int n from app.attendance_records where student_id=$1", [studentId]);
      return { events: events.rows[0].n, attendance: attendance.rows[0].n };
    }
    const borisBefore = await effects(boris);
    const aminaBefore = await effects(amina);
    await assert.rejects(
      () => scanControl.scan(jeanne, { mode: "entry", qr_payload: borisCard.qr_payload }),
      /Le lieu du scan est requis/,
    );
    await assert.rejects(
      () => scanControl.scan(jeanne, { mode: "pre_exit", qr_payload: aminaCard.qr_payload, authorized_person_id: marie }),
      /accès officiel/,
    );
    await assert.rejects(
      () => scanControl.scan(jeanne, { mode: "gate_exit", qr_payload: aminaCard.qr_payload, authorized_person_id: marie }),
      /accès officiel/,
    );
    await assert.rejects(
      () => scanControl.scan(jeanne, { mode: "entry", qr_payload: borisCard.qr_payload, location_id: missingId }),
      /n'est pas autorisé/,
    );
    await assert.rejects(
      () => scanControl.scan(jeanne, { mode: "entry", qr_payload: borisCard.qr_payload, location_id: inactiveId }),
      /n'est pas autorisé/,
    );
    await assert.rejects(
      () => scanControl.scan(jeanne, { mode: "gate_exit", qr_payload: aminaCard.qr_payload, location_id: foreignLocationId, authorized_person_id: marie }),
      /accès officiel/,
    );
    assert.deepEqual(await effects(boris), borisBefore);
    assert.deepEqual(await effects(amina), aminaBefore);
    const allowedEntry = await scanControl.scan(jeanne, { mode: "entry", qr_payload: borisCard.qr_payload, location_id: courtId });
    assert.equal(allowedEntry.result, "PRESENT");
    assert.equal((await effects(boris)).events, borisBefore.events + 1);
    const verifiedWithoutLocation = await scanControl.scan(jeanne, { mode: "verify", qr_payload: faridCard.qr_payload });
    assert.equal(verifiedWithoutLocation.result, "VALID");

    const iris = await addStudent("MAT-I", "Iris", "Lukusa", classId);
    const lea = await addStudent("MAT-L", "Lea", "Mukendi", classId);
    const jules = await addStudent("MAT-J", "Jules", "Kalala", classId);
    const oscar = await addStudent("MAT-O", "Oscar", "Ndaya", classId);
    const nina = await addStudent("MAT-N", "Nina", "Pembele", classId);
    const paule = await addStudent("MAT-P", "Paule", "Okito", classId);
    const rita = await addStudent("MAT-R", "Rita", "Sansgrant", classId);
    async function postCard(token, url, payload) {
      const headers = { "content-type": "application/json" };
      if (token) headers.cookie = "schoolsafe_session=" + token;
      return locationApp.inject({ method: "POST", url, headers, payload });
    }
    async function getCard(token, url) {
      const headers = {};
      if (token) headers.cookie = "schoolsafe_session=" + token;
      return locationApp.inject({ method: "GET", url, headers });
    }
    async function cardRow(cardId) {
      const row = await admin.query("select id, status, card_number, signature, school_id from app.student_cards where id = $1", [cardId]);
      return row.rows[0] || null;
    }
    async function cardsFor(studentId) {
      const rows = await admin.query("select id, status, card_number, signature from app.student_cards where student_id = $1 order by issued_at", [studentId]);
      return rows.rows;
    }
    const printWithoutCreate = await postCard("staff", "/native/cards/students/" + rita + "/credential", {});
    assert.equal(printWithoutCreate.statusCode, 403, printWithoutCreate.body);
    assert.equal(printWithoutCreate.json().message, "Capacité non déléguée");
    assert.equal(printWithoutCreate.json().data, undefined);
    assert.equal((await cardsFor(rita)).length, 0);
    const anonymousCredential = await postCard(undefined, "/native/cards/students/" + rita + "/credential", {});
    assert.equal(anonymousCredential.statusCode, 401, anonymousCredential.body);
    assert.equal(anonymousCredential.json().message, "Session requise");
    const anonymousRead = await getCard(undefined, "/native/cards/students/" + rita + "/credential");
    assert.equal(anonymousRead.statusCode, 401, anonymousRead.body);
    assert.equal(anonymousRead.json().message, "Session requise");
    const emptyRead = await getCard("e2e", "/native/cards/students/" + rita + "/credential");
    assert.equal(emptyRead.statusCode, 200, emptyRead.body);
    assert.equal(emptyRead.json().data.card_number, null);
    assert.equal(emptyRead.json().data.qr_payload, "");
    assert.equal((await cardsFor(rita)).length, 0);
    const anonymousIssue = await postCard("", "/native/scan-control/cards/issue", { student_id: iris });
    assert.equal(anonymousIssue.statusCode, 401, anonymousIssue.body);
    assert.equal(anonymousIssue.json().message, "Session requise");
    for (const url of [
      "/native/scan-control/cards/loss",
      "/native/scan-control/cards/" + randomUUID() + "/canonical-replace",
      "/native/scan-control/cards/" + randomUUID() + "/reprint",
      "/native/scan-control/cards/" + randomUUID() + "/revoke",
    ]) {
      const anonymous = await postCard("", url, { student_id: iris, reason: "Sans session" });
      assert.equal(anonymous.statusCode, 401, anonymous.body);
      assert.equal(anonymous.json().data, undefined);
    }
    const missingStudent = await postCard("e2e", "/native/scan-control/cards/issue", {});
    assert.equal(missingStudent.statusCode, 400, missingStudent.body);
    assert.equal(missingStudent.json().message, "Donnée invalide");
    const staffIssue = await postCard("staff", "/native/scan-control/cards/issue", { student_id: iris });
    assert.equal(staffIssue.statusCode, 403, staffIssue.body);
    assert.equal(staffIssue.json().message, "Capacité non déléguée");
    assert.equal((await cardsFor(iris)).length, 0);
    await scanControl.grant(jeanne, { profile_id: noprint.profileId, capability: "CARD_CREATE", action: "assign", reason: "Emission sans impression" });
    const printDenied = await postCard("noprint", "/native/scan-control/cards/issue", { student_id: nina });
    assert.equal(printDenied.statusCode, 403, printDenied.body);
    assert.equal(printDenied.json().message, "Capacité non déléguée");
    assert.equal((await cardsFor(nina)).length, 0);
    const createWithoutPrint = await postCard("noprint", "/native/cards/students/" + nina + "/credential", {});
    assert.equal(createWithoutPrint.statusCode, 403, createWithoutPrint.body);
    assert.equal(createWithoutPrint.json().message, "Capacité non déléguée");
    assert.equal(createWithoutPrint.json().data, undefined);
    assert.notEqual(createWithoutPrint.statusCode, 500);
    assert.equal((await cardsFor(nina)).length, 0);
    const issued = await postCard("e2e", "/native/scan-control/cards/issue", { student_id: iris });
    assert.equal(issued.statusCode, 200, issued.body);
    const irisCard = issued.json().data;
    assert.match(irisCard.card_number, /^SS-/);
    assert.match(irisCard.qr_payload, /^schoolsafe:\/\/card\//);
    assert.equal(irisCard.status, "active");
    assert.equal((await cardRow(irisCard.card_id)).school_id, schoolId);
    const staffRead = await getCard("staff", "/native/cards/students/" + iris + "/credential");
    assert.equal(staffRead.statusCode, 200, staffRead.body);
    assert.equal(staffRead.json().data.card_id, irisCard.card_id);
    assert.equal((await cardsFor(iris)).length, 1);
    const noprintRead = await getCard("noprint", "/native/cards/students/" + iris + "/credential");
    assert.equal(noprintRead.statusCode, 403, noprintRead.body);
    assert.equal(noprintRead.json().message, "Capacité non déléguée");
    assert.notEqual(noprintRead.statusCode, 500);
    const lossWithoutReason = await postCard("e2e", "/native/scan-control/cards/loss", { student_id: iris, card_id: irisCard.card_id });
    assert.equal(lossWithoutReason.statusCode, 400, lossWithoutReason.body);
    assert.equal((await cardRow(irisCard.card_id)).status, "active");
    const loss = await postCard("e2e", "/native/scan-control/cards/loss", { student_id: iris, card_id: irisCard.card_id, reason: "Perdue à la cour" });
    assert.equal(loss.statusCode, 200, loss.body);
    assert.equal(loss.json().data.status, "lost");
    assert.equal((await cardRow(irisCard.card_id)).status, "lost");
    const lostScan = await scanControl.scan(jeanne, { mode: "verify", qr_payload: irisCard.qr_payload });
    assert.equal(lostScan.result, "LOST");
    const leaIssued = await postCard("e2e", "/native/scan-control/cards/issue", { student_id: lea });
    assert.equal(leaIssued.statusCode, 200, leaIssued.body);
    const leaCard = leaIssued.json().data;
    const leaReplace = await postCard("e2e", "/native/scan-control/cards/" + leaCard.card_id + "/canonical-replace", {});
    assert.equal(leaReplace.statusCode, 200, leaReplace.body);
    const leaNext = leaReplace.json().data;
    assert.equal(leaNext.old_status, "replaced");
    assert.notEqual(leaNext.card_id, leaCard.card_id);
    assert.notEqual(leaNext.signature, leaCard.signature);
    assert.notEqual(leaNext.qr_payload, leaCard.qr_payload);
    assert.match(leaNext.qr_payload, /^schoolsafe:\/\/card\//);
    assert.equal((await cardRow(leaCard.card_id)).status, "replaced");
    assert.equal((await cardRow(leaNext.card_id)).status, "active");
    const oldLea = await scanControl.scan(jeanne, { mode: "verify", qr_payload: leaCard.qr_payload });
    const newLea = await scanControl.scan(jeanne, { mode: "verify", qr_payload: leaNext.qr_payload });
    assert.equal(oldLea.result, "REPLACED");
    assert.equal(newLea.result, "VALID");
    assert.equal(newLea.card_id, leaNext.card_id);
    const julesIssued = await postCard("e2e", "/native/scan-control/cards/issue", { student_id: jules });
    const julesCard = julesIssued.json().data;
    const reprintWithoutReason = await postCard("e2e", "/native/scan-control/cards/" + julesCard.card_id + "/reprint", {});
    assert.equal(reprintWithoutReason.statusCode, 400, reprintWithoutReason.body);
    const reprint = await postCard("e2e", "/native/scan-control/cards/" + julesCard.card_id + "/reprint", { reason: "Support abîmé" });
    assert.equal(reprint.statusCode, 200, reprint.body);
    assert.equal(reprint.json().data.card_id, julesCard.card_id);
    assert.equal(reprint.json().data.card_number, julesCard.card_number);
    assert.equal(reprint.json().data.reprint, "authorized");
    assert.equal((await cardsFor(jules)).length, 1);
    assert.equal((await cardRow(julesCard.card_id)).status, "active");
    const revokeWithoutReason = await postCard("e2e", "/native/scan-control/cards/" + julesCard.card_id + "/revoke", {});
    assert.equal(revokeWithoutReason.statusCode, 400, revokeWithoutReason.body);
    assert.equal((await cardRow(julesCard.card_id)).status, "active");
    const revoke = await postCard("e2e", "/native/scan-control/cards/" + julesCard.card_id + "/revoke", { reason: "Carte retirée" });
    assert.equal(revoke.statusCode, 200, revoke.body);
    assert.equal(revoke.json().data.status, "revoked");
    assert.equal((await cardRow(julesCard.card_id)).status, "revoked");
    const revokedScan = await scanControl.scan(jeanne, { mode: "verify", qr_payload: julesCard.qr_payload });
    assert.equal(revokedScan.result, "REVOKED");
    await scanControl.grant(jeanne, { profile_id: noprint.profileId, capability: "CARD_REPRINT", action: "assign", reason: "Réimpression sans impression" });
    const pauleIssued = await postCard("e2e", "/native/scan-control/cards/issue", { student_id: paule });
    const pauleCard = pauleIssued.json().data;
    const reprintDenied = await postCard("noprint", "/native/scan-control/cards/" + pauleCard.card_id + "/reprint", { reason: "Sans droit d'impression" });
    assert.equal(reprintDenied.statusCode, 403, reprintDenied.body);
    assert.equal((await cardRow(pauleCard.card_id)).status, "active");
    await scanControl.grant(jeanne, { profile_id: noprint.profileId, capability: "CARD_LOST", action: "assign", reason: "Perte sans impression" });
    const lossDenied = await postCard("noprint", "/native/scan-control/cards/loss", { student_id: paule, card_id: pauleCard.card_id, reason: "Sans droit d'impression" });
    assert.equal(lossDenied.statusCode, 403, lossDenied.body);
    assert.equal((await cardRow(pauleCard.card_id)).status, "active");
    await scanControl.grant(jeanne, { profile_id: noprint.profileId, capability: "CARD_REPLACE", action: "assign", reason: "Remplacement sans impression" });
    const oscarIssued = await postCard("e2e", "/native/scan-control/cards/issue", { student_id: oscar });
    const oscarCard = oscarIssued.json().data;
    const oscarReplace = await postCard("noprint", "/native/scan-control/cards/" + oscarCard.card_id + "/canonical-replace", {});
    assert.equal(oscarReplace.statusCode, 200, oscarReplace.body);
    assert.equal((await cardRow(oscarCard.card_id)).status, "replaced");
    assert.notEqual(oscarReplace.json().data.signature, oscarCard.signature);
    const foreignStudentId = randomUUID();
    const foreignCardId = randomUUID();
    const foreignYearId = randomUUID();
    const foreignClassId = randomUUID();
    await admin.query(
      "insert into app.academic_years(id, school_id, label, starts_on, ends_on, periods, is_active) values($1,$2,'2026-2027','2026-09-01','2027-07-31','Trimestres',true)",
      [foreignYearId, foreignSchoolId],
    );
    await admin.query(
      "insert into app.classes(id, school_id, academic_year_id, cycle_key, name) values($1,$2,$3,'primary','1re Primaire')",
      [foreignClassId, foreignSchoolId, foreignYearId],
    );
    await admin.query(
      "insert into app.students(id, school_id, class_id, matricule, first_name, last_name, date_of_birth, lifecycle_status) values($1,$2,$3,'MAT-X','Nora','Externe','2016-04-02','active')",
      [foreignStudentId, foreignSchoolId, foreignClassId],
    );
    await admin.query("insert into app.student_enrollments(school_id, student_id, academic_year_id, class_id, status, starts_on) values($1,$2,$3,$4,'active','2026-09-01')", [foreignSchoolId, foreignStudentId, foreignYearId, foreignClassId]);
    await admin.query("set session_replication_role = replica");
    try {
      await admin.query(
        "insert into app.student_cards(id, school_id, student_id, card_number, card_secret, signature, status) values($1,$2,$3,$4,$5,$6,'active')",
        [foreignCardId, foreignSchoolId, foreignStudentId, "SS-FOREIGN-" + foreignCardId.slice(0, 8), randomUUID(), "foreign-signature"],
      );
    } finally {
      await admin.query("set session_replication_role = origin");
    }
    const foreignReplace = await postCard("e2e", "/native/scan-control/cards/" + foreignCardId + "/canonical-replace", {});
    const foreignRevoke = await postCard("e2e", "/native/scan-control/cards/" + foreignCardId + "/revoke", { reason: "Autre école" });
    const foreignReprint = await postCard("e2e", "/native/scan-control/cards/" + foreignCardId + "/reprint", { reason: "Autre école" });
    const foreignLoss = await postCard("e2e", "/native/scan-control/cards/loss", { student_id: foreignStudentId, card_id: foreignCardId, reason: "Autre école" });
    for (const foreignResponse of [foreignReplace, foreignRevoke, foreignReprint, foreignLoss]) {
      assert.notEqual(foreignResponse.statusCode, 200, foreignResponse.body);
      assert.equal(foreignResponse.json().data, undefined);
    }
    assert.equal((await cardRow(foreignCardId)).status, "active");
    assert.equal((await cardRow(foreignCardId)).school_id, foreignSchoolId);
    const invalidCard = await postCard("e2e", "/native/scan-control/cards/pas-un-uuid/revoke", { reason: "Identifiant illisible" });
    assert.notEqual(invalidCard.statusCode, 200, invalidCard.body);
    assert.equal(invalidCard.json().data, undefined);
    const unknownCard = await postCard("e2e", "/native/scan-control/cards/" + randomUUID() + "/canonical-replace", {});
    assert.notEqual(unknownCard.statusCode, 200, unknownCard.body);
    assert.equal(unknownCard.json().data, undefined);
    assert.equal((await cardRow(leaNext.card_id)).status, "active");
    const sonia = await addStudent("MAT-S", "Sonia", "Deuxdroits", classId);
    const firstCredential = await postCard("e2e", "/native/cards/students/" + sonia + "/credential", {});
    assert.equal(firstCredential.statusCode, 200, firstCredential.body);
    assert.match(firstCredential.json().data.qr_payload, /^schoolsafe:\/\/card\//);
    const repeatedCredential = await postCard("e2e", "/native/cards/students/" + sonia + "/credential", {});
    assert.equal(repeatedCredential.statusCode, 200, repeatedCredential.body);
    assert.equal(repeatedCredential.json().data.card_id, firstCredential.json().data.card_id);
    assert.equal((await cardsFor(sonia)).length, 1);
    const twin = await addStudent("MAT-T", "Tina", "Simultanee", classId);
    const [left, right] = await Promise.all([
      postCard("e2e", "/native/cards/students/" + twin + "/credential", {}),
      postCard("e2e", "/native/cards/students/" + twin + "/credential", {}),
    ]);
    assert.equal(left.statusCode, 200, left.body);
    assert.equal(right.statusCode, 200, right.body);
    assert.equal(left.json().data.card_id, right.json().data.card_id);
    const twinCards = await cardsFor(twin);
    assert.equal(twinCards.length, 1);
    assert.equal(twinCards[0].status, "active");
    const foreignCredential = await postCard("e2e", "/native/cards/students/" + foreignStudentId + "/credential", {});
    assert.equal(foreignCredential.statusCode, 404, foreignCredential.body);
    assert.equal(foreignCredential.json().data, undefined);
    assert.equal((await cardsFor(foreignStudentId)).length, 1);
    assert.equal((await cardRow(foreignCardId)).status, "active");
    assert.equal((await cardRow(foreignCardId)).school_id, foreignSchoolId);
    console.log("CARD_CREDENTIAL_CREATE_REQUIRED=PASS");
    console.log("CARD_ISSUE_LOSS_REPLACE_REPRINT_REVOKE_SESSION_GRANT_PRINT_FOREIGN_INVALID=PASS");
    await locationApp.close();
    console.log("LOCATION_ROUTE_AND_REFUSAL=PASS");
  } finally {
    if (facade) await new Promise((resolve) => facade.server.close(resolve));
    await Promise.allSettled([admin.end(), auth.end(), migrator.end(), pool.end(), scanPool.end()]);
  }
}

const base = process.env.DATABASE_URL;
const passwords = Object.fromEntries(["api", "auth", "worker", "migrator"].map((role) => [role, process.env["SCHOOLSAFE_" + role.toUpperCase() + "_PASSWORD"]]));
assert.ok(base, "DATABASE_URL required");
for (const role of ["api", "auth", "worker", "migrator"]) assert.ok(passwords[role] && passwords[role].length >= 32, role);
const name = "schoolsafe_test_scancontrol";
try {
  await recreate(base, name);
  await installSchoolDatabase({ connectionString: databaseUrl(base, name).toString(), database: name, mode: "apply", passwords, log: () => {} });
  await prove(base, name, passwords);
} catch (error) {
  console.error(error.stack || error.message);
  process.exitCode = 1;
}
