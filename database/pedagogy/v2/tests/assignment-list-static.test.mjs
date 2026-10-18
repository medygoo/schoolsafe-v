import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const sql = await readFile(new URL("../01_teacher_assignment_list.sql", import.meta.url), "utf8");

test("additive homeroom readback replaces only the list function", () => {
  assert.match(sql, /\\set ON_ERROR_STOP on/);
  assert.match(sql, /^begin;$/m);
  assert.match(sql, /^commit;$/m);
  assert.match(sql, /set local role schoolsafe_owner/);
  assert.equal((sql.match(/create or replace function/g) ?? []).length, 1);
  assert.match(sql, /function api\.teacher_assignment_list\(\)/);
  assert.match(sql, /security definer/);
  assert.match(sql, /set search_path = pg_catalog/);
  assert.match(sql, /'is_tutor', ta\.is_tutor/);
  assert.match(sql, /ta\.teacher_profile_id/);
  assert.match(sql, /iam\.require_access\('school\.structure\.manage'/);
  assert.match(sql, /to schoolsafe_api/);
  assert.doesNotMatch(sql, /grant\s+[\s\S]{0,80}\s+to\s+public/i);
  assert.doesNotMatch(sql, /create table/i);
  assert.doesNotMatch(sql, /is_tutor\s*=\s*true/);
});
