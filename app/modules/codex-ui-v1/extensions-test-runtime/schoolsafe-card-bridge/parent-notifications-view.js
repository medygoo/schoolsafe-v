'use strict';
(() => {
  const list=document.getElementById('notifications'),state=document.getElementById('state'),retry=document.getElementById('retry');
  const labels={'student.arrival':"Arrivée à l'école",'student.pickup':"Récupération confirmée",'student.fee_control':'Contrôle des frais','student.birthday':'Anniversaire a venir','student.top10.ecole':"Top 10 de l'ecole",'student.top10.classe':'Top 10 de sa classe'};
  labels['security.entry']="Arrivée physique enregistrée";
  labels['financial.control']='Contrôle financier — état observé';
  const balances={paid:'À jour',partial:'Partiellement payé',due:'Impayé',exempt:'Exonéré',no_data:'Données absentes'};
  labels['payment.receipt']='Recu de paiement';
  // Lot K2a (05/11) : messages de l'ecole (fil du parent) et leurs categories de filtre.
  Object.assign(labels,{'school.announcement':"Message de l'école",'homework.published':'Nouveau devoir','grade.published':'Nouvelle note',
    'absence.detected':'Absence constatée','attendance.closure':'Présences du jour','case.updated':"Suivi du dossier de l'élève",
    'security.p1':'Alerte de sécurité','fee.reminder':'Rappel de frais'});
  const groups={'school.notes':['grade.published'],'school.homework':['homework.published'],'school.absences':['absence.detected','attendance.closure'],
    'school.announcements':['school.announcement','case.updated'],'school.alerts':['security.p1'],'student.fee_control':['fee.reminder']};
  let items=[],filter='all',busy=false,loaded=false;
  const syncState=document.getElementById('pwa-sync-state');
  async function updateReadButtons(){
    if(!window.SchoolSafeOutbox)return;
    for(const button of list.querySelectorAll('[data-receipt-read]')){
      const status=await SchoolSafeOutbox.state(Number(button.dataset.receiptRead));
      if(!button.isConnected)continue;
      button.textContent={confirmed:'Lu',pending:'En attente de synchronisation',rejected:'Refuse',unread:'Marquer comme lu',unavailable:'Connexion requise'}[status];
      button.disabled=status!=='unread';
    }
  }
  function render() {
    list.replaceChildren();
    const visible=items.filter(item=>filter==='all'||item.event_type===filter||(filter==='remediation'&&item.event_type.startsWith('remediation.'))||(filter==='palmares'&&item.event_type.startsWith('student.top10.'))||(filter==='student.arrival'&&item.event_type==='security.entry')||(filter==='student.fee_control'&&item.event_type==='financial.control')||(groups[filter]||[]).includes(item.event_type));
    for(const item of visible) {
      const remediation=item.event_type.startsWith('remediation.');
      if(!labels[item.event_type]&&!remediation&&item.event_type!=='payment.receipt') continue;
      const row=document.createElement('li');row.dataset.type=item.event_type;
      const title=document.createElement('strong');title.textContent=(item.student_name?item.student_name+' : ':'')+(labels[item.event_type]||item.payload?.message||'Rattrapage');row.append(title);
      if(item.event_type==='payment.receipt') {
        const link=document.createElement('a');link.textContent='Consulter le recu';
        link.href='/schoolsafe-receipt.php?id='+item.payload.receipt_id;row.append(link);
        const status=document.createElement('p');status.textContent={valid:'Paiement enregistre',cancelled:'Paiement annule',refunded:'Paiement rembourse'}[item.payload.status];row.append(status);
        const read=document.createElement('button');read.type='button';read.dataset.receiptRead=String(item.payload.receipt_id);read.textContent='Connexion requise';read.disabled=true;
        read.addEventListener('click',async()=>{
          read.disabled=true;
          try{await SchoolSafeOutbox.markRead(item.payload.receipt_id);await updateReadButtons();}
          catch{syncState.textContent='Enregistrement local indisponible. Aucune confirmation.';await updateReadButtons();}
        });row.append(read);
      }
      if(item.event_type==='financial.control'||item.event_type==='security.entry'||(item.event_type==='student.pickup'&&item.payload?.record_id)) {
        const details=document.createElement('p');details.textContent=item.payload.message;details.style.whiteSpace='pre-wrap';row.append(details);
      }
      if(item.id.startsWith('notify:')) {
        // Lot K2a (05/11) : titre puis texte complet, toujours par textContent (aucun HTML interprete).
        if(item.payload?.title) {const t=document.createElement('p');t.textContent=item.payload.title;t.style.fontWeight='600';row.append(t);}
        if(item.payload?.message) {const m=document.createElement('p');m.textContent=item.payload.message;m.style.whiteSpace='pre-wrap';row.append(m);}
      }
      if(remediation) {const p=document.createElement('p');p.textContent=item.payload?.course||'';row.append(p);}
      if(item.event_type.startsWith('student.top10.')) {const p=document.createElement('p');p.textContent=item.payload.message+' '+item.payload.period;row.append(p);}
      if(item.event_type==='student.birthday') {const p=document.createElement('p');p.textContent=item.payload.message+' '+item.payload.birthday_date;row.append(p);}
      if(item.event_type==='student.fee_control') {const p=document.createElement('p');p.textContent=item.payload?.feeLabel||balances[item.payload?.balanceStatus]||'Statut indisponible';row.append(p);}
      const date=new Date(item.payload?.occurredAt||item.created_at);
      if(!Number.isNaN(date.getTime())) {const time=document.createElement('time');time.dateTime=date.toISOString();time.textContent=new Intl.DateTimeFormat('fr',{dateStyle:'medium',timeStyle:'short'}).format(date);row.append(time);}
      list.append(row);
    }
    state.textContent=visible.length?'':'Aucune notification dans cette catégorie.';
    void updateReadButtons();
  }
  async function load() {
    if(busy||document.hidden) return;busy=true;retry.hidden=true;
    try {
      const response=await fetch('parent-notifications.php',{credentials:'same-origin',cache:'no-store'});
      if(!response.ok) throw new Error(String(response.status));
      const result=await response.json();
      if(!Array.isArray(result.data?.notifications)) throw new Error('INVALID_RESULT');
      items=SchoolSafeNotificationData.normalizeNotifications(result.data.notifications);loaded=true;render();
      if(result.data.degradedSources?.length)state.textContent='Certaines sources sont temporairement indisponibles. Les notifications disponibles restent affichees.';
    } catch(error) {
      if(!navigator.onLine&&loaded){state.textContent='Hors connexion : notifications precedemment chargees.';return;}
      list.replaceChildren();items=[];loaded=false;
      state.textContent=['401','403'].includes(error.message)?'Connexion parent requise.':'Notifications indisponibles. Réessayez dans un instant.';retry.hidden=false;
    } finally {busy=false;}
  }
  document.querySelectorAll('[data-filter]').forEach(button=>button.addEventListener('click',()=>{
    filter=button.dataset.filter;document.querySelectorAll('[data-filter]').forEach(other=>other.setAttribute('aria-pressed',String(other===button)));if(loaded) render();
  }));
  retry.addEventListener('click',load);document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
  window.addEventListener('schoolsafe:outbox',()=>void updateReadButtons());
  if(window.SchoolSafeOutbox)SchoolSafeOutbox.ready.then(updateReadButtons);
  document.getElementById('pwa-sync').addEventListener('click',async event=>{
    event.currentTarget.disabled=true;
    try{await SchoolSafeOutbox.sync();syncState.textContent='Synchronisation terminee.';await updateReadButtons();}
    catch{syncState.textContent='Synchronisation en attente. Connexion ou session a verifier.';}
    finally{event.currentTarget.disabled=false;}
  });
  setInterval(load,15000);load();
})();
