'use strict';
const {createNativeWebPushTransport}=require('./native-webpush-transport.cjs');
function createNativeWebPushWorker({enabled=false,store,...config}) {
  const transport=createNativeWebPushTransport({enabled,...config});
  let busy=false;
  return {async runOnce() {
    if(!enabled) return {status:'disabled',processed:0};
    if(busy) return {status:'busy',processed:0};
    if(!store||typeof store.claimBoundDelivery!=='function'||typeof store.finishDelivery!=='function') throw new Error('NATIVE_BOUND_STORE_REQUIRED');
    busy=true;
    try {
      const job=await store.claimBoundDelivery();
      if(!job) return {status:'idle',processed:0};
      let result;
      try {result=await transport.deliver(job);} catch {result={status:'blocked'};}
      await store.finishDelivery(job,result.status);
      return {status:result.status,processed:1};
    } finally {busy=false;}
  }};
}
module.exports={createNativeWebPushWorker};
