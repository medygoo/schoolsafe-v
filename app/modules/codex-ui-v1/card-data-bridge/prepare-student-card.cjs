'use strict';

const { verifyNativeEnvelope } = require('./native-envelope.cjs');
const { ensureStudentMatricule } = require('./student-matricule.cjs');
const { toStudioInput } = require('./rosario-card-input.cjs');

// Trusted server configuration only; never expose these dependencies to a request body.
async function prepareStudentCard(envelope, { nativeVerification, registryDb, authorizePreparation }) {
  if (typeof authorizePreparation !== 'function') throw new Error('PREPARATION_AUTHORIZATION_REQUIRED');
  const claim = await verifyNativeEnvelope(envelope, nativeVerification);
  // Native provenance is not sufficient permission to allocate a business identifier.
  if (await authorizePreparation(claim) !== true) throw new Error('PREPARATION_ACCESS_DENIED');
  const existing = claim.input.verified?.matricule;
  const matricule = await ensureStudentMatricule(registryDb, {
    sourceInstance: claim.sourceInstance,
    nativeSchoolId: claim.nativeSchoolId,
    nativeStudentId: String(claim.input.student.STUDENT_ID),
    existingMatricule: existing == null || existing === '' ? null : existing,
  });
  const source = {
    ...claim.input,
    verified: { ...claim.input.verified, matricule },
  };
  return toStudioInput(source);
}

module.exports = { prepareStudentCard };
