'use strict';
const { withNativeScanContext } = require('./native-scan-context.cjs');

function validatePrintInput(input) {
  if (!input || typeof input !== 'object' || Array.isArray(input)
      || Object.keys(input).sort().join(',') !== 'action,format,nativeStudentId,qrPayload'
      || input.action !== 'print-request'
      || typeof input.nativeStudentId !== 'string' || !/^[1-9][0-9]{0,18}$/.test(input.nativeStudentId)
      || !['badge', 'carte'].includes(input.format)
      || typeof input.qrPayload !== 'string' || input.qrPayload.length > 2048
      || !/^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(input.qrPayload)) {
    throw new Error('INVALID_PRINT_INPUT');
  }
  return input;
}

function requireCurrentCredential(credential, qrPayload) {
  if (credential?.status !== 'active' || !credential.card_id
      || typeof credential.card_number !== 'string' || typeof credential.signature !== 'string'
      || !credential.card_number || !credential.signature
      || qrPayload !== 'schoolsafe://card/' + credential.card_number + '/' + credential.signature) {
    throw new Error('ACTIVE_CARD_CHANGED');
  }
}

async function requestNativeCardPrint(envelope, dependencies) {
  return withNativeScanContext(envelope, dependencies, async ({ client, claim }) => {
    if (claim.operation !== 'management') throw new Error('INVALID_PRINT_INPUT');
    const input = validatePrintInput(claim.input);
    const mapped = await dependencies.mappingPool.query(
      "select target_entity_id from card_bridge.entity_map where source_instance=$1 and native_school_id=$2 and entity_type='student' and native_entity_id=$3",
      [claim.sourceInstance, claim.nativeSchoolId, input.nativeStudentId]);
    if (mapped.rows.length !== 1) throw new Error('NATIVE_MAPPING_REQUIRED');
    const student = mapped.rows[0].target_entity_id;
    await client.query('select iam.require_access($1,null,$2)', ['cards.request.print', student]);
    const read = async () => (await client.query('select api.student_card_read($1) as result', [student])).rows[0]?.result;
    const credential = await read();
    requireCurrentCredential(credential, input.qrPayload);
    const result = await client.query('select api.card_print_request_create($1,$2,null,null,null,null,$3::jsonb) as result',
      [student, input.format, JSON.stringify({ source: 'rosario-local-studio',
        credential_card_id: credential.card_id, credential_card_number: credential.card_number })]);
    const data = result.rows[0]?.result;
    if (!data?.id || data.student_id !== student || data.status !== 'pending') throw new Error('PRINT_REQUEST_UNCONFIRMED');
    // Roll back the request if the engine credential changed during creation.
    requireCurrentCredential(await read(), input.qrPayload);
    return data;
  });
}

function registerNativeCardPrintRoute(app, dependencies) {
  app.post('/bridge/cards/print-request', { bodyLimit: 20000 }, async (request, reply) => {
    reply.header('Cache-Control', 'no-store');
    if (request.headers.origin || Object.keys(request.query || {}).length) {
      return reply.code(400).send({ error: 'INVALID_REQUEST', operationPerformed: false });
    }
    try {
      const data = await requestNativeCardPrint(request.body, dependencies);
      return { data, operationPerformed: true };
    } catch (error) {
      const code = error?.message;
      const status = code === 'ACTIVE_CARD_CHANGED' ? 409
        : ['INVALID_SIGNATURE', 'REPLAY_REJECTED'].includes(code) ? 401
        : ['INVALID_PRINT_INPUT', 'INVALID_SCAN_ENVELOPE', 'INVALID_SCAN_CLAIM'].includes(code) ? 400
        : ['SCHOOL_ACCESS_DENIED', 'NATIVE_MAPPING_REQUIRED'].includes(code) || error?.code === '42501' ? 403 : 503;
      return reply.code(status).send({ error: status === 409 ? code : 'CARD_PRINT_REQUEST_REJECTED', operationPerformed: false });
    }
  });
}
module.exports = { validatePrintInput, requireCurrentCredential, requestNativeCardPrint, registerNativeCardPrintRoute };
