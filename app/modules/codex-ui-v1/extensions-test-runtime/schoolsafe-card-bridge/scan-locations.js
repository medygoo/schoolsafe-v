'use strict';
(() => {
 const root=document.getElementById('location-config'),form=document.getElementById('location-form');
 const status=document.getElementById('location-status'),list=document.getElementById('location-list');
 let busy=false;
 async function request(input) {
  const response=await fetch('scanner-relay.php?operation=location-management',{method:'POST',credentials:'same-origin',
   headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':root.dataset.csrf},body:JSON.stringify(input)});
  if(!response.ok)throw new Error('Configuration indisponible ou permission manquante.');
  const result=await response.json();
  if(!result.data||typeof result.data!=='object')throw new Error('Reponse invalide.');
  return result.data;
 }
 async function refresh() {
  if(root.dataset.read!=='1')return;
  const data=await request({action:'list'});
  if(!Array.isArray(data.locations))throw new Error('Reponse invalide.');
  list.replaceChildren();
  for(const row of data.locations) {
   const item=document.createElement('p');
   item.textContent=row.code+' : '+row.label+' ('+(row.is_active?'actif':'inactif')+') ';
   if(form) {
    const edit=document.createElement('button');edit.type='button';edit.textContent='Modifier';
    edit.addEventListener('click',()=>{
     for(const key of ['id','code','label','kind'])form.elements[key].value=row[key];
     form.elements.is_active.checked=row.is_active===true;
    });item.append(edit);
   }
   list.append(item);
  }
  status.textContent=data.locations.length?'':'Aucun lieu configure.';
 }
 if(form)form.addEventListener('submit',async(event)=>{
  event.preventDefault();if(busy||!form.reportValidity())return;busy=true;
  const button=form.querySelector('button[type=submit]');button.disabled=true;
  try {
   const input={action:'save',code:form.elements.code.value,label:form.elements.label.value,
    kind:form.elements.kind.value,is_active:form.elements.is_active.checked};
   if(form.elements.id.value)input.id=form.elements.id.value;
   await request(input);form.reset();status.textContent='Lieu enregistre.';await refresh();
  }catch(error){status.textContent=error.message;}
  finally{busy=false;button.disabled=false;}
 });
 refresh().catch(error=>{status.textContent=error.message;});
})();
