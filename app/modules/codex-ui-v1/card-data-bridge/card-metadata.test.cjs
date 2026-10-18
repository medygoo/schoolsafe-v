const test = require('node:test');
const assert = require('node:assert/strict');
const { readCardMetadata } = require('./card-metadata.cjs');

test('read bundle uses native SQL functions and mapped student only', async () => {
  let count = 0;
  const result = await readCardMetadata({ context: { studentId: 'mapped-student', schoolId: 'mapped-school' },
    client: { query: async (sql, args) => {
      count++;
      assert.match(sql, /^select api\./);
      assert.ok(!/issue|insert|update|delete/i.test(sql));
      if (sql.includes('$1')) assert.deepEqual(args, ['mapped-student']);
      return { rows: [{ result: sql.includes('student_card_read') ? { card_number: null, signature: null } : [] }] };
    } } });
  assert.equal(count, 5);
  assert.equal(result.studentId, 'mapped-student');
  assert.equal(result.credential.card_number, null);
  assert.ok(!Object.hasOwn(result.credential, 'qr_payload'));
});

test('malformed result and SQL denial propagate without invented fields', async () => {
  await assert.rejects(readCardMetadata({ context: {}, client: { query: async () => ({ rows: [] }) } }), /RESULT_INVALID/);
  await assert.rejects(readCardMetadata({ context: {}, client: { query: async () => { throw new Error('ACCESS_DENIED'); } } }), /ACCESS_DENIED/);
});
