'use strict';
(() => {
 const byId=id=>document.getElementById(id),form=byId('scan-form'),state=byId('scanner-state'),result=byId('scan-result'),people=byId('scan-people'),confirm=byId('pickup-confirm');
 const modes={entry:['Entrée','SCAN_ENTRY'],pickup:['Récupération','SCAN_PICKUP'],fees:['Frais scolaires','SCAN_FEES'],canteen:['Frais de cantine','SCAN_CANTEEN'],verify:['Vérification','SCAN_VERIFY']};
 const names={INVALID:'QR invalide.',VALID:'Carte valide.',PRESENT:'Arrivée enregistrée.',ALREADY_PRESENT:'Arrivée déjà enregistrée.',CONFIRMED:'Remise confirmée.',PICKUP_CHOICE:'Sélectionnez la personne présente.',NEED_HUMAN_CONFIRMATION:'Vérification humaine requise.',DENIED:'Remise refusée.',LOST:'Carte perdue.',REVOKED:'Carte révoquée.',REPLACED:'Carte remplacée.',NOT_DELEGATED:'Autorisation absente.',OUT_OF_SCOPE:'Hors du périmètre autorisé.'};
 const balances={paid:'Payé',partial:'Partiellement payé',due:'Impayé',exempt:'Exonéré',no_data:'Données absentes'};
 let csrf='',mode='verify',busy=false,proof=null,campaign=null,stream=null,cameraRun=0;
 function clearPickup(){proof=null;confirm.hidden=true;byId('human-confirm').checked=false;people.replaceChildren();}
 function stopCamera(){cameraRun++;if(stream)stream.getTracks().forEach(track=>track.stop());stream=null;byId('scan-video').srcObject=null;byId('camera-area').hidden=true;}
 async function request(operation,input){
  const response=await fetch('scanner-relay.php?operation='+operation,{credentials:'same-origin',cache:'no-store',
   ...(operation==='scan'?{method:'POST',headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':csrf},body:JSON.stringify(input)}:{})});
  if(!response.ok)throw new Error(String(response.status));return response.json();
 }
 function setMode(next){if(busy)return;stopCamera();clearPickup();mode=next;result.textContent='';
  byId('location-label').hidden=!['entry','pickup'].includes(mode);byId('scan-location').required=['entry','pickup'].includes(mode);
  state.textContent=['entry','pickup'].includes(mode)&&!byId('scan-location').options.length?'Aucun lieu de scan configuré dans cette école.':'';
  byId('modes').querySelectorAll('button').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.mode===mode)));
 }
 async function scan(input){
  if(busy)return;busy=true;stopCamera();confirm.hidden=true;
  form.querySelectorAll('button,input,select').forEach(button=>button.disabled=true);
  try {
   const response=await request('scan',input),data=response.data;
   if(!data||typeof data.result!=='string')throw new Error('INVALID_RESULT');
   result.dataset.feeColor=data.result==='FEE_POLICY_STATUS'?data.color:'';
   if(data.result==='FEE_STATUS'){const who=data.student_name?data.student_name+(typeof data.class_name==='string'&&data.class_name?' ('+data.class_name+')':'')+' : ':'';const label=balances[data.balance_status]||'Statut indisponible';result.textContent=typeof data.balance==='string'?who+label+' — solde '+data.balance:who+label+' — Orienter au bureau, controle a verifier';}else result.textContent=data.result==='FEE_POLICY_STATUS'?data.label:(data.student_name?data.student_name+(typeof data.class_name==='string'&&data.class_name?' ('+data.class_name+')':'')+' : ':'')+(data.result==='CANTEEN_STATUS'?data.label:(names[data.result]||'Résultat du moteur : '+data.result))+(data.late===true?' (retard)':'');
   if(typeof data.photo_url==='string'&&data.photo_url){
    let photo;try{photo=new URL(data.photo_url,location.href);}catch(_){}
    if(photo&&photo.origin===location.origin&&['http:','https:'].includes(photo.protocol)){
     const image=document.createElement('img');image.className='scan-student-photo';image.alt='Photo de '+(data.student_name||'l’élève');image.width=96;image.height=128;image.style.objectFit='contain';image.referrerPolicy='same-origin';image.onerror=()=>image.remove();image.src=photo.href;result.prepend(image);
    }
   }
   proof=null;if(data.result!=='NEED_HUMAN_CONFIRMATION')people.replaceChildren();byId('human-confirm').checked=false;
   if(data.result==='PICKUP_CHOICE'&&Array.isArray(data.people))renderPeople(data.people,input);
   if(data.result==='NEED_HUMAN_CONFIRMATION'&&typeof data.proof_token==='string'){
    proof={...input,proof_token:data.proof_token,human_confirmed:true};confirm.hidden=false;
   }
  }catch(error){clearPickup();result.textContent=['401','403'].includes(error.message)?'Connexion ou autorisation requise.':'Scan indisponible. Aucune confirmation reçue.';}
  finally{busy=false;form.querySelectorAll('button,input,select').forEach(button=>button.disabled=false);}
 }
 function enlargePhoto(src,alt){const overlay=document.createElement('div');overlay.className='scan-photo-overlay';overlay.setAttribute('role','dialog');overlay.setAttribute('aria-label',alt||'Photo agrandie');overlay.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.8);display:flex;align-items:center;justify-content:center;z-index:9999';const big=document.createElement('img');big.src=src;big.alt=alt||'';big.style.cssText='max-width:92vw;max-height:92vh;object-fit:contain';overlay.append(big);overlay.addEventListener('click',()=>overlay.remove());document.body.append(overlay);}
 function renderPeople(rows,input){for(const person of rows){
  const article=document.createElement('article');article.className='scan-person';
  const title=document.createElement('strong');title.textContent=person.name||'Personne';article.append(title);
  const roleLine=typeof person.role==='string'&&person.role?person.role:'';
  const status=document.createElement('p');status.textContent=(roleLine?roleLine+' — ':'')+'Photo indisponible ou non validée.';article.append(status);
  const choose=document.createElement('button');choose.type='button';choose.textContent='Choisir cette personne';choose.disabled=true;article.append(choose);
  if(typeof person.photo_path==='string'&&person.photo_accepted===true&&person.pickup_ready===true){
   let url;try{url=new URL(person.photo_path,location.href);}catch(_){}
   if(url&&url.origin===location.origin&&['http:','https:'].includes(url.protocol)){
    const image=document.createElement('img');image.alt='Photo de '+(person.name||'la personne');image.referrerPolicy='same-origin';
    image.style.cursor='zoom-in';
    image.addEventListener('click',()=>{if(!choose.disabled)enlargePhoto(image.src,image.alt);});
    image.onload=()=>{if(image.naturalWidth>0){choose.disabled=false;status.textContent=(roleLine?roleLine+' — ':'')+'Photo validée.';}};
    image.onerror=()=>{choose.disabled=true;status.textContent='Photo impossible à afficher.';};image.src=url.href;article.prepend(image);
   }
  }
  choose.addEventListener('click',()=>{if(!choose.disabled&&!busy){people.replaceChildren(article);choose.disabled=true;scan({...input,authorized_person_id:person.id});}});people.append(article);
 }}
 async function initialize(){
  byId('scanner-retry').hidden=true;form.hidden=true;clearPickup();stopCamera();
  try{const caps=await request('capabilities');csrf=caps.csrfToken;const data=caps.data;
   if(typeof csrf!=='string'||!Array.isArray(data?.capabilities))throw new Error('INVALID_RESULT');campaign=data.open_campaign_id||null;
   byId('scan-location').replaceChildren();
   let locationsReady=false;
   if(data.capabilities.some(capability=>['SCAN_ENTRY','SCAN_PICKUP'].includes(capability))){
    try{
     const locations=await request('locations');
     if(!Array.isArray(locations.data?.locations))throw new Error('INVALID_RESULT');
     for(const row of locations.data.locations){
      if(typeof row?.id!=='string'||typeof row.label!=='string')throw new Error('INVALID_RESULT');
      const option=document.createElement('option');option.value=row.id;option.textContent=row.label;byId('scan-location').append(option);
     }
     locationsReady=byId('scan-location').options.length>0;
    }catch(_){byId('scan-location').replaceChildren();}
   }
   byId('modes').replaceChildren();let first=null;
   for(const [key,[label,capability]] of Object.entries(modes)){const button=document.createElement('button');button.type='button';button.textContent=label;button.dataset.mode=key;button.disabled=!data.capabilities.includes(capability)||(key==='fees'&&data.fee_policy_active!==true)||(['entry','pickup'].includes(key)&&!locationsReady);if(key==='fees'&&data.fee_policy_active!==true)button.title='Aucun barème actif';if(['entry','pickup'].includes(key)&&!locationsReady)button.title='Lieux de scan indisponibles';button.setAttribute('aria-pressed','false');button.addEventListener('click',()=>setMode(key));byId('modes').append(button);if(!button.disabled&&!first)first=key;}
   if(!first){state.textContent=data.capabilities.some(capability=>['SCAN_ENTRY','SCAN_PICKUP'].includes(capability))?'Aucun lieu de scan disponible dans cette école.':'Aucune tâche de scan attribuée.';byId('scanner-retry').hidden=false;return;}
   const requestedMode=location.hash.slice(1);
   const requestedButton=Array.from(byId('modes').querySelectorAll('button')).find(button=>button.dataset.mode===requestedMode&&!button.disabled);
   setMode(requestedButton?requestedMode:first);form.hidden=false;
  }catch(error){state.textContent=['401','403'].includes(error.message)?'Connexion ou autorisation School Safe requise.':'Service de scan School Safe indisponible. Réessayez ou contactez l’administration.';byId('scanner-retry').hidden=false;}
 }
 form.addEventListener('submit',event=>{event.preventDefault();clearPickup();const input={mode,qr_payload:byId('scan-qr').value.trim()};
  if(['entry','pickup'].includes(mode))input.location_id=byId('scan-location').value;scan(input);
 });
 confirm.addEventListener('submit',event=>{event.preventDefault();if(proof&&byId('human-confirm').checked)scan(proof);});
 byId('camera').addEventListener('click',async()=>{
  if(busy)return;if(!('BarcodeDetector'in window)){state.textContent='Caméra QR non prise en charge. Utilisez le lecteur externe.';return;}
  stopCamera();const run=cameraRun;
  try{const acquired=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});
   if(run!==cameraRun){acquired.getTracks().forEach(track=>track.stop());return;}stream=acquired;const video=byId('scan-video');video.srcObject=stream;byId('camera-area').hidden=false;await video.play();
   const detector=new BarcodeDetector({formats:['qr_code']});
   const detect=async()=>{if(run!==cameraRun)return;try{const codes=await detector.detect(video);if(codes[0]){byId('scan-qr').value=codes[0].rawValue;stopCamera();form.requestSubmit();return;}}catch(_){stopCamera();state.textContent='Lecture caméra indisponible.';return;}if(run===cameraRun)setTimeout(detect,250);};detect();
  }catch(_){stopCamera();state.textContent='Accès caméra indisponible.';}
 });
 byId('camera-stop').addEventListener('click',stopCamera);byId('scanner-retry').addEventListener('click',initialize);
 byId('scan-qr').addEventListener('input',clearPickup);byId('scan-location').addEventListener('change',clearPickup);
 window.addEventListener('pagehide',stopCamera);document.addEventListener('visibilitychange',()=>{if(document.hidden)stopCamera();});initialize();
})();
