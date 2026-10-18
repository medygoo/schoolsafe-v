// Initialiseur de tests jetable. Jamais importé par le serveur.
// Identités fictives, hachages argon2id, rôles déjà provisionnés.
// Aucun changement de schéma, aucune ligne ops.supabase_school_access.
import { spawn } from "node:child_process";
import { createHmac, generateKeyPairSync, randomBytes, randomUUID, sign } from "node:crypto";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { createServer } from "node:https";
import { tmpdir } from "node:os";
import path from "node:path";
import { pathToFileURL } from "node:url";
import pg from "pg";
import { hash as argonHash } from "@node-rs/argon2";
import { tsImport } from "tsx/esm/api";
import { installSchoolDatabase } from "./install-school-db.mjs";
import { repositoryRoot } from "./installation-plan.mjs";

const base = process.env.DATABASE_URL;
const passwords = Object.fromEntries(["api", "auth", "worker", "migrator"].map((role) => [role, process.env["SCHOOLSAFE_" + role.toUpperCase() + "_PASSWORD"]]));
const cardSecret = process.env.CARD_HMAC_SECRET;
const fixturePassword = process.env.MULTIPROFILE_PASSWORD;
if (!base) throw new Error("DATABASE_URL required");
for (const role of ["api", "auth", "worker", "migrator"]) {
  if (!passwords[role] || passwords[role].length < 32) throw new Error(role);
}
if (!cardSecret || cardSecret.length < 32) throw new Error("CARD_HMAC_SECRET required");
if (!fixturePassword || fixturePassword.length < 32) throw new Error("MULTIPROFILE_PASSWORD required");

const keep = process.env.KEEP_SERVER === "1";
const dbName = "schoolsafe_test_multiprofile";
const port = "8787";
const origin = "http://127.0.0.1:" + port;
const png = Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==", "base64");
const pngB64 = png.toString("base64");
const results = [];
const statePath = path.join(tmpdir(), "schoolsafe-multiprofile-state.json");

function record(label, value) {
  const line = label + "=" + value;
  results.push(line);
  console.log(line);
}
function databaseUrl(database) {
  const url = new URL(base);
  url.pathname = "/" + database;
  return url;
}
function roleUrl(database, role) {
  const url = databaseUrl(database);
  url.username = "schoolsafe_" + role;
  url.password = passwords[role];
  return url.toString();
}
function asObject(value) {
  return typeof value === "string" ? JSON.parse(value) : value;
}
function signCard(cardNumber) {
  return createHmac("sha256", cardSecret).update(cardNumber).digest("base64url").slice(0, 32);
}
async function recreate(name) {
  const client = new pg.Client({ connectionString: base, connectionTimeoutMillis: 5000 });
  await client.connect();
  await client.query("select pg_terminate_backend(pid) from pg_stat_activity where datname=$1 and pid<>pg_backend_pid()", [name]);
  await client.query('drop database if exists "' + name + '"');
  await client.query('create database "' + name + '"');
  await client.end();
}
async function waitForPostgres() {
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try {
      const client = new pg.Client({ connectionString: base, connectionTimeoutMillis: 2000 });
      await client.connect();
      const version = await client.query("show server_version_num");
      await client.end();
      return version.rows[0].server_version_num;
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
  }
  throw new Error("postgres unavailable");
}

const authority = generateKeyPairSync("ed25519");
const installation = generateKeyPairSync("ed25519");
const keyId = "schoolsafe-local-test-v1";
const installationId = randomUUID();
const dir = mkdtempSync(path.join(tmpdir(), "schoolsafe-multiprofile-"));
const installationKeyPath = path.join(dir, "installation.pem");
const pfxPath = path.join(dir, "activation.pfx");
const pfxPassword = "license-temp";
writeFileSync(installationKeyPath, installation.privateKey.export({ type: "pkcs8", format: "pem" }));
const publicPem = authority.publicKey.export({ type: "spki", format: "pem" }).toString();
const { canonicalizePayloadV1 } = await tsImport(
  pathToFileURL(path.join(repositoryRoot, "server/src/licensenative/canonicalize.ts")).href,
  import.meta.url,
);
const { signCardNumber } = await tsImport(
  pathToFileURL(path.join(repositoryRoot, "server/src/security/service.ts")).href,
  import.meta.url,
);

function envelope(payload) {
  const canonical = canonicalizePayloadV1(payload);
  const signature = sign(null, Buffer.from(canonical, "utf8"), authority.privateKey).toString("base64url");
  return { payload, signature };
}
let issuedStep = 0;
function nextIssuedAt() {
  issuedStep += 1;
  return new Date(Date.now() + issuedStep * 1000).toISOString();
}
function payloadFor(kind, body) {
  const status = kind === "revoked" ? "revoked" : kind === "suspended" ? "suspended" : "active";
  return {
    version: 1,
    license_id: randomUUID(),
    school_id: body.school_id,
    installation_id: body.installation_id,
    status,
    plan: "school-test",
    modules: ["cards"],
    issued_at: nextIssuedAt(),
    expires_at: "2027-10-11T00:00:00.000Z",
    perpetual: false,
    key_id: keyId,
  };
}

await new Promise((resolve, reject) => {
  const child = spawn("powershell", ["-NoProfile", "-Command", `
    $cert = New-SelfSignedCertificate -DnsName '127.0.0.1' -CertStoreLocation 'Cert:\\CurrentUser\\My' -NotAfter (Get-Date).AddDays(1) -KeyExportPolicy Exportable
    $pwd = ConvertTo-SecureString -String '${pfxPassword}' -Force -AsPlainText
    Export-PfxCertificate -Cert $cert -FilePath '${pfxPath.replace(/'/g, "''")}' -Password $pwd | Out-Null
    Remove-Item -Path ('Cert:\\CurrentUser\\My\\' + $cert.Thumbprint)
  `], { stdio: "inherit" });
  child.on("exit", (code) => code === 0 ? resolve() : reject(new Error("certificate " + code)));
});

