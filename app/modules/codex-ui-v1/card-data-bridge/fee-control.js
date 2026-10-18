'use strict';
(()=>{
 const form=document.getElementById('fee-model-form'),list=document.getElementById('fee-models'),state=document.getElementById('fee-state'),save=document.getElementById('fee-save'),stop=document.getElementById('fee-stop');
 let csrf='',busy=false;
 async function send(input){if(busy)return;busy=true;save.disabled=true;stop.disabled=true;list.querySelectorAll('button').forEach(b=>b.disabled=true);
  try{const response=await fetch('scanner-relay.php?operation=fees',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':csrf},body:JSON.stringify(input)});
   const body=await response.json();if(!response.ok||!Array.isArray(body.data))throw new Error();render(body.data);state.textContent='';
  }catch(_){state.textContent='Opération non confirmée. Vérifiez le contrôle avant de recommencer.';}finally{busy=false;save.disabled=false;stop.disabled=false;list.querySelectorAll('button').forEach(b=>b.disabled=false);}}
 function render(rows){list.replaceChildren();for(const row of rows){const section=document.createElement('section'),text=document.createElement('p');text.textContent=row.label+' — minimum '+row.threshold+' — tolérance '+row.tolerance+' % — '+(row.active?'Actif':'Inactif');section.append(text);
  if(!row.active){const button=document.createElement('button');button.type='button';button.textContent='Activer ce barème';button.addEventListener('click',()=>send({action:'activate',id:row.id}));section.append(button);}list.append(section);}if(!rows.length)list.textContent='Aucun barème enregistré.';}
 form.addEventListener('submit',e=>{e.preventDefault();send({action:'save',label:form.elements.label.value.trim(),threshold:form.elements.threshold.value,tolerance:Number(form.elements.tolerance.value)});});
 stop.addEventListener('click',()=>send({action:'stop'}));
 fetch('scanner-relay.php?operation=capabilities',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json()).then(body=>{if(typeof body.csrfToken!=='string')throw new Error();csrf=body.csrfToken;return send({action:'list'});}).catch(()=>{state.textContent='Connexion et permissions requises.';});
})();
