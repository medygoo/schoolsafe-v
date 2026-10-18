'use strict';

const { randomUUID } = require('node:crypto');
const { withNativeCardContext } = require('./native-card-context.cjs');
const { toStudioInput } = require('./rosario-card-input.cjs');
const { readCardMetadata } = require('./card-metadata.cjs');
const { buildStudioBridgeInput } = require('./studio-bridge-input.cjs');
const { resolveStudioSchooling } = require('./resolve-studio-schooling.cjs');
const { checkCardIssuance } = require('./card-issuance-check.cjs');

const invalid = new Set(['INVALID_ENVELOPE', 'INVALID_CLAIM', 'INVALID_NATIVE_SCOPE']);
const unauthorized = new Set(['INVALID_SIGNATURE', 'REPLAY_REJECTED']);
const forbidden = new Set(['SCHOOL_ACCESS_DENIED', 'NATIVE_MAPPING_REQUIRED', 'TARGET_RECORDS_REQUIRED']);

function registerCardBridgeRoutes(app, dependencies) {
  const studioInput = async (requestContext) => {
    const metadata = await readCardMetadata(requestContext);
    const input = await resolveStudioSchooling(requestContext.claim, metadata, dependencies.mappingPool, requestContext.client);
    return buildStudioBridgeInput(input, metadata);
  };
  for (const [url, operation] of [
    ['/bridge/cards/prepare', async ({ claim }) => toStudioInput(claim.input)],
    ['/bridge/cards/metadata', readCardMetadata],
    ['/bridge/cards/studio-input', studioInput],
    ['/bridge/cards/issuance-check', async context => checkCardIssuance(await studioInput(context), Boolean(dependencies.issuer))],
  ]) {
  app.post(url, { bodyLimit: 300000 }, async (request, reply) => {
    const requestId = randomUUID();
    reply.header('Cache-Control', 'no-store');
    reply.header('X-Content-Type-Options', 'nosniff');
    // Server-to-server only. No cookies, client context overrides or browser CORS.
    if (request.headers.origin || Object.keys(request.query || {}).length) {
      return reply.code(400).send({ error: 'INVALID_REQUEST', request_id: requestId });
    }
    const envelope = request.body;
    if (!envelope || typeof envelope !== 'object' || Array.isArray(envelope)
        || Object.keys(envelope).sort().join(',') !== 'body,signature') {
      return reply.code(400).send({ error: 'INVALID_ENVELOPE', request_id: requestId });
    }
    try {
      const data = await withNativeCardContext(envelope, dependencies, operation);
      // Native input identifiers remain native; this route never emits a credential.
      return { data, request_id: requestId };
    } catch (error) {
      const code = error instanceof Error ? error.message : '';
      if (invalid.has(code) || error instanceof SyntaxError) {
        return reply.code(400).send({ error: 'INVALID_NATIVE_DATA', request_id: requestId });
      }
      if (unauthorized.has(code)) {
        return reply.code(401).send({ error: 'NATIVE_AUTH_REQUIRED', request_id: requestId });
      }
      if (forbidden.has(code) || error?.code === '42501') {
        return reply.code(403).send({ error: 'ACCESS_DENIED', request_id: requestId });
      }
      request.log.error({ requestId, code: 'CARD_BRIDGE_UNAVAILABLE' }, 'Card bridge preparation failed');
      return reply.code(503).send({ error: 'CARD_BRIDGE_UNAVAILABLE', request_id: requestId });
    }
  });
  }
}

module.exports = { registerCardBridgeRoutes };