const activation = createServer({ pfx: readFileSync(pfxPath), passphrase: pfxPassword }, async (req, res) => {
  const chunks = [];
  for await (const chunk of req) chunks.push(chunk);
  const body = JSON.parse(Buffer.concat(chunks).toString("utf8") || "{}");
  if (req.url !== "/v1/activation/redeem" || !["live", "revoked", "suspended"].includes(body.activation_code)) {
    res.writeHead(404);
    res.end();
    return;
  }
  res.writeHead(200, { "content-type": "application/json" });
  res.end(JSON.stringify(envelope(payloadFor(body.activation_code, body))));
});
await new Promise((resolve) => activation.listen(0, "127.0.0.1", resolve));
const activationUrl = "https://127.0.0.1:" + activation.address().port;

let server = null;
let detached = null;

function licensedEnv() {
  return {
    NODE_ENV: "development",
    HOST: "127.0.0.1",
    PORT: port,
    PGHOST: "127.0.0.1",
    PGPORT: new URL(base).port,
    PGDATABASE: dbName,
    PGUSER: "schoolsafe_api",
    PGPASSWORD: passwords.api,
    PGAUTH_USER: "schoolsafe_auth",
    PGAUTH_PASSWORD: passwords.auth,
    CARD_HMAC_SECRET: cardSecret,
    ACTIVATION_SERVICE_URL: activationUrl,
    ACTIVATION_INSTALLATION_ID: installationId,
    ACTIVATION_INSTALLATION_PRIVATE_KEY_PATH: installationKeyPath,
    ACTIVATION_LICENSE_PUBLIC_KEYS_JSON: JSON.stringify({ [keyId]: publicPem }),
    NODE_TLS_REJECT_UNAUTHORIZED: "0",
  };
}
async function boot(extra, options = {}) {
  const child = spawn(process.execPath, ["--import", "tsx", "src/index.ts"], {
    cwd: path.join(repositoryRoot, "server"),
    env: { ...process.env, ...extra },
    detached: options.detached === true,
    stdio: options.detached ? "ignore" : ["ignore", "pipe", "pipe"],
    windowsHide: true,
  });
  if (!options.detached) {
    let log = "";
    child.stdout.on("data", (chunk) => { log += chunk; });
    child.stderr.on("data", (chunk) => { log += chunk; });
    child.log = () => log;
  }
  for (let attempt = 0; attempt < 40; attempt += 1) {
    if (child.exitCode !== null) throw new Error(child.log ? child.log() : "server exited");
    try {
      const response = await fetch(origin + "/");
      if (response.status < 500) return child;
    } catch { /* starting */ }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  throw new Error(child.log ? child.log() : "server did not start");
}
async function stop(child) {
  if (!child || child.killed) return;
  spawn("taskkill", ["/PID", String(child.pid), "/T", "/F"], { stdio: "ignore", windowsHide: true });
  await new Promise((resolve) => {
    const timer = setTimeout(resolve, 5000);
    child.once("exit", () => { clearTimeout(timer); resolve(); });
  });
}
async function login(email) {
  const response = await fetch(origin + "/auth/native/login", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ login: email, password: fixturePassword }),
  });
  const body = await response.json();
  const cookie = typeof response.headers.getSetCookie === "function"
    ? response.headers.getSetCookie().map((item) => item.split(";")[0]).join("; ")
    : "";
  return { status: response.status, body, cookie };
}
async function call(cookie, method, urlPath, payload, headers = {}) {
  const response = await fetch(origin + urlPath, {
    method,
    headers: { cookie, "content-type": "application/json", ...headers },
    body: payload === undefined ? undefined : JSON.stringify(payload),
  });
  const text = await response.text();
  let json = {};
  try { json = text ? JSON.parse(text) : {}; } catch { json = { raw: text.slice(0, 300) }; }
  return { status: response.status, json };
}
function payloadOf(response) {
  return response.json?.data ?? response.json ?? {};
}
function resultOf(response) {
  const data = payloadOf(response);
  return data.result || data.code || response.json?.code || response.status;
}

async function setupSchool(auth, migrator, nameFr, email, first, last) {
  const tokenHash = randomBytes(32).toString("hex");
  const passwordHash = await argonHash(fixturePassword, { memoryCost: 19456, timeCost: 2, parallelism: 1 });
  const setupPayload = {
    identity: { name_fr: nameFr },
    cycles: ["primary"],
    academic_year: { label: "2026-2027", starts_on: "2026-09-01", ends_on: "2027-07-31", periods: "Trimestres" },
    contact: {},
    brand: {},
  };
  await migrator.query("select ops.authorize_school_setup($1,$2)", [tokenHash, 3600]);
  const staged = asObject((await auth.query("select api.setup_stage_school($1,$2::jsonb) r", [tokenHash, JSON.stringify(setupPayload)])).rows[0].r);
  const created = asObject((await auth.query("select api.setup_complete_school($1,$2,$3,$4,$5,$6) r", [tokenHash, email, passwordHash, first, last, null])).rows[0].r);
  return { schoolId: staged.school_id, yearId: staged.academic_year_id, userId: created.user_id, profileId: created.profile_id };
}

