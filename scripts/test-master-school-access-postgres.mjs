import assert from "node:assert/strict";
import pg from "pg";

const payload = {
  admin: { first_name: "Ada", last_name: "Master" },
  identity: { name_fr: "Ecole Maitre" },
  cycles: ["primary"],
  academic_year: { label: "2026-2027", starts_on: "2026-09-01", ends_on: "2027-07-01" },
  contact: { country: "RDC", province: "Kinshasa", city: "Kinshasa", address: "Adresse test" },
  brand: { primary_color: "#112233", accent_color: "#abcdef" },
};

const principal = "55555555-5555-4555-8555-555555555555";
const otherPrincipal = "66666666-6666-4666-8666-666666666666";
const replacement = "77777777-7777-4777-8777-777777777777";
const wrongSubject = "88888888-8888-4888-8888-888888888888";

function hash(seed) {
  return seed.repeat(64).slice(0, 64);
}

export async function proveMasterSchoolAccess(connectionString) {
  const db = new pg.Client({ connectionString });
  await db.connect();
  async function asAuth(sql, params = []) {
    await db.query("set local session authorization schoolsafe_auth");
    try {
      return await db.query(sql, params);
    } finally {
      await db.query("reset session authorization");
    }
  }
  async function expectCheck(sql, params = []) {
    await db.query("savepoint master_expect");
    try {
      await db.query("set local session authorization schoolsafe_auth");
      await db.query(sql, params);
      throw new Error("expected check failure");
    } catch (error) {
      await db.query("rollback to savepoint master_expect");
      if (error.message === "expected check failure") throw error;
      assert.equal(error.code, "23514");
    }
  }
  try {
    const forced = await db.query("select c.relrowsecurity and c.relforcerowsecurity as forced from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'ops' and c.relname = 'supabase_school_principals'");
    assert.equal(forced.rows[0].forced, true);
    for (const role of ["public", "schoolsafe_api", "schoolsafe_auth", "schoolsafe_worker", "schoolsafe_migrator", "schoolsafe_auditor"]) {
      const privilege = await db.query("select has_table_privilege($1, 'ops.supabase_school_principals', 'select') as sel, has_table_privilege($1, 'ops.supabase_school_principals', 'insert') as ins, has_table_privilege($1, 'ops.supabase_school_principals', 'update') as upd", [role]);
      assert.equal(privilege.rows[0].sel, false);
      assert.equal(privilege.rows[0].ins, false);
      assert.equal(privilege.rows[0].upd, false);
    }
    for (const fn of [
      "api.master_school_access_targets()",
      "api.master_school_access_status(uuid)",
      "api.master_school_access_apply(uuid,uuid,text)",
      "api.master_school_access_relink(uuid,uuid,uuid)",
    ]) {
      const auth = await db.query("select has_function_privilege('schoolsafe_auth', $1, 'execute') as allowed", [fn]);
      const api = await db.query("select has_function_privilege('schoolsafe_api', $1, 'execute') as allowed", [fn]);
      assert.equal(auth.rows[0].allowed, true, fn);
      assert.equal(api.rows[0].allowed, false, fn);
    }

    await db.query("begin");
    await db.query("insert into iam.users (id, auth_provider, external_subject, email, phone) values ($1::uuid, 'supabase', $1::text, 'master-a@example.com', '+243810000201')", [principal]);
    await db.query("insert into iam.users (id, auth_provider, external_subject, email, phone) values ($1::uuid, 'supabase', $1::text, 'master-b@example.com', '+243810000202')", [otherPrincipal]);
    const created = await asAuth("select api.auth_supabase_principal_create_school($1, $2::jsonb) result", [principal, JSON.stringify(payload)]);
    const other = await asAuth("select api.auth_supabase_principal_create_school($1, $2::jsonb) result", [otherPrincipal, JSON.stringify({ ...payload, identity: { name_fr: "Ecole Voisine" } })]);
    const school = created.rows[0].result;
    const neighbor = other.rows[0].result;
    assert.equal(school.user_id, principal);
    const link = await db.query("select school_id, principal_user_id, supabase_subject from ops.supabase_school_principals where school_id = $1", [school.school_id]);
    assert.equal(link.rows.length, 1);
    assert.equal(link.rows[0].principal_user_id, principal);
    assert.equal(link.rows[0].supabase_subject, principal);
    assert.equal((await asAuth("select api.master_school_access_status($1) status", [school.school_id])).rows[0].status, "active");
    const targets = await asAuth("select school_id, supabase_subject from api.master_school_access_targets()");
    assert.equal(targets.rows.filter((row) => row.school_id === school.school_id && row.supabase_subject === principal).length, 1);

    const identityA = (await db.query("select id from auth.identities where user_id = $1", [principal])).rows[0].id;
    const identityB = (await db.query("select id from auth.identities where user_id = $1", [otherPrincipal])).rows[0].id;
    await db.query("insert into auth.sessions (identity_id, profile_id, token_hash, expires_at) values ($1, $2, $3, now() + interval '1 day')", [identityA, school.profile_id, hash("a")]);
    await db.query("insert into auth.sessions (identity_id, profile_id, token_hash, expires_at) values ($1, $2, $3, now() + interval '1 day')", [identityB, neighbor.profile_id, hash("b")]);
    const schoolsBefore = (await db.query("select count(*)::int n from app.schools")).rows[0].n;
    const profilesBefore = (await db.query("select count(*)::int n from iam.profiles")).rows[0].n;

    assert.equal((await asAuth("select api.master_school_access_apply($1, $2, 'suspended') status", [school.school_id, principal])).rows[0].status, "suspended");
    assert.equal((await db.query("select count(*)::int n from auth.sessions where profile_id = $1 and revoked_at is null", [school.profile_id])).rows[0].n, 0);
    assert.equal((await db.query("select count(*)::int n from auth.sessions where profile_id = $1 and revoked_at is null", [neighbor.profile_id])).rows[0].n, 1);
    assert.equal((await asAuth("select api.master_school_access_status($1) status", [school.school_id])).rows[0].status, "suspended");

    assert.equal((await asAuth("select api.master_school_access_apply($1, $2, 'active') status", [school.school_id, principal])).rows[0].status, "active");
    assert.equal((await db.query("select count(*)::int n from auth.sessions where profile_id = $1 and revoked_at is not null", [school.profile_id])).rows[0].n, 1);
    await db.query("insert into auth.sessions (identity_id, profile_id, token_hash, expires_at) values ($1, $2, $3, now() + interval '1 day')", [identityA, school.profile_id, hash("c")]);
    assert.equal((await db.query("select count(*)::int n from auth.sessions where token_hash = $1 and revoked_at is null", [hash("c")])).rows[0].n, 1);

    assert.equal((await asAuth("select api.master_school_access_apply($1, $2, 'revoked') status", [school.school_id, principal])).rows[0].status, "revoked");
    assert.equal((await db.query("select count(*)::int n from auth.sessions where profile_id = $1 and revoked_at is null", [school.profile_id])).rows[0].n, 0);
    assert.equal((await db.query("select count(*)::int n from app.schools")).rows[0].n, schoolsBefore);
    assert.equal((await db.query("select count(*)::int n from iam.profiles")).rows[0].n, profilesBefore);
    await expectCheck("select api.master_school_access_apply($1, $2, 'active')", [school.school_id, principal]);
    await expectCheck("select api.master_school_access_apply($1, $2, 'suspended')", [school.school_id, principal]);
    assert.equal((await asAuth("select api.master_school_access_status($1) status", [school.school_id])).rows[0].status, "revoked");

    await db.query("savepoint same_email");
    try {
      await db.query("insert into iam.users (id, auth_provider, external_subject, email) values ($1::uuid, 'supabase', $2::text, 'master-a@example.com')", [replacement, replacement]);
      throw new Error("same email must not create a second principal");
    } catch (error) {
      await db.query("rollback to savepoint same_email");
      if (error.message === "same email must not create a second principal") throw error;
      assert.equal(error.code, "23505");
    }
    assert.equal((await db.query("select supabase_subject::text subject from ops.supabase_school_principals where school_id = $1", [school.school_id])).rows[0].subject, principal);
    assert.equal((await asAuth("select api.master_school_access_status($1) status", [school.school_id])).rows[0].status, "revoked");
    await expectCheck("select api.master_school_access_relink($1, $2, $3)", [school.school_id, wrongSubject, replacement]);
    assert.equal((await asAuth("select api.master_school_access_relink($1, $2, $3) status", [school.school_id, principal, replacement])).rows[0].status, "active");
    const relinked = await db.query("select u.id user_id, u.external_subject, p.supabase_subject::text subject, a.access_status from iam.users u join ops.supabase_school_principals p on p.principal_user_id = u.id join ops.supabase_school_access a on a.school_id = p.school_id where p.school_id = $1", [school.school_id]);
    assert.equal(relinked.rows[0].user_id, principal);
    assert.equal(relinked.rows[0].external_subject, replacement);
    assert.equal(relinked.rows[0].subject, replacement);
    assert.equal(relinked.rows[0].access_status, "active");
    assert.equal((await db.query("select count(*)::int n from auth.sessions where profile_id = $1 and revoked_at is null", [school.profile_id])).rows[0].n, 0);
    assert.equal((await db.query("select count(*)::int n from auth.sessions where profile_id = $1 and revoked_at is null", [neighbor.profile_id])).rows[0].n, 1);
    assert.equal((await db.query("select count(*)::int n from audit.events where event_type = 'school.master_access.suspended' and entity_id = $1", [school.school_id])).rows[0].n, 1);
    assert.equal((await db.query("select count(*)::int n from audit.events where event_type = 'school.master_access.relinked' and entity_id = $1 and payload::text not like '%email%'", [school.school_id])).rows[0].n, 1);
    await db.query("rollback");
    console.log("MASTER_SCHOOL_ACCESS PASS");
  } finally {
    await db.query("rollback").catch(() => {});
    await db.end();
  }
}

if (process.argv[1]?.endsWith("test-master-school-access-postgres.mjs")) {
  proveMasterSchoolAccess(process.env.DATABASE_URL).catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
  });
}
