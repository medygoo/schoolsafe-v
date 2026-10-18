const test = require('node:test');
const assert = require('node:assert/strict');
const { assertCentralSchoolAccess } = require('./central-school-access.cjs');
const school = '8f1c3850-6568-4a26-8312-ed582c95891b';

test('native central statuses retain existing semantics', async () => {
  for (const [status, allowed] of [[null, true], ['active', true], ['suspended', false], ['revoked', false]]) {
    assert.equal(await assertCentralSchoolAccess({ query: async (sql, params) => {
      assert.match(sql, /api\.master_school_access_status/);
      assert.deepEqual(params, [school]);
      return { rows: [{ status }] };
    } }, school), allowed);
  }
});

test('missing, ambiguous and unexpected results fail closed', async () => {
  for (const rows of [[], [{ status: null }, { status: null }], [{}], [{ status: 'unknown' }]]) {
    await assert.rejects(assertCentralSchoolAccess({ query: async () => ({ rows }) }, school), /RESULT_INVALID/);
  }
});

test('database outage is not interpreted as active access', async () => {
  await assert.rejects(assertCentralSchoolAccess({ query: async () => { throw new Error('UNAVAILABLE'); } }, school), /UNAVAILABLE/);
});
