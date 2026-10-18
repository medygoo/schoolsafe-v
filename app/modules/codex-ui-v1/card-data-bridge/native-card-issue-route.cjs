'use strict';
const { randomUUID } = require('node:crypto');
const { withNativeCardContext } = require('./native-card-context.cjs');
const { readCardMetadata } = require('./card-metadata.cjs');
const { resolveStudioSchooling } = require('./resolve-studio-schooling.cjs');
const { buildStudioBridgeInput } = require('./studio-bridge-input.cjs');
const { issuePreparedNativeCard } = require('./native-card-issuer.cjs');

async function issueNativeEnvelope(envelope, dependencies) {
  const resolved = await withNativeCardContext(envelope, dependencies, async request => {
    if (request.claim.operation !== 'issue-card') throw new Error('ISSUE_OPERATION_REQUIRED');
    const metadata = await readCardMetadata(request);
    const input = await resolveStudioSchooling(request.claim, metadata, dependencies.mappingPool, request.client);
    return { context: request.context, prepared: buildStudioBridgeInput(input, metadata) };
  });
  return issuePreparedNativeCard({ ...resolved, service: dependencies.issuer,
    assertSchoolAccess: dependencies.assertSchoolAccess });
}

function registerNativeCardIssueRoute(app, dependencies) {
  app.post('/bridge/cards/issue', { bodyLimit: 300000 }, async (request, reply) => {
    const requestId = randomUUID();
    reply.header('Cache-Control', 'no-store');
    if (request.headers.origin || Object.keys(request.query || {}).length
        || !request.body || Object.keys(request.body).sort().join(',') !== 'body,signature') {
      return reply.code(400).send({ error: 'INVALID_REQUEST', request_id: requestId });
    }
    try {
      const data = await issueNativeEnvelope(request.body, dependencies);
      return { data, operationPerformed: true, request_id: requestId };
    } catch (error) {
      const code = error?.message;
      const status = code === 'CARD_DATA_INCOMPLETE' ? 409
        : ['INVALID_SIGNATURE', 'REPLAY_REJECTED'].includes(code) ? 401
        : ['ISSUE_OPERATION_REQUIRED', 'INVALID_CLAIM', 'INVALID_NATIVE_SCOPE', 'INVALID_ENVELOPE'].includes(code) ? 400
        : ['SCHOOL_ACCESS_DENIED', 'NATIVE_MAPPING_REQUIRED', 'TARGET_RECORDS_REQUIRED'].includes(code)
          || error?.code === '42501' ? 403 : 503;
      return reply.code(status).send({ error: status === 409 ? code
        : status === 503 ? 'CARD_ISSUER_UNAVAILABLE' : 'CARD_ISSUE_REJECTED',
        operationPerformed: false, request_id: requestId });
    }
  });
}
module.exports = { issueNativeEnvelope, registerNativeCardIssueRoute };
