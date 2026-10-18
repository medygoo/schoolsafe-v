'use strict';
function validateScanInput(input) {
  const keys=['mode','qr_payload','location_id','authorized_person_id','campaign_id','justification','note','human_confirmed','proof_token'];
  if(!input || Array.isArray(input) || typeof input!=='object' || Object.keys(input).some(k=>!keys.includes(k))
    || !['entry','pickup','fees','verify'].includes(input.mode)) throw new Error('INVALID_SCAN_INPUT');
  if(typeof input.qr_payload!=='string'||input.qr_payload.length>2000
    || !/^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(input.qr_payload)) throw new Error('INVALID_SCAN_INPUT');
  for(const key of ['location_id','authorized_person_id','campaign_id']) {
    if(input[key]!==undefined && (typeof input[key]!=='string'||!/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(input[key]))) throw new Error('INVALID_SCAN_INPUT');
  }
  if(input.human_confirmed!==undefined&&typeof input.human_confirmed!=='boolean') throw new Error('INVALID_SCAN_INPUT');
  if(input.proof_token!==undefined&&(typeof input.proof_token!=='string'||input.proof_token.length<16||input.proof_token.length>80)) throw new Error('INVALID_SCAN_INPUT');
  if(input.human_confirmed===true&&!input.proof_token) throw new Error('INVALID_SCAN_INPUT');
  for(const key of ['note','justification']) if(input[key]!==undefined&&(typeof input[key]!=='string'||input[key].length>500)) throw new Error('INVALID_SCAN_INPUT');
  return input;
}
module.exports={validateScanInput};