try {
  const version = await waitForPostgres();
  record("POSTGRES", version);
  await recreate(dbName);
  await installSchoolDatabase({
    connectionString: databaseUrl(dbName).toString(),
    database: dbName,
    mode: "apply",
    passwords,
    log: () => {},
  });
  const admin = new pg.Client({ connectionString: databaseUrl(dbName).toString() });
  const auth = new pg.Client({ connectionString: roleUrl(dbName, "auth") });
  const migrator = new pg.Client({ connectionString: roleUrl(dbName, "migrator") });
  await Promise.all([admin.connect(), auth.connect(), migrator.connect()]);
  const jeanne = await setupSchool(auth, migrator, "École Cartes Réelles", "jeanne-mp@example.test", "Jeanne", "Mbayo");
  const other = await setupSchool(auth, migrator, "École Isolement", "other-mp@example.test", "Oscar", "Autre");
  await admin.query("update iam.profiles set display_name = 'Jeanne Mbayo' where id = $1", [jeanne.profileId]);
  await admin.query("update iam.profiles set display_name = 'Oscar Autre' where id = $1", [other.profileId]);
  record("SCHOOL_A", jeanne.schoolId);
  record("SCHOOL_B", other.schoolId);
  record("ACCESS_ROWS", (await admin.query("select count(*)::int n from ops.supabase_school_access")).rows[0].n);

  const classA = randomUUID();
  const classB = randomUUID();
  const locationId = randomUUID();
  const portalId = randomUUID();
  await admin.query(
    "insert into app.classes(id,school_id,academic_year_id,cycle_key,name,option,teacher_profile_id) values($1,$2,$3,'primary','3e Primaire','Générale',$4)",
    [classA, jeanne.schoolId, jeanne.yearId, jeanne.profileId],
  );
  await admin.query(
    "insert into app.classes(id,school_id,academic_year_id,cycle_key,name,option) values($1,$2,$3,'primary','4e Primaire','Générale')",
    [classB, jeanne.schoolId, jeanne.yearId],
  );
  await admin.query("insert into app.locations(id,school_id,code,label) values($1,$2,'PORTAIL','Portail')", [locationId, jeanne.schoolId]);
  await admin.query(
    "insert into app.security_portals(id,school_id,location_id,code,label,is_open,is_active) values($1,$2,$3,'PORTAIL','Portail',true,true)",
    [portalId, jeanne.schoolId, locationId],
  );
  await admin.query("insert into app.school_settings(school_id,lockdown_active) values($1,false) on conflict (school_id) do update set lockdown_active = false", [jeanne.schoolId]);

  const passwordHash = await argonHash(fixturePassword, { memoryCost: 19456, timeCost: 2, parallelism: 1 });
  async function fixturePerson(email, display, roleCode) {
    const userId = randomUUID();
    const identityId = randomUUID();
    const profileId = randomUUID();
    await admin.query("insert into iam.users(id,auth_provider,email,is_active) values($1,'local',$2,true)", [userId, email]);
    await admin.query("insert into auth.identities(id,user_id,email,status) values($1,$2,$3,'active')", [identityId, userId, email]);
    await admin.query("insert into auth.credentials(identity_id,password_hash,algo_version,must_change) values($1,$2,'argon2id-v1',false)", [identityId, passwordHash]);
    await admin.query(
      "insert into iam.profiles(id,user_id,school_id,display_name,is_active,account_status) values($1,$2,$3,$4,true,'active')",
      [profileId, userId, jeanne.schoolId, display],
    );
    await admin.query("begin");
    try {
      await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, jeanne.schoolId, randomUUID()]);
      await admin.query(
        "insert into iam.profile_roles(school_id,profile_id,role_id,assigned_by,is_active) select $1,$2,id,$3,true from iam.roles where school_id=$1 and code=$4",
        [jeanne.schoolId, profileId, jeanne.profileId, roleCode],
      );
      await admin.query("commit");
    } catch (error) {
      await admin.query("rollback");
      throw error;
    }
    return { userId, profileId, email, display, roleCode };
  }
  const direction = await fixturePerson("direction-mp@example.test", "Diane Direction", "school_head");
  const parent = await fixturePerson("parent-mp@example.test", "Marie Kabila", "parent");
  const guard = await fixturePerson("guard-mp@example.test", "Paul Portail", "guard");
  const fees = await fixturePerson("fees-mp@example.test", "Chantal Frais", "staff");
  const outsider = await fixturePerson("outsider-mp@example.test", "Ines Sansdroit", "staff");
  await admin.query("begin");
  try {
    await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, jeanne.schoolId, randomUUID()]);
    await admin.query(
      `update iam.grant_scopes s set target_id = $1
       from iam.role_permission_grants g
       join iam.roles r on r.id = g.role_id
       where s.grant_id = g.id and r.school_id = $2 and r.code = 'guard' and s.scope_code = 'assigned_portal'`,
      [portalId, jeanne.schoolId],
    );
    await admin.query("commit");
  } catch (error) {
    await admin.query("rollback");
    throw error;
  }

  async function addStudent(schoolId, yearId, classId, matricule, first, last) {
    const studentId = randomUUID();
    await admin.query(
      "insert into app.students(id,school_id,class_id,matricule,first_name,last_name,date_of_birth,lifecycle_status) values($1,$2,$3,$4,$5,$6,'2016-04-02','active')",
      [studentId, schoolId, classId, matricule, first, last],
    );
    await admin.query(
      "insert into app.student_enrollments(school_id,student_id,academic_year_id,class_id,status,starts_on) values($1,$2,$3,$4,'active','2026-09-01')",
      [schoolId, studentId, yearId, classId],
    );
    return studentId;
  }
  async function addGuardian(studentId, type, fullName, phone, primary, profileId) {
    const id = randomUUID();
    await admin.query(
      "insert into app.student_guardians(id,school_id,student_id,profile_id,guardian_type,full_name,phone,is_primary,is_authorized_pickup,is_active) values($1,$2,$3,$4,$5,$6,$7,$8,true,true)",
      [id, jeanne.schoolId, studentId, profileId, type, fullName, phone, primary],
    );
    return id;
  }
  const amina = await addStudent(jeanne.schoolId, jeanne.yearId, classA, "MAT-A", "Amina", "Kabila");
  const boris = await addStudent(jeanne.schoolId, jeanne.yearId, classB, "MAT-B", "Boris", "Ilunga");
  const claire = await addStudent(jeanne.schoolId, jeanne.yearId, classA, "MAT-C", "Claire", "Mbuyi");
  const diane = await addStudent(jeanne.schoolId, jeanne.yearId, classA, "MAT-D", "Diane", "Ngoy");
  const hugo = await addStudent(jeanne.schoolId, jeanne.yearId, classA, "MAT-H", "Hugo", "Banza");
  const gina = await addStudent(jeanne.schoolId, jeanne.yearId, classA, "MAT-G", "Gina", "Mwamba");
  const lea = await addStudent(jeanne.schoolId, jeanne.yearId, classA, "MAT-L", "Lea", "Mukendi");
  const mere = await addGuardian(amina, "mere", "Marie Kabila", "+243810009001", true, parent.profileId);
  const pere = await addGuardian(amina, "pere", "Joseph Kabila", "+243810009011", false, null);
  const tuteur = await addGuardian(amina, "tuteur", "Oscar Tshibanda", "+243810009012", false, null);
  const ginaGuardian = await addGuardian(gina, "mere", "Lara Mwamba", "+243810009013", true, null);
  const feeStructure = randomUUID();
  const studentFee = randomUUID();
  await admin.query(
    "insert into app.fee_structures(id,school_id,academic_year_id,cycle_key,label,amount,currency) values($1,$2,$3,'primary','Frais de test',100,'USD')",
    [feeStructure, jeanne.schoolId, jeanne.yearId],
  );
  await admin.query("begin");
  try {
    await admin.query("select api.set_request_context($1,$2,$3,$4)", [jeanne.userId, jeanne.profileId, jeanne.schoolId, randomUUID()]);
    await admin.query(
      "insert into app.student_fees(id,school_id,student_id,fee_structure_id,status,amount_expected,amount_paid,amount_remaining) values($1,$2,$3,$4,'pending',100,0,100)",
      [studentFee, jeanne.schoolId, amina, feeStructure],
    );
    await admin.query("commit");
  } catch (error) {
    await admin.query("rollback");
    throw error;
  }
  const classOther = randomUUID();
  await admin.query(
    "insert into app.classes(id,school_id,academic_year_id,cycle_key,name,option) values($1,$2,$3,'primary','1re Primaire','Générale')",
    [classOther, other.schoolId, other.yearId],
  );
  const foreignStudent = await addStudent(other.schoolId, other.yearId, classOther, "MAT-Z", "Zoe", "Etrangere");
  const foreignNumber = "SS-" + other.schoolId.slice(0, 8) + "-MAT-Z";
  const foreignSignature = signCardNumber(foreignNumber, cardSecret);
  if (foreignSignature !== signCard(foreignNumber)) throw new Error("signature mismatch");
  await admin.query("begin");
  try {
    await admin.query("select api.set_request_context($1,$2,$3,$4)", [other.userId, other.profileId, other.schoolId, randomUUID()]);
    await admin.query(
      "insert into app.student_cards(school_id,student_id,card_number,card_secret,signature,status) values($1,$2,$3,$4,$5,'active')",
      [other.schoolId, foreignStudent, foreignNumber, randomBytes(16).toString("hex"), foreignSignature],
    );
    await admin.query("commit");
  } catch (error) {
    await admin.query("rollback");
    throw error;
  }
  await auth.end();
  await migrator.end();

  const people = { jeanne: { ...jeanne, email: "jeanne-mp@example.test" }, direction, parent, guard, fees, outsider, other: { ...other, email: "other-mp@example.test" } };
  const ids = { classA, classB, locationId, portalId, amina, boris, claire, diane, hugo, gina, lea, mere, pere, tuteur, ginaGuardian, studentFee, foreignNumber, foreignSignature };

  server = await boot(licensedEnv());
  const sessions = {};
  for (const key of ["jeanne", "direction", "parent", "guard", "fees", "outsider", "other"]) {
    const logged = await login(people[key].email);
    sessions[key] = logged.cookie;
    record("LOGIN_" + key.toUpperCase(), logged.status === 200 && logged.cookie.includes("schoolsafe_session=") ? "PASS" : "STOP " + logged.status + " " + JSON.stringify(logged.body).slice(0, 180));
  }

  async function prove(label, response, ok) {
    record(label, ok ? "PASS" : "STOP " + response.status + " " + JSON.stringify(response.json).slice(0, 220));
    return ok;
  }

  for (const schoolKey of ["jeanne", "other"]) {
    const redeemed = await call(sessions[schoolKey], "POST", "/native/license/redeem", { activation_code: "live" });
    record("LICENSE_" + schoolKey.toUpperCase(), redeemed.status + ":" + (payloadOf(redeemed).state || redeemed.json.code));
  }
  const open = await call(sessions.jeanne, "GET", "/native/scan-control/capabilities");
  await prove("LICENSE_GATE_OPEN", open, open.status === 200);
  const probe = await call(sessions.jeanne, "POST", "/native/access/users", {
    first_name: "Probe",
    last_name: "Creation",
    email: "probe-mp@example.test",
    phone: "+243810000099",
    role_codes: ["guard"],
    employee_number: "EMP-PROBE",
    job_title: "Gardien",
    hired_on: "2026-09-01",
  }, { "x-schoolsafe-action": "access-write" });
  record("PRODUCTION_CREATE_HTTP", probe.status + ":" + (probe.json.code || probe.json.message || "ok"));

  const profile = await call(sessions.jeanne, "PUT", "/native/school/official-profile", {
    name: "École Cartes Réelles",
    city: "Kinshasa",
    province: "Kinshasa",
    country: "République démocratique du Congo",
    phone: "+243810000001",
    address: "12 Avenue de la Paix",
  });
  await prove("OFFICIAL_PROFILE", profile, profile.status === 200);
  const form = new FormData();
  form.append("logo", new Blob([png], { type: "image/png" }), "logo.png");
  const logoResponse = await fetch(origin + "/native/school/logo", { method: "POST", headers: { cookie: sessions.jeanne }, body: form });
  const logoJson = await logoResponse.json().catch(() => ({}));
  record("LOGO_UPLOAD", logoResponse.status === 200 ? "PASS" : "STOP " + logoResponse.status + " " + JSON.stringify(logoJson).slice(0, 180));
  const identity = await call(sessions.jeanne, "GET", "/native/cards/school-identity");
  const identityData = payloadOf(identity);
  record("SCHOOL_IDENTITY", identity.status === 200 && identityData.name ? identityData.name : "STOP " + identity.status);
  record("SCHOOL_YEAR_READY", identityData.city || "");

  const caps = ["SCAN_ENTRY", "SCAN_PICKUP", "SCAN_VERIFY", "SCAN_FEES", "CARD_CREATE", "CARD_PRINT", "CARD_LOST", "CARD_REPLACE", "CARD_REPRINT", "CARD_REVOKE"];
  for (const capability of caps) {
    const granted = await call(sessions.jeanne, "POST", "/native/scan-control/grants", {
      profile_id: jeanne.profileId, capability, action: "assign", reason: "Poste de Jeanne",
    });
    if (granted.status !== 200) record("GRANT_JEANNE_" + capability, "STOP " + granted.status);
  }
  for (const capability of ["SCAN_ENTRY", "SCAN_PICKUP", "SCAN_VERIFY"]) {
    const granted = await call(sessions.jeanne, "POST", "/native/scan-control/grants", {
      profile_id: guard.profileId, capability, action: "assign", reason: "Poste du portail",
    });
    record("GRANT_GUARD_" + capability, granted.status === 200 ? "PASS" : "STOP " + granted.status + " " + JSON.stringify(granted.json).slice(0, 160));
  }
  const feeOn = await call(sessions.jeanne, "POST", "/native/scan-control/fees/module", { enabled: true });
  record("FEE_MODULE", feeOn.status === 200 ? "PASS" : "STOP " + feeOn.status + " " + JSON.stringify(feeOn.json).slice(0, 160));
  const delegated = await call(sessions.jeanne, "POST", "/native/scan-control/fees/delegations", {
    profile_id: fees.profileId,
    capability: "SCAN_FEES",
    action: "assign",
    reason: "Contrôle de la 3e Primaire",
    class_ids: [classA],
  });
  record("FEE_DELEGATION", delegated.status === 200 ? "PASS" : "STOP " + delegated.status + " " + JSON.stringify(delegated.json).slice(0, 180));

  const cards = {};
  for (const [name, studentId] of [["amina", amina], ["boris", boris], ["claire", claire], ["diane", diane], ["hugo", hugo], ["gina", gina], ["lea", lea]]) {
    const issued = await call(sessions.jeanne, "POST", "/native/scan-control/cards/issue", { student_id: studentId });
    const data = payloadOf(issued);
    cards[name] = data;
    record("ISSUE_" + name.toUpperCase(), issued.status === 200 && data.qr_payload ? "PASS" : "STOP " + issued.status + " " + JSON.stringify(issued.json).slice(0, 180));
  }
  const year = await call(sessions.jeanne, "GET", "/native/cards/students/" + amina + "/academic-year");
  record("ACADEMIC_YEAR", year.status === 200 ? JSON.stringify(payloadOf(year)).slice(0, 120) : "STOP " + year.status);

  async function scan(cookie, mode, qr, extra = {}) {
    return call(cookie, "POST", "/native/scan-control/scan", {
      mode, qr_payload: qr, location_id: locationId, ...extra,
    });
  }
  if (!cards.amina.qr_payload) throw new Error("amina card was not issued");
  const entry = await scan(sessions.guard, "entry", cards.amina.qr_payload);
  record("ENTRY_VALID", resultOf(entry));
  const entryAgain = await scan(sessions.guard, "entry", cards.amina.qr_payload);
  record("ENTRY_SECOND", resultOf(entryAgain));
  const entryEvents = await admin.query("select count(*)::int n from app.security_events where student_id=$1 and event_type='entry'", [amina]);
  record("ENTRY_EVENTS", entryEvents.rows[0].n);
  const attendance = await admin.query("select arrived_at is not null as arrived from app.attendance_records where student_id=$1 and day=current_date", [amina]);
  record("ENTRY_ATTENDANCE", attendance.rows[0]?.arrived === true ? "PASS" : "STOP");

  const hexNumber = cards.amina.qr_payload.split("/")[3];
  const hex = "schoolsafe://card/" + hexNumber + "/0123456789abcdef0123456789abcdef";
  const hexScan = await scan(sessions.guard, "verify", hex);
  record("HEX_SIGNATURE", resultOf(hexScan));
  const forged = await scan(sessions.guard, "verify", "schoolsafe://card/SS-FORGED/" + "a".repeat(32));
  record("FORGED_QR", resultOf(forged));
  const foreign = await scan(sessions.guard, "verify", "schoolsafe://card/" + foreignNumber + "/" + foreignSignature);
  record("FOREIGN_CARD", resultOf(foreign) + ":" + (payloadOf(foreign).reason || ""));
  const verifyEventsBefore = (await admin.query("select count(*)::int n from app.security_events where student_id=$1", [lea])).rows[0].n;
  const verified = await scan(sessions.guard, "verify", cards.lea.qr_payload);
  record("VERIFY_VALID", resultOf(verified));
  const verifyEventsAfter = (await admin.query("select count(*)::int n from app.security_events where student_id=$1 and event_type in ('entry','pickup_confirmed')", [lea])).rows[0].n;
  record("VERIFY_NO_MOVEMENT", verifyEventsBefore + "->" + verifyEventsAfter);

  const lost = await call(sessions.jeanne, "POST", "/native/scan-control/cards/loss", { student_id: diane, card_id: cards.diane.card_id, reason: "Carte déclarée perdue" });
  record("LOST_DECLARE", lost.status === 200 ? "PASS" : "STOP " + lost.status + " " + JSON.stringify(lost.json).slice(0, 160));
  const lostScan = await scan(sessions.guard, "entry", cards.diane.qr_payload);
  record("LOST_SCAN", resultOf(lostScan));
  const replaced = await call(sessions.jeanne, "POST", "/native/scan-control/cards/" + cards.claire.card_id + "/canonical-replace");
  record("REPLACE", replaced.status === 200 && payloadOf(replaced).qr_payload ? "PASS" : "STOP " + replaced.status + " " + JSON.stringify(replaced.json).slice(0, 180));
  const oldClaire = await scan(sessions.guard, "verify", cards.claire.qr_payload);
  record("REPLACED_OLD_QR", resultOf(oldClaire));
  const newClaire = await scan(sessions.guard, "verify", payloadOf(replaced).qr_payload);
  record("REPLACED_NEW_QR", resultOf(newClaire));
  const revoked = await call(sessions.jeanne, "POST", "/native/scan-control/cards/" + cards.hugo.card_id + "/revoke", { reason: "Carte retirée" });
  record("REVOKE", revoked.status === 200 ? "PASS" : "STOP " + revoked.status + " " + JSON.stringify(revoked.json).slice(0, 160));
  const revokedScan = await scan(sessions.guard, "verify", cards.hugo.qr_payload);
  record("REVOKED_SCAN", resultOf(revokedScan));

  const feesAmina = await scan(sessions.fees, "fees", cards.amina.qr_payload);
  record("FEES_AMINA", resultOf(feesAmina) + ":" + JSON.stringify(payloadOf(feesAmina)).slice(0, 180));
  const feesBoris = await scan(sessions.fees, "fees", cards.boris.qr_payload);
  record("FEES_OTHER_CLASS", resultOf(feesBoris));
  const feesLea = await scan(sessions.fees, "fees", cards.lea.qr_payload);
  record("FEES_NO_DATA", resultOf(feesLea) + ":" + JSON.stringify(payloadOf(feesLea)).slice(0, 180));
  const forgedStatus = await call(sessions.fees, "POST", "/native/scan-control/scan", {
    mode: "fees", qr_payload: cards.amina.qr_payload, location_id: locationId, status: "paid",
  });
  record("FEES_FORGED_STATUS", forgedStatus.status + ":" + (forgedStatus.json.code || resultOf(forgedStatus)));
  const payment = await call(sessions.fees, "POST", "/native/finance/payments", {
    student_fee_id: studentFee, amount: 10, currency: "USD", mode: "cash",
  });
  record("FEES_CANNOT_PAY", payment.status + ":" + (payment.json.code || "ok"));
  const entryDespiteDue = await scan(sessions.guard, "entry", cards.boris.qr_payload);
  record("UNPAID_DOES_NOT_BLOCK_ENTRY", resultOf(entryDespiteDue));

  const expiredAt = new Date(Date.now() - 60_000).toISOString();
  await admin.query("update app.scan_control_grants set expires_at = $1 where profile_id = $2 and capability = 'SCAN_FEES' and revoked_at is null", [expiredAt, fees.profileId]);
  const expiredFees = await scan(sessions.fees, "fees", cards.amina.qr_payload);
  record("FEES_EXPIRED", resultOf(expiredFees));
  const redelegated = await call(sessions.jeanne, "POST", "/native/scan-control/fees/delegations", {
    profile_id: fees.profileId, capability: "SCAN_FEES", action: "assign", reason: "Renouvellement", class_ids: [classA],
  });
  record("FEE_REDELEGATE", redelegated.status === 200 ? "PASS" : "STOP " + redelegated.status);
  const during = await scan(sessions.fees, "fees", cards.amina.qr_payload);
  const removed = await call(sessions.jeanne, "POST", "/native/scan-control/fees/delegations", {
    profile_id: fees.profileId, capability: "SCAN_FEES", action: "revoke", reason: "Retrait pendant la consultation",
  });
  const afterRemove = await scan(sessions.fees, "fees", cards.amina.qr_payload);
  record("FEES_DURING", resultOf(during));
  record("FEES_REVOKED_DURING", resultOf(afterRemove) + " revoke=" + removed.status);
  await call(sessions.jeanne, "POST", "/native/scan-control/fees/delegations", {
    profile_id: fees.profileId, capability: "SCAN_FEES", action: "assign", reason: "Rétablissement pour le navigateur", class_ids: [classA],
  });

  const portrait = new FormData();
  portrait.append("portrait", new Blob([png], { type: "image/png" }), "portrait.png");
  const portraitResponse = await fetch(origin + "/native/family/guardians/" + pere + "/portrait", {
    method: "POST", headers: { cookie: sessions.jeanne }, body: portrait,
  });
  const portraitJson = await portraitResponse.json().catch(() => ({}));
  record("PORTRAIT_UPLOAD", portraitResponse.status === 200 ? "PASS" : "STOP " + portraitResponse.status + " " + JSON.stringify(portraitJson).slice(0, 180));
  const directionReview = await call(sessions.direction, "POST", "/native/family/guardians/" + pere + "/photo-review", { decision: "accepted", note: "Vu" });
  record("DIRECTION_PHOTO_BEFORE_EXCEPTION", directionReview.status + ":" + (directionReview.json.code || resultOf(directionReview)));
  const exception = await call(sessions.jeanne, "POST", "/native/access/profiles/" + direction.profileId + "/exceptions", {
    permission: "school.guardian.manage", effect: "allow", reason: "La direction valide les photographies",
  }, { "x-schoolsafe-action": "access-write" });
  record("DIRECTION_EXCEPTION", exception.status + ":" + (exception.json.code || "ok"));
  const directionReviewAfter = await call(sessions.direction, "POST", "/native/family/guardians/" + pere + "/photo-review", { decision: "accepted", note: "Photographie acceptée" });
  record("DIRECTION_PHOTO_AFTER", directionReviewAfter.status + ":" + JSON.stringify(directionReviewAfter.json).slice(0, 180));
  const merePortrait = new FormData();
  merePortrait.append("portrait", new Blob([png], { type: "image/png" }), "portrait.png");
  const merePhoto = await fetch(origin + "/native/family/guardians/" + mere + "/portrait", {
    method: "POST", headers: { cookie: sessions.jeanne }, body: merePortrait,
  });
  record("MERE_PORTRAIT", merePhoto.status);
  if (merePhoto.status === 200) {
    const mereReview = await call(sessions.direction, "POST", "/native/family/guardians/" + mere + "/photo-review", { decision: "accepted", note: "Mère acceptée" });
    record("MERE_REVIEW", mereReview.status + ":" + (mereReview.json.code || "ok"));
  }
  const external = await call(sessions.jeanne, "POST", "/native/family/students/" + amina + "/accredited", {
    full_name: "Nadia Externe", phone: "+243810009099", consent: "Accord du responsable",
  });
  record("EXTERNAL_REGISTER", external.status + ":" + JSON.stringify(external.json).slice(0, 160));
  const parentMine = await call(sessions.parent, "GET", "/native/family/students/" + amina + "/pickup-authorizations/mine");
  record("PARENT_OWN_CHILD", parentMine.status + ":" + JSON.stringify(parentMine.json).slice(0, 160));
  const parentForeign = await call(sessions.parent, "GET", "/native/family/students/" + boris + "/pickup-authorizations/mine");
  record("PARENT_OTHER_FAMILY", parentForeign.status + ":" + (parentForeign.json.code || "ok"));
  const directReact = await call(sessions.parent, "POST", "/native/family/guardians/" + pere + "/reactivation-decide", { decision: "approved", reason: "Tentative directe" });
  record("PARENT_DIRECT_REACTIVATE", directReact.status + ":" + (directReact.json.code || "ok"));

  const choice = await scan(sessions.guard, "pickup", cards.amina.qr_payload);
  record("PICKUP_CHOICE", resultOf(choice) + ":" + JSON.stringify(payloadOf(choice)).slice(0, 220));
  const prepared = await scan(sessions.guard, "pickup", cards.amina.qr_payload, { authorized_person_id: mere });
  const proof = payloadOf(prepared).proof_token || "";
  record("PICKUP_PREPARE", resultOf(prepared) + " token=" + (proof ? "yes" : "no"));
  if (proof) {
    const [first, second] = await Promise.all([
      scan(sessions.guard, "pickup", cards.amina.qr_payload, { authorized_person_id: mere, human_confirmed: true, proof_token: proof }),
      scan(sessions.guard, "pickup", cards.amina.qr_payload, { authorized_person_id: mere, human_confirmed: true, proof_token: proof }),
    ]);
    record("PICKUP_CONCURRENT", resultOf(first) + " | " + resultOf(second));
  }
  const suspendPrep = await scan(sessions.guard, "pickup", cards.amina.qr_payload, { authorized_person_id: pere });
  const suspendToken = payloadOf(suspendPrep).proof_token || "";
  record("SUSPEND_PREPARE", resultOf(suspendPrep));
  const suspended = await call(sessions.parent, "POST", "/native/family/guardians/" + pere + "/parent-suspend", { reason: "Absence imprévue" });
  record("PARENT_SUSPEND", suspended.status + ":" + JSON.stringify(suspended.json).slice(0, 160));
  if (suspendToken) {
    const refused = await scan(sessions.guard, "pickup", cards.amina.qr_payload, { authorized_person_id: pere, human_confirmed: true, proof_token: suspendToken });
    record("PICKUP_WHILE_SUSPENDED", resultOf(refused) + ":" + JSON.stringify(payloadOf(refused)).slice(0, 160));
  }
  const asked = await call(sessions.parent, "POST", "/native/family/guardians/" + pere + "/reactivation-request", { reason: "Retour du père" });
  record("REACTIVATION_REQUEST", asked.status + ":" + JSON.stringify(asked.json).slice(0, 140));
  const decided = await call(sessions.direction, "POST", "/native/family/guardians/" + pere + "/reactivation-decide", { decision: "approved", reason: "Identité confirmée" });
  record("DIRECTION_REACTIVATE", decided.status + ":" + JSON.stringify(decided.json).slice(0, 160));

  const ginaPrep = await scan(sessions.guard, "pickup", cards.gina.qr_payload, { authorized_person_id: ginaGuardian });
  const ginaToken = payloadOf(ginaPrep).proof_token || "";
  record("GINA_PREPARE", resultOf(ginaPrep));
  if (ginaToken) {
    const ginaRevoke = await call(sessions.jeanne, "POST", "/native/scan-control/cards/" + cards.gina.card_id + "/revoke", { reason: "Révoquée après préparation" });
    const ginaConfirm = await scan(sessions.guard, "pickup", cards.gina.qr_payload, { authorized_person_id: ginaGuardian, human_confirmed: true, proof_token: ginaToken });
    record("REVOKE_AFTER_PREPARE", ginaRevoke.status + ":" + resultOf(ginaConfirm));
  }

  const printCarte = await call(sessions.jeanne, "POST", "/native/cards/students/" + lea + "/print", {
    format: "carte", front_image_base64: pngB64, back_image_base64: pngB64, metadata: { face: "recto-verso", classe: "3e Primaire" },
  });
  const printBadge = await call(sessions.jeanne, "POST", "/native/cards/students/" + lea + "/print", {
    format: "badge", front_image_base64: pngB64, back_image_base64: pngB64, metadata: { face: "badge" },
  });
  record("PRINT_CARTE", printCarte.status + ":" + JSON.stringify(payloadOf(printCarte)).slice(0, 140));
  record("PRINT_BADGE", printBadge.status + ":" + JSON.stringify(payloadOf(printBadge)).slice(0, 140));
  const prints = await call(sessions.jeanne, "GET", "/native/cards/print-requests?limit=10");
  record("PRINT_REREAD", prints.status + ":" + (Array.isArray(payloadOf(prints)) ? payloadOf(prints).length : JSON.stringify(payloadOf(prints)).slice(0, 80)));
  const batch = await call(sessions.jeanne, "POST", "/native/cards/batches", { status: "submitted" });
  record("CLASS_BATCH_R2", batch.status + ":" + (batch.json.code || "ok"));

  const outsiderScan = await scan(sessions.outsider, "entry", cards.amina.qr_payload);
  record("OUTSIDER_SCAN", outsiderScan.status + ":" + (outsiderScan.json.code || resultOf(outsiderScan)));
  const otherStudent = await call(sessions.other, "GET", "/native/cards/students/" + amina + "/credential");
  record("OTHER_SCHOOL_STUDENT", otherStudent.status + ":" + (otherStudent.json.code || "ok"));
  const otherIdentity = await call(sessions.other, "GET", "/native/cards/school-identity");
  record("OTHER_SCHOOL_IDENTITY", payloadOf(otherIdentity).name || otherIdentity.json.code || otherIdentity.status);

  const outsiderLogin = await login(outsider.email);
  await admin.query("update auth.sessions set expires_at = now() - interval '1 minute' where profile_id = $1", [outsider.profileId]);
  const expiredSession = await call(outsiderLogin.cookie, "GET", "/native/scan-control/capabilities");
  record("SESSION_EXPIRED", expiredSession.status + ":" + (expiredSession.json.code || "ok"));
  const fresh = await login(outsider.email);
  const loggedOut = await call(fresh.cookie, "POST", "/auth/native/logout");
  const afterLogout = await call(fresh.cookie, "GET", "/native/scan-control/capabilities");
  record("LOGOUT_THEN_API", loggedOut.status + "->" + afterLogout.status + ":" + (afterLogout.json.code || "ok"));

  const cacheStart = Date.now();
  const cached = await call(sessions.jeanne, "GET", "/native/scan-control/capabilities");
  const revokeLicense = await call(sessions.jeanne, "POST", "/native/license/redeem", { activation_code: "revoked" });
  const stillOpen = await call(sessions.jeanne, "GET", "/native/scan-control/capabilities");
  const waitMs = Math.max(0, 62000 - (Date.now() - cacheStart));
  await new Promise((resolve) => setTimeout(resolve, waitMs));
  const afterTtl = await call(sessions.jeanne, "GET", "/native/scan-control/capabilities");
  record("LICENSE_CACHE_IMMEDIATE", cached.status + " revoke=" + (payloadOf(revokeLicense).state || revokeLicense.json.code) + " still=" + stillOpen.status + ":" + (stillOpen.json.code || "open"));
  record("LICENSE_CACHE_AFTER_TTL", afterTtl.status + ":" + (afterTtl.json.code || "open") + " waited_ms=" + (Date.now() - cacheStart));
  const restored = await call(sessions.jeanne, "POST", "/native/license/redeem", { activation_code: "live" });
  record("LICENSE_RESTORED", restored.status + ":" + (payloadOf(restored).state || restored.json.code));

  await stop(server);
  server = null;
  detached = await boot(licensedEnv(), { detached: true });
  if (detached.unref) detached.unref();
  const finalLogin = await login(jeanne.email);
  const finalRoute = await call(finalLogin.cookie, "GET", "/native/cards/school-identity");
  record("SERVER_RESTARTED", finalRoute.status === 200 ? "PASS" : "STOP " + finalRoute.status + " " + (finalRoute.json.code || ""));

  const state = {
    origin,
    pid: detached.pid,
    schoolA: jeanne.schoolId,
    schoolB: other.schoolId,
    password: fixturePassword,
    people: Object.fromEntries(Object.entries(people).map(([key, value]) => [key, { email: value.email, profileId: value.profileId, display: value.display || key }])),
    ids,
    cards: Object.fromEntries(Object.entries(cards).map(([key, value]) => [key, {
      id: value.card_id || null,
      qr: value.qr_payload || null,
    }])),
    replacedClaireQr: payloadOf(replaced).qr_payload || null,
    foreignQr: "schoolsafe://card/" + foreignNumber + "/" + foreignSignature,
    results,
  };
  writeFileSync(statePath, JSON.stringify(state, null, 2));
  record("STATE", statePath);
  record("KEEP_SERVER", keep ? "yes" : "no");
  if (!keep) {
    await stop(detached);
    detached = null;
  }
} finally {
  if (server) await stop(server);
  if (!keep && detached) await stop(detached);
  activation.close();
  if (!keep) rmSync(dir, { recursive: true, force: true });
}
