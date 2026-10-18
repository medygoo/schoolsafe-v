'use strict';
const {withNativeScanContext}=require('./native-scan-context.cjs');
const kinds=['gate','door','classroom','office','control_point'];
function validateLocationInput(input) {
  if(!input||typeof input!=='object'||Array.isArray(input))throw new Error('INVALID_LOCATION_INPUT');
  if(input.action==='list'&&Object.keys(input).length===1)return input;
  if(input.action!=='save'||Object.keys(input).some(k=>!['action','id','code','label','kind','is_active'].includes(k))
    ||(input.id!==undefined&&!/^[a-f0-9]{8}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i.test(input.id))
    ||typeof input.code!=='string'||!input.code.trim()||input.code.length>100
    ||typeof input.label!=='string'||!input.label.trim()||input.label.length>200
    ||!kinds.includes(input.kind)||typeof input.is_active!=='boolean')throw new Error('INVALID_LOCATION_INPUT');
  return {...input,code:input.code.trim(),label:input.label.trim()};
}
function registerNativeLocationRoutes(app,dependencies) {
 app.post('/bridge/scanner/location-management',{bodyLimit:20000},async(request,reply)=>{
  reply.header('Cache-Control','no-store');
  if(request.headers.origin||Object.keys(request.query||{}).length)return reply.code(400).send({error:'INVALID_REQUEST'});
  try {
   const data=await withNativeScanContext(request.body,dependencies,async({client,claim})=>{
    if(claim.operation!=='location-management')throw new Error('INVALID_LOCATION_INPUT');
    const input=validateLocationInput(claim.input);
    return (await client.query('select api.scan_control_location_manage($1::jsonb) as result',[JSON.stringify(input)])).rows[0].result;
   });
   return {data};
  }catch(error){
   const status=['INVALID_SIGNATURE','REPLAY_REJECTED'].includes(error.message)?401:
    ['INVALID_LOCATION_INPUT','INVALID_SCAN_CLAIM','INVALID_SCAN_ENVELOPE'].includes(error.message)?400:
    error.code==='42501'||['NATIVE_MAPPING_REQUIRED','SCHOOL_ACCESS_DENIED'].includes(error.message)?403:error.code==='23505'?409:503;
   return reply.code(status).send({error:'LOCATION_CONFIGURATION_UNAVAILABLE'});
  }
 });
}
module.exports={validateLocationInput,registerNativeLocationRoutes};
