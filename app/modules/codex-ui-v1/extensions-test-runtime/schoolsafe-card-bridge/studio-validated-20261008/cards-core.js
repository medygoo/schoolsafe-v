// ── SS CARDS STUDIO — DATA & HELPERS ──
(function(){
  const PATG={
    'Animaux de la RDC 🇨🇩':[['rdc-okapi','Okapi'],['rdc-bonobo','Bonobo'],['rdc-gorille','Gorille de montagne'],['rdc-paon-congo','Paon du Congo'],['rdc-elephant','Éléphant de forêt'],['rdc-hippopotame','Hippopotame'],['rdc-crocodile','Crocodile du Nil'],['rdc-leopard','Léopard'],['rdc-lion','Lion'],['rdc-perroquet-gris','Perroquet gris'],['rdc-chimpanze','Chimpanzé'],['rdc-bongo','Bongo']],
    'Pierres & minerais 💎':[['min-diamant','Diamant'],['min-or','Or'],['min-cuivre','Cuivre'],['min-cobalt','Cobalt'],['min-coltan','Coltan'],['min-cassiterite','Cassitérite'],['min-malachite','Malachite'],['min-tourmaline','Tourmaline'],['min-amethyste','Améthyste'],['min-wolframite','Wolframite'],['min-heterogenite','Hétérogénite'],['min-quartz','Quartz']],
    'Animaux aquatiques 🌊':[['aqua-orque','Orque'],['aqua-narval','Narval'],['aqua-beluga','Béluga'],['aqua-morse','Morse'],['aqua-loutre','Loutre de mer'],['aqua-saumon','Saumon'],['aqua-pieuvre','Pieuvre géante'],['aqua-crabe-royal','Crabe royal'],['aqua-homard','Homard'],['aqua-baleine-grise','Baleine grise'],['aqua-otarie','Otarie'],['aqua-phoque','Phoque']],
    'Animaux terrestres 🐾':[['terre-kangourou','Kangourou'],['terre-koala','Koala'],['terre-panda','Panda'],['terre-ours-polaire','Ours polaire'],['terre-jaguar','Jaguar'],['terre-puma','Puma'],['terre-bison','Bison'],['terre-grizzly','Grizzly'],['terre-lama','Lama'],['terre-alpaga','Alpaga'],['terre-paresseux','Paresseux'],['terre-capybara','Capybara']],
    'Oiseaux 🦜':[['ois-toucan','Toucan'],['ois-ara-rouge','Ara rouge'],['ois-kiwi','Kiwi'],['ois-cacatoes','Cacatoès'],['ois-manchot','Manchot'],['ois-aigle','Aigle'],['ois-colibri','Colibri'],['ois-harfang','Harfang'],['ois-macareux','Macareux'],['ois-condor','Condor'],['ois-oiseau-paradis','Oiseau de paradis'],['ois-cardinal','Cardinal rouge']]
  };
  const PATS=[];
  Object.values(PATG).forEach(arr=>arr.forEach(([v,n])=>PATS.push({v,n})));
  window._ssCARDS={
    PATG, PATS,
    FAMS:[['A','Arc-en-ciel'],['B','Océan ludique'],['C','Pop Bento'],['D','Prestige Or'],['E','Ciel rêveur'],['F',"Cahier d'écolier"],['G','Jungle Safari'],['H','Espace Galaxie'],['I','Bonbons Pastel'],['J','Tableau & Craie'],['L','Le Sage — charte de l\'école']],
    FGRAD:{A:'linear-gradient(90deg,#0d2b7a,#e8a91d)',B:'linear-gradient(90deg,#075e63,#ff8552)',C:'linear-gradient(90deg,#312e81,#facc15)',D:'linear-gradient(90deg,#0a0f2e,#c9a030)',E:'linear-gradient(90deg,#4f8ff7,#fbbf24)',F:'linear-gradient(90deg,#c2410c,#60a5fa)',G:'linear-gradient(90deg,#0f3d24,#f6a71d)',H:'linear-gradient(90deg,#141238,#818cf8)',I:'linear-gradient(90deg,#db2777,#f9a8d4)',J:'linear-gradient(90deg,#1f3a2d,#facc15)',L:'linear-gradient(90deg,#1a2228,#c09018)'},
    CCOLS:[
      {cc:'#d63384',soft:'#fce4f1',dark:'#8f1f57'},
      {cc:'#e8590c',soft:'#ffe8d9',dark:'#9c3a06'},
      {cc:'#7b5cd6',soft:'#ece5fb',dark:'#4f3592'},
      {cc:'#1446aa',soft:'#dbe7fb',dark:'#0d2f77'},
      {cc:'#08825a',soft:'#d7f3e7',dark:'#05543a'},
      {cc:'#b8860b',soft:'#fdf3d1',dark:'#7a5a05'},
      {cc:'#0f7f8f',soft:'#d5f2f5',dark:'#0a545f'},
      {cc:'#c22f2f',soft:'#fde2e2',dark:'#7e1d1d'},
      {cc:'#3d3f9e',soft:'#e3e4fa',dark:'#26276b'},
      {cc:'#0d2b7a',soft:'#dde5f8',dark:'#081b4e'}
    ],
    // 4 variantes de couleur par famille (accent --ss-cc)
    FVARS:{
      A:[{cc:'#1446aa',soft:'#dbe7fb',dark:'#0d2f77',l:'Saphir'},{cc:'#7b2d8b',soft:'#f3e4f9',dark:'#4f1a5e',l:'Mauve'},{cc:'#0f7f8f',soft:'#d5f2f5',dark:'#0a545f',l:'Lagon'},{cc:'#1a6b3a',soft:'#d4f5e2',dark:'#0f4024',l:'Forêt'}],
      B:[{cc:'#0d9488',soft:'#ccfbf1',dark:'#0a6b5e',l:'Turquoise'},{cc:'#2563c4',soft:'#dbe9ff',dark:'#1845a0',l:'Azur'},{cc:'#1a6b3a',soft:'#d4f5e2',dark:'#0f4024',l:'Forêt'},{cc:'#7b2d8b',soft:'#f3e4f9',dark:'#4f1a5e',l:'Violet'}],
      C:[{cc:'#4338ca',soft:'#ede9ff',dark:'#2c258f',l:'Iris'},{cc:'#7c3aed',soft:'#ede9fe',dark:'#5b21b6',l:'Améthyste'},{cc:'#db2777',soft:'#fde7f2',dark:'#9d1b55',l:'Cerise'},{cc:'#0f7f8f',soft:'#d5f2f5',dark:'#0a545f',l:'Lagon'}],
      D:[{cc:'#c9a030',soft:'#fef3d0',dark:'#8a6b15',l:'Or'},{cc:'#b8860b',soft:'#fdf3d1',dark:'#7a5a05',l:'Ambre'},{cc:'#d97706',soft:'#fef3c7',dark:'#92500a',l:'Bronze'},{cc:'#c2410c',soft:'#ffe8d9',dark:'#8a2f0a',l:'Cuivre'}],
      E:[{cc:'#2563c4',soft:'#dbe9ff',dark:'#1845a0',l:'Azur'},{cc:'#4f8ff7',soft:'#e0ecff',dark:'#2c5cc4',l:'Ciel'},{cc:'#7c3aed',soft:'#ede9fe',dark:'#5b21b6',l:'Iris'},{cc:'#0f7f8f',soft:'#d5f2f5',dark:'#0a545f',l:'Lagon'}],
      F:[{cc:'#c2410c',soft:'#ffe8d9',dark:'#8a2f0a',l:'Brique'},{cc:'#b8860b',soft:'#fdf3d1',dark:'#7a5a05',l:'Ocre'},{cc:'#1446aa',soft:'#dbe7fb',dark:'#0d2f77',l:'Encre'},{cc:'#0f7f8f',soft:'#d5f2f5',dark:'#0a545f',l:'Ardoise'}],
      G:[{cc:'#1a6b3a',soft:'#d4f5e2',dark:'#0f4024',l:'Forêt'},{cc:'#0f7f8f',soft:'#d5f2f5',dark:'#0a545f',l:'Savane'},{cc:'#b8860b',soft:'#fdf3d1',dark:'#7a5a05',l:'Sahara'},{cc:'#d97706',soft:'#fef3c7',dark:'#92500a',l:'Soleil'}],
      H:[{cc:'#818cf8',soft:'#eef2ff',dark:'#4f46e5',l:'Nébuleuse'},{cc:'#7c3aed',soft:'#ede9fe',dark:'#5b21b6',l:'Cosmos'},{cc:'#0d9488',soft:'#ccfbf1',dark:'#0a6b5e',l:'Aurore'},{cc:'#f59e0b',soft:'#fef3c7',dark:'#92500a',l:'Étoile'}],
      I:[{cc:'#db2777',soft:'#fde7f2',dark:'#9d1b55',l:'Framboise'},{cc:'#c026d3',soft:'#fdf4ff',dark:'#86198f',l:'Bonbon'},{cc:'#f59e0b',soft:'#fef3c7',dark:'#92500a',l:'Pêche'},{cc:'#7c3aed',soft:'#ede9fe',dark:'#5b21b6',l:'Lavande'}],
      J:[{cc:'#2c5642',soft:'#d4f0e5',dark:'#1a3828',l:'Mousse'},{cc:'#b8860b',soft:'#fdf3d1',dark:'#7a5a05',l:'Craie'},{cc:'#1446aa',soft:'#dbe7fb',dark:'#0d2f77',l:'Encre'},{cc:'#c22f2f',soft:'#fde2e2',dark:'#7e1d1d',l:'Brique'}],
      L:[{cc:'#556777',soft:'#dadee2',dark:'#1a2228',l:'Le mur'},{cc:'#657786',soft:'#f0f1f2',dark:'#1a2228',l:'Mur clair'},{cc:'#7a5a0d',soft:'#fae6b8',dark:'#1a2228',l:'Or de l\'emblème'},{cc:'#1a2228',soft:'#dadee2',dark:'#1a2228',l:'Encre'}]
    },
    // Palette 12 couleurs pour personnalisation par classe
    CARDPAL:[
      {cc:'#1446aa',soft:'#dbe7fb',dark:'#0d2f77',l:'Saphir'},
      {cc:'#7b2d8b',soft:'#f3e4f9',dark:'#4f1a5e',l:'Mauve'},
      {cc:'#0f7f8f',soft:'#d5f2f5',dark:'#0a545f',l:'Lagon'},
      {cc:'#08825a',soft:'#d7f3e7',dark:'#05543a',l:'Émeraude'},
      {cc:'#b8860b',soft:'#fdf3d1',dark:'#7a5a05',l:'Ambre'},
      {cc:'#c22f2f',soft:'#fde2e2',dark:'#7e1d1d',l:'Rubis'},
      {cc:'#d63384',soft:'#fce4f1',dark:'#8f1f57',l:'Rose'},
      {cc:'#e8590c',soft:'#ffe8d9',dark:'#9c3a06',l:'Corail'},
      {cc:'#3d3f9e',soft:'#e3e4fa',dark:'#26276b',l:'Indigo'},
      {cc:'#0d2b7a',soft:'#dde5f8',dark:'#081b4e',l:'Marine'},
      {cc:'#1a6b3a',soft:'#d4f5e2',dark:'#0f4024',l:'Forêt'},
      {cc:'#7b5cd6',soft:'#ece5fb',dark:'#4f3592',l:'Lavande'}
    ]
  };
})();

