'use strict';
(() => {
 const byId=id=>document.getElementById(id),parent=document.body.dataset.parent==='true';
 const allowedActions=JSON.parse(document.body.dataset.actions||'[]');
 const native=document.body.dataset.engine==='native';
 const actions={
 'parent-activate':['Réactiver une personne que j’ai désactivée',['nativeStudentId','guardianId','reason']],
 'parent-list':['Consulter les personnes de mes enfants',['nativeStudentId']],
 'parent-suspend':['Suspendre une personne accréditée',['nativeStudentId','authorizationId','reason']],
 'parent-family-suspend':['Suspendre une personne familiale',['nativeStudentId','guardianId','reason']],
 'parent-reactivation-request':['Demander la réactivation d’une accréditation',['nativeStudentId','authorizationId','reason']],
 'parent-family-reactivation-request':['Demander la réactivation d’une personne familiale',['nativeStudentId','guardianId','reason']],
 'school-list':['Consulter les personnes d’un élève',['nativeStudentId']],
 'register-family':['Ajouter un responsable familial',['nativeStudentId','guardianType','fullName','phone','email','address','isPrimary']],
 'school-family-approve':['Valider la récupération par une personne familiale',['nativeStudentId','guardianId','reason','consent']],
 'school-retire':['Retirer une personne sur demande reçue à l’école',['nativeStudentId','guardianId','reason','consent']],
 'school-replace':['Remplacer une personne sur demande reçue à l’école',['nativeStudentId','guardianId','reason','fullName','phone','email','address','consent','photoPath']],
 'register-external':['Enregistrer une demande d’accréditation',['nativeStudentId','fullName','phone','email','address','consent','photoPath']],
 'photo-set':['Associer une photo',['nativeStudentId','guardianId','photoPath']],
 'photo-review':['Valider ou refuser une photo',['nativeStudentId','guardianId','decision','reason']],
 'validate-authorization':['Valider une accréditation',['nativeStudentId','authorizationId']],
 'grants':['Consulter les tâches de scan attribuées',[]],
 'delegate':['Attribuer ou retirer une tâche de scan',['profileId','capability','decision','reason']],
 'print-request':['Créer une demande d’impression',['nativeStudentId','format']]
 };
 // The existing native PHP consumes authorization IDs for reactivation and
 // requires a reason for an external request. Do not offer fields it discards.
 if(native) {
  actions['parent-activate'][1]=['nativeStudentId','authorizationId','reason'];
  actions['register-external'][1]=['nativeStudentId','fullName','phone','reason','consent'];
  actions['register-family'][1]=['nativeStudentId','guardianType','fullName','phone','isPrimary'];
  actions['school-family-approve'][1]=['nativeStudentId','guardianId','reason','consent','parentStaffId'];
 }
 const labels={parentStaffId:'Compte parent déjà lié à cet enfant (identifiant, facultatif)',nativeStudentId:'Identifiant élève Rosario',authorizationId:'Référence de l’accréditation',guardianId:'Référence de la personne',profileId:'Référence du profil lié',reason:'Motif',fullName:'Nom complet',phone:'Téléphone',email:'Email',address:'Adresse',consent:'Consentement du parent principal',photoPath:'Photo enregistrée',decision:'Décision',capability:'Tâche',format:'Format',guardianType:'Lien familial',isPrimary:'Désigner comme principal'};
 let csrf='',busy=false;
 const action=byId('management-action'),fields=byId('management-fields'),state=byId('management-state'),output=byId('management-result'),submit=byId('management-submit');
 for(const [key,[label]] of Object.entries(actions)){
  if((parent!==key.startsWith('parent-')&&key!=='print-request')||!allowedActions.includes(key))continue;
  const option=document.createElement('option');option.value=key;option.textContent=label;action.append(option);
 }
 function build(){fields.replaceChildren();output.replaceChildren();
  if(!actions[action.value])return;
  for(const key of actions[action.value][1]){
   const label=document.createElement('label');label.textContent=labels[key];
   let input;
   const choices=key==='capability'?{'SCAN_ENTRY':'Entrée','SCAN_PICKUP':'Récupération','SCAN_FEES':'Contrôle des frais','SCAN_VERIFY':'Vérification'}
    :key==='guardianType'?{pere:'Père',mere:'Mère',tuteur:'Tuteur'}
    :key==='format'?{badge:'Badge vertical',carte:'Carte horizontale'}
    :key==='decision'?(action.value==='delegate'?{assign:'Attribuer',revoke:'Retirer'}:{accepted:'Accepter',rejected:'Refuser'}):null;
   if(choices){input=document.createElement('select');for(const [value,text] of Object.entries(choices)){const option=document.createElement('option');option.value=value;option.textContent=text;input.append(option);}}
   else{input=document.createElement('input');input.type=key==='isPrimary'?'checkbox':key==='nativeStudentId'?'number':key==='email'?'email':'text';input.maxLength=500;if(key==='nativeStudentId')input.min='1';}
   if(native&&key==='fullName')input.maxLength=200;
   if(native&&key==='phone')input.maxLength=64;
   if(key==='parentStaffId'){input.type='number';input.min='1';input.max='2147483647';}
   input.name=key;input.required=!['phone','email','address','isPrimary','parentStaffId'].includes(key);label.append(input);fields.append(label);
  }
 }
 function paragraph(text){const p=document.createElement('p');p.textContent=text;output.append(p);}
 function show(data){output.replaceChildren();
  if(Array.isArray(data)){for(const row of data)paragraph((row.operator_name||'Profil')+' : '+row.capability+' — '+row.profile_id);if(!data.length)paragraph('Aucune tâche attribuée.');return;}
  if(Array.isArray(data.family)||Array.isArray(data.accredited)){
   for(const [group,rows] of [['Famille',data.family||[]],['Personnes accréditées',data.accredited||[]]]){
    const heading=document.createElement('h2');heading.textContent=group;output.append(heading);
    for(const row of rows){paragraph((row.name||'Personne')+(row.is_primary?' — Parent principal':'')+' — '+(row.pickup_ready?'Récupération autorisée':'Récupération non autorisée'));
     paragraph('Personne : '+row.guardian_id+(row.authorization_id?' · Accréditation : '+row.authorization_id:''));
     if(row.parent_staff_id)paragraph('Compte parent lié : '+row.parent_staff_id);
     if(row.photo_path){let url;try{url=new URL(row.photo_path,location.href);}catch(_){}if(url&&url.origin===location.origin&&['http:','https:'].includes(url.protocol)){const image=document.createElement('img');image.src=url.href;image.alt='Photo de '+(row.name||'la personne');image.style.cssText='width:100px;height:125px;object-fit:contain';image.onerror=()=>{image.remove();paragraph('Photo indisponible.');};output.append(image);}}
    }if(!rows.length)paragraph('Aucune personne enregistrée.');
   }return;
  }
  if(action.value==='register-family'){
   paragraph('Responsable familial enregistré. Récupération non autorisée.');
   if(data.is_primary===true)paragraph('Responsable principal.');
  }else paragraph(data.status==='pending'?'Demande enregistrée, en attente de validation.':data.status==='revoked'?'Autorisation retirée. Dossier et historique conservés.':data.status==='active'?'Autorisation de récupération active.':data.status==='suspended'?'Autorisation de récupération désactivée.':'Opération confirmée par le moteur.');
  if(native&&action.value==='register-external')paragraph('La photo doit encore être associée et validée avant toute récupération.');
  if(data.parent_staff_id)paragraph('Compte parent lié : '+data.parent_staff_id);
  if(data.id)paragraph('Référence : '+data.id);
  if(data.guardian_id)paragraph('Personne : '+data.guardian_id);
  if(data.authorization_id)paragraph('Accréditation : '+data.authorization_id);
 }
 const requestedAction=location.hash.slice(1);
 if(Array.from(action.options).some(option=>option.value===requestedAction))action.value=requestedAction;
 action.addEventListener('change',build);build();
 byId('management-form').addEventListener('submit',async event=>{
  event.preventDefault();if(busy||!csrf||!allowedActions.includes(action.value))return;busy=true;submit.disabled=true;action.disabled=true;state.textContent='Traitement...';
  const input={action:action.value};fields.querySelectorAll('[name]').forEach(field=>{if(field.type==='checkbox')input[field.name]=field.checked;else if(field.value)input[field.name]=field.value;});
  try{const response=await fetch('scanner-relay.php?operation=management',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':csrf},body:JSON.stringify(input)});
   const result=await response.json();if(!response.ok)throw new Error(response.status===409&&['PRIMARY_SELECTION_REQUIRED','PRIMARY_PARENT_REQUIRED','PRIMARY_TRANSFER_REQUIRED'].includes(result.error)?result.error:String(response.status));show(result.data);state.textContent='';
  }catch(error){output.replaceChildren();state.textContent=error.message==='PRIMARY_SELECTION_REQUIRED'?'Aucun responsable principal actif. Cochez « Désigner comme principal » pour enregistrer le premier responsable familial.':error.message==='PRIMARY_PARENT_REQUIRED'?'Le responsable principal doit être relié explicitement à un compte parent déjà associé à cet enfant. Faites vérifier le dossier par l’école.':error.message==='PRIMARY_TRANSFER_REQUIRED'?'La personne est déjà reliée à un autre compte parent. Une procédure de transfert est nécessaire.':error.message==='403'?'Permission ou périmètre refusé.':error.message==='409'?'Conflit ou vérification nécessaire. Rechargez le dossier avant de recommencer.':'Opération non confirmée. Vérifiez le dossier avant de recommencer.';}
  finally{busy=false;submit.disabled=false;action.disabled=false;}
 });
 fetch('scanner-relay.php?operation=capabilities',{credentials:'same-origin',cache:'no-store'}).then(async response=>{if(!response.ok)throw new Error();const data=await response.json();if(typeof data.csrfToken!=='string')throw new Error();csrf=data.csrfToken;submit.disabled=!action.options.length;state.textContent=action.options.length?'':'Aucune action autorisée.';}).catch(()=>{state.textContent='Session Rosario indisponible.';});
})();
