'use strict';
const assert=require('node:assert/strict');const fs=require('node:fs');const vm=require('node:vm');const path=require('node:path');
const root=path.resolve(__dirname,'../app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-card-bridge');
const adapter=require(path.join(root,'studio-original-adapter.js'));let calls=0;let body;
const context={SchoolSafeCardAdapter:adapter,fetch:async(url,options)=>{assert(url.includes('resource=studio-input&student_id=5'));assert.equal(options.credentials,'same-origin');assert.equal(options.method,undefined);calls++;return {ok:true,json:async()=>body};}};
vm.runInNewContext(fs.readFileSync(path.join(root,'studio-native-client.js'),'utf8'),context);
const original={data:{backend:'native-registry',issuanceAllowed:false,operationPerformed:false,missing:['class.mapping'],nativeReference:{studentId:'5',schoolId:'1',year:'2026'},targetReference:{studentId:'5',schoolId:'1',year:'2026'},input:{record:{id:'5',school_id:'1',first_name:'SYNTHETIC'},credential:{}}}};
(async()=>{let passed=0;body=structuredClone(original);const result=await context.SchoolSafeStudioNativeBridge.loadNativeStudioCard('5');assert.equal(result.view.student.id,'5');console.log('PASS Native integer identities reach the existing studio adapter without invented UUIDs');passed++;
for(const [label,mutate]of [
 ['foreign school',x=>x.data.targetReference.schoolId='2'],
 ['foreign child',x=>x.data.targetReference.studentId='6'],
 ['foreign year',x=>x.data.targetReference.year='2025'],
 ['forged record scope',x=>x.data.input.record.school_id='2'],
 ['unrecognized backend cannot bypass UUID validation',x=>x.data.backend='unrecognized']]){
 body=structuredClone(original);mutate(body);await assert.rejects(()=>context.SchoolSafeStudioNativeBridge.loadNativeStudioCard('5'),/STUDIO_BRIDGE_RESULT_INVALID/);console.log('PASS Reject '+label);passed++;
}assert.equal(calls,6);console.log('PASS Preparation only performs read requests');passed++;console.log(JSON.stringify({passed,failed:0}));})().catch(e=>{console.error(e);process.exitCode=1;});
