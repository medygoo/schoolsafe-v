'use strict';
const { toStudioInput } = require('./rosario-card-input.cjs');

function buildStudioBridgeInput(nativeInput, metadata) {
  const prepared = toStudioInput(nativeInput);
  const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
  if (!metadata || typeof metadata.studentId !== 'string'
      || !uuid.test(metadata.studentId)
      || !uuid.test(metadata.schoolId || '')) {
    throw new Error('TARGET_MAPPING_REQUIRED');
  }
  const credential = metadata.credential || {};
  if (!prepared.input.record.matricule || credential.matricule !== prepared.input.record.matricule) {
    throw new Error('MATRICULE_MAPPING_MISMATCH');
  }
  // Serialize only an existing active engine credential, never mint a number/signature.
  const qr = credential.status === 'active' && typeof credential.card_number === 'string'
    && typeof credential.signature === 'string' && /^[^/\s]+$/.test(credential.card_number)
    && /^[^/\s]+$/.test(credential.signature)
    ? `schoolsafe://card/${credential.card_number}/${credential.signature}` : '';
  const input = { ...prepared.input,
    record: { ...prepared.input.record, id: metadata.studentId, school_id: metadata.schoolId },
    guardianPhones: metadata.guardianPhones,
    credential: { card_id: credential.card_id ?? null, card_number: credential.card_number ?? null,
      signature: credential.signature ?? null, status: credential.status ?? null, qr_payload: qr },
  };
  if (!Array.isArray(input.guardianPhones)) throw new Error('CARD_METADATA_RESULT_INVALID');
  return { ...prepared, input,
    missing: qr ? prepared.missing.filter(name => name !== 'card.official_qr') : [...new Set([...prepared.missing, 'card.official_qr'])],
    targetReference: { studentId: metadata.studentId, schoolId: metadata.schoolId },
    issuanceAllowed: false,
  };
}
module.exports = { buildStudioBridgeInput };
