import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { randomBytes, randomUUID, createHash } from 'node:crypto';
import { pathToFileURL } from 'node:url';
import pg from 'pg';
import { hash as argonHash } from '@node-rs/argon2';
import { tsImport } from 'tsx/esm/api';
import { installSchoolDatabase } from './install-school-db.mjs';
import { loadInstallationPlan, repositoryRoot } from './installation-plan.mjs';
import { renderAdditiveUpgrade } from './render-additive-upgrade.mjs';

const UNIT = 'database/pedagogy/v2/01_teacher_assignment_list.sql';
const digest = (value) => createHash('sha256').update(value).digest('hex');

function databaseUrl(base, database) {
  const url = new URL(base);
  url.pathname = '/' + database;
  return url;
}

async function recreate(adminUrl, name) {
  const client = new pg.Client({ connectionString: adminUrl, connectionTimeoutMillis: 5000 });
  await client.connect();
  await client.query('select pg_terminate_backend(pid) from pg_stat_activity where datname=$1 and pid<>pg_backend_pid()', [name]);
  await client.query('drop database if exists "' + name + '"');
  await client.query('create database "' + name + '"');
  await client.end();
}

function roleUrl(base, name, role, password) {
  const url = databaseUrl(base, name);
  url.username = 'schoolsafe_' + role;
  url.password = password;
  return url.toString();
}

async function installWithoutReadback(base, name, passwords) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'schoolsafe-b2-old-'));
  fs.cpSync(path.join(repositoryRoot, 'database'), path.join(root, 'database'), { recursive: true });
  const plan = loadInstallationPlan();
  const historical = { ...plan, units: plan.units.filter((unit) => unit.file !== UNIT) };
  delete historical.digest;
  fs.unlinkSync(path.join(root, UNIT));
  fs.writeFileSync(path.join(root, 'database/pedagogy/v2/manifest.json'), JSON.stringify({ schema: 'schoolsafe-migrations-v2', name: 'pedagogy', version: 2, units: [] }));
  fs.writeFileSync(path.join(root, 'database/pedagogy/v2/manifest.sha256'), '\n');
  fs.writeFileSync(path.join(root, 'database/installation/v2/manifest.json'), JSON.stringify(historical));
  const connectionString = databaseUrl(base, name).toString();
  await installSchoolDatabase({ connectionString, database: name, mode: 'apply', passwords, root, log: () => {} });
  fs.rmSync(root, { recursive: true });
}

async function functionHasTutor(admin) {
  const def = (await admin.query("select pg_get_functiondef('api.teacher_assignment_list()'::regprocedure) def")).rows[0].def;
  return def.includes("'is_tutor'") && def.includes('teacher_profile_id');
}

async function upgradeAlreadyMigrated(base, name, passwords) {
  const admin = new pg.Client({ connectionString: databaseUrl(base, name).toString() });
  await admin.connect();
  assert.equal(await functionHasTutor(admin), false, 'historical pedagogy list must omit is_tutor');
  const installed = (await admin.query('select unit_order,file_name,sha256 from ops.installation_units order by unit_order')).rows;
  assert.equal(installed.some((row) => row.file_name === UNIT), false);
  await admin.end();
  const migrator = new pg.Client({ connectionString: roleUrl(base, name, 'migrator', passwords.migrator) });
  await migrator.connect();
  const sql = renderAdditiveUpgrade({ installed });
  const applies = sql.match(/-- APPLY \d+ \S+/g) || [];
  assert.deepEqual(applies, ['-- APPLY 80 database/pedagogy/v2/01_teacher_assignment_list.sql']);
  await migrator.query(sql);
  await migrator.end();
  const after = new pg.Client({ connectionString: databaseUrl(base, name).toString() });
  await after.connect();
  assert.equal(await functionHasTutor(after), true);
  const ledger = (await after.query('select file_name from ops.installation_units where file_name=$1', [UNIT])).rows;
  assert.equal(ledger.length, 1);
  await after.end();
}

