'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const {validateManagement:validate}=require('./native-management-route.cjs');
const id='04286836-0c33-4744-aec2-dfab27c15f22';
test('school replacement requires identified person, reason, consent and safe photo',()=>{
 const input={action:'school-replace',nativeStudentId:'1',guardianId:id,reason:'Demande reçue à l’école',fullName:'TEST',consent:'TEST',photoPath:'/uploads/authorized-persons/test.png'};
 assert.equal(validate(input).guardianId,id);
 assert.throws(()=>validate({...input,consent:''}));
 assert.throws(()=>validate({...input,photoPath:'/private/test'}));
 assert.throws(()=>validate({...input,action:'parent-replace'}));
});
test('native student only, no target context override',()=>{
 assert.equal(validate({action:'parent-list',nativeStudentId:'1'}).nativeStudentId,'1');
 assert.throws(()=>validate({action:'parent-list',nativeStudentId:'1',schoolId:id}));
 assert.throws(()=>validate({action:'parent-list',nativeStudentId:id}));
});
test('suspension requires reason and exact authorization identifier',()=>{
 assert.throws(()=>validate({action:'parent-suspend',nativeStudentId:'1',authorizationId:id,reason:''}));
 assert.equal(validate({action:'parent-suspend',nativeStudentId:'1',authorizationId:id,reason:'Test'}).reason,'Test');
});
test('delegation has no automatic all-permissions mode',()=>{
 assert.throws(()=>validate({action:'delegate',profileId:id,capability:'ALL',decision:'assign',reason:'Test'}));
 assert.equal(validate({action:'delegate',profileId:id,capability:'SCAN_VERIFY',decision:'assign',reason:'Test'}).capability,'SCAN_VERIFY');
});
test('external registration needs consent and safe portrait path',()=>{
 const input={action:'register-external',nativeStudentId:'1',fullName:'TEST',consent:'Consentement',photoPath:'/uploads/authorized-persons/test.png'};
 assert.equal(validate(input).fullName,'TEST');
 assert.throws(()=>validate({...input,consent:''}));
 assert.throws(()=>validate({...input,photoPath:'/uploads/authorized-persons/../private'}));
});
test('print requires known format; no client credential accepted',()=>{
 assert.equal(validate({action:'print-request',nativeStudentId:'1',format:'badge'}).format,'badge');
 assert.throws(()=>validate({action:'print-request',nativeStudentId:'1',format:'badge',signature:'invented'}));
});
