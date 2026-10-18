const test = require('node:test');
const assert = require('node:assert/strict');
const { dispatchFamilyScan } = require('./family-scan-dispatch.cjs');
const id = '04286836-0c33-4744-aec2-dfab27c15f22';
const context = {userId:id,profileId:id,schoolId:id,requestId:'test-request'};
function fixture() {
  const calls = [];
  return {context,studentId:id,calls,
    family:{parentSuspend:async (...args)=>{calls.push(args);return {status:'suspended'};},
      parentReactivationRequest:async (...args)=>{calls.push(args);return {status:'reactivation_pending'};}},
    scanner:{scan:async (...args)=>{calls.push(args);return {result:'DENIED'};}}};
}
test('principal suspension delegates without local permission assignment', async () => {
  const a=fixture();
  assert.deepEqual(await dispatchFamilyScan({...a,operation:'parent-suspend',input:{authorizationId:id,reason:'Test'}}),{status:'suspended'});
  assert.deepEqual(a.calls[0],[context,id,'Test']);
});
test('parent requests reactivation, never activates locally', async () => {
  assert.deepEqual(await dispatchFamilyScan({...fixture(),operation:'parent-reactivation-request',input:{authorizationId:id,reason:'Test'}}),{status:'reactivation_pending'});
  await assert.rejects(dispatchFamilyScan({...fixture(),operation:'parent-activate'}));
});
test('all four official modes preserve the native refusal result', async () => {
  for(const mode of ['entry','pickup','fees','verify']) {
    assert.deepEqual(await dispatchFamilyScan({...fixture(),operation:'scan',input:{mode,qr_payload:'schoolsafe://card/number/signature'}}),{result:'DENIED'});
  }
});
test('demonstration QR and unsupported exit modes never reach the engine', async () => {
  for(const input of [{mode:'entry',qr_payload:'schoolsafe://student/demo'},
    {mode:'gate_exit',qr_payload:'schoolsafe://card/number/signature'}]) {
    const a=fixture();await assert.rejects(dispatchFamilyScan({...a,operation:'scan',input}));assert.equal(a.calls.length,0);
  }
});
test('context override and confirmation without proof are rejected', async () => {
  for(const input of [{authorizationId:id,reason:'Test',profileId:id},
    {authorizationId:id,reason:''}]) await assert.rejects(dispatchFamilyScan({...fixture(),operation:'parent-suspend',input}));
  await assert.rejects(dispatchFamilyScan({...fixture(),operation:'scan',input:{mode:'pickup',qr_payload:'schoolsafe://card/number/signature',human_confirmed:true}}));
});
test('SQL authorization failure propagates without an allowed result', async () => {
  const a=fixture(); a.family.parentSuspend=async()=>{throw new Error('Access denied');};
  await assert.rejects(dispatchFamilyScan({...a,operation:'parent-suspend',input:{authorizationId:id,reason:'Test'}}),/Access denied/);
});