async function proveServerReadback(base, name, passwords) {
  const admin = new pg.Client({ connectionString: databaseUrl(base, name).toString() });
  const auth = new pg.Client({ connectionString: roleUrl(base, name, 'auth', passwords.auth) });
  const migrator = new pg.Client({ connectionString: roleUrl(base, name, 'migrator', passwords.migrator) });
  const pool = new pg.Pool({ connectionString: roleUrl(base, name, 'api', passwords.api), max: 2 });
  await Promise.all([admin.connect(), auth.connect(), migrator.connect()]);
  try {
    const tokenHash = digest(randomBytes(32));
    const email = 'homeroom-' + randomUUID() + '@example.test';
    const passwordHash = await argonHash(randomBytes(32).toString('hex'), { memoryCost: 19456, timeCost: 2, parallelism: 1 });
    const payload = {
      identity: { name_fr: 'Synthetic Homeroom School' },
      cycles: ['primary'],
      academic_year: { label: '2026-2027', starts_on: '2026-09-01', ends_on: '2027-07-31', periods: 'Trimestres' },
      contact: {},
      brand: {},
    };
    await migrator.query('select ops.authorize_school_setup($1,$2)', [tokenHash, 3600]);
    const asObject = (value) => typeof value === 'string' ? JSON.parse(value) : value;
    const staged = asObject((await auth.query('select api.setup_stage_school($1,$2::jsonb) r', [tokenHash, JSON.stringify(payload)])).rows[0].r);
    const created = asObject((await auth.query('select api.setup_complete_school($1,$2,$3,$4,$5,$6) r', [tokenHash, email, passwordHash, 'Synthetic', 'Admin', null])).rows[0].r);
    const classId = randomUUID();
    const subjectId = randomUUID();
    await admin.query("insert into app.classes(id,school_id,academic_year_id,cycle_key,name) values($1,$2,$3,'primary','6e A')", [classId, staged.school_id, staged.academic_year_id]);
    await admin.query("insert into app.subjects(id,school_id,academic_year_id,code,name) values($1,$2,$3,'MATH','Math')", [subjectId, staged.school_id, staged.academic_year_id]);
    const { createPedagogyNativeService } = await tsImport(pathToFileURL(path.join(repositoryRoot, 'server/src/pedagogynative/service.ts')).href, import.meta.url);
    const service = createPedagogyNativeService(pool);
    const context = { userId: created.user_id, profileId: created.profile_id, schoolId: staged.school_id, requestId: randomUUID() };
    const id = await service.setHomeroom(context, created.profile_id, classId, subjectId, staged.academic_year_id);
    const rows = await service.listTeacherAssignments(context);
    const tutor = rows.find((row) => row.profile_id === created.profile_id);
    assert.ok(tutor, 'GET assignments must return the homeroom teacher');
    assert.equal(tutor.id, id);
    assert.equal(tutor.is_tutor, true);
    assert.equal(rows.some((row) => row.is_tutor === true && row.profile_id !== created.profile_id), false);
  } finally {
    await Promise.allSettled([admin.end(), auth.end(), migrator.end(), pool.end()]);
  }
}

const base = process.env.DATABASE_URL;
const passwords = Object.fromEntries(['api', 'auth', 'worker', 'migrator'].map((role) => [role, process.env['SCHOOLSAFE_' + role.toUpperCase() + '_PASSWORD']]));
assert.ok(base, 'DATABASE_URL required');
for (const role of ['api', 'auth', 'worker', 'migrator']) assert.ok(passwords[role] && passwords[role].length >= 32, role);

const fresh = 'schoolsafe_test_b2fresh';
const old = 'schoolsafe_test_b2old';
try {
  await recreate(base, fresh);
  await installSchoolDatabase({ connectionString: databaseUrl(base, fresh).toString(), database: fresh, mode: 'apply', passwords, log: () => {} });
  const freshAdmin = new pg.Client({ connectionString: databaseUrl(base, fresh).toString() });
  await freshAdmin.connect();
  assert.equal(await functionHasTutor(freshAdmin), true);
  await freshAdmin.end();
  await proveServerReadback(base, fresh, passwords);
  console.log('FRESH_INSTALL=PASS');
  console.log('HOMEROOM_POST_GET_READBACK fresh=PASS');

  await recreate(base, old);
  await installWithoutReadback(base, old, passwords);
  await upgradeAlreadyMigrated(base, old, passwords);
  console.log('ALREADY_MIGRATED_DB_UPGRADE=PASS');
  await proveServerReadback(base, old, passwords);
  console.log('HOMEROOM_POST_GET_READBACK upgraded=PASS');
} catch (error) {
  console.error(error.message);
  if (error.cause) console.error(error.cause.message || error.cause);
  process.exitCode = 1;
}
