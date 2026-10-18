(function (root) {
  'use strict';
  var instanceNumber = 0;
  var scriptBase = new URL('.', document.currentScript.src);
  var assets = {
    logo: new URL('../../schoolsafe-logo.png', scriptBase).href,
    jaspe: new URL('../../assets/jaspe2d/attente-v13/adossee-detouree.png', scriptBase).href
  };
  var metricLabels = {
    total: 'Eleves inscrits', total_students: 'Eleves inscrits', present_today: 'Presents aujourd\'hui', absent_today: 'Absents aujourd\'hui',
    currently_on_site: 'Sur site', pre_exit: 'Sorties preparees', exited: 'Sorties du jour', pickups: 'Remises du jour'
  };
  var auditColumns = [
    ['occurred_at','Date'],['operator_name','Operateur'],['mode','Mode'],['decision','Decision'],['reason','Motif']
  ];
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
  }
  function icon(name) { return '<i data-lucide="'+esc(name)+'" aria-hidden="true"></i>'; }
  function button(label, attrs, name) {
    return '<button type="button" '+attrs+'>'+ (name ? icon(name) : '') + '<span>'+esc(label)+'</span></button>';
  }
  function iconButton(label, attrs, name) {
    return '<button class="sscx-icon" type="button" title="'+esc(label)+'" aria-label="'+esc(label)+'" '+attrs+'>'+icon(name)+'</button>';
  }
  function object(value) { return value && typeof value === 'object' && !Array.isArray(value); }
  function validate(reader, data) {
    if (reader === 'scanAudit') {
      if (!Array.isArray(data) || data.some(function (row) { return !object(row); })) throw new Error('Historique serveur invalide.');
      return data.map(function (row) {
        var result = {};
        auditColumns.forEach(function (column) {
          var value = row[column[0]];
          if (value != null && typeof value !== 'string') throw new Error('Historique serveur invalide.');
          result[column[0]] = value;
        });
        return result;
      });
    }
    if (!object(data)) throw new Error('Synthese serveur invalide.');
    if (reader === 'onSiteStudent') {
      if (typeof data.currently_on_site !== 'boolean') throw new Error('Statut de presence invalide.');
      return { currently_on_site: data.currently_on_site };
    }
    var result = {};
    Object.keys(metricLabels).forEach(function (key) {
      if (Object.prototype.hasOwnProperty.call(data, key)) {
        if (!Number.isSafeInteger(data[key]) || data[key] < 0) throw new Error('Compteur de presence invalide.');
        result[key] = data[key];
      }
    });
    if (!Object.keys(result).length) throw new Error('Aucun compteur attendu dans la reponse.');
    return result;
  }
  function mountApplication(host, options) {
    options = options || {};
    if (typeof host === 'string') host = document.querySelector(host);
    if (!host) throw new Error('Conteneur absent');
    var catalog = root.SchoolSafeCodexCatalog;
    if (!catalog) throw new Error('Catalogue absent');
    var uid = 'sscx-' + (++instanceNumber);
    var state = { branch: '', action: null, allowed: [], theme: 'light', panel: false, query: '', auditDate:'', auditMode:'', auditDecision:'', page: 0, data: null, rows: [], selected: '', params: {}, message: '', kind: '', busy: false };
    var epoch = 0, requestId = 0, controller = null, destroyed = false, lastTrigger = null;
    var events = new AbortController();
    var views = null;
    var navigationHistory = [];
    var mobileViewport = root.matchMedia('(max-width: 700px)');
    function syncNavigationAccess() {
      var mobile=mobileViewport.matches,menu=mobile&&host.classList.contains('sscx-menu-open'),context=mobile&&host.classList.contains('sscx-context-open');
      var main=host.querySelector('.sscx-content'),topbar=host.querySelector('.sscx-topbar'),primary=host.querySelector('.sscx-primary');
      if(main)main.inert=menu||context;if(topbar)topbar.inert=menu||context;if(primary)primary.inert=context;
      host.querySelector('.sscx-mobile [data-command="dashboard"]')?.setAttribute('aria-current',state.action?'false':'page');
    }
    function navigationViewportChanged(){
      host.classList.remove('sscx-menu-open','sscx-context-open');
      host.querySelector('[data-command="menu"]')?.setAttribute('aria-expanded','false');syncNavigationAccess();
    }
    mobileViewport.addEventListener('change',navigationViewportChanged);
    function session() { return options.getSession ? options.getSession() : null; }
    function key(s) {
      return s ? JSON.stringify([s.identityKey,s.schoolId,s.revision,s.demo,s.role]) : '';
    }
    function demo(s) { return !!(s && s.demo === true && options.demo === true); }
    async function allowed(action, params, purpose) {
      var s = session();
      if (!s) return false;
      if (demo(s)) {
        var profile = catalog.roles[s.role];
        return !!(profile && (s.role === 'admin' || profile.actions.indexOf(action.id) >= 0));
      }
      if (s.demo || !s.identityKey || !s.schoolId || typeof options.authorize !== 'function') return false;
      return (await options.authorize(action, s, params || {}, purpose || 'execute')) === true;
    }
    function cancel() { requestId++; if (controller) controller.abort(); controller = null; }
    function clear() { cancel(); state.data=null; state.rows=[]; state.params={}; state.selected=''; state.query='';state.auditDate='';state.auditMode='';state.auditDecision=''; state.page=0; state.busy=false; state.message=''; }
    function current(action, generation, sKey) {
      return !destroyed && generation === epoch && key(session()) === sKey && state.action && state.action.id === action.id;
    }
    function paintIcons() { if (root.lucide) root.lucide.createIcons({ root: host }); }
    function profileTitle() {
      var s=session();
      return s ? (s.title || catalog.roles[s.role]?.label || 'Compte connecte') : 'Session indisponible';
    }
    function statusHtml() {
      if (!state.message) return '';
      return '<div class="sscx-state sscx-state--'+esc(state.kind)+'" role="'+(state.kind==='error'||state.kind==='denied'?'alert':'status')+'">'+icon(state.kind==='error'?'circle-alert':state.kind==='loading'?'loader-circle':'info')+'<span>'+esc(state.message)+'</span></div>';
    }
    function shell() {
      var s=session(), isDemo=demo(s);
      host.classList.add('sscx-app');
      host.dataset.theme=state.theme;
      host.innerHTML='<a class="sscx-skip" href="#'+uid+'-main">Aller au contenu</a>'+
        '<aside class="sscx-primary" id="'+uid+'-primary" aria-label="Menu principal">'+
        '<div class="sscx-brand"><img src="'+assets.logo+'" alt=""><strong>SchoolSafe</strong></div>'+
        '<p class="sscx-school">'+esc(s?.schoolName || 'Etablissement')+'</p><nav class="sscx-primary-list"></nav>'+
        '<div class="sscx-primary-footer">'+button('JASPE','data-command="jaspe"','message-circle')+'<span>'+esc(profileTitle())+'</span></div></aside>'+
        '<div class="sscx-workspace"><header class="sscx-topbar"><div><span class="sscx-eyebrow">'+esc(profileTitle())+'</span><strong>'+esc(s?.schoolName || 'Etablissement')+'</strong></div>'+
        '<div class="sscx-top-tools"><span class="sscx-badge '+(isDemo?'sscx-demo':'')+'">'+(isDemo?'DEMONSTRATION':'Session')+'</span><div class="sscx-theme" role="group" aria-label="Theme">'+
        iconButton('Theme blanc','data-theme-choice="light" aria-pressed="'+(state.theme==='light')+'"','sun')+
        iconButton('Theme bleu','data-theme-choice="dark" aria-pressed="'+(state.theme==='dark')+'"','moon')+'</div>'+iconButton('JASPE','data-command="jaspe"','message-circle')+'</div></header>'+
        '<div class="sscx-body"><aside class="sscx-context" id="'+uid+'-context" aria-label="Sous-menu" hidden></aside>'+
        '<main class="sscx-content" id="'+uid+'-main" tabindex="-1"></main></div></div>'+
        '<nav class="sscx-mobile" aria-label="Navigation mobile">'+button('Tableau de bord','data-command="dashboard"','layout-dashboard')+button('JASPE','data-command="jaspe"','message-circle')+button('Menu','data-command="menu" aria-controls="'+uid+'-primary" aria-expanded="false"','menu')+'</nav>'+
        '<dialog class="sscx-dialog" id="'+uid+'-jaspe" aria-labelledby="'+uid+'-jaspe-title"><header><h2 id="'+uid+'-jaspe-title">JASPE</h2>'+iconButton('Fermer','data-command="close-dialog"','x')+'</header><div class="sscx-jaspe-stage"><img src="'+assets.jaspe+'" alt="JASPE, assistante SchoolSafe"></div><p>Conversation indisponible dans cette demonstration.</p><div class="sscx-dialog-actions">'+button('Ecrit','data-command="jaspe-text" aria-pressed="true"','message-circle')+button('Audio','data-command="jaspe-audio" aria-pressed="false"','mic')+'</div><form data-jaspe-demo-form class="sscx-form"><label for="'+uid+'-chat">Votre message</label><div class="sscx-field-row"><input id="'+uid+'-chat" name="message" required maxlength="2000" autocomplete="off"><button type="submit" title="Envoyer" aria-label="Envoyer">'+icon('arrow-up')+'</button></div></form><p class="sscx-jaspe-status" role="status"></p></dialog>'+
        '<dialog class="sscx-dialog" id="'+uid+'-detail" aria-labelledby="'+uid+'-detail-title"><header><h2 id="'+uid+'-detail-title">Detail du controle</h2>'+iconButton('Fermer','data-command="close-dialog"','x')+'</header><dl></dl></dialog>';
      renderNavigation(); renderContext(); renderContent(); paintIcons();
      host.querySelectorAll('dialog').forEach(function (dialog) {
        dialog.addEventListener('close',function () { if (lastTrigger?.isConnected) lastTrigger.focus(); },{signal:events.signal});
      });
    }
    function renderNavigation() {
      var nav=host.querySelector('.sscx-primary-list');
      nav.innerHTML=button('Tableau de bord','data-command="dashboard" '+(!state.branch?'aria-current="page"':''),'layout-dashboard')+
        catalog.branches.map(function (branch) {
          var visible=state.allowed.some(function (a) { return a.branch===branch.id; });
          return button(branch.label,'data-branch="'+branch.id+'" '+(state.branch===branch.id?'aria-current="page"':'')+(visible?'':' disabled'),branch.icon);
        }).join('');
      paintIcons();syncNavigationAccess();
    }
    function renderContext() {
      var panel=host.querySelector('.sscx-context'), branch=catalog.branches.find(function (b) { return b.id===state.branch; });
      panel.dataset.uiBranch=branch?.id||'';
      panel.hidden=!branch;
      if (!branch) { panel.innerHTML=''; syncNavigationAccess(); return; }
      var actions=state.allowed.filter(function (a) { return a.branch===branch.id; }), groups=[];
      actions.forEach(function (a) { if(groups.indexOf(a.group)<0)groups.push(a.group); });
      panel.innerHTML='<header><strong class="sscx-context-title">'+icon(branch.icon)+'<span>'+esc(branch.label)+'</span></strong>'+iconButton('Fermer le sous-menu','data-command="close-context"','x')+'</header><nav>'+groups.map(function (group) {
        return '<section><h2>'+esc(group)+'</h2>'+actions.filter(function (a) { return a.group===group; }).map(function (a) {
          return button(a.label,'data-action-id="'+esc(a.id)+'" '+(state.action?.id===a.id?'aria-current="page"':''),root.SchoolSafeCodexLot2?.iconFor(a)||branch.icon);
        }).join('')+'</section>';
      }).join('')+'</nav>';
      paintIcons();syncNavigationAccess();
    }
    function dashboardHtml() {
      var s=session(), title=s?.scopeLabel || (demo(s) ? catalog.roles[s.role]?.scope : '') || 'Perimetre de la session';
      var priority=s?.role==='guard'?/Scanner un QR|Enregistrer une entree|Personnes autoris|scan-history/i:s?.role==='parent'?/presence-student|Personnes autoris|Devoirs|Resultats|Reçus/i:s?.role==='teacher'?/presence-teacher|presence-class|Devoirs|notes|preparation/i:/presence-school|scan-fees|Encaissements|Enregistrer un paiement|Alertes|Approbations|Personnel|Documents/i;
      var quick=state.allowed.filter(function (a) { return priority.test(a.id+' '+a.label); }).slice(0,5);
      if(!quick.length)quick=state.allowed.slice(0,5);
      return '<header class="sscx-page-title"><div><span class="sscx-eyebrow">'+esc(profileTitle())+'</span><h1>Tableau de bord</h1><p>'+esc(title)+'</p></div></header>'+
        (demo(s)?'<p class="sscx-demo-banner">Donnees de demonstration. Aucun appel metier.</p>':'')+
        (views ? views.dashboardHtml() : '<section class="sscx-section"><h2>Ma journee</h2><div class="sscx-shortcuts">'+quick.map(function (a) { return button(a.label,'data-action-id="'+a.id+'"',a.branch==='security'?'history':'users'); }).join('')+'</div>'+(!quick.length?'<p>Aucune synthese autorisee pour cette session.</p>':'')+'</section>')+
        '<section class="sscx-section"><h2>Mes espaces</h2><div class="sscx-module-grid">'+catalog.branches.filter(function (b) { return state.allowed.some(function (a) { return a.branch===b.id; }); }).map(function (b) {
          var n=state.allowed.filter(function (a) {return a.branch===b.id;}).length;
          return '<button type="button" class="sscx-module" data-branch="'+b.id+'">'+icon(b.icon)+'<span><strong>'+esc(b.label)+'</strong><small>'+n+' sous-fonctions</small></span>'+icon('arrow-up-right')+'</button>';
        }).join('')+'</div></section>';
    }
    function choicesHtml() {
      return '<option value="">Selectionner</option>'+state.rows.map(function (row) {return '<option value="'+esc(row.id)+'" '+(state.selected===row.id?'selected':'')+'>'+esc(row.label)+'</option>';}).join('');
    }
    function readHtml(a) {
      var parameter=(a.reader==='onSiteStudent'||a.reader==='scanAudit')?'student_id':a.reader==='onSiteClass'?'class_id':'';
      var form='';
      if(parameter) form='<form class="sscx-form" data-read-form><label for="'+uid+'-selection">'+(parameter==='class_id'?'Classe autorisee':'Eleve autorise')+'</label><div class="sscx-field-row"><select id="'+uid+'-selection" name="'+parameter+'" required>'+choicesHtml()+'</select><button class="sscx-button" type="submit" '+(!state.rows.length||state.busy?'disabled':'')+'>'+icon('search')+'<span>Consulter</span></button></div></form>';
      else form='<div class="sscx-toolbar">'+iconButton('Actualiser','data-command="refresh" '+(state.busy?'disabled':''),'refresh-cw')+'</div>';
      var result='';
      if(state.data!==null){
        if(a.reader==='scanAudit')result=auditHtml();
        else result='<div class="sscx-metrics">'+Object.keys(state.data).map(function (k) {
          var v=state.data[k], text=typeof v==='boolean'?(v?'Present sur site':'Absent du site'):v;
          return '<article class="sscx-metric"><span>'+esc(metricLabels[k])+'</span><strong>'+esc(text)+'</strong><small>'+ (demo(session())?'Demonstration':'Lecture serveur')+'</small></article>';
        }).join('')+'</div>';
      }
      var links=[['presence-student','Eleve'],['presence-class','Classe'],['presence-school','Etablissement'],['scan-history','Historique']].filter(function(pair){return pair[0]!==a.id && state.allowed.some(function(x){return x.id===pair[0];});});
      return '<div class="sscx-related-actions" aria-label="Perimetre de consultation">'+links.map(function(pair){return button(pair[1],'data-action-id="'+pair[0]+'"','arrow-up-right');}).join('')+'</div>'+form+(parameter?'<div class="sscx-toolbar">'+iconButton('Actualiser la lecture','data-command="refresh" '+(state.busy||!Object.keys(state.params).length?'disabled':''),'refresh-cw')+'</div>':'')+statusHtml()+result;
    }
    function auditHtml() {
      var rows=state.data.filter(function (row) {return auditColumns.some(function (c) {return String(row[c[0]]||'').toLocaleLowerCase().includes(state.query.toLocaleLowerCase());})&&(!state.auditDate||String(row.occurred_at||'').slice(0,10)===state.auditDate)&&(!state.auditMode||row.mode===state.auditMode)&&(!state.auditDecision||row.decision===state.auditDecision);});
      var max=Math.max(0,Math.ceil(rows.length/10)-1);state.page=Math.min(state.page,max);
      return '<label class="sscx-search-label" for="'+uid+'-filter">Filtrer les controles</label><input class="sscx-search" id="'+uid+'-filter" type="search" value="'+esc(state.query)+'" data-filter placeholder="Date, operateur, mode, decision">'+
        '<div class="sscx-list-filters"><label>Date<input type="date" data-audit-filter="auditDate" value="'+esc(state.auditDate)+'"></label>'+[['auditMode','Mode','mode'],['auditDecision','Decision','decision']].map(function(item){var values=Array.from(new Set(state.data.map(function(row){return row[item[2]];}).filter(Boolean)));return '<label>'+item[1]+'<select data-audit-filter="'+item[0]+'"><option value="">Tous</option>'+values.map(function(value){return '<option value="'+esc(value)+'" '+(state[item[0]]===value?'selected':'')+'>'+esc(value)+'</option>';}).join('')+'</select></label>';}).join('')+'</div>'+
        (rows.length?'<div class="sscx-table-scroll" tabindex="0" role="region" aria-label="Historique des controles"><table><caption>Controles '+(demo(session())?'de demonstration':'du dossier selectionne')+'</caption><thead><tr>'+auditColumns.map(function(c){return '<th scope="col">'+esc(c[1])+'</th>';}).join('')+'<th scope="col">Detail</th></tr></thead><tbody>'+rows.slice(state.page*10,state.page*10+10).map(function(row){return '<tr>'+auditColumns.map(function(c){return '<td>'+esc(row[c[0]]||'Non renseigne')+'</td>';}).join('')+'<td>'+iconButton('Voir le detail','data-audit-index="'+state.data.indexOf(row)+'"','eye')+'</td></tr>';}).join('')+'</tbody></table></div>':'<p class="sscx-empty" role="status">Aucun controle correspondant.</p>')+
        '<div class="sscx-pagination">'+iconButton('Page precedente','data-page="-1" '+(state.page===0?'disabled':''),'chevron-left')+'<span>Page '+(state.page+1)+' / '+(max+1)+' · '+rows.length+' controles</span>'+iconButton('Page suivante','data-page="1" '+(state.page===max?'disabled':''),'chevron-right')+'</div>';
    }
    function familyMessage(a) {
      if(/Studio des cartes/.test(a.label))return 'L\'impression officielle, le lot ZIP et la production pour toute l\'ecole restent a verifier.';
      if(/certificativ|ENAFEP|TENASOSP|EXETAT/i.test(a.label))return 'Preparation des dossiers uniquement. Aucun resultat national ni diplome d\'Etat n\'est produit ici.';
      if(a.branch==='accounting')return 'La comptabilite legale n\'est pas validee dans cette interface.';
      if(a.branch==='inventory')return 'Les mouvements de stock reels restent a raccorder.';
      if(a.branch==='people' && /salaire|prime|contrat/i.test(a.label))return 'Les operations RH et de paie restent a raccorder.';
      if(a.branch==='reports')return 'Les rapports generaux doivent etre verifies contre leur source officielle.';
      if(a.branch==='communication')return 'Le canal selectionne doit etre raccorde au service autorise.';
      return 'La fonction existante s\'ouvre dans son ecran SchoolSafe apres raccordement.';
    }
    function renderContent(focus) {
      var main=host.querySelector('.sscx-content'),a=state.action;
      main.dataset.uiBranch=a?.branch||'';
      if(!a)main.innerHTML=dashboardHtml()+statusHtml();
      else main.innerHTML='<header class="sscx-page-title"><div><p class="sscx-breadcrumb">'+esc(catalog.branches.find(function(b){return b.id===a.branch;})?.label)+' / '+esc(a.group)+'</p><h1>'+esc(a.label)+'</h1></div>'+iconButton('Retour a l\'ecran precedent','data-command="back"','arrow-left')+'</header>'+
        (demo(session())?'<p class="sscx-demo-banner">Donnees et parcours de demonstration.</p>':'')+
        (a.type==='read'?readHtml(a):a.type==='unavailable'?(views?.unavailableHtml()||'<div class="sscx-state" role="status">'+icon('circle-alert')+'<p>'+esc(a.reason)+'</p></div>'):views?views.html(a)+statusHtml():'<section class="sscx-section"><h2>'+esc(a.label)+'</h2><p>'+esc(familyMessage(a))+'</p><div class="sscx-toolbar">'+button('Ouvrir la fonction','data-command="open-existing" '+(state.busy?'disabled':''),'arrow-up-right')+'</div>'+statusHtml()+'</section>');
      paintIcons();
      syncNavigationAccess();
      if(state.busy)main.querySelectorAll('[data-command="open-existing"], [data-lot2-mode], [data-lot2-operation-form] button[type="submit"]').forEach(function(b){b.disabled=true;});
      if(focus){main.focus({preventScroll:true});main.querySelector('.sscx-page-title')?.scrollIntoView({block:'nearest',inline:'nearest',behavior:'instant'});}
    }
    function message(text,kind) {state.message=text;state.kind=kind||'unavailable';renderContent();}
    async function refreshSession() {
      views?.reset();
      navigationHistory=[];
      host.classList.remove('sscx-menu-open','sscx-context-open');
      epoch++; clear(); state.action=null;state.branch='';state.allowed=[];state.panel=false;
      var generation=epoch,sKey=key(session());shell();
      if(!session()){message('Session indisponible.','denied');return;}
      var results=await Promise.all(catalog.actions.map(async function(a){try{return await allowed(a,{},'navigation')?a:null;}catch{return null;}}));
      if(destroyed||generation!==epoch||sKey!==key(session()))return;
      state.allowed=results.filter(Boolean);state.message=state.allowed.length?'':'Aucune fonction autorisee ou contrat d\'acces indisponible.';
      shell();
      if(views)await views.activate(null);
    }
    async function loadChoices(action,generation,sKey) {
      if(!/onSiteStudent|onSiteClass|scanAudit/.test(action.reader))return;
      state.busy=true;message('Chargement des dossiers autorises.','loading');
      var list=demo(session())?options.demoData?.choices:options.listAuthorized;
      if(typeof list!=='function'){state.busy=false;message('Liste des dossiers autorises indisponible.');return;}
      try{
        var rows=await list(action.reader==='onSiteClass'?'class':'student',session());
        if(!current(action,generation,sKey))return;
        if(!await allowed(action,{},'navigation')){state.busy=false;state.rows=[];message('Acces retire.','denied');return;}
        if(!current(action,generation,sKey))return;
        if(!Array.isArray(rows)||rows.some(function(row){return !object(row)||typeof row.id!=='string'||typeof row.label!=='string';}))throw new Error('Liste de dossiers invalide.');
        state.rows=rows.map(function(row){return {id:row.id,label:row.label};});state.busy=false;
        message(rows.length?'Selectionnez un dossier.':'Aucun dossier autorise.','empty');
      }catch(e){if(current(action,generation,sKey)){state.busy=false;message(e.denied?'Acces refuse aux dossiers.':'Impossible de charger les dossiers autorises.',e.denied?'denied':'error');}}
    }
    async function selectAction(id,fromHistory) {
      var a=catalog.actions.find(function(item){return item.id===id;});if(!a)return;
      if(!fromHistory && state.action?.id!==id){navigationHistory.push(state.action?.id||null);if(navigationHistory.length>30)navigationHistory.shift();}
      views?.reset();
      epoch++; clear(); var generation=epoch,sKey=key(session());state.action=a;state.branch=a.branch;state.panel=false;
      host.classList.remove('sscx-menu-open','sscx-context-open');renderNavigation();renderContext();renderContent(true);
      host.querySelector('[data-command="menu"]')?.setAttribute('aria-expanded','false');
      try{
        if(!await allowed(a,{},'navigation')){if(current(a,generation,sKey))message('Acces refuse.','denied');return;}
        if(!current(a,generation,sKey))return;
        if(a.type==='read'){
          if(/onSiteStudent|onSiteClass|scanAudit/.test(a.reader))await loadChoices(a,generation,sKey);
          else await loadRead();
        }else if(a.type==='existing'){
          message(demo(session())?'Interaction visuelle. Fonction metier non raccordee.':'Selectionnez Ouvrir pour acceder a la fonction existante.');
          if(views)await views.activate(a);
        }else if(a.type==='unavailable'&&demo(session())&&options.featureScreens===true){
          if(views)await views.activate(a);
        }
      }catch(e){if(current(a,generation,sKey))message('Verification d\'acces indisponible.','denied');}
    }
    async function loadRead(params) {
      var a=state.action;if(!a||a.type!=='read')return;
      params=params||state.params;var generation=epoch,sKey=key(session());cancel();var id=requestId;controller=new AbortController();
      state.data=null;state.busy=true;state.params=params;message('Chargement en cours.','loading');
      try{
        if(!await allowed(a,params,'read'))throw Object.assign(new Error('Acces refuse.'),{denied:true});
        if(!current(a,generation,sKey)||id!==requestId)return;
        var reader=demo(session())?options.demoData?.read:options.read;
        if(typeof reader!=='function'){state.busy=false;message('Source de donnees non raccordee.');return;}
        var data=await reader(a.reader,params,{session:session(),signal:controller.signal});
        if(!current(a,generation,sKey)||id!==requestId)return;
        if(!await allowed(a,params,'read'))throw Object.assign(new Error('Acces retire.'),{denied:true});
        if(!current(a,generation,sKey)||id!==requestId)return;
        state.data=validate(a.reader,data);state.busy=false;
        message(Array.isArray(state.data)&&!state.data.length?'Aucun controle pour ce dossier.':demo(session())?'Resultat de demonstration.':'Lecture terminee.', 'ready');
      }catch(e){if(current(a,generation,sKey)&&id===requestId){state.busy=false;state.data=null;message(e.denied?'Acces refuse ou retire.':'Lecture impossible. Reessayez ou contactez votre administrateur.',e.denied?'denied':'error');}}
    }
    async function openExisting(mode,operationParams) {
      if(state.busy)return;
      var a=state.action;if(!a||a.type!=='existing')return;
      var route=a.legacy,params=operationParams||{};
      if(operationParams)route=Object.assign({},a.legacy,{operation:params.operation});
      if(mode){
        if(!['entry','pickup','fees','verify'].includes(mode))return;
        if(a.id==='scan-fees' && mode!=='fees'){message('Ce parcours financier ne donne aucun droit de retrait ou d\'entree.','denied');return;}
        params={mode:mode,capability:{entry:'SCAN_ENTRY',pickup:'SCAN_PICKUP',fees:'SCAN_FEES',verify:'SCAN_VERIFY'}[mode]};
        route={branch:'security',action:'Scanner un QR',mode:mode};
      }
      var generation=epoch,sKey=key(session());state.busy=true;message('Verification de l\'acces.','loading');
      try{
        var authorized=await allowed(a,params,'execute');
        if(!current(a,generation,sKey))return;
        if(!authorized){state.busy=false;message('Acces refuse.','denied');return;}
        if(demo(session())||typeof options.openExisting!=='function'){state.busy=false;message('Fonction non raccordee. Aucune operation effectuee.');return;}
        var result=await options.openExisting(route,session(),a,params);
        if(!current(a,generation,sKey))return;
        state.busy=false;
        message(result?.opened===true?'Ecran existant ouvert.':'Fonction non raccordee. Aucune operation effectuee.',result?.opened===true?'ready':'unavailable');
      }catch(e){if(current(a,generation,sKey)){state.busy=false;message('Ouverture impossible.','error');}}
    }
    async function openJaspe(trigger,mode) {
      var sKey=key(session()),generation=epoch;
      var action={id:'jaspe',permission:'safe.assistant.use',type:'existing',branch:'safe'};
      if(!demo(session())){
        try{
          if(!await allowed(action,{},'execute')){message('JASPE non autorisee pour cette session.','denied');return;}
          if(generation!==epoch||sKey!==key(session()))return;
          if(typeof options.openJaspe!=='function'){message('Conversation JASPE non raccordee.');return;}
          var result=await options.openJaspe(trigger,mode||'text',session());
          if(generation!==epoch||sKey!==key(session()))return;
          if(result?.opened!==true)message('Conversation JASPE indisponible.');
        }catch{if(generation===epoch&&sKey===key(session()))message('Conversation JASPE indisponible.','error');}
        return;
      }
      lastTrigger=trigger;host.querySelector('#'+uid+'-jaspe').showModal();
    }
    function onClick(event) {
      var b=event.target.closest('button');if(!b||!host.contains(b)||b.disabled)return;
      if(views?.onClick(b))return;
      if(b.dataset.themeChoice){state.theme=b.dataset.themeChoice;host.dataset.theme=state.theme;host.querySelectorAll('[data-theme-choice]').forEach(function(btn){btn.setAttribute('aria-pressed',String(btn.dataset.themeChoice===state.theme));});return;}
      if(b.dataset.actionId){selectAction(b.dataset.actionId);return;}
      if(b.dataset.branch){state.branch=b.dataset.branch;state.panel=true;host.classList.remove('sscx-menu-open');host.querySelector('[data-command="menu"]')?.setAttribute('aria-expanded','false');host.classList.add('sscx-context-open');renderNavigation();renderContext();host.querySelector('.sscx-context button')?.focus();return;}
      if(b.dataset.page){state.page+=Number(b.dataset.page);renderContent();host.querySelector('[data-page="'+b.dataset.page+'"]')?.focus();return;}
      if(b.dataset.auditIndex!=null){var row=state.data?.[Number(b.dataset.auditIndex)];if(!row)return;lastTrigger=b;var dialog=host.querySelector('#'+uid+'-detail');dialog.querySelector('dl').innerHTML=auditColumns.map(function(c){return '<dt>'+esc(c[1])+'</dt><dd>'+esc(row[c[0]]||'Non renseigne')+'</dd>';}).join('');dialog.showModal();return;}
      switch(b.dataset.command){
        case 'back':var previous=navigationHistory.pop();if(previous){selectAction(previous,true);break;}showDashboard(false);break;
        case 'dashboard':showDashboard(true);break;
        case 'menu':state.panel=false;host.classList.remove('sscx-context-open');host.classList.toggle('sscx-menu-open');syncNavigationAccess();b.setAttribute('aria-expanded',String(host.classList.contains('sscx-menu-open')));if(host.classList.contains('sscx-menu-open'))host.querySelector('.sscx-primary-list button').focus();break;
        case 'close-context':host.classList.remove('sscx-context-open');host.querySelector('.sscx-context').hidden=true;(matchMedia('(max-width: 700px)').matches?host.querySelector('[data-command="menu"]'):host.querySelector('.sscx-primary [data-branch="'+state.branch+'"]'))?.focus();break;
        case 'refresh':loadRead();break;
        case 'open-existing':openExisting();break;
        case 'jaspe':openJaspe(b);break;
        case 'close-dialog':b.closest('dialog').close();break;
        case 'jaspe-text':case 'jaspe-audio':var d=b.closest('dialog');d.querySelectorAll('[data-command^="jaspe-"]').forEach(function(btn){btn.setAttribute('aria-pressed',String(btn===b));});d.querySelector('.sscx-jaspe-status').textContent=b.dataset.command==='jaspe-audio'?'Audio indisponible dans la demonstration.':'Conversation ecrite indisponible dans la demonstration.';break;
      }
      syncNavigationAccess();
    }
    function showDashboard(resetHistory){if(resetHistory)navigationHistory=[];epoch++;clear();views?.reset();state.action=null;state.branch='';host.classList.remove('sscx-context-open','sscx-menu-open');host.querySelector('[data-command="menu"]')?.setAttribute('aria-expanded','false');renderNavigation();renderContext();renderContent(true);views?.activate(null);}
    host.addEventListener('click',onClick,{signal:events.signal});
    host.addEventListener('toggle',function(event){views?.onToggle(event.target);},{capture:true,signal:events.signal});
    host.addEventListener('submit',function(event){
      if(event.target.matches('[data-lot2-draft], [data-lot2-operation-form], [data-school-workflow-form], [data-feature-form]')){event.preventDefault();views?.onSubmit(event.target);return;}
      if(event.target.matches('[data-jaspe-demo-form]')){event.preventDefault();event.target.closest('dialog').querySelector('.sscx-jaspe-status').textContent='Envoi indisponible. Aucun message transmis.';return;}
      if(!event.target.matches('[data-read-form]'))return;event.preventDefault();if(!event.target.reportValidity())return;var entries=Object.fromEntries(new FormData(event.target));state.selected=Object.values(entries)[0];loadRead(entries);
    },{signal:events.signal});
    host.addEventListener('input',function(event){if(views?.onInput(event.target))return;if(!event.target.matches('[data-filter]'))return;var position=event.target.selectionStart;state.query=event.target.value;state.page=0;renderContent();var input=host.querySelector('[data-filter]');input.focus();input.setSelectionRange(position,position);},{signal:events.signal});
    host.addEventListener('change',function(event){var el=event.target;if(el.dataset.auditFilter && ['auditDate','auditMode','auditDecision'].includes(el.dataset.auditFilter)){state[el.dataset.auditFilter]=el.value;state.page=0;renderContent();host.querySelector('[data-audit-filter="'+el.dataset.auditFilter+'"]')?.focus();return;}views?.onChange(el);},{signal:events.signal});
    host.addEventListener('keydown',function(event){views?.onKey(event);},{signal:events.signal});
    host.addEventListener('keydown',function(event){
      if(event.key==='Escape'){var wasOpen=host.classList.contains('sscx-menu-open')||host.classList.contains('sscx-context-open');host.classList.remove('sscx-menu-open','sscx-context-open');syncNavigationAccess();host.querySelector('[data-command="menu"]')?.setAttribute('aria-expanded','false');if(wasOpen)host.querySelector('[data-command="menu"]')?.focus();}
      if(event.key==='Tab'&&mobileViewport.matches&&!host.querySelector('dialog[open]')){
        var panel=host.classList.contains('sscx-context-open')?'.sscx-context':host.classList.contains('sscx-menu-open')?'.sscx-primary':'';
        if(!panel)return;var controls=Array.from(host.querySelectorAll(panel+' button:not(:disabled), .sscx-mobile button:not(:disabled)')).filter(function(el){return el.getClientRects().length&&!el.closest('[inert]');});
        var first=controls[0],last=controls[controls.length-1];
        if(event.shiftKey&&event.target===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&event.target===last){event.preventDefault();first?.focus();}
      }
    },{signal:events.signal});
    async function openRecord(id,recordId){await selectAction(id);var a=state.action;if(a?.id!==id)return;if(a.type==='read'){var parameter=a.reader==='onSiteClass'?'class_id':a.reader==='onSiteStudent'?'student_id':'';if(parameter&&state.rows.some(function(r){return r.id===recordId;})){state.selected=recordId;await loadRead({[parameter]:recordId});}}else views?.openRecord(recordId);}
    if(root.SchoolSafeCodexLot2)views=root.SchoolSafeCodexLot2.create(host,{session:session,demo:function(){return demo(session());},authorize:allowed,actions:function(){return state.allowed;},schoolWorkflows:options.schoolWorkflows===true,featureScreens:options.featureScreens===true,demoData:options.demoData,consult:options.consult,loadDashboard:options.loadDashboard,render:renderContent,icons:paintIcons,familyMessage:familyMessage,openRecord:openRecord,openOperation:function(a,params){if(state.action?.id===a.id)openExisting(null,params);},openMode:function(a,mode){if(state.action?.id===a.id)openExisting(mode);}});
    var ready=refreshSession();
    return {ready:ready,refreshSession:refreshSession,open:selectAction,destroy:function(){destroyed=true;epoch++;mobileViewport.removeEventListener('change',navigationViewportChanged);views?.destroy();cancel();events.abort();host.querySelectorAll('dialog[open]').forEach(function(d){d.close();});host.innerHTML='';host.classList.remove('sscx-app');}};
  }
  root.SchoolSafeCodexUiV1.mountApplication=mountApplication;
  root.SchoolSafeCodexUiV1.validateRead=validate;
})(window);
