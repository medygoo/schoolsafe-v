'use strict';
(() => {
  const list=document.getElementById('notifications'),state=document.getElementById('state'),retry=document.getElementById('retry');
  const labels={'student.arrival':"Arrivée à l'école",'student.pickup':"Récupération confirmée",'student.fee_control':'Contrôle des frais'};
  const balances={paid:'Payé',partial:'Partiellement payé',due:'Impayé',exempt:'Exonéré',no_data:'Données absentes'};
  let items=[],filter='all',busy=false,loaded=false;
  const push=window.SchoolSafeNotificationPush.createNotificationPush();
  function render() {
    list.replaceChildren();
    const visible=items.filter(item=>filter==='all'||item.event_type===filter);
    for(const item of visible) {
      if(!labels[item.event_type]) continue;
      const row=document.createElement('li');row.dataset.type=item.event_type;
      const title=document.createElement('strong');title.textContent=(item.student_name?item.student_name+' : ':'')+labels[item.event_type];row.append(title);
      if(item.event_type==='student.fee_control') {const p=document.createElement('p');p.textContent=item.payload?.feeLabel||balances[item.payload?.balanceStatus]||'Statut indisponible';row.append(p);}
      const date=new Date(item.payload?.occurredAt||item.created_at);
      if(!Number.isNaN(date.getTime())) {const time=document.createElement('time');time.dateTime=date.toISOString();time.textContent=new Intl.DateTimeFormat('fr',{dateStyle:'medium',timeStyle:'short'}).format(date);row.append(time);}
      list.append(row);
    }
    state.textContent=visible.length?'':'Aucune notification dans cette catégorie.';
  }
  async function load() {
    if(busy||document.hidden) return;busy=true;retry.hidden=true;
    try {
      const response=await fetch('parent-notifications.php',{credentials:'same-origin',cache:'no-store'});
      if(!response.ok) throw new Error(String(response.status));
      const result=await response.json();
      if(!Array.isArray(result.data?.notifications)) throw new Error('INVALID_RESULT');
       items=window.SchoolSafeNotificationData.normalizeNotifications(result.data.notifications);
       push.observe(items);loaded=true;render();
    } catch(error) {
       list.replaceChildren();items=[];loaded=false;push.reset();
      state.textContent=['401','403'].includes(error.message)?'Connexion parent requise.':'Notifications indisponibles. Réessayez dans un instant.';retry.hidden=false;
    } finally {busy=false;}
  }
  document.querySelectorAll('[data-filter]').forEach(button=>button.addEventListener('click',()=>{
    filter=button.dataset.filter;document.querySelectorAll('[data-filter]').forEach(other=>other.setAttribute('aria-pressed',String(other===button)));if(loaded) render();
  }));
  retry.addEventListener('click',load);document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
  setInterval(load,15000);load();
})();