// JSON arguments embedded in double-quoted HTML event attributes.
window.ssCardArg = value => esc(JSON.stringify(value)).replace(/"/g, '&quot;');
window.ssGetClassPat=function(cl,which){
  if(cl&&cl.card_pat&&cl.card_pat!=='auto'){
    const found=(window._ssCARDS.PATS||[]).find(p=>p.v===cl.card_pat);
    if(found) return found;
  }
  return ssGetPat(which);
};

window.ssClassType=function(cl){
  if(!cl) return {type:'badge',colorIdx:3,col:null};
  const cycle=cl.cycle||'', name=(cl.name||'').toLowerCase();
  let type='badge', colorIdx=3;
  if(cycle==='maternelle'){
    let ci=2;
    if(name.includes('petite'))ci=0;
    else if(name.includes('moyenne'))ci=1;
    type='badge'; colorIdx=ci;
  } else if(cycle==='humanites'||cycle==='secondaire'){
    type='carte'; colorIdx=9;
  } else {
    if(/\b5[eè°]/.test(name)||name.startsWith('5')){type='carte';colorIdx=7;}
    else if(/\b6[eè°]/.test(name)||name.startsWith('6')){type='carte';colorIdx=8;}
    else if(/\b1[eè°]/.test(name)||name.startsWith('1')){type='badge';colorIdx=3;}
    else if(/\b2[eè°]/.test(name)||name.startsWith('2')){type='badge';colorIdx=4;}
    else if(/\b3[eè°]/.test(name)||name.startsWith('3')){type='badge';colorIdx=5;}
    else if(/\b4[eè°]/.test(name)||name.startsWith('4')){type='badge';colorIdx=6;}
  }
  // Couleur personnalisée par classe (écrase le CCOLS par défaut)
  const col=cl.card_color?{cc:cl.card_color,soft:cl.card_color_soft||'#dbe7fb',dark:cl.card_color_dark||'#0d2f77'}:null;
  return {type,colorIdx,col};
};

window.ssGetPat=function(which){
  const {PATS,FAMS}=window._ssCARDS;
  const fIdx=Math.max(0,FAMS.findIndex(([id])=>id===(window._ssCardFam||'L')));
  const cIdx=window._ssColorIdx||3;
  const sel=document.getElementById(which==='badge'?'ss-pat-badge':'ss-pat-carte');
  const v=sel?sel.value:'auto';
  if(v==='auto'||!v)return PATS[(fIdx*13+cIdx*7+(which==='carte'?31:0))%PATS.length];
  return PATS.find(p=>p.v===v)||PATS[0];
};

window.ssPatEl=function(p,size,center){
  const px=p.v.startsWith('min-')?'PIERRE PRÉCIEUSE · ':'';
  return `<div class="pat${center?' center':''}"><img style="width:${size}px;height:${size}px" src="patrimoine/${esc(p.v)}.png" alt="${esc(p.n)}" onerror="this.style.visibility='hidden'"><span class="nm2">${esc((px+p.n).toUpperCase())}</span></div>`;
};

window.ssVeil=function(p,show){
  if(!show)return '';
  const sl=p.v, pos=sl.startsWith('min-')?'center':'center 12%', sz=sl.startsWith('min-')?'cover':'160%';
  return `<div class="patveil active" style="background-image:url('patrimoine/${esc(sl)}.png');background-position:${pos};background-size:${sz}"></div>`;
};

window.ssBuildBadge=function(s,cl,teacher,year,patB,patStyle){
  // patStyle: 'vignette' | 'fond' | 'both'
  const showFond=patStyle==='fond'||patStyle==='both';
  const showVig=patStyle==='vignette'||patStyle==='both';
  const sc=window._schoolInfo(), logo=window.SCHOOL_LOGO||window.SCHOOL_LOGO_DOC;
  const snm=sc.name||"NOM DE L'ÉCOLE", slg=sc.slogan||sc.sub||'', clNm=cl?cl.name:'—';
  const dob=s.dob?s.dob.split('-').reverse().join('/'):'—';
  const tchr=teacher?teacher.name:'—';
  const addr=sc.address||'', phone=sc.phone||'—', email=sc.email||'—', site=sc.website||sc.site||'';
  const par=(DB.users||[]).find(u=>u.id===s.pid);
  const parNm=par?par.name:(s.nom_papa||s.nom_maman||'—'), parPh=par?par.phone||'—':'—';
  const ap=(DB.aps||[]).filter(a=>a.sid===s.id&&a.active)[0];
  const apNm=ap?ap.name:'—', apPh=ap?ap.phone||'—':'—';
  const mat=s.mat||s.id||'000';
  const pts=(s.name||'').trim().split(/\s+/);
  const n1=pts[0]||'—', n2=pts[1]||'', n3=pts.slice(2).join(' ')||'';
  const sz=snm.length>32?'11px':snm.length>22?'13px':'15.5px';
  const logoH=logo?`<div class="logo-slot" style="border:none;background:transparent;padding:0"><img src="${logo}" alt=""></div>`:'<div class="logo-slot"><span style="font-size:15px">🏫</span><span>LOGO<br>ÉCOLE</span></div>';
  const px=patB.v.startsWith('min-')?'PIERRE PRÉCIEUSE · ':'';
  // Vignette ou fond ou les deux
  const vigEl=showVig?ssPatEl(patB,52,true):'';
  const nmEl=`<div class="pat center" style="margin:0"><span class="nm2">${esc((px+patB.n).toUpperCase())}</span></div>`;
  const bottomEl=showVig?vigEl:(showFond?nmEl:'');
  // Overlay recto: hors du bodyz (flex), position absolue relative à .art (340×540) — immunisé
  // contre le sélecteur .patveil.active~* et contre l'écrasement des margins par styles inline
  const rectoOverlay=bottomEl?`<div style="position:absolute;bottom:58px;left:50%;transform:translateX(-50%);z-index:10;pointer-events:none">${bottomEl}</div>`:'';

  const recto=`<div class="art badge" id="ss-br">
  <div class="face-tag">RECTO — BADGE VERTICAL</div>
  <div class="head">
    <div class="arc"><i></i><i></i><i></i><i></i><i></i></div>
    <div class="head-row">${logoH}<div class="school"><div class="rdc">Rép. Démocratique du Congo</div><div class="nm" style="font-size:${sz}">${esc(snm)}</div>${slg?`<div class="slg">${esc(slg)}</div>`:''}</div><div class="flag"></div></div>
    <div class="rolebar">BADGE ÉLÈVE</div>
  </div>
  <div class="bodyz">
    ${showFond?ssVeil(patB,true):''}
    <div class="brow">
      <div class="pcol">
        <div class="photo-wrap">
          <div class="photo">${s.photo?`<img src="${esc(s.photo)}" alt="">`:'<div class="ph"><svg viewBox="0 0 100 100"><circle cx="50" cy="36" r="17" fill="#a9b6d3"/><path d="M18 88c4-20 17-30 32-30s28 10 32 30z" fill="#a9b6d3"/></svg></div>'}</div>
        </div>
        <div class="qr-side" id="ss-qr-br"></div>
        <b class="scan">SCAN ENTRÉE / SORTIE</b>
      </div>
      <div class="fcol">
        <div class="frw"><div class="ic" style="background:#d5f2f5;color:#0f7f8f">👤</div><div style="flex:1;min-width:0"><div class="k">Nom</div><div class="v">${esc(n1)}</div></div></div>
        <div class="frw"><div class="ic" style="background:#ffe8d9;color:#e8590c">👤</div><div style="flex:1;min-width:0"><div class="k">Post-nom</div><div class="v">${esc(n2||'—')}</div></div></div>
        <div class="frw"><div class="ic" style="background:#ece5fb;color:#7b5cd6">👤</div><div style="flex:1;min-width:0"><div class="k">Prénom</div><div class="v">${esc(n3||'—')}</div></div></div>
        <div class="frw"><div class="ic" style="background:#dadee2;color:#556777">🎫</div><div style="flex:1;min-width:0"><div class="k">Matricule</div><div class="v">${esc(s.mat||'—')}</div></div></div>
        <div class="frw"><div class="ic" style="background:#fdf3d1;color:#b8860b">🏫</div><div style="flex:1;min-width:0"><div class="k">Classe</div><div class="v">${esc(clNm)}</div></div></div>
        <div class="frw"><div class="ic" style="background:#fce4f1;color:#d63384">🎂</div><div style="flex:1;min-width:0"><div class="k">Date de naissance</div><div class="v">${esc(dob)}</div></div></div>
        <div class="frw"><div class="ic" style="background:#d7f3e7;color:#08825a">📚</div><div style="flex:1;min-width:0"><div class="k">Année scolaire</div><div class="v">${esc(year)}</div></div></div>
        <div class="frw"><div class="ic" style="background:#ffe9f1;color:#d6336c">🍎</div><div style="flex:1;min-width:0"><div class="k">Enseignant(e)</div><div class="v">${esc(tchr)}</div></div></div>
      </div>
    </div>
    <span class="deco" style="top:4px;right:8px;font-size:20px;transform:rotate(10deg)">A</span>
    <span class="deco" style="top:30px;right:26px;font-size:13px;transform:rotate(-14deg)">b</span>
    <span class="deco" style="bottom:46px;left:8px;font-size:17px;transform:rotate(8deg)">c</span>
    <div class="foot" style="position:absolute;bottom:0;left:-18px;right:-18px;z-index:20"><div style="height:22px;display:flex;align-items:center;padding:0 4px"><span style="font-size:10px;font-weight:900;letter-spacing:.5px">SchoolSafe</span></div><div><div class="t1">Sécurisé par SchoolSafe</div><div class="t2">un enfant protégé, un parent informé</div></div><span style="margin-left:auto;background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.3);border-radius:999px;padding:3px 10px;font-family:'Baloo 2',cursive;font-size:10px;font-weight:900;letter-spacing:.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px">${esc(clNm)}</span></div>
  </div>
  ${rectoOverlay}
</div>`;

  const verso=`<div class="art badge verso" id="ss-bv">
  <div class="face-tag">VERSO — BADGE VERTICAL</div>
  <div class="head" style="padding-bottom:16px">
    <div class="arc"><i></i><i></i><i></i><i></i><i></i></div>
    <div class="head-row">${logoH}<div class="school"><div class="rdc">Badge élève — Verso</div><div class="nm" style="font-size:${sz}">${esc(snm)}</div></div><div class="flag"></div></div>
  </div>
  <div class="bodyz">
    ${showFond?ssVeil(patB,true):''}
    <div class="qr-big" id="ss-qr-bv"></div>
    <div class="qr-cap">Scan entrée / sortie · Matricule <b>${esc(s.mat||'—')}</b></div>
    <div class="rows">
      <div class="rw"><div class="ic" style="background:#d5f2f5;color:#0f7f8f">👨‍👩‍👧</div><div class="grow"><div class="k">Parent / Tuteur</div><div class="v">${esc(parNm)}</div></div><div><div class="k">Téléphone</div><div class="v">${esc(parPh)}</div></div></div>
      <div class="rw"><div class="ic" style="background:#ece5fb;color:#7b5cd6">🛡️</div><div class="grow"><div class="k">Personne autorisée</div><div class="v">${esc(apNm)}</div></div><div><div class="k">Téléphone</div><div class="v">${esc(apPh)}</div></div></div>
      ${addr?`<div class="rw"><div class="ic" style="background:#ffe8d9;color:#e8590c">📍</div><div class="grow"><div class="k">Adresse de l'école</div><div class="v">${esc(addr)}</div></div></div>`:''}
      <div class="rw"><div class="ic" style="background:#dadee2;color:#556777">📞</div><div class="grow"><div class="k">N° Direction</div><div class="v">${esc(phone)}</div></div><div><div class="k">Email</div><div class="v">${esc(email)}</div></div></div>
      ${site?`<div class="rw"><div class="ic" style="background:#fdf3d1;color:#b8860b">🌐</div><div class="grow"><div class="k">Site web</div><div class="v">${esc(site)}</div></div></div>`:''}
    </div>
    ${showVig?`<div style="margin-top:auto">${ssPatEl(patB,40,true)}</div>`:(showFond?`<div style="margin-top:auto">${nmEl}</div>`:'')}
    <div class="sec-note"><b>🛡️ Note de sécurité :</b> En cas de perte ou d'urgence, contactez immédiatement la direction de l'école.</div>
    <div class="sign-row"><div class="sg"><div class="line"></div><b>SIGNATURE</b></div><div class="stamp">SCEAU<br>DE<br>L'ÉCOLE</div><div class="sg"><div class="line"></div><b>DIRECTEUR(TRICE)</b></div></div>
    <div class="foot" style="margin:auto -18px -12px"><div style="height:22px;display:flex;align-items:center;padding:0 4px"><span style="font-size:10px;font-weight:900">SchoolSafe</span></div><div><div class="t1">Sécurisé par SchoolSafe</div><div class="t2">un enfant protégé, un parent informé</div></div></div>
  </div>
</div>`;
  return {recto,verso,qr:'schoolsafe://student/'+mat};
};

window.ssBuildCarte=function(s,cl,teacher,year,patC,patStyle){
  // patStyle: 'vignette' | 'fond' | 'both'
  const showFond=patStyle==='fond'||patStyle==='both';
  const showVig=patStyle==='vignette'||patStyle==='both';
  const sc=window._schoolInfo(), logo=window.SCHOOL_LOGO||window.SCHOOL_LOGO_DOC;
  const snm=sc.name||"NOM DE L'ÉCOLE", slg=sc.slogan||sc.sub||'', clNm=cl?cl.name:'—';
  const addr=sc.address||"Adresse de l'école", phone=sc.phone||'—', email=sc.email||'—', site=sc.website||sc.site||'';
  const dob=s.dob?s.dob.split('-').reverse().join('/'):'—', tchr=teacher?teacher.name:'—', opt=cl?cl.option||'':'';
  const mat=s.mat||s.id||'000';
  const par=(DB.users||[]).find(u=>u.id===s.pid);
  const parNm=par?par.name:(s.nom_papa||s.nom_maman||'—'), parPh=par?par.phone||'—':'—';
  const ap=(DB.aps||[]).filter(a=>a.sid===s.id&&a.active)[0];
  const apNm=ap?ap.name:'—', apPh=ap?ap.phone||'—':'—';
  const pts=(s.name||'').trim().split(/\s+/);
  const dispNm=pts.slice(0,2).join(' '), fn=pts.slice(2).join(' ')||'';
  const sz=snm.length>32?'11px':snm.length>22?'13px':'17px';
  const logoH=logo?`<div class="logo-slot" style="border:none;background:transparent;padding:0"><img src="${logo}" alt=""></div>`:'<div class="logo-slot"><span style="font-size:15px">🏫</span><span>LOGO<br>ÉCOLE</span></div>';
  const px=patC.v.startsWith('min-')?'PIERRE PRÉCIEUSE · ':'';
  const nmEl=`<div class="pat" style="margin-top:auto;padding:2px 0"><span class="nm2">${esc((px+patC.n).toUpperCase())}</span></div>`;

  const recto=`<div class="art carte" id="ss-cr">
  <div class="face-tag">RECTO — CARTE HORIZONTALE</div>
  <div class="head"><div class="arc"><i></i><i></i><i></i><i></i><i></i></div>${logoH}<div class="school"><div class="rdc">République Démocratique du Congo</div><div class="nm" style="font-size:${sz}">${esc(snm)}</div>${slg?`<div class="slg">${esc(slg)}</div>`:''}</div><div class="flag"></div></div>
  <div class="titlebar"><span class="tb-type">CARTE D'ÉLÈVE</span><span class="tb-cls">${esc(clNm)}</span></div>
  <div class="bodyz">
    ${showFond?ssVeil(patC,true):''}
    <div class="photo-col">
      <div class="photo">${s.photo?`<img src="${esc(s.photo)}" alt="">`:'<div class="ph"><svg viewBox="0 0 100 100"><circle cx="50" cy="36" r="17" fill="#a9b6d3"/><path d="M18 88c4-20 17-30 32-30s28 10 32 30z" fill="#a9b6d3"/></svg></div>'}</div>
    </div>
    <div class="info">
      <div class="sname"><div class="n1">${esc(dispNm)}</div>${fn?`<div class="n2">${esc(fn)}</div>`:''}</div>
      <div class="grid">
        <div class="cell"><div class="k">Matricule</div><div class="v">${esc(s.mat||'—')}</div></div>
        <div class="cell"><div class="k">Né(e) le</div><div class="v">${esc(dob)}</div></div>
        ${opt?`<div class="cell"><div class="k">Option</div><div class="v">${esc(opt)}</div></div>`:'<div class="cell"></div>'}
        <div class="cell"><div class="k">Année scolaire</div><div class="v">${esc(year)}</div></div>
        <div class="cell"><div class="k">Titulaire</div><div class="v">${esc(tchr)}</div></div>
        <div class="cell"><div class="k">N° Direction</div><div class="v">${esc(phone)}</div></div>
      </div>
      ${showVig?ssPatEl(patC,38,false):showFond?nmEl:''}
    </div>
    <div class="qr-col"><div class="qr-box" id="ss-qr-cr"></div><b>SCAN ENTRÉE / SORTIE</b></div>
  </div>
  <div class="foot"><div style="height:20px;display:flex;align-items:center;padding:0 4px"><span style="font-size:9px;font-weight:900">SchoolSafe</span></div><div><div class="t1">SchoolSafe</div><div class="t2">un enfant protégé, un parent informé</div></div><div class="val">VALIDE ${esc(year)}</div></div>
</div>`;

  const verso=`<div class="art carte verso" id="ss-cv">
  <div class="face-tag">VERSO — CARTE HORIZONTALE</div>
  <div class="head" style="padding:10px 18px">${logo?`<div class="logo-slot" style="width:36px;height:36px;border:none;background:transparent;padding:0"><img src="${logo}" alt=""></div>`:'<div class="logo-slot" style="width:36px;height:36px;font-size:11px"><span>LOGO</span></div>'}<div class="school"><div class="rdc">En cas de perte, merci de restituer à :</div><div class="nm" style="font-size:14px">${esc(snm)} — ${esc(addr)}</div></div><div class="flag"></div></div>
  <div class="bodyz">
    ${showFond?ssVeil(patC,true):''}
    <div class="vleft">
      <div class="mini-rows">
        <div class="mrw"><div class="ic">👨‍👩‍👧</div><div style="flex:1"><div class="k">Parent / Tuteur</div><div class="v">${esc(parNm)}</div></div><div><div class="k">Tél</div><div class="v">${esc(parPh)}</div></div></div>
        <div class="mrw"><div class="ic">🛡️</div><div style="flex:1"><div class="k">Personne autorisée</div><div class="v">${esc(apNm)}</div></div><div><div class="k">Tél</div><div class="v">${esc(apPh)}</div></div></div>
        <div class="mrw"><div class="ic">📞</div><div style="flex:1"><div class="k">N° Direction</div><div class="v">${esc(phone)}</div></div><div><div class="k">Email</div><div class="v">${esc(email)}</div></div></div>
        ${site?`<div class="mrw"><div class="ic">🌐</div><div style="flex:1"><div class="k">Site web</div><div class="v">${esc(site)}</div></div><div><div class="k">Année</div><div class="v">${esc(year)}</div></div></div>`:''}
      </div>
      ${showVig?ssPatEl(patC,40,false):showFond?`<div class="pat" style="margin-top:auto"><span class="nm2">${esc((px+patC.n).toUpperCase())}</span></div>`:''}
      <div class="sec-note" style="margin-top:auto"><b>🛡️ Note de sécurité :</b> accès contrôlé par scan SchoolSafe.</div>
    </div>
    <div class="vright">
      <div class="qr-box" style="width:110px;height:110px" id="ss-qr-cv"></div>
      <b style="font-size:7.5px;color:#5d6784;letter-spacing:.8px;font-weight:900">VÉRIFICATION SCHOOLSAFE</b>
      <div class="stamp">SCEAU<br>DE<br>L'ÉCOLE</div>
    </div>
  </div>
  <div class="foot"><div style="height:20px;display:flex;align-items:center;padding:0 4px"><span style="font-size:9px;font-weight:900">SchoolSafe</span></div><div><div class="t1">SchoolSafe</div><div class="t2">${site?esc(site):'www.schoolsafe.cd'}</div></div><div class="val">N° ${esc(s.mat||'—')}</div></div>
</div>`;
  return {recto,verso,qr:'schoolsafe://student/'+mat};
};

window.ssGenQR=function(elId,data,size){
  const el=document.getElementById(elId); if(!el||!window.QRCode)return;
  el.innerHTML='';
  try{new QRCode(el,{text:data,width:size||76,height:size||76,colorDark:'#17203a',colorLight:'#ffffff'});}catch(e){}
};

window.ssRenderPreview=function(){
  const studio=document.getElementById('ss-card-studio'); if(!studio)return;
  const sid=window._ssPreviewSid;
  const s=sid?(DB.students||[]).find(x=>x.id===sid):null;
  if(!s){studio.innerHTML='<div style="text-align:center;padding:30px;color:#888;font-size:13px">← Sélectionnez un élève pour voir l\'aperçu</div>';return;}
  const cl=(DB.classes||[]).find(c=>c.id===s.cid);
  const teacher=cl?(DB.users||[]).find(u=>u.id===(cl.titulaire_id||cl.teacher_id)):null;
  const year=DB.settings?.year||'2025-2026';
  const {type,colorIdx,col:clCol}=ssClassType(cl);
  const col=clCol||(window._ssCARDS.CCOLS[colorIdx]||window._ssCARDS.CCOLS[3]);
  const patB=ssGetClassPat(cl,'badge'), patC=ssGetClassPat(cl,'carte');
  const patStyle=document.getElementById('ss-pat-style')?.value||'vignette';
  studio.style.setProperty('--ss-cc',col.cc);
  studio.style.setProperty('--ss-cc-soft',col.soft);
  studio.style.setProperty('--ss-cc-dark',col.dark);
  window._ssColorIdx=colorIdx;
  // Mettre à jour l'indicateur de type badge/carte
  const lbl=document.getElementById('ss-type-label');
  if(lbl) lbl.innerHTML=type==='badge'
    ?'🏷️ <b>Badge vertical</b> 340×540 px — Maternelle → 4ᵉ Primaire'
    :'💳 <b>Carte PVC horizontale</b> 560×353 px — 5ᵉ/6ᵉ/Humanités';
  if(type==='badge'){
    const {recto,verso,qr}=ssBuildBadge(s,cl,teacher,year,patB,patStyle);
    studio.innerHTML=`<div style="display:flex;gap:22px;flex-wrap:wrap;justify-content:center;padding:10px 0"><div class="ss-badge-wrap">${recto}</div><div class="ss-badge-wrap">${verso}</div></div>`;
    setTimeout(()=>{ssGenQR('ss-qr-br',qr,76);ssGenQR('ss-qr-bv',qr,76);},30);
  } else {
    const {recto,verso,qr}=ssBuildCarte(s,cl,teacher,year,patC,patStyle);
    studio.innerHTML=`<div style="display:flex;flex-direction:column;gap:14px;align-items:center;padding:10px 0"><div class="ss-carte-wrap">${recto}</div><div class="ss-carte-wrap">${verso}</div></div>`;
    setTimeout(()=>{ssGenQR('ss-qr-cr',qr,76);ssGenQR('ss-qr-cv',qr,92);},30);
  }
};

