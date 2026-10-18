import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { generateKeyPairSync, randomBytes, randomUUID, sign } from "node:crypto";
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
assert.ok(base, "DATABASE_URL required");
for (const role of ["api", "auth", "worker", "migrator"]) assert.ok(passwords[role] && passwords[role].length >= 32, role);

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
async function recreate(name) {
  const client = new pg.Client({ connectionString: base, connectionTimeoutMillis: 5000 });
  await client.connect();
  await client.query("select pg_terminate_backend(pid) from pg_stat_activity where datname=$1 and pid<>pg_backend_pid()", [name]);
  await client.query('drop database if exists "' + name + '"');
  await client.query('create database "' + name + '"');
  await client.end();
}
function asObject(value) {
  return typeof value === "string" ? JSON.parse(value) : value;
}

const authority = generateKeyPairSync("ed25519");
const installation = generateKeyPairSync("ed25519");
const keyId = "schoolsafe-local-test-v1";
const installationId = randomUUID();
const dir = mkdtempSync(path.join(tmpdir(), "schoolsafe-license-"));
const installationKeyPath = path.join(dir, "installation.pem");
const pfxPath = path.join(dir, "activation.pfx");
const pfxPassword = "license-temp";
writeFileSync(installationKeyPath, installation.privateKey.export({ type: "pkcs8", format: "pem" }));
const publicPem = authority.publicKey.export({ type: "spki", format: "pem" }).toString();

const { canonicalizePayloadV1 } = await tsImport(
  pathToFileURL(path.join(repositoryRoot, "server/src/licensenative/canonicalize.ts")).href,
  import.meta.url,
);

function envelope(payload, forged = false) {
  const canonical = canonicalizePayloadV1(payload);
  let signature = sign(null, Buffer.from(canonical, "utf8"), authority.privateKey).toString("base64url");
  if (forged) signature = "A".repeat(signature.length);
  return { payload, signature };
}
function payloadFor(kind, body, issuedAt) {
  const schoolId = kind === "other" ? randomUUID() : body.school_id;
  const status = kind === "suspended" ? "suspended" : kind === "revoked" ? "revoked" : "active";
  const expired = kind === "expired";
  const issued = expired ? "2019-06-01T00:00:00.000Z" : issuedAt;
  return {
    version: 1,
    license_id: randomUUID(),
    school_id: schoolId,
    installation_id: body.installation_id,
    status,
    plan: "school-test",
    modules: ["cards"],
    issued_at: issued,
    expires_at: expired ? "2020-01-01T00:00:00.000Z" : "2027-10-10T00:00:00.000Z",
    perpetual: false,
    key_id: keyId,
  };
}

const kinds = {
  forged: "forged",
  other: "other",
  expired: "expired",
  suspended: "suspended",
  revoked: "revoked",
  live: "live",
};
let issuedStep = 0;
function nextIssuedAt() {
  issuedStep += 1;
  return new Date(Date.now() + issuedStep * 1000).toISOString();
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
  const kind = kinds[body.activation_code];
  if (req.url !== "/v1/activation/redeem" || !kind) {
    res.writeHead(404);
    res.end();
    return;
  }
  const signed = envelope(payloadFor(kind, body, nextIssuedAt()), kind === "forged");
  res.writeHead(200, { "content-type": "application/json" });
  res.end(JSON.stringify(signed));
});
await new Promise((resolve) => activation.listen(0, "127.0.0.1", resolve));
const activationPort = activation.address().port;
const activationUrl = "https://127.0.0.1:" + activationPort;

const name = "schoolsafe_test_license";
const email = "license-" + randomUUID() + "@example.test";
const password = "L" + randomBytes(18).toString("base64url");
let server;
const results = [];
function record(label, value) {
  results.push(label + "=" + value);
  console.log(label + "=" + value);
}

