import pg from "pg";
import { pathToFileURL } from "node:url";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function flag(name) {
  const index = process.argv.indexOf(name);
  return index >= 0 ? process.argv[index + 1] ?? "" : "";
}

export function assertRelinkArguments(input) {
  if (input.confirm !== "RELINK") throw new Error("CONFIRMATION_REQUIRED");
  if (!UUID.test(input.schoolId) || !UUID.test(input.oldSubject) || !UUID.test(input.newSubject) || input.oldSubject.toLowerCase() === input.newSubject.toLowerCase()) {
    throw new Error("EXACT_SUBJECT_REQUIRED");
  }
}

function banned(value) {
  if (!value || value === "none") return false;
  const until = Date.parse(value);
  return Number.isFinite(until) && until > Date.now();
}

export async function relinkMasterSchoolAccess(options) {
  assertRelinkArguments(options);
  const response = await options.fetchImpl(`${options.supabaseUrl.replace(/\/$/, "")}/auth/v1/admin/users/${encodeURIComponent(options.newSubject)}`, {
    method: "GET",
    headers: {
      apikey: options.serviceRoleKey,
      authorization: `Bearer ${options.serviceRoleKey}`,
      accept: "application/json",
    },
  });
  if (!response.ok) throw new Error("REPLACEMENT_SUBJECT_UNAVAILABLE");
  const body = await response.json();
  if (banned(body.banned_until)) throw new Error("REPLACEMENT_SUBJECT_BANNED");
  const db = new options.Client({ connectionString: options.databaseUrl });
  await db.connect();
  try {
    await db.query("select api.master_school_access_relink($1::uuid, $2::uuid, $3::uuid)", [
      options.schoolId,
      options.oldSubject,
      options.newSubject,
    ]);
  } finally {
    await db.end();
  }
}

async function main() {
  try {
    await relinkMasterSchoolAccess({
      schoolId: flag("--school-id"),
      oldSubject: flag("--old-subject"),
      newSubject: flag("--new-subject"),
      confirm: flag("--confirm"),
      supabaseUrl: process.env.SUPABASE_URL ?? "",
      serviceRoleKey: process.env.SUPABASE_SERVICE_ROLE_KEY ?? "",
      databaseUrl: process.env.DATABASE_URL ?? "",
      fetchImpl: fetch,
      Client: pg.Client,
    });
  } catch (error) {
    const message = error instanceof Error ? error.message : "RELINK_FAILED";
    console.error(message === "CONFIRMATION_REQUIRED" || message === "EXACT_SUBJECT_REQUIRED" || message === "REPLACEMENT_SUBJECT_UNAVAILABLE" || message === "REPLACEMENT_SUBJECT_BANNED" ? message : "RELINK_FAILED");
    process.exitCode = 2;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  await main();
}
