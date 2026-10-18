const test = require('node:test');
const assert = require('node:assert/strict');
const { resolveCardContext } = require('./resolve-card-context.cjs');

const claim = { sourceInstance: 'codex-addons-local', nativeSchoolId: '1', nativeUserId: '3',
  input: { enrollment: { STUDENT_ID: '1' } } };
const mapping = { target_profile_id: 'profile', target_school_id: 'school', target_student_id: 'student' };

test('missing or ambiguous mapping never queries target', async () => {
  for (const rows of [[], [mapping, mapping]]) {
    await assert.rejects(resolveCardContext(claim, { query: async () => ({ rows }) },
      { query: async () => { throw new Error('UNEXPECTED_TARGET_QUERY'); } }), /NATIVE_MAPPING_REQUIRED/);
  }
});

test('target lookup binds reviewed IDs and original native identity', async () => {
  const record = { profile_id: 'profile', user_id: 'user', school_id: 'school', student_id: 'student' };
  const result = await resolveCardContext(claim, { query: async () => ({ rows: [mapping] }) }, {
    query: async (sql, params) => {
      assert.deepEqual(params, ['profile', 'school', 'student', 'codex-addons-local:3']);
      assert.match(sql, /card_bridge_local\.resolve_identity\(\$1, \$2, \$3, \$4\)/);
      return { rows: [record] };
    },
  });
  assert.deepEqual(result, record);
});

test('rejected or ambiguous target is blocked', async () => {
  for (const rows of [[], [{}, {}]]) {
    await assert.rejects(resolveCardContext(claim, { query: async () => ({ rows: [mapping] }) },
      { query: async () => ({ rows }) }), /TARGET_RECORDS_REQUIRED/);
  }
});
