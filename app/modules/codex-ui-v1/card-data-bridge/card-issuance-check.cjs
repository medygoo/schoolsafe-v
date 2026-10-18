'use strict';

// Read-only preflight. The native issuance service remains the sole issuer.
function checkCardIssuance(prepared, issuerConnected = false) {
  if (!prepared || prepared.issuanceAllowed !== false || !Array.isArray(prepared.missing)) {
    throw new Error('CARD_PREFLIGHT_INPUT_INVALID');
  }
  const blockers = prepared.missing.filter(key => key !== 'card.official_qr');
  const hasActiveCredential = Boolean(prepared.input?.credential?.qr_payload);
  return {
    nativeReference: prepared.nativeReference,
    targetReference: prepared.targetReference,
    blockers: [...new Set(blockers)],
    dataComplete: blockers.length === 0,
    hasActiveCredential,
    issuanceAllowed: false,
    operationPerformed: false,
    issuer: 'CardsNativeService.issueCardCredential',
    issuerConnected: issuerConnected === true,
  };
}
module.exports = { checkCardIssuance };
