'use strict';
(() => {
 const NAME='schoolsafe-pwa-outbox-v1',TTL=7*86400000,MAX=200;
 let context=null,running=null;
 const open=new Promise((resolve,reject)=>{
  const request=indexedDB.open(NAME,1);
  request.onupgradeneeded=()=>request.result.createObjectStore('operations',{keyPath:'id'});
  request.onsuccess=()=>{request.result.onversionchange=()=>request.result.close();resolve(request.result);};
  request.onerror=()=>reject(request.error);
  request.onblocked=()=>reject(new Error('STORAGE_BLOCKED'));
 });
 async function transaction(mode,callback){
  const db=await open;
  return new Promise((resolve,reject)=>{
   const tx=db.transaction('operations',mode),store=tx.objectStore('operations');let value;
   tx.oncomplete=()=>resolve(value);tx.onerror=tx.onabort=()=>reject(tx.error||new Error('STORAGE_FAILED'));
   callback(store,v=>{value=v;});
  });
 }
 const rows=()=>transaction('readonly',(store,done)=>{store.getAll().onsuccess=event=>done(event.target.result);});
 const notify=()=>window.dispatchEvent(new CustomEvent('schoolsafe:outbox'));
 async function refresh(){
  const response=await fetch('/pwa-sync.php',{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)});
  if(!response.ok){context=null;throw new Error(response.status===401||response.status===403?'SESSION_REQUIRED':'SERVER_UNAVAILABLE');}
  const value=await response.json();
  if(!/^[a-f0-9]{64}$/.test(value.actor||'')||!/^[a-f0-9]{64}$/.test(value.csrf||'')||!Array.isArray(value.receiptReads)||value.receiptReads.length>1000||!value.receiptReads.every(id=>Number.isSafeInteger(id)&&id>0))throw new Error('INVALID_CONTEXT');
  context=value;return value;
 }
 async function state(receiptId){
  const verified=context;
  if(!verified)return 'unavailable';
  if(verified.receiptReads.includes(receiptId))return 'confirmed';
  let current;
  try{current=(await rows()).filter(row=>row.actor===verified.actor&&row.receiptId===receiptId&&Date.now()-row.created<TTL);}
  catch{return 'unavailable';}
  if(current.some(row=>row.status==='confirmed'))return 'confirmed';
  if(current.some(row=>row.status==='pending'))return 'pending';
  if(current.some(row=>row.status==='rejected'))return 'rejected';
  return 'unread';
 }
 async function markRead(receiptId){
  if(!context||!Number.isSafeInteger(receiptId)||receiptId<1)throw new Error('SESSION_REQUIRED');
  const actor=context.actor;
  await transaction('readwrite',(store,done)=>{
   store.getAll().onsuccess=event=>{
    const all=event.target.result,now=Date.now();
    for(const row of all)if(now-row.created>=TTL)store.delete(row.id);
    const active=all.filter(row=>now-row.created<TTL);
    if(active.some(row=>row.actor===actor&&row.receiptId===receiptId&&['pending','confirmed'].includes(row.status))){done();return;}
    if(active.length>=MAX){store.transaction.abort();return;}
    store.add({id:crypto.randomUUID(),actor,kind:'receipt.read',receiptId,created:now,status:'pending'});done();
   };
  });notify();
  if(navigator.onLine)void sync().catch(()=>{});
 }
 async function run(){
  const verified=await refresh();
  await transaction('readwrite',store=>{store.getAll().onsuccess=event=>{
   for(const row of event.target.result)if(Date.now()-row.created>=TTL)store.delete(row.id);
  };});
  const pending=(await rows()).filter(row=>row.actor===verified.actor&&row.status==='pending'&&Date.now()-row.created<TTL).sort((a,b)=>a.created-b.created);
  for(const row of pending){
   const response=await fetch('/pwa-sync.php',{method:'POST',credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000),headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':verified.csrf},body:JSON.stringify({actor:row.actor,id:row.id,kind:row.kind,receiptId:row.receiptId})});
   if(!response.ok){
    const error=await response.json().catch(()=>({}));
    if([400,409].includes(response.status)||(response.status===403&&error.error==='RECEIPT_DENIED')){
     await transaction('readwrite',store=>store.put({...row,status:'rejected'}));notify();continue;
    }
    if(response.status===401||response.status===403)context=null;
    throw new Error('SYNC_PAUSED');
   }
   const result=await response.json();
   if(result.confirmed!==true||result.id!==row.id||result.receiptId!==row.receiptId)throw new Error('INVALID_CONFIRMATION');
   await transaction('readwrite',store=>store.put({...row,status:'confirmed'}));
   if(context?.actor===row.actor&&!context.receiptReads.includes(row.receiptId))context.receiptReads.push(row.receiptId);
   notify();
  }
  notify();
 }
 function sync(){if(!running)running=run().finally(()=>{running=null;});return running;}
 window.SchoolSafeOutbox={markRead,state,sync,ready:sync().catch(()=>{})};
 window.addEventListener('online',()=>void sync().catch(()=>{}));
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&navigator.onLine)void sync().catch(()=>{});});
})();
