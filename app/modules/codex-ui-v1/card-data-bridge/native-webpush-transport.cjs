'use strict';
// No timer, CLI entry point, key generation or implicit activation.
function createNativeWebPushTransport({enabled=false,webPush,publicKey,privateKey,subject}) {
  if(!enabled) return {deliver:async()=>({status:'disabled'})};
  if(!webPush||typeof webPush.sendNotification!=='function'||!publicKey||!privateKey||!/^mailto:[^\s]+$/.test(subject||'')) throw new Error('WEBPUSH_CONFIGURATION_REQUIRED');
  return {async deliver(job) {
    // Storage adapter must atomically claim a delivery and resolve a unique native
    // mapping of the captured recipient. Never resolve the student's current parent.
    if(job?.recipientBindingVersion!==1||!/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(job.recipientProfileId||'')||job.nativeMappingCount!==1
      ||!Number.isSafeInteger(job.nativeRecipientId)||job.nativeRecipientId<1
      ||job.subscriptionOwnerId!==job.nativeRecipientId||!job.deliveryClaimed
      ||!['student.arrival','student.pickup','student.fee_control','student.birthday'].includes(job.eventType)) throw new Error('BOUND_DELIVERY_REQUIRED');
    const s=job.subscription,u=new URL(s?.endpoint);
    if(u.protocol!=='https:'||u.username||u.password||u.port||u.hash||!['fcm.googleapis.com','updates.push.services.mozilla.com','web.push.apple.com'].includes(u.hostname)
      ||!/^[A-Za-z0-9_-]{87}$/.test(s?.keys?.p256dh||'')||!/^[A-Za-z0-9_-]{22}$/.test(s?.keys?.auth||'')) throw new Error('INVALID_SUBSCRIPTION');
    try {
      await webPush.sendNotification(s,JSON.stringify({version:1}),{TTL:300,timeout:10000,vapidDetails:{subject,publicKey,privateKey}});
      return {status:'sent'};
    } catch(error) {
      return {status:[404,410].includes(error.statusCode)?'expired':'retry'};
    }
  }};
}
module.exports={createNativeWebPushTransport};
