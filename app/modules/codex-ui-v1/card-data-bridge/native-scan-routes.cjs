'use strict';
const {withNativeScanContext}=require('./native-scan-context.cjs');
const {validateScanInput}=require('./native-scan-input.cjs');
function registerNativeScanRoutes(app,dependencies) {
  for(const operation of ['capabilities','locations','scan']) {
    app.post('/bridge/scanner/'+operation,{bodyLimit:20000},async(request,reply)=>{
      reply.header('Cache-Control','no-store');
      if(request.headers.origin || Object.keys(request.query||{}).length) return reply.code(400).send({error:'INVALID_REQUEST'});
      try {
        const data=await withNativeScanContext(request.body,dependencies,async({client,context,claim})=>{
          if(claim.operation!==operation) throw new Error('INVALID_SCAN_CLAIM');
          if(operation==='scan') {
            if(claim.input?.mode==='canteen') {
              if(Object.keys(claim.input).some(key=>!['mode','qr_payload'].includes(key))) throw new Error('INVALID_SCAN_INPUT');
              const input=validateScanInput({mode:'verify',qr_payload:claim.input.qr_payload});
              if(!dependencies.scanner) throw new Error('SCAN_ENGINE_UNAVAILABLE');
              const card=await dependencies.scanner.scan(context,input,client);
              if(card.result!=='VALID') return card;
              const mapping=await dependencies.mappingPool.query("select native_entity_id from card_bridge.entity_map where source_instance=$1 and native_school_id=$2 and entity_type='student' and target_entity_id=$3",[claim.sourceInstance,claim.nativeSchoolId,card.student_id]);
              if(mapping.rows.length!==1) throw new Error('NATIVE_MAPPING_REQUIRED');
              return {result:'CANTEEN_IDENTITY',nativeStudentId:mapping.rows[0].native_entity_id};
            }
            const input=validateScanInput(claim.input);
            if(!dependencies.scanner) throw new Error('SCAN_ENGINE_UNAVAILABLE');
            const result=await dependencies.scanner.scan(context,input,client);
            if(['VALID','PRESENT','ALREADY_PRESENT','PICKUP_CHOICE','NEED_HUMAN_CONFIRMATION','CONFIRMED'].includes(result.result)
                && typeof result.student_id==='string'){
              const mapping=await dependencies.mappingPool.query("select native_entity_id from card_bridge.entity_map where source_instance=$1 and native_school_id=$2 and entity_type='student' and target_entity_id=$3",[claim.sourceInstance,claim.nativeSchoolId,result.student_id]);
              if(mapping.rows.length===1)result.nativeStudentId=mapping.rows[0].native_entity_id;
            }
            return result;
          }
          if(Object.keys(claim.input).length) throw new Error('INVALID_SCAN_CLAIM');
          if(operation==='locations') {
            const result=await client.query('select id,code,label,kind from api.scan_control_location_list()');
            return {locations:result.rows};
          }
          const result=await client.query('select api.scan_control_my_capabilities() as result');
          const caps=result.rows[0].result;
          let active=false;
          if(caps.capabilities?.includes('SCAN_FEES'))active=Boolean((await client.query('select card_bridge_fees.active_model($1) as result',[claim.nativeYear])).rows[0].result);
          const capabilities=[...(caps.capabilities||[])];
          if(capabilities.includes('SCAN_VERIFY')) capabilities.push('SCAN_CANTEEN');
          return {...caps,capabilities,fee_policy_active:active,schoolId:context.schoolId};
        });
        return {data};
      } catch(error){
        const code=error?.message;
        const status=['INVALID_SIGNATURE','REPLAY_REJECTED'].includes(code)?401
          : ['NATIVE_MAPPING_REQUIRED','SCHOOL_ACCESS_DENIED'].includes(code)||error?.code==='42501'?403
          : ['INVALID_SCAN_CLAIM','INVALID_SCAN_ENVELOPE','INVALID_SCAN_INPUT'].includes(code)?400:503;
        return reply.code(status).send({error:'SCAN_CONTEXT_UNAVAILABLE'});
      }
    });
  }
}
module.exports={registerNativeScanRoutes};
