'use strict';
(() => {
 const vault=SchoolSafePaymentVault,select=document.getElementById('vault'),state=document.getElementById('state'),workspace=document.getElementById('workspace');
 let verified=null,settings=null,hideTimer=null;
 const safeContext=value=>({actor:value.actor,studentId:value.studentId,currency:value.currency,date:value.date});
 async function check(){
  const response=await fetch('/pwa-payment-context.php',{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)});
  if(!response.ok){verified=null;throw Error('SESSION_REQUIRED');}
  const value=await response.json();
  if(!/^[a-f0-9]{64}$/.test(value.actor||'')||!Number.isSafeInteger(value.studentId)||value.studentId<1||typeof value.currency!=='string')throw Error('INVALID_CONTEXT');
  verified=value;return value;
 }
 function lock(){vault.lock();settings=null;workspace.hidden=true;document.getElementById('unlock').hidden=false;document.getElementById('drafts').replaceChildren();document.getElementById('draft').reset();document.getElementById('passphrase').value='';}
 function network(){document.getElementById('connection').textContent=navigator.onLine?'Brouillons locaux - aucun encaissement automatique.':'Hors connexion - aucun paiement confirme.';}
 async function render(){
  const list=document.getElementById('drafts');list.replaceChildren();
  for(const record of await vault.list()){
   let value=record.value;
   if(navigator.onLine&&verified?.actor===value.actor&&value.stage){
    try{
     const response=await fetch('/pwa-payment-result.php?draft_id='+record.id,{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)});
     if(response.ok){const result=await response.json();if(['confirmed','altered'].includes(result.state)){await vault.setResult(record.id,result);value=(await vault.list()).find(row=>row.id===record.id)?.value||value;}}
    }catch{/* Keep the last verified state; never infer a financial confirmation. */}
   }
   const li=document.createElement('li'),p=document.createElement('p');
   p.textContent='Brouillon : eleve n° '+value.studentId+' - '+(value.amountMinor/100).toFixed(2)+' '+value.currency+' - '+value.date+' - '+value.comment;li.append(p);
   const pending=document.createElement('p');pending.textContent={prepared:'Repris au formulaire : paiement a verifier, pas confirme ici.',confirmed:'Paiement enregistre lors de la derniere verification.',altered:'Paiement modifie ou annule : consulter le dossier.'}[value.stage]||'Brouillon : paiement non confirme.';li.append(pending);
   if(value.stage==='confirmed'&&value.confirmation){const actual=document.createElement('p');actual.textContent='Paiement confirme : '+(value.confirmation.amountMinor/100).toFixed(2)+' '+value.confirmation.currency+' - '+value.confirmation.date+' - '+value.confirmation.comment;li.append(actual);}
   if(value.receiptId){const link=document.createElement('a');link.textContent='Consulter le recu';link.href='/schoolsafe-receipt.php?id='+value.receiptId;li.append(link);}
   const resume=document.createElement('button');resume.type='button';resume.textContent='Reprendre dans le formulaire';
   resume.disabled=['confirmed','altered'].includes(value.stage);
   resume.addEventListener('click',async()=>{
    resume.disabled=true;
    try{
     const current=await check();
     if(current.actor!==value.actor||current.studentId!==value.studentId||current.currency!==value.currency)throw Error('CONTEXT_MISMATCH');
     const payload={actor:value.actor,studentId:value.studentId,amountMinor:value.amountMinor,date:value.date,comment:value.comment,currency:value.currency,draftId:record.id};
     const response=await fetch('/pwa-payment-context.php',{method:'POST',credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000),headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':current.csrf},body:JSON.stringify(payload)});
     if(!response.ok)throw Error('PREPARATION_REFUSED');
     const result=await response.json();
     if(['confirmed','altered'].includes(result.state)){await vault.setResult(record.id,result);await render();return;}
     if(result.prepared!==true)throw Error('INVALID_RESULT');await vault.setPrepared(record.id);
     location.assign('/Modules.php?modname=Student_Billing/StudentPayments.php&student_id='+value.studentId);
    }catch{state.textContent='Reprise indisponible : verifier la connexion, le compte et l’eleve selectionne. Aucun paiement cree.';resume.disabled=false;}
   });li.append(resume);
   const remove=document.createElement('button');remove.type='button';remove.textContent='Supprimer le brouillon';remove.addEventListener('click',async()=>{try{await vault.remove(record.id);await render();}catch{state.textContent='Suppression locale indisponible.';}});li.append(remove);list.append(li);
  }
 }
 document.getElementById('unlock').addEventListener('submit',async event=>{
  event.preventDefault();const button=event.submitter;button.disabled=true;
  try{
   const current=navigator.onLine?await check():null,actor=select.value;
   if(current&&current.actor!==actor)throw Error('ACCOUNT_MISMATCH');
   settings=await vault.unlock(actor,document.getElementById('passphrase').value,current?safeContext(current):null);
   document.getElementById('passphrase').value='';document.getElementById('student').value=settings.studentId;
   document.getElementById('currency').textContent=settings.currency;document.getElementById('date').value=settings.date;
   workspace.hidden=false;document.getElementById('unlock').hidden=true;state.textContent='Espace local deverrouille.';await render();
  }catch{lock();state.textContent='Deverrouillage impossible : phrase incorrecte, stockage indisponible ou autre compte.';}
  finally{button.disabled=false;}
 });
 document.getElementById('draft').addEventListener('submit',async event=>{
  event.preventDefault();const button=event.submitter;button.disabled=true;
  try{
   const amount=document.getElementById('amount').value.trim().replace(',','.');
   if(!/^\d+(\.\d{1,2})?$/.test(amount))throw Error('INVALID_AMOUNT');
   const [whole,fraction='']=amount.split('.'),amountMinor=Number(whole)*100+Number(fraction.padEnd(2,'0'));
   if(!Number.isSafeInteger(amountMinor)||amountMinor<1||amountMinor>999999999||!settings)throw Error('INVALID_AMOUNT');
   const comment=document.getElementById('comment').value.trim(),date=document.getElementById('date').value;
   if(!comment||comment.length>250||/[\x00-\x1f\x7f]/.test(comment)||!/^\d{4}-\d{2}-\d{2}$/.test(date))throw Error('INVALID_DRAFT');
   await vault.save({actor:settings.actor,studentId:settings.studentId,amountMinor,date,comment,currency:settings.currency});
   document.getElementById('amount').value='';document.getElementById('comment').value='';state.textContent='Brouillon chiffre enregistre sur ce telephone. Aucun paiement confirme.';await render();
  }catch{state.textContent='Enregistrement impossible : verifier les champs, le deverrouillage et le stockage.';}
  finally{button.disabled=false;}
 });
 document.getElementById('lock').addEventListener('click',()=>{lock();state.textContent='Espace verrouille.';});
 document.getElementById('check').addEventListener('click',async()=>{try{const current=await check();if(current.actor!==settings?.actor){lock();throw Error('ACCOUNT_MISMATCH');}await render();state.textContent='Session verifiee. Aucun paiement envoye.';}catch{state.textContent='Session a verifier. Les brouillons restent locaux.';}});
 window.addEventListener('online',()=>{
  network();if(settings)check().then(async current=>{if(current.actor!==settings?.actor)lock();else await render();}).catch(()=>{lock();state.textContent='Session a verifier. Espace local verrouille.';});
 });window.addEventListener('offline',network);window.addEventListener('pagehide',lock);
 document.addEventListener('visibilitychange',()=>{clearTimeout(hideTimer);if(document.hidden)hideTimer=setTimeout(lock,60000);});
 (async()=>{
  network();const current=await check().catch(()=>null),actors=await vault.actors();
  if(current&&!actors.includes(current.actor))actors.unshift(current.actor);
  actors.forEach((actor,index)=>{const option=document.createElement('option');option.value=actor;option.textContent='Espace local '+(index+1)+(current?.actor===actor?' - compte connecte':'');select.append(option);});
  if(current)select.value=current.actor;
  if(!actors.length)state.textContent='Ouvrir d’abord un dossier eleve dans les paiements avec un compte habilite.';
 })().catch(()=>{state.textContent='Stockage local indisponible.';});
})();
