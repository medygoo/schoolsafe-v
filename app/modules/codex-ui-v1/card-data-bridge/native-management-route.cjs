'use strict';
const {withNativeScanContext}=require('./native-scan-context.cjs');
const uuid=value=>typeof value==='string'&&/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(value);
const nonempty=value=>typeof value==='string'&&value.trim().length>0&&value.length<=500;
function validateManagement(input){
 if(!input||typeof input!=='object'||Array.isArray(input))throw new Error('INVALID_MANAGEMENT_INPUT');
 const fields={ 'parent-list':['nativeStudentId'],'school-list':['nativeStudentId'],
 'parent-activate':['nativeStudentId','guardianId','reason'],
 'school-retire':['nativeStudentId','guardianId','reason','consent'],
 'school-family-approve':['nativeStudentId','guardianId','reason','consent'],
 'school-replace':['nativeStudentId','guardianId','reason','fullName','phone','email','address','consent','photoPath'],
 'parent-suspend':['nativeStudentId','authorizationId','reason'],'parent-family-suspend':['nativeStudentId','guardianId','reason'],
 'parent-reactivation-request':['nativeStudentId','authorizationId','reason'],'parent-family-reactivation-request':['nativeStudentId','guardianId','reason'],
 'register-external':['nativeStudentId','fullName','phone','email','address','consent','photoPath'],
 'register-family':['nativeStudentId','guardianType','fullName','phone','email','address','isPrimary'],
 'photo-review':['nativeStudentId','guardianId','decision','reason'],'photo-set':['nativeStudentId','guardianId','photoPath'],
 'validate-authorization':['nativeStudentId','authorizationId'],
 'print-request':['nativeStudentId','format'],
 'grants':[],'delegate':['profileId','capability','decision','reason'] };
 const keys=typeof input.action==='string'&&Object.hasOwn(fields,input.action)?fields[input.action]:null;if(!keys||Object.keys(input).some(k=>k!=='action'&&!keys.includes(k)))throw new Error('INVALID_MANAGEMENT_INPUT');
 if(keys.includes('nativeStudentId')&&(typeof input.nativeStudentId!=='string'||!/^[1-9][0-9]{0,18}$/.test(input.nativeStudentId)))throw new Error('INVALID_MANAGEMENT_INPUT');
 for(const key of ['guardianId','authorizationId','profileId'])if(keys.includes(key)&&!uuid(input[key]))throw new Error('INVALID_MANAGEMENT_INPUT');
 for(const key of ['reason','fullName','consent'])if(keys.includes(key)&&!nonempty(input[key]))throw new Error('INVALID_MANAGEMENT_INPUT');
 for(const key of ['phone','email','address'])if(input[key]!==undefined&&(typeof input[key]!=='string'||input[key].length>500))throw new Error('INVALID_MANAGEMENT_INPUT');
 if(input.action==='register-family'){
  if(!['pere','mere','tuteur'].includes(input.guardianType)||typeof input.isPrimary!=='boolean')throw new Error('INVALID_MANAGEMENT_INPUT');
  for(const key of ['fullName','phone','email','address'])if(typeof input[key]==='string'&&/[\u0000-\u001f\u007f]/.test(input[key]))throw new Error('INVALID_MANAGEMENT_INPUT');
  if(input.email?.trim()&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email.trim()))throw new Error('INVALID_MANAGEMENT_INPUT');
 }
 if(keys.includes('photoPath')&&(!nonempty(input.photoPath)||!/^\/uploads\/authorized-persons\/[A-Za-z0-9._/-]+$/.test(input.photoPath)||input.photoPath.split('/').includes('..')))throw new Error('INVALID_MANAGEMENT_INPUT');
 if(input.action==='photo-review'&&!['accepted','rejected'].includes(input.decision))throw new Error('INVALID_MANAGEMENT_INPUT');
 if(input.action==='delegate'&&(!['assign','revoke'].includes(input.decision)||!['SCAN_ENTRY','SCAN_PICKUP','SCAN_FEES','SCAN_VERIFY'].includes(input.capability)))throw new Error('INVALID_MANAGEMENT_INPUT');
 if(input.action==='print-request'&&!['badge','carte'].includes(input.format))throw new Error('INVALID_MANAGEMENT_INPUT');
 return input;
}
function registerNativeManagementRoute(app,dependencies){
 app.post('/bridge/scanner/management',{bodyLimit:20000},async(request,reply)=>{
  reply.header('Cache-Control','no-store');
  if(request.headers.origin||Object.keys(request.query||{}).length)return reply.code(400).send({error:'INVALID_REQUEST'});
  try{
   const data=await withNativeScanContext(request.body,dependencies,async({client,context,claim})=>{
    if(claim.operation!=='management')throw new Error('INVALID_MANAGEMENT_INPUT');
    const input=validateManagement(claim.input),action=input.action;
    const sql=async(text,args=[])=>{const result=await client.query(text,args);return result.rows[0].result;};
    if(action==='grants')return sql('select api.scan_control_grant_list() as result');
    if(action==='delegate')return sql('select api.scan_control_grant_set($1,$2,$3,$4) as result',[input.profileId,input.capability,input.decision,input.reason]);
    const map=await dependencies.mappingPool.query(`select target_entity_id from card_bridge.entity_map where source_instance=$1 and native_school_id=$2 and entity_type='student' and native_entity_id=$3`,[claim.sourceInstance,claim.nativeSchoolId,input.nativeStudentId]);
    if(map.rows.length!==1)throw new Error('NATIVE_MAPPING_REQUIRED');const student=map.rows[0].target_entity_id;
    if(action==='print-request'){
     await client.query('select iam.require_access($1,null,$2)',['cards.request.print',student]);
     const credential=await sql('select api.student_card_read($1) as result',[student]);
     if(credential?.status!=='active'||!credential?.card_number)throw new Error('ACTIVE_CARD_REQUIRED');
     return sql('select api.card_print_request_create($1,$2,null,null,null,null,$3::jsonb) as result',[student,input.format,JSON.stringify({source:'rosario-local-bridge',credential_card_number:credential.card_number})]);
    }
    const parent=action.startsWith('parent-');
    const view=await sql(parent?'select api.pickup_parent_authorization_list($1) as result':'select api.pickup_school_authorization_list($1) as result',[student]);
    if(action==='parent-list'||action==='school-list')return view;
    const family=Array.isArray(view.family)?view.family:[],accredited=Array.isArray(view.accredited)?view.accredited:[];
    // The native family view contains only active family guardians.
    if(action==='register-family'&&!family.some(row=>row.is_primary===true)&&input.isPrimary===false)throw new Error('PRIMARY_SELECTION_REQUIRED');
    if(input.guardianId&&!family.concat(accredited).some(row=>row.guardian_id===input.guardianId))throw new Error('MANAGEMENT_ENTITY_NOT_ACCESSIBLE');
    if(input.authorizationId&&!accredited.some(row=>row.authorization_id===input.authorizationId))throw new Error('MANAGEMENT_ENTITY_NOT_ACCESSIBLE');
    const calls={
     'register-family':['select api.student_guardian_add($1,$2::jsonb) as result',[student,JSON.stringify({guardian_type:input.guardianType,full_name:input.fullName?.trim(),phone:input.phone?.trim(),email:input.email?.trim(),address:input.address?.trim(),is_primary:input.isPrimary,is_authorized_pickup:false})]],
     'school-family-approve':['select card_bridge_family.school_family_approve($1,$2,$3,$4) as result',[student,input.guardianId,input.reason,input.consent]],
     'parent-activate':['select card_bridge_family.parent_activate($1,$2,$3) as result',[student,input.guardianId,input.reason]],
     'school-retire':['select card_bridge_family.school_retire($1,$2,$3,$4) as result',[student,input.guardianId,input.reason,input.consent]],
     'school-replace':['select card_bridge_family.school_replace($1,$2,$3,$4::jsonb) as result',[student,input.guardianId,input.reason,JSON.stringify({full_name:input.fullName,phone:input.phone,email:input.email,address:input.address,consent:input.consent,photo_path:input.photoPath})]],
     'parent-suspend':['select api.pickup_parent_suspend($1,$2) as result',[input.authorizationId,input.reason]],
     'parent-family-suspend':['select api.pickup_parent_family_suspend($1,$2) as result',[input.guardianId,input.reason]],
     'parent-reactivation-request':['select api.pickup_parent_reactivation_request($1,$2) as result',[input.authorizationId,input.reason]],
     'parent-family-reactivation-request':['select api.pickup_parent_family_reactivation_request($1,$2) as result',[input.guardianId,input.reason]],
     'photo-review':['select api.pickup_photo_review($1,$2,$3) as result',[input.guardianId,input.decision,input.reason]],
     'photo-set':['select api.scan_control_portrait_set($1,$2) as result',[input.guardianId,input.photoPath]],
     'validate-authorization':['select api.pickup_authorization_validate($1) as result',[input.authorizationId]],
     'register-external':['select api.pickup_external_register($1,$2::jsonb) as result',[student,JSON.stringify({full_name:input.fullName,phone:input.phone,email:input.email,address:input.address,consent:input.consent,photo_path:input.photoPath})]]
    };
    return sql(...calls[action]);
   });return {data};
  }catch(error){const message=error.message;
   const status=['INVALID_SIGNATURE','REPLAY_REJECTED'].includes(message)?401
    : error.code==='42501'||['NATIVE_MAPPING_REQUIRED','SCHOOL_ACCESS_DENIED','MANAGEMENT_ENTITY_NOT_ACCESSIBLE'].includes(message)?403
    : message==='INVALID_MANAGEMENT_INPUT'||error.code==='23514'?400:['ACTIVE_CARD_REQUIRED','PRIMARY_SELECTION_REQUIRED'].includes(message)?409:503;
   return reply.code(status).send({error:message==='PRIMARY_SELECTION_REQUIRED'?message:'MANAGEMENT_UNAVAILABLE'});
  }
 });
}
module.exports={registerNativeManagementRoute,validateManagement};
