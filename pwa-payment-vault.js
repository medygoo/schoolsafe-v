'use strict';
(() => {
 const LIMIT=100;let key=null,actor=null;
 const database=new Promise((resolve,reject)=>{
  const request=indexedDB.open('schoolsafe-payment-drafts-v1',1);
  request.onupgradeneeded=()=>{request.result.createObjectStore('vaults',{keyPath:'actor'});request.result.createObjectStore('drafts',{keyPath:'id'});};
  request.onsuccess=()=>{request.result.onversionchange=()=>request.result.close();resolve(request.result);};request.onerror=()=>reject(request.error);request.onblocked=()=>reject(Error('STORAGE_BLOCKED'));
 });
 async function tx(storeName,mode,callback){const db=await database;return new Promise((resolve,reject)=>{
  const tx=db.transaction(storeName,mode),store=tx.objectStore(storeName);let result;
  tx.oncomplete=()=>resolve(result);tx.onerror=tx.onabort=()=>reject(tx.error||Error('STORAGE_FAILED'));callback(store,value=>{result=value;});
 });}
 const all=store=>tx(store,'readonly',(s,done)=>{s.getAll().onsuccess=e=>done(e.target.result);});
 const get=(store,id)=>tx(store,'readonly',(s,done)=>{s.get(id).onsuccess=e=>done(e.target.result);});
 async function derive(passphrase,salt){
  const base=await crypto.subtle.importKey('raw',new TextEncoder().encode(passphrase),'PBKDF2',false,['deriveKey']);
  return crypto.subtle.deriveKey({name:'PBKDF2',salt,iterations:600000,hash:'SHA-256'},base,{name:'AES-GCM',length:256},false,['encrypt','decrypt']);
 }
 async function encrypt(value,k,a){const iv=crypto.getRandomValues(new Uint8Array(12));return {iv,data:await crypto.subtle.encrypt({name:'AES-GCM',iv,additionalData:new TextEncoder().encode(a)},k,new TextEncoder().encode(JSON.stringify(value)))};}
 async function decrypt(value,k,a){return JSON.parse(new TextDecoder().decode(await crypto.subtle.decrypt({name:'AES-GCM',iv:value.iv,additionalData:new TextEncoder().encode(a)},k,value.data)));}
 async function unlock(a,passphrase,context){
  lock();if(!/^[a-f0-9]{64}$/.test(a)||typeof passphrase!=='string'||passphrase.length<12)throw Error('INVALID_UNLOCK');
  let vault=await get('vaults',a);
  if(!vault){
   if(!context||context.actor!==a)throw Error('ONLINE_CONTEXT_REQUIRED');
   const salt=crypto.getRandomValues(new Uint8Array(16)),candidate=await derive(passphrase,salt);
   const verifier=await encrypt({type:'schoolsafe-vault-v1'},candidate,'verifier:'+a),settings=await encrypt(context,candidate,'settings:'+a);
   vault={actor:a,salt,verifier,settings};
   await tx('vaults','readwrite',(s,done)=>{const read=s.get(a);read.onsuccess=()=>{if(read.result){s.transaction.abort();return;}s.add(vault);done();};});
  }
  const candidate=await derive(passphrase,vault.salt),verifier=await decrypt(vault.verifier,candidate,'verifier:'+a);
  if(verifier.type!=='schoolsafe-vault-v1')throw Error('INVALID_UNLOCK');
  const settings=await decrypt(vault.settings,candidate,'settings:'+a);key=candidate;actor=a;
  if(context?.actor===a){
   const settings=await encrypt(context,candidate,'settings:'+a);await tx('vaults','readwrite',s=>s.put({...vault,settings}));return context;
  }
  return settings;
 }
 function lock(){key=null;actor=null;}
 async function save(value){
  const k=key,a=actor;if(!k||!a||value.actor!==a)throw Error('LOCKED');
  const now=Date.now(),id=crypto.randomUUID(),encrypted=await encrypt(value,k,'draft:'+a+':'+id);
  await tx('drafts','readwrite',s=>{s.getAll().onsuccess=e=>{
   const records=e.target.result;
   if(records.filter(r=>r.actor===a).length>=LIMIT){s.transaction.abort();return;}
   s.add({id,actor:a,created:now,...encrypted});
  };});return id;
 }
 async function list(){const k=key,a=actor;if(!k||!a)throw Error('LOCKED');
  const records=(await all('drafts')).filter(r=>r.actor===a).sort((x,y)=>y.created-x.created);
  return Promise.all(records.map(async r=>({id:r.id,value:await decrypt(r,k,'draft:'+a+':'+r.id)})));
 }
 async function remove(id){const row=await get('drafts',id);if(!key||!actor||row?.actor!==actor)throw Error('LOCKED');await tx('drafts','readwrite',s=>s.delete(id));}
 async function setResult(id,result){
  if(!['prepared','confirmed','altered'].includes(result.state))throw Error('INVALID_RESULT');
  if(result.state!=='prepared'&&(!Number.isSafeInteger(result.paymentId)||result.paymentId<1||!Number.isSafeInteger(result.receiptId)||result.receiptId<1))throw Error('INVALID_RESULT');
  if(result.state==='confirmed'&&(!Number.isSafeInteger(result.confirmedAmountMinor)||result.confirmedAmountMinor<1||result.confirmedAmountMinor>999999999
   ||typeof result.confirmedDate!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(result.confirmedDate)||typeof result.confirmedComment!=='string'||typeof result.currency!=='string'))throw Error('INVALID_RESULT');
  const k=key,a=actor,row=await get('drafts',id);if(!k||!a||row?.actor!==a)throw Error('LOCKED');
  const value=await decrypt(row,k,'draft:'+a+':'+id),encrypted=await encrypt({...value,stage:result.state,
   ...(result.state==='prepared'?{}:{paymentId:result.paymentId,receiptId:result.receiptId}),
   ...(result.state==='confirmed'?{confirmation:{amountMinor:result.confirmedAmountMinor,date:result.confirmedDate,comment:result.confirmedComment,currency:result.currency}}:{})},k,'draft:'+a+':'+id);
  await tx('drafts','readwrite',s=>s.put({...row,...encrypted}));
 }
 window.SchoolSafePaymentVault={actors:async()=> (await all('vaults')).map(v=>v.actor),unlock,lock,save,list,remove,setResult,setPrepared:id=>setResult(id,{state:'prepared'})};
})();
