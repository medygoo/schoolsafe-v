const test = require('node:test');
const assert = require('node:assert/strict');
const { createHmac } = require('node:crypto');
const { withNativeCardContext } = require('./native-card-context.cjs');

const secret = Buffer.alloc(32, 7);
const claim = { version: 1, audience: 'schoolsafe-card-bridge', sourceInstance: 'codex-addons-local',
  nativeUserId: '1', nativeSchoolId: '1', nativeYear: '2026', issuedAt: 100, expiresAt: 120,
  nonce: 'a'.repeat(64), input: { authorized: true, student: { STUDENT_ID: '1' },
    enrollment: { STUDENT_ID: '1', SCHOOL_ID: '1', SYEAR: '2026' } } };
const body = JSON.stringify(claim);
const envelope = { body, signature: createHmac('sha256', secret).update(body).digest('hex') };

function setup({ school = true, permission = true, nonce = true } = {}) {
  const events = [];
  const client = {
    query: async (sql, params) => {
      events.push(sql);
      if (sql.includes('card_bridge_local.resolve_identity')) return { rows: [{ user_id: 'user', profile_id: 'profile',
        school_id: 'school', student_id: 'student' }] };
      if (sql.includes('set_request_context')) assert.deepEqual(params.slice(0, 3), ['user', 'profile', 'school']);
      if (sql.includes('require_access')) {
        assert.deepEqual(params, ['cards.request.print', 'student']);
        if (!permission) throw new Error('ACCESS_DENIED');
      }
      return { rows: [] };
    },
    release: () => events.push('release'),
  };
  return { events, dependencies: {
    nativeVerification: { secret, sourceInstance: claim.sourceInstance, consumeNonce: async () => nonce, now: () => 110 },
    mappingPool: { query: async () => ({ rows: [{ target_profile_id: 'profile', target_school_id: 'school', target_student_id: 'student' }] }) },
    targetPool: { connect: async () => { events.push('connect'); return client; } },
    assertSchoolAccess: async () => school,
  } };
}

test('signed provenance, mapped context, central check and permission precede operation', async () => {
  const { events, dependencies } = setup();
  assert.equal(await withNativeCardContext(envelope, dependencies, async ({ context }) => {
    assert.equal(context.studentId, 'student');
    assert.match(context.requestId, /^[a-f0-9-]{36}$/);
    events.push('operation'); return 'prepared';
  }), 'prepared');
  assert.ok(events.findIndex(x => x.includes('require_access')) < events.indexOf('operation'));
  assert.deepEqual(events.slice(-3), ['operation', 'COMMIT', 'release']);
});

test('school denial or unavailable result prevents operation and rolls back', async () => {
  for (const school of [false, undefined]) {
    const { events, dependencies } = setup({ school });
    if (school === undefined) dependencies.assertSchoolAccess = async () => undefined;
    await assert.rejects(withNativeCardContext(envelope, dependencies,
      async () => assert.fail('operation must not execute')), /SCHOOL_ACCESS_DENIED/);
    assert.deepEqual(events.slice(-2), ['ROLLBACK', 'release']);
  }
});

test('permission denial prevents operation', async () => {
  const { events, dependencies } = setup({ permission: false });
  await assert.rejects(withNativeCardContext(envelope, dependencies,
    async () => assert.fail('operation must not execute')), /ACCESS_DENIED/);
  assert.deepEqual(events.slice(-2), ['ROLLBACK', 'release']);
});

test('bad signature and replay never open target connection', async () => {
  for (const replay of [false, true]) {
    const { events, dependencies } = setup({ nonce: !replay });
    await assert.rejects(withNativeCardContext(replay ? envelope : { ...envelope, signature: '0'.repeat(64) },
      dependencies, async () => assert.fail('operation must not execute')), /INVALID_SIGNATURE|REPLAY_REJECTED/);
    assert.deepEqual(events, []);
  }
});

test('operation failure rolls back and releases connection', async () => {
  const { events, dependencies } = setup();
  await assert.rejects(withNativeCardContext(envelope, dependencies,
    async () => { throw new Error('BUSINESS_FAILURE'); }), /BUSINESS_FAILURE/);
  assert.deepEqual(events.slice(-2), ['ROLLBACK', 'release']);
});

test('missing central adapter is rejected before any database connection', async () => {
  const { events, dependencies } = setup();
  delete dependencies.assertSchoolAccess;
  await assert.rejects(withNativeCardContext(envelope, dependencies, async () => {}), /CONFIGURATION_REQUIRED/);
  assert.deepEqual(events, []);
});
