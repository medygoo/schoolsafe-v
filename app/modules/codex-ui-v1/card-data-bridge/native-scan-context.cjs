'use strict';
const {randomUUID}=require('node:crypto');
const {verifyNativeScanEnvelope}=require('./native-scan-envelope.cjs');
async function withNativeScanContext(envelope,dependencies,operation) {
  const claim=await verifyNativeScanEnvelope(envelope,dependencies.nativeVerification);
  const maps=await dependencies.mappingPool.query(`SELECT s.target_school_id,e.target_entity_id AS profile_id
    FROM card_bridge.school_map s JOIN card_bridge.entity_map e USING(source_instance,native_school_id)
    WHERE s.source_instance=$1 AND s.native_school_id=$2 AND e.entity_type='staff' AND e.native_entity_id=$3`,
    [claim.sourceInstance,claim.nativeSchoolId,claim.nativeUserId]);
  if(maps.rows.length!==1) throw new Error('NATIVE_MAPPING_REQUIRED');
  const client=await dependencies.targetPool.connect();
  try {
    await client.query('BEGIN');
    const mapped=maps.rows[0];
    const rows=await client.query('select * from card_bridge_local.resolve_operator($1,$2,$3)',
      [mapped.profile_id,mapped.target_school_id,`${claim.sourceInstance}:${claim.nativeUserId}`]);
    if(rows.rows.length!==1) throw new Error('NATIVE_MAPPING_REQUIRED');
    const identity=rows.rows[0];
    if(await dependencies.assertSchoolAccess(identity.school_id)!==true) throw new Error('SCHOOL_ACCESS_DENIED');
    const context={userId:identity.user_id,profileId:identity.profile_id,schoolId:identity.school_id,requestId:randomUUID()};
    await client.query('select api.set_request_context($1,$2,$3,$4)',Object.values(context));
    const result=await operation({client,context,claim});
    await client.query('COMMIT');return result;
  } catch(error){await client.query('ROLLBACK').catch(()=>{});throw error;}
  finally {client.release();}
}
module.exports={withNativeScanContext};
