const test = require('node:test');
const assert = require('node:assert/strict');
const { createHmac } = require('node:crypto');
const { createRequire } = require('node:module');
const path = require('node:path');
// Harness dependencies are installed outside Rosario and the existing Studio.
const fastify = createRequire(path.join(process.cwd(), 'package.json'))('fastify');
const { registerCardBridgeRoutes } = require('./card-bridge-routes.cjs');
const secret = Buffer.alloc(32, 8);

async function fixture(t, { denied = false, unavailable = false } = {}) {
  let consumed = false;
  let connected = 0;
  const app = fastify({ logger: false });
  registerCardBridgeRoutes(app, {
    nativeVerification: { secret, sourceInstance: 'codex-addons-local', now: () => 110,
      consumeNonce: async () => { if (consumed) return false; consumed = true; return true; } },
    mappingPool: { query: async () => ({ rows: [{ target_profile_id: 'profile', target_school_id: 'school', target_student_id: 'student' }] }) },
    targetPool: { connect: async () => {
      connected++;
      if (unavailable) throw new Error('DATABASE_PASSWORD_PRIVATE');
      return { release() {}, query: async sql => {
        if (sql.includes('card_bridge_local.resolve_identity')) return { rows: [{ user_id: 'user', profile_id: 'profile', school_id: 'school', student_id: 'student' }] };
        return { rows: [] };
      } };
    } },
    assertSchoolAccess: async () => !denied,
  });
  t.after(() => app.close());
  const body = JSON.stringify({ version: 1, audience: 'schoolsafe-card-bridge', sourceInstance: 'codex-addons-local',
    nativeUserId: '1', nativeSchoolId: '1', nativeYear: '2026', issuedAt: 100, expiresAt: 120, nonce: 'b'.repeat(64),
    input: { authorized: true, student: { STUDENT_ID: '1', FIRST_NAME: 'TEST', LAST_NAME: 'CODEX' },
      school: { ID: '1', SYEAR: '2026', TITLE: 'TEST' },
      enrollment: { STUDENT_ID: '1', SCHOOL_ID: '1', SYEAR: '2026' }, verified: { matricule: 'SCH0023-000001' } } });
  return { app, connected: () => connected, envelope: { body,
    signature: createHmac('sha256', secret).update(body).digest('hex') } };
}

test('HTTP preparation returns missing fields without issuing a card', async t => {
  const { app, envelope } = await fixture(t);
  const response = await app.inject({ method: 'POST', url: '/bridge/cards/prepare', payload: envelope });
  assert.equal(response.statusCode, 200);
  assert.equal(response.headers['cache-control'], 'no-store');
  assert.equal(response.json().data.issuanceAllowed, false);
  assert.ok(response.json().data.missing.includes('card.official_qr'));
});

test('HTTP invalid signature and replay are refused', async t => {
  const { app, envelope, connected } = await fixture(t);
  assert.equal((await app.inject({ method: 'POST', url: '/bridge/cards/prepare', payload: { ...envelope, signature: '0'.repeat(64) } })).statusCode, 401);
  assert.equal(connected(), 0);
  assert.equal((await app.inject({ method: 'POST', url: '/bridge/cards/prepare', payload: envelope })).statusCode, 200);
  assert.equal((await app.inject({ method: 'POST', url: '/bridge/cards/prepare', payload: envelope })).statusCode, 401);
});

test('HTTP context override and browser-origin calls never reach SQL', async t => {
  const { app, envelope, connected } = await fixture(t);
  for (const options of [ { url: '/bridge/cards/prepare?school_id=2', payload: envelope },
    { url: '/bridge/cards/prepare', payload: { ...envelope, profileId: 'admin' } },
    { url: '/bridge/cards/prepare', payload: envelope, headers: { origin: 'http://127.0.0.1:5187' } } ]) {
    assert.equal((await app.inject({ method: 'POST', ...options })).statusCode, 400);
  }
  assert.equal(connected(), 0);
});

test('HTTP central denial is forbidden', async t => {
  const { app, envelope } = await fixture(t, { denied: true });
  assert.equal((await app.inject({ method: 'POST', url: '/bridge/cards/prepare', payload: envelope })).statusCode, 403);
});

test('HTTP database failure does not expose private diagnostics', async t => {
  const { app, envelope } = await fixture(t, { unavailable: true });
  const response = await app.inject({ method: 'POST', url: '/bridge/cards/prepare', payload: envelope });
  assert.equal(response.statusCode, 503);
  assert.equal(response.json().error, 'CARD_BRIDGE_UNAVAILABLE');
  assert.ok(!response.body.includes('PASSWORD'));
});
