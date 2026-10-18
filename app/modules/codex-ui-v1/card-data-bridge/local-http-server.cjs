'use strict';
const { createRequire } = require('node:module');
const { readFileSync } = require('node:fs');
const load = createRequire('/deps/package.json');
const fastify = load('fastify');
const { Pool } = load('pg');
const { registerCardBridgeRoutes } = require('./card-bridge-routes.cjs');
const { nonceConsumer } = require('./consume-nonce.cjs');
const { assertCentralSchoolAccess } = require('./central-school-access.cjs');
const { registerNativeCardIssueRoute } = require('./native-card-issue-route.cjs');
const { registerNativeScanRoutes } = require('./native-scan-routes.cjs');
const { registerNativeNotificationRoute } = require('./native-notification-route.cjs');
const { startNotificationCollector } = require('./notification-collector.cjs');
const { registerNativeManagementRoute } = require('./native-management-route.cjs');
const config = JSON.parse(readFileSync('/private/http-config.json', 'utf8'));
if (config.environment !== 'local-isolated-test-only'
    || config.target.database !== 'schoolsafe_cards_isolated'
    || config.mapping.database !== 'schoolsafe_card_bridge_test'
    || !/^[a-f0-9]{64}$/.test(config.secretHex || '')) throw new Error('ISOLATED_CONFIG_REQUIRED');
const targetPool = new Pool({ ...config.target, max: 4, connectionTimeoutMillis: 5000 });
const mappingPool = new Pool({ ...config.mapping, max: 4, connectionTimeoutMillis: 5000 });
const authPool = new Pool({ ...config.target, ...config.centralAuth, max: 2, connectionTimeoutMillis: 5000 });
const app = fastify({ logger: false, requestTimeout: 10000 });
const dependencies = {
  nativeVerification: { secret: Buffer.from(config.secretHex, 'hex'), sourceInstance: 'codex-addons-local',
    consumeNonce: nonceConsumer(mappingPool) },
  targetPool, mappingPool, assertSchoolAccess: school => assertCentralSchoolAccess(authPool, school),
};
registerCardBridgeRoutes(app, dependencies);
// The unmodified native service is bundled in the isolated harness. No fallback signing key.
if (config.cardSecretHex) {
  if (!/^[a-f0-9]{64}$/.test(config.cardSecretHex)) throw new Error('INVALID_CARD_SIGNING_CONFIG');
  process.env.CARD_HMAC_SECRET = config.cardSecretHex;
}
if (process.env.CARD_HMAC_SECRET) {
  dependencies.issuer = load('./cards-native-service.cjs').createCardsNativeService(targetPool);
  dependencies.scanner = load('./scan-control-service.cjs').createScanControlService(targetPool, { cards: dependencies.issuer });
}
registerNativeCardIssueRoute(app, dependencies);
require('./native-card-print-route.cjs').registerNativeCardPrintRoute(app, dependencies);
registerNativeScanRoutes(app, dependencies);
require('./native-location-routes.cjs').registerNativeLocationRoutes(app, dependencies);
registerNativeNotificationRoute(app, dependencies);
registerNativeManagementRoute(app, dependencies);
require('./native-fee-routes.cjs').registerNativeFeeRoutes(app,dependencies);
let notificationCollector = { status: 'starting' };
const stopCollector = startNotificationCollector(targetPool, { onState: state => { notificationCollector = state; } });
app.get('/health', async () => ({ status: 'up', environment: config.environment, issuanceEnabled: Boolean(dependencies.issuer), notificationCollector }));
async function close() {
  await stopCollector();
  await app.close();
  await Promise.all([targetPool.end(), mappingPool.end(), authPool.end()]);
}
process.once('SIGTERM', () => close().catch(() => { process.exitCode = 1; }));
app.listen({ host: '0.0.0.0', port: 8080 }).catch(() => { console.error('BRIDGE_START_FAILED'); process.exitCode = 1; });
