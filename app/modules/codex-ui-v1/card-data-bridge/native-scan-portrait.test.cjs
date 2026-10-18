const test=require('node:test');const assert=require('node:assert/strict');
const contextPath=require.resolve('./native-scan-context.cjs');
let input={mode:'verify',qr_payload:'schoolsafe://card/test/test'};
require.cache[contextPath]={id:contextPath,filename:contextPath,loaded:true,exports:{withNativeScanContext:async(_body,_dependencies,run)=>run({client:{},context:{schoolId:'test-school'},claim:{operation:'scan',input,sourceInstance:'test-source',nativeSchoolId:'1'}})}};
const {registerNativeScanRoutes}=require('./native-scan-routes.cjs');
async function execute(result,rows){let handler,lookups=0;registerNativeScanRoutes({post(path,_options,run){if(path==='/bridge/scanner/scan')handler=run;}},{scanner:{scan:async()=>({...result})},mappingPool:{query:async(_sql,args)=>{lookups++;assert.deepEqual(args,['test-source','1','test-student']);return {rows};}}});
let status=200;const reply={header(){return this;},code(value){status=value;return this;},send(value){return value;}};const response=await handler({headers:{},query:{},body:{}},reply);return {response,status,lookups};}
test('admissible identity uses a school-scoped server mapping',async()=>{const value=await execute({result:'VALID',student_id:'test-student'},[{native_entity_id:'7'}]);assert.equal(value.response.data.nativeStudentId,'7');assert.equal(value.lookups,1);});
test('refused result does not obtain a native portrait identity',async()=>{const value=await execute({result:'INVALID',student_id:'test-student'},[{native_entity_id:'7'}]);assert.equal(value.response.data.nativeStudentId,undefined);assert.equal(value.lookups,0);});
test('ambiguous mapping does not select an arbitrary child',async()=>{const value=await execute({result:'VALID',student_id:'test-student'},[{native_entity_id:'7'},{native_entity_id:'8'}]);assert.equal(value.response.data.nativeStudentId,undefined);});
test('browser cannot provide its own native student identity',async()=>{input={...input,nativeStudentId:'9'};const value=await execute({result:'VALID',student_id:'test-student'},[]);assert.equal(value.status,400);assert.equal(value.lookups,0);});
