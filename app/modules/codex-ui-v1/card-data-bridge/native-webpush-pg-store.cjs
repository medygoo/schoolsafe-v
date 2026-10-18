'use strict';
const {randomUUID}=require('node:crypto');
function createNativeWebPushPgStore({nativePool,mappingPool,targetPool,sourceInstance='codex-addons-local',nativeYear}) {
  if(!Number.isSafeInteger(nativeYear)||nativeYear<1) throw new Error('EXPLICIT_NATIVE_YEAR_REQUIRED');
  async function mapping(profile,student,school) {
    const r=await mappingPool.query(`select e.native_entity_id::text as staff_id,s.native_school_id::text as school_id,
      c.native_entity_id::text as student_id from card_bridge.school_map s
      join card_bridge.entity_map e on e.source_instance=s.source_instance and e.native_school_id=s.native_school_id and e.entity_type='staff'
      join card_bridge.entity_map c on c.source_instance=s.source_instance and c.native_school_id=s.native_school_id and c.entity_type='student'
      where s.source_instance=$1 and s.target_school_id=$2 and e.target_entity_id=$3 and c.target_entity_id=$4`,[sourceInstance,school,profile,student]);
    if(r.rows.length!==1) throw new Error('AMBIGUOUS_NATIVE_RECIPIENT');
    const m=r.rows[0];
    for(const v of Object.values(m)) if(!/^[1-9][0-9]*$/.test(v)||!Number.isSafeInteger(Number(v))) throw new Error('INVALID_NATIVE_MAPPING');
    return m;
  }
  return {
    async stageBoundEvent(eventId) {
      const gate=await nativePool.query('select activated_at from schoolsafe_notification_push_activation where singleton=true');
      if(gate.rows.length!==1) throw new Error('PUSH_ACTIVATION_REQUIRED');
      const events=await targetPool.query(`select id,school_id,student_id,recipient_profile_id,event_type from card_bridge_notifications.outbox
        where id=$1 and created_at >= $2 and payload->'recipientBindingVersion'='1'::jsonb
        and event_type in ('student.arrival','student.pickup','student.fee_control')`,[eventId,gate.rows[0].activated_at]);
      if(events.rows.length!==1) throw new Error('CAPTURED_EVENT_REQUIRED');
      const e=events.rows[0],m=await mapping(e.recipient_profile_id,e.student_id,e.school_id);
      const staged=await nativePool.query(`insert into schoolsafe_notification_push_delivery
        (event_id,recipient_profile_id,event_type,endpoint,staff_id,school_id,syear,student_id)
        select $1,$2,$3,s.endpoint,s.staff_id,s.school_id,s.syear,$7 from schoolsafe_notification_push_subscriptions s
        where s.staff_id=$4 and s.school_id=$5 and s.syear=$6
        and exists(select 1 from staff st where st.staff_id=s.staff_id and st.profile='parent' and st.syear=s.syear)
        and exists(select 1 from students_join_users u join student_enrollment en on en.student_id=u.student_id
          where u.staff_id=s.staff_id and u.student_id=$7 and en.school_id=s.school_id and en.syear=s.syear)
        on conflict(event_id,endpoint) do nothing`,[e.id,e.recipient_profile_id,e.event_type,m.staff_id,m.school_id,nativeYear,m.student_id]);
      return {staged:staged.rowCount};
    },
    async claimBoundDelivery() {
      const c=await nativePool.connect();
      try {
        await c.query('BEGIN');
        // Lock order: delivery row first, then subscription row. Finish uses the same order.
        const claimed=await c.query('select * from schoolsafe_notification_push_claim($1)',[randomUUID()]);
        if(!claimed.rows.length) {await c.query('COMMIT');return null;}
        const d=claimed.rows[0];
        const subscriptions=await c.query(`select s.* from schoolsafe_notification_push_subscriptions s
          where s.endpoint=$1 and s.staff_id=$2 and s.school_id=$3 and s.syear=$4
          and exists(select 1 from staff st where st.staff_id=s.staff_id and st.profile='parent' and st.syear=s.syear)
          and exists(select 1 from students_join_users u join student_enrollment e on e.student_id=u.student_id
            where u.staff_id=s.staff_id and u.student_id=$5 and e.school_id=s.school_id and e.syear=s.syear)
          for update of s`,[d.endpoint,d.staff_id,d.school_id,d.syear,d.student_id]);
        if(subscriptions.rows.length!==1) {
          await c.query('select schoolsafe_notification_push_finish($1,$2,$3,$4)',[d.event_id,d.endpoint,d.lease_token,'blocked']);
          await c.query('COMMIT');return null;
        }
        const s=subscriptions.rows[0];await c.query('COMMIT');
        return {delivery:d,recipientBindingVersion:1,recipientProfileId:d.recipient_profile_id,nativeMappingCount:1,
          nativeRecipientId:Number(d.staff_id),subscriptionOwnerId:Number(s.staff_id),deliveryClaimed:true,eventType:d.event_type,
          subscription:{endpoint:s.endpoint,keys:{p256dh:s.p256dh,auth:s.auth}}};
      } catch(e) {await c.query('ROLLBACK').catch(()=>{});throw e;} finally {c.release();}
    },
    async finishDelivery(job,status) {
      const d=job.delivery;
      const r=await nativePool.query('select schoolsafe_notification_push_finish($1,$2,$3,$4) as finished',[d.event_id,d.endpoint,d.lease_token,status]);
      if(r.rows[0]?.finished!==true) throw new Error('LEASE_LOST');
    }
  };
}
module.exports={createNativeWebPushPgStore};
