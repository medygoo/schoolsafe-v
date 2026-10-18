'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const {validateScanInput:validate}=require('./native-scan-input.cjs');
test('four modes accepted, legacy modes rejected',()=>{
 for(const mode of ['entry','pickup','fees','verify']) assert.equal(validate({mode,qr_payload:'schoolsafe://card/ABC/signature'}).mode,mode);
 for(const mode of ['pre_exit','gate_exit']) assert.throws(()=>validate({mode,qr_payload:'schoolsafe://card/ABC/signature'}));
});
test('identity overrides and demo QR rejected',()=>{
 assert.throws(()=>validate({mode:'verify',qr_payload:'demo'}));
 assert.throws(()=>validate({mode:'verify',qr_payload:'schoolsafe://card/ABC/signature',profileId:'override'}));
});
test('confirmation requires proof and valid entity identifiers',()=>{
 assert.throws(()=>validate({mode:'pickup',qr_payload:'schoolsafe://card/ABC/signature',human_confirmed:true}));
 assert.throws(()=>validate({mode:'entry',qr_payload:'schoolsafe://card/ABC/signature',location_id:'bad'}));
});
