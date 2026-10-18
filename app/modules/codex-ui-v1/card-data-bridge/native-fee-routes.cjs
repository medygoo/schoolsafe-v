'use strict';
const {createRequire}=require('node:module');
const {withNativeScanContext}=require('./native-scan-context.cjs');
const {decimalCents,classifyFee}=require('./fee-policy.cjs');
function registerNativeFeeRoutes(app,dependencies){
 app.post('/bridge/scanner/fees',{bodyLimit:20000},async(request,reply)=>{
  reply.header('Cache-Control','no-store');
  if(request.headers.origin||Object.keys(request.query||{}).length)return reply.code(400).send({error:'INVALID_REQUEST'});
  try{
   const data=await withNativeScanContext(request.body,dependencies,async({client,context,claim})=>{
    if(claim.operation!=='fees')throw new Error('INVALID_FEE_INPUT');
    const input=claim.input,action=input.action;
    if(['list','save','activate','stop'].includes(action)){
     const allowed={list:['action'],save:['action','label','threshold','tolerance'],activate:['action','id'],stop:['action']}[action];
     if(Object.keys(input).some(k=>!allowed.includes(k)))throw new Error('INVALID_FEE_INPUT');
     if(action==='save'){
      if(typeof input.label!=='string'||!input.label.trim()||input.label.length>100||decimalCents(input.threshold)<=0n||!Number.isInteger(input.tolerance)||input.tolerance<0||input.tolerance>100)throw new Error('INVALID_FEE_INPUT');
     }
     if(action==='activate'&&!/^[a-f0-9-]{36}$/i.test(input.id||''))throw new Error('INVALID_FEE_INPUT');
     return (await client.query('select card_bridge_fees.manage($1,$2,$3::jsonb) as result',[claim.nativeYear,action,JSON.stringify(input)])).rows[0].result;
    }
    const allowed=action==='resolve'?['action','qr_payload']:['action','qr_payload','modelId','paid','hasData'];
    if(!['resolve','result'].includes(action)||Object.keys(input).some(k=>!allowed.includes(k))||typeof input.qr_payload!=='string'||input.qr_payload.length>4096)throw new Error('INVALID_FEE_INPUT');
    const policy=(await client.query('select card_bridge_fees.active_model($1) as result',[claim.nativeYear])).rows[0].result;
    if(!policy)return {result:'FEE_POLICY_STATUS',color:'yellow',label:'Orienter au bureau — aucun contrôle actif',reason:'no_active_policy'};
    const {parseQrPayload,signCardNumber}=createRequire('/deps/package.json')('/deps/native-security.cjs');
    const parsed=parseQrPayload(input.qr_payload);
    if(!parsed||signCardNumber(parsed.cardNumber,process.env.CARD_HMAC_SECRET)!==parsed.signature)return {result:'INVALID',label:'QR invalide'};
    const card=(await client.query('select api.scanner_card_lookup($1,$2,$3) as result',[parsed.cardNumber,parsed.signature,'SCAN_FEES'])).rows[0].result;
    if(!card?.found||card.signature_matches!==true||card.status!=='active')return {result:'INVALID',label:'Carte indisponible'};
    // A scoped delegation cannot be widened by the new policy layer.
    const scope=await client.query('select card_bridge_fees.scan_scope($1) as allowed',[card.class_id]);
    if(scope.rows[0].allowed!==true)throw new Error('FEE_SCOPE_DENIED');
    const mapping=await dependencies.mappingPool.query("select native_entity_id from card_bridge.entity_map where source_instance=$1 and native_school_id=$2 and entity_type='student' and target_entity_id=$3",[claim.sourceInstance,claim.nativeSchoolId,card.student_id]);
    if(mapping.rows.length!==1)throw new Error('FEE_SCOPE_DENIED');
    if(action==='resolve')return {nativeStudentId:mapping.rows[0].native_entity_id,modelId:policy.id};
    if(input.modelId!==policy.id)return {result:'FEE_POLICY_STATUS',color:'yellow',label:'Orienter au bureau — contrôle modifié',reason:'policy_changed'};
    if(typeof input.hasData!=='boolean')throw new Error('INVALID_FEE_INPUT');
    const result=classifyFee({threshold:String(policy.threshold),tolerancePercent:policy.tolerance,paid:input.paid,hasData:input.hasData});
    await client.query('select card_bridge_fees.record_result($1,$2::jsonb)',[card.student_id,JSON.stringify({...result,modelId:policy.id,nativeYear:claim.nativeYear,source:'rosario_billing'})]);
    return {result:'FEE_POLICY_STATUS',...result};
   });return {data};
  }catch(error){if(dependencies.onFeeTestError)dependencies.onFeeTestError(error);return reply.code(error.code==='42501'||error.message==='FEE_SCOPE_DENIED'?403:error.message==='INVALID_FEE_INPUT'||error.message==='INVALID_AMOUNT'?400:503).send({error:'FEE_CONTROL_UNAVAILABLE'});}
 });
}
module.exports={registerNativeFeeRoutes};