try {
  await recreate(name);
  await installSchoolDatabase({
    connectionString: databaseUrl(name).toString(),
    database: name,
    mode: "apply",
    passwords,
    log: () => {},
  });
  const auth = new pg.Client({ connectionString: roleUrl(name, "auth") });
  const migrator = new pg.Client({ connectionString: roleUrl(name, "migrator") });
  await Promise.all([auth.connect(), migrator.connect()]);
  const tokenHash = randomBytes(32).toString("hex");
  const passwordHash = await argonHash(password, { memoryCost: 19456, timeCost: 2, parallelism: 1 });
  const setupPayload = {
    identity: { name_fr: "Ecole Licence Reelle" },
    cycles: ["primary"],
    academic_year: { label: "2026-2027", starts_on: "2026-09-01", ends_on: "2027-07-31", periods: "Trimestres" },
    contact: {},
    brand: {},
  };
  await migrator.query("select ops.authorize_school_setup($1,$2)", [tokenHash, 3600]);
  const staged = asObject((await auth.query("select api.setup_stage_school($1,$2::jsonb) r", [tokenHash, JSON.stringify(setupPayload)])).rows[0].r);
  await auth.query("select api.setup_complete_school($1,$2,$3,$4,$5,$6) r", [tokenHash, email, passwordHash, "Jeanne", "Mbayo", null]);
  await auth.end();
  await migrator.end();
  record("SCHOOL", staged.school_id);
  record("ACCESS_ROW", "not inserted");

  const commonEnv = {
    NODE_ENV: "development",
    HOST: "127.0.0.1",
    PORT: "8791",
    PGHOST: "127.0.0.1",
    PGPORT: new URL(base).port,
    PGDATABASE: name,
    PGUSER: "schoolsafe_api",
    PGPASSWORD: passwords.api,
    PGAUTH_USER: "schoolsafe_auth",
    PGAUTH_PASSWORD: passwords.auth,
    CARD_HMAC_SECRET: "license-card-hmac-secret-value-32",
  };
  const licensedEnv = {
    ...commonEnv,
    ACTIVATION_SERVICE_URL: activationUrl,
    ACTIVATION_INSTALLATION_ID: installationId,
    ACTIVATION_INSTALLATION_PRIVATE_KEY_PATH: installationKeyPath,
    ACTIVATION_LICENSE_PUBLIC_KEYS_JSON: JSON.stringify({ [keyId]: publicPem }),
    NODE_TLS_REJECT_UNAUTHORIZED: "0",
  };

  async function boot(extra) {
    const child = spawn(process.execPath, ["--import", "tsx", "src/index.ts"], {
      cwd: path.join(repositoryRoot, "server"),
      env: { ...process.env, ...extra },
      stdio: ["ignore", "pipe", "pipe"],
      windowsHide: true,
    });
    let log = "";
    child.stdout.on("data", (chunk) => { log += chunk; });
    child.stderr.on("data", (chunk) => { log += chunk; });
    const url = "http://127.0.0.1:8791/";
    for (let attempt = 0; attempt < 40; attempt += 1) {
      if (child.exitCode !== null) throw new Error(log);
      try {
        const response = await fetch(url);
        if (response.status < 500) return child;
      } catch { /* server still starting */ }
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
    child.kill();
    throw new Error(log || "server did not start");
  }
  async function stop(child) {
    if (!child || child.killed) return;
    spawn("taskkill", ["/PID", String(child.pid), "/T", "/F"], { stdio: "ignore", windowsHide: true });
    await new Promise((resolve) => child.once("exit", resolve));
  }
  async function session() {
    const login = await fetch("http://127.0.0.1:8791/auth/native/login", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ login: email, password }),
    });
    const body = await login.json();
    assert.equal(login.status, 200, JSON.stringify(body));
    assert.equal(body.ok === false, false);
    const cookie = login.headers.getSetCookie().map((item) => item.split(";")[0]).join("; ");
    assert.match(cookie, /schoolsafe_session=/);
    return cookie;
  }
  async function call(cookie, method, urlPath, payload) {
    const response = await fetch("http://127.0.0.1:8791" + urlPath, {
      method,
      headers: { cookie, "content-type": "application/json" },
      body: payload === undefined ? undefined : JSON.stringify(payload),
    });
    const text = await response.text();
    let json = {};
    try { json = JSON.parse(text); } catch { json = { raw: text }; }
    return { status: response.status, json };
  }

  server = await boot(commonEnv);
  {
    const cookie = await session();
    const closed = await call(cookie, "GET", "/native/scan-control/capabilities");
    assert.equal(closed.status, 403);
    assert.equal(closed.json.code, "LICENSE_INACTIVE");
    assert.match(closed.json.message, /License verification unavailable/);
    record("NO_ACTIVATION_SERVICE", "LICENSE_INACTIVE");
  }
  await stop(server);
  server = null;

  server = await boot({ ...licensedEnv, ACTIVATION_SERVICE_URL: "https://127.0.0.1:9" });
  {
    const cookie = await session();
    const down = await call(cookie, "POST", "/native/license/redeem", { activation_code: "live" });
    assert.equal(down.status, 409);
    assert.equal(down.json.code, "ACTIVATION_REJECTED");
    const route = await call(cookie, "GET", "/native/scan-control/capabilities");
    assert.equal(route.status, 403);
    assert.equal(route.json.code, "LICENSE_INACTIVE");
    record("ACTIVATION_DOWN_WITHOUT_PROOF", "LICENSE_INACTIVE");
  }
  await stop(server);
  server = null;

  server = await boot(licensedEnv);
  const cookie = await session();
  for (const [code, expectedRedeem, expectedRoute] of [
    ["forged", 409, "LICENSE_INACTIVE"],
    ["other", 409, "LICENSE_INACTIVE"],
    ["expired", 200, "LICENSE_INACTIVE"],
    ["suspended", 200, "LICENSE_INACTIVE"],
    ["revoked", 200, "LICENSE_INACTIVE"],
    ["live", 200, "OPEN"],
  ]) {
    const redeemed = await call(cookie, "POST", "/native/license/redeem", { activation_code: code });
    assert.equal(redeemed.status, expectedRedeem, code + " " + JSON.stringify(redeemed.json));
    if (code === "expired") assert.equal(redeemed.json.data.state, "expired");
    if (code === "suspended") assert.equal(redeemed.json.data.state, "suspended");
    if (code === "revoked") assert.equal(redeemed.json.data.state, "revoked");
    if (code === "live") {
      assert.equal(redeemed.json.data.state, "active");
      record("LIVE_REDEEM", redeemed.json.data.license_id);
      continue;
    }
    const route = await call(cookie, "GET", "/native/scan-control/capabilities");
    if (expectedRoute === "OPEN") {
      assert.equal(route.status, 200, JSON.stringify(route.json));
      const scan = await call(cookie, "POST", "/native/scan-control/scan", {
        mode: "entry",
        qr_payload: "schoolsafe://card/SS-NONE/" + "a".repeat(32),
        location_id: randomUUID(),
      });
      assert.notEqual(scan.json.code, "LICENSE_INACTIVE");
      assert.equal(scan.status, 403);
      assert.equal(scan.json.code, "ACCESS_DENIED");
      record("LIVE_SIGNATURE_SCHOOL_INSTALLATION", "OPEN");
      record("USER_WITHOUT_SCAN_GRANT", "ACCESS_DENIED");
    } else {
      assert.equal(route.status, 403, code + " " + JSON.stringify(route.json));
      assert.equal(route.json.code, "LICENSE_INACTIVE");
      record(code.toUpperCase(), "LICENSE_INACTIVE");
    }
  }
  await stop(server);
  server = await boot(licensedEnv);
  {
    const openCookie = await session();
    const route = await call(openCookie, "GET", "/native/scan-control/capabilities");
    assert.equal(route.status, 200, JSON.stringify(route.json));
    const scan = await call(openCookie, "POST", "/native/scan-control/scan", {
      mode: "entry",
      qr_payload: "schoolsafe://card/SS-NONE/" + "a".repeat(32),
      location_id: randomUUID(),
    });
    assert.notEqual(scan.json.code, "LICENSE_INACTIVE");
    assert.equal(scan.status, 403);
    assert.equal(scan.json.code, "ACCESS_DENIED");
    record("LIVE_SIGNATURE_SCHOOL_INSTALLATION", "OPEN");
    record("USER_WITHOUT_SCAN_GRANT", "ACCESS_DENIED");
    const status = await call(openCookie, "GET", "/native/license/status");
    assert.equal(status.status, 200);
    assert.equal(status.json.data.state, "active");
    record("STORED_STATE", status.json.data.state);
  }
  await stop(server);
  server = null;
  record("NATIVE_LICENSE", "PASS");
} finally {
  if (server) spawn("taskkill", ["/PID", String(server.pid), "/T", "/F"], { stdio: "ignore", windowsHide: true });
  activation.close();
  rmSync(dir, { recursive: true, force: true });
}
