(function(root) {
  'use strict';
  const uuid=/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
  const native=/^[1-9][0-9]{0,8}$/;
  const kinds=['detected','approved','refused','session_scheduled','session_held','session_postponed','session_cancelled','reassessment_passed','reassessment_repeat','reassessment_closed','billing_configured','payment_recorded','payment_confirmed','payment_review_required'];
  const text=(v,n)=>typeof v==='string'&&v.length<=n;
  const date=v=>text(v,64)&&/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::?\d{2})?)?$/.test(v)&&Number.isFinite(Date.parse(v));
  function normalizeNotifications(rows) {
    if(!Array.isArray(rows)||rows.length>200) throw new Error('INVALID_NOTIFICATIONS');
    const seen=new Set();
    return rows.map(row=>{
      const p=row?.payload, birthday=row?.event_type==='student.birthday', palmares=['student.top10.ecole','student.top10.classe'].includes(row?.event_type), remediation=kinds.some(k=>row?.event_type==='remediation.'+k);
      if(!row||typeof row.id!=='string'||typeof row.student_id!=='string'||seen.has(row.id)
        ||!text(row.student_name,250)||!date(row.created_at)||!p||typeof p!=='object'||Array.isArray(p)) throw new Error('INVALID_NOTIFICATIONS');
      seen.add(row.id);
      let payload;
      if(row.event_type==='payment.receipt') {
        if(!native.test(row.student_id)||!Number.isSafeInteger(p.receipt_id)||p.receipt_id<1
          ||row.id!=='receipt:'+p.receipt_id||!text(p.message,500)
          ||!['valid','cancelled','refunded'].includes(p.status)) throw new Error('INVALID_NOTIFICATIONS');
        payload={message:p.message,receipt_id:p.receipt_id,status:p.status};
      } else if(birthday) {
        if(!/^birthday:[1-9][0-9]{0,8}:\d{4}-\d{2}-\d{2}$/.test(row.id)||!native.test(row.student_id)
          ||row.id!=='birthday:'+row.student_id+':'+p.birthday_date
          ||!text(p.message,500)||!p.message.trim()||typeof p.school_open!=='boolean') throw new Error('INVALID_NOTIFICATIONS');
        payload={message:p.message,birthday_date:p.birthday_date,school_open:p.school_open};
      } else if(palmares) {
        if(!/^palmares:[1-9][0-9]*$/.test(row.id)||!native.test(row.student_id)
          ||!text(p.message,500)||!p.message.trim()||!['ecole','classe'].includes(p.scope)
          ||row.event_type!=='student.top10.'+p.scope||!Number.isInteger(p.position)||p.position<1||p.position>10
          ||!text(p.period,500)||!text(p.class,500)) throw new Error('INVALID_NOTIFICATIONS');
        payload={message:p.message,scope:p.scope,position:p.position,period:p.period,class:p.class};
      } else if(remediation) {
        if(!/^remediation:[1-9][0-9]*$/.test(row.id)||row.id.length>64||!native.test(row.student_id)
          ||!text(p.message,500)||!p.message.trim()||!text(p.course,500)
          ||!Number.isSafeInteger(p.case_id)||p.case_id<1) throw new Error('INVALID_NOTIFICATIONS');
        payload={message:p.message,course:p.course,case_id:p.case_id};
      } else {
        if(!uuid.test(row.id)||!uuid.test(row.student_id)||!['student.arrival','student.pickup','student.fee_control'].includes(row.event_type)||!date(p.occurredAt)) throw new Error('INVALID_NOTIFICATIONS');
        payload={occurredAt:p.occurredAt};
        if(row.event_type==='student.fee_control') {
          if(['paid','partial','due','exempt','no_data'].includes(p.balanceStatus)&&p.feeColor===undefined&&p.feeLabel===undefined) payload.balanceStatus=p.balanceStatus;
          else if(p.balanceStatus===undefined&&['blue','yellow','red'].includes(p.feeColor)&&text(p.feeLabel,500)&&p.feeLabel.trim()) {payload.feeColor=p.feeColor;payload.feeLabel=p.feeLabel;}
          else throw new Error('INVALID_NOTIFICATIONS');
        }
      }
      return {id:row.id,student_id:row.student_id,student_name:row.student_name,event_type:row.event_type,created_at:row.created_at,payload};
    });
  }
  const api={normalizeNotifications};
  if(typeof module!=='undefined'&&module.exports) module.exports=api;
  else root.SchoolSafeNotificationData=api;
})(typeof window==='undefined'?globalThis:window);
