(function () {
    'use strict';
    function mount() {
        var root = document.getElementById('ss-photo-dashboard');
        if (!root || root.dataset.photoReady) return;
        if (window.schoolSafePhotoDashboard) window.schoolSafePhotoDashboard.destroy();
        root.dataset.photoReady = 'true';
        var created = [];
        var avatar = null;
        var disposed = false;
        var observer = null;
        var themeObserver = null;
        var original = document.querySelector('#body > .ss-dashboard-context');
        if (original) {
            var search = original.querySelector('.ss-dashboard-search');
            if (search) root.querySelector('[data-photo-search]').appendChild(search);
            ['school','syear'].forEach(function (key) {
                var slot = original.querySelector('[data-native-dashboard-context="' + key + '"]');
                if (slot) root.querySelector(key === 'school' ? '[data-photo-school]' : '[data-photo-year]').appendChild(slot.parentElement);
            });
        }
        var campus = root.querySelector('.ss-photo-campus');
        var theme = root.querySelector('.ss-photo-theme');
        function updateTheme() {
            var dark = document.documentElement.dataset.ssBackground === 'night';
            theme.setAttribute('aria-pressed', String(dark));
            theme.setAttribute('aria-label', dark ? 'Activer le mode clair' : 'Activer le mode sombre');
            if (campus) {
                var source = dark ? campus.dataset.campusNight : campus.dataset.campusDay;
                if (campus.getAttribute('src') !== source) campus.src = source;
            }
            document.querySelectorAll('#ss-jaspe-dialog iframe').forEach(function (frame) {
                try { frame.contentDocument.documentElement.dataset.ssConversationTheme = document.documentElement.dataset.ssBackground; } catch (_) {}
            });
        }
        theme.addEventListener('click', function () {
            var next = document.documentElement.dataset.ssBackground === 'night' ? 'white' : 'night';
            document.documentElement.dataset.ssBackground = next;
            try { localStorage.setItem('schoolsafe-background', next); } catch (_) {}
        });
        themeObserver = new MutationObserver(updateTheme);
        themeObserver.observe(document.documentElement, {attributes:true, attributeFilter:['data-ss-background']});
        updateTheme();
        function openDetails() {
            var details = document.getElementById('ss-photo-rubriques');
            details.open = true;
            details.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',block:'start'});
        }
        root.querySelectorAll('a[href="#ss-photo-rubriques"]').forEach(function (link) {
            link.addEventListener('click', function (event) { event.preventDefault(); openDetails(); });
        });
        root.querySelectorAll('[data-open-native-branch]').forEach(function (link) {
            var branch = link.dataset.openNativeBranch;
            if (branch === 'pilotage') return;
            link.addEventListener('click', function (event) {
                var native = document.querySelector('#menu [data-ss-branch="' + branch + '"] > .menu-top');
                if (!native) return;
                event.preventDefault();
                if (matchMedia('(max-width:900px)').matches) {
                    var menu = document.getElementById('ss-mobile-menu');
                    if (menu && menu.getAttribute('aria-expanded') !== 'true') menu.click();
                }
                native.click();
                native.focus();
            });
        });
        function askJaspe(question) {
            var opener = document.getElementById('ss-jaspe-button');
            if (!opener) return;
            var existing = document.getElementById('ss-jaspe-dialog');
            if (!existing || !existing.open) opener.click();
            var dialog = document.getElementById('ss-jaspe-dialog');
            var frame = dialog && dialog.querySelector('iframe');
            if (!frame) return;
            function send() {
                if (!dialog.open) return;
                var doc = frame.contentDocument;
                var input = doc && doc.getElementById('jaspe-question');
                if (!input) return;
                if (question) { input.value = question.slice(0,2000); doc.getElementById('jaspe-search').requestSubmit(); }
                else if (matchMedia('(pointer:fine)').matches) input.focus();
            }
            if (frame.contentDocument && frame.contentDocument.getElementById('jaspe-question')) send();
            else frame.addEventListener('load', send, {once:true});
        }
        root.querySelectorAll('[data-open-jaspe]').forEach(function (button) { button.addEventListener('click', function () { askJaspe(''); }); });
        root.querySelectorAll('[data-jaspe-question]').forEach(function (button) { button.addEventListener('click', function () { askJaspe(button.dataset.jaspeQuestion); }); });
        var question = root.querySelector('.ss-photo-question');
        if (question) question.addEventListener('submit', function (event) { event.preventDefault(); var input=question.querySelector('input'); askJaspe(input.value.trim()); input.value=''; });
        var panel = document.getElementById('ss-photo-create');
        var nav = document.getElementById('ss-mobile-navigation');
        // The application's original mobile navigation owns JASPE and Watch.
        // Keep account tools available on small screens without duplicating their handlers.
        var tools = document.querySelector('#footerwrap .ss-header-tools');
        var account = null;
        if (tools) {
            account=document.createElement('details');account.className='ss-photo-account-menu';
            var summary=document.createElement('summary');summary.title='Actions du compte';summary.setAttribute('aria-label','Actions du compte');summary.textContent='…';account.appendChild(summary);
            var list=document.createElement('div');account.appendChild(list);
            Array.from(tools.querySelectorAll('.ss-header-action')).slice(1).forEach(function(original){
                var button=document.createElement('button');button.type='button';button.textContent=original.getAttribute('aria-label')||original.title;
                button.addEventListener('click',function(){account.open=false;original.click();});list.appendChild(button);
            });tools.appendChild(account);created.push(account);
        }
        function closePopups(event) {
            if (event.type==='keydown' && event.key!=='Escape') return;
            if (!panel.hidden && (event.type==='keydown' || (!panel.contains(event.target) && !event.target.closest('.ss-photo-mobile-add')))) {
                panel.hidden=true;root.ownerDocument.querySelector('.ss-photo-mobile-add')?.setAttribute('aria-expanded','false');
            }
            if (account && account.open && (event.type==='keydown' || !account.contains(event.target))) account.open=false;
        }
        document.addEventListener('click',closePopups);document.addEventListener('keydown',closePopups);
        var stage=root.querySelector('.ss-photo-jaspe-stage');
        if(stage)import('/modules/SchoolSafe_Jaspe/jaspe2d/modules/jaspe2d/live-companion.js?v=coherent-1').then(async function(module){
            if(disposed)return;
            var isVisible=function(){if(disposed||document.hidden||!root.isConnected||document.querySelector('#ss-jaspe-dialog[open]'))return false;var box=stage.getBoundingClientRect();return box.bottom>0&&box.top<innerHeight;};
            var handle=await module.mountLiveCompanion(stage,root,{isVisible:isVisible,isBust:()=>true,activityTarget:stage,idleLiveliness:.5,allowIdleMotion:()=>!matchMedia('(prefers-reduced-motion:reduce)').matches});
            if(disposed){handle.destroy();return;}avatar=handle;root.dataset.jaspeEngine='v12';handle.play('wave',{intensity:.35});
        }).catch(function(){if(!disposed){stage.textContent='JASPE';root.dataset.jaspeEngine='unavailable';}});
        function destroy() {
            if(disposed)return;disposed=true;
            if(avatar)avatar.destroy();if(observer)observer.disconnect();if(themeObserver)themeObserver.disconnect();
            document.removeEventListener('click',closePopups);document.removeEventListener('keydown',closePopups);
            created.forEach(function(node){node.remove();});
        }
        observer=new MutationObserver(function(){if(!root.isConnected)destroy();});observer.observe(document.body,{childList:true,subtree:true});
        window.addEventListener('pagehide',destroy,{once:true});
        window.schoolSafePhotoDashboard={destroy:destroy};
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();

/* Shared school shell: one header, native controls, paged dashboard. */
(()=>{
 'use strict';
 function mount(){
  const root=document.getElementById('ss-photo-dashboard');if(!root||root.dataset.fixedShell)return;
  window.schoolSafeFixedDashboard?.destroy();root.dataset.fixedShell='1';
  const body=document.body,header=document.getElementById('footerwrap'),toolbar=root.querySelector('.ss-photo-toolbar');
  if(!header||!toolbar)return;
  body.classList.add('ss-school-shell');
  const brand=toolbar.querySelector('.ss-photo-mobile-brand'),menu=document.getElementById('BottomButtonMenu');
  const left=document.createElement('div');left.className='ss-fixed-header-brand';
  const account=document.createElement('details');account.className='ss-fixed-account';
  const summary=document.createElement('summary');summary.textContent=header.querySelector('.ss-user')?.textContent.trim()||'Mon compte';
  const tools=document.createElement('div');tools.className='ss-fixed-account-tools';
  const original=Array.from(header.childNodes);
  original.forEach(node=>{if(node!==menu)tools.appendChild(node);});
  if(menu)left.appendChild(menu);
  if(brand){const logo=brand.querySelector('img');if(logo){logo.src='/schoolsafe-logo.png';logo.classList.add('ss-animated-brand');}left.appendChild(brand);}
  account.append(summary,tools);header.append(left,account);
  // Keep the actual central links and connection information, not copies.
  const central=Array.from(document.querySelector('#body')?.children||[]).find(e=>e.tagName==='P'&&e.querySelector('a[href*="schoolsafe-administrators.php"]'));
  if(central)tools.appendChild(central);
  const loginInfo=document.querySelector('#body>.ss-dashboard-login-info');if(loginInfo)tools.appendChild(loginInfo);
  const detailed=new URL(location.href).searchParams.has('details_tab');
  const generated=[];
  function adoptHeaderTools(){
   header.querySelectorAll(':scope > .ss-header-tools,:scope > .ss-header-profile').forEach(node=>{if(!generated.includes(node))generated.push(node);tools.appendChild(node);});
  }
  adoptHeaderTools();
  let observer;
  const maintainShell=()=>{if(root.isConnected){if(!body.classList.contains('ss-school-shell'))body.classList.add('ss-school-shell');if(!detailed&&!body.classList.contains('ss-fixed-school'))body.classList.add('ss-fixed-school');}};
  const classObserver=new MutationObserver(maintainShell);classObserver.observe(body,{attributes:true,attributeFilter:['class']});
  function destroy(){
   observer?.disconnect();classObserver.disconnect();body.classList.remove('ss-school-shell','ss-fixed-school');
   if(brand)toolbar.prepend(brand);
   if(central&&root.isConnected)root.before(central);
   if(loginInfo&&root.isConnected)root.before(loginInfo);
   original.forEach(node=>header.appendChild(node));generated.forEach(node=>header.appendChild(node));
   left.remove();account.remove();
  }
  observer=new MutationObserver(()=>{if(!root.isConnected)destroy();else adoptHeaderTools();});observer.observe(body,{childList:true,subtree:true});
  window.schoolSafeFixedDashboard={destroy};window.addEventListener('pagehide',destroy,{once:true});
  if(detailed)return;
  body.classList.add('ss-fixed-school');
  const park=document.createElement('div');park.hidden=true;park.className='ss-fixed-park';
  const footer=root.querySelector('.ss-photo-footer'),viewport=document.createElement('div');viewport.className='ss-fixed-viewport';
  const pages=[];
  function page(label,nodes){
   if(!nodes.length)return;
   const panel=document.createElement('section');panel.className='ss-fixed-view';panel.setAttribute('aria-label',label);panel.hidden=true;
   nodes.forEach(n=>panel.appendChild(n));viewport.appendChild(panel);pages.push({label,panel});
  }
  function chunks(label,container,selector,size){
   if(!container)return;
   const items=Array.from(container.querySelectorAll(selector));
   for(let i=0;i<items.length;i+=size){const section=document.createElement('section'),heading=document.createElement('h2'),grid=document.createElement('div');
    heading.textContent=label;grid.className=container.className;grid.removeAttribute('id');
    items.slice(i,i+size).forEach(item=>grid.appendChild(item));section.append(heading,grid);page(label+(items.length>size?' '+(i/size+1):''),[section]);
   }
  }
  const hero=root.querySelector('.ss-photo-hero');if(hero)page('Accueil',[hero]);
  chunks('Indicateurs',root.querySelector('.ss-photo-metrics'),'.ss-photo-metric',3);
  const finance=root.querySelector('.ss-closing-finance');if(finance)page('Encaissements',[finance]);
  chunks('Modules',root.querySelector('.ss-photo-module-grid'),'.ss-photo-module',4);
  root.querySelectorAll('.ss-photo-charts>.ss-photo-chart').forEach(card=>page(card.querySelector('h2')?.textContent||'Synthèse',[card]));
  const life=root.querySelector('.ss-photo-school-life');if(life)Array.from(life.children).forEach((card,i)=>page('Vie scolaire '+(i+1),[card]));
  const alerts=root.querySelector('.ss-photo-alerts');if(alerts)page('Alertes importantes',[alerts]);
  const detailNav=root.querySelector('.ss-dashboard-details .ss-dashboard-tabs')||root.querySelector('.ss-dashboard-details nav');
  if(detailNav){const section=document.createElement('section'),heading=document.createElement('h2'),text=document.createElement('p');heading.textContent='Dossiers et informations détaillées';text.textContent='Ouvrez une rubrique pour consulter ses listes et ses rapports selon vos droits.';section.append(heading,text,detailNav);page('Dossiers détaillés',[section]);}
  Array.from(root.children).forEach(node=>{if(node!==toolbar&&node!==footer&&node.id!=='ss-photo-create'&&node.tagName!=='SCRIPT'&&node.tagName!=='LINK')park.appendChild(node);});
  const nav=document.createElement('nav');nav.className='ss-fixed-pager';nav.setAttribute('aria-label','Vues du tableau de bord');
  const previous=document.createElement('button'),next=document.createElement('button'),select=document.createElement('select'),status=document.createElement('span');
  previous.type=next.type='button';previous.textContent='Précédent';next.textContent='Suivant';select.setAttribute('aria-label','Vue du tableau de bord');status.setAttribute('role','status');
  pages.forEach((p,i)=>{const option=document.createElement('option');option.value=String(i);option.textContent=p.label;select.appendChild(option);});
  let current=0;
  function show(index){current=Math.max(0,Math.min(pages.length-1,index));pages.forEach((p,i)=>p.panel.hidden=i!==current);select.value=String(current);previous.disabled=current===0;next.disabled=current===pages.length-1;status.textContent=(current+1)+' / '+pages.length;viewport.scrollTop=0;}
  previous.addEventListener('click',()=>show(current-1));next.addEventListener('click',()=>show(current+1));select.addEventListener('change',()=>show(Number(select.value)));
  nav.append(previous,select,status,next);root.append(park,nav,viewport);if(footer)root.appendChild(footer);
  root.addEventListener('click',event=>{const link=event.target.closest('a[href^="#ss-photo-"]');if(!link)return;const target=link.getAttribute('href').includes('alert')?'Alertes importantes':'Modules';const index=pages.findIndex(p=>p.label.startsWith(target));if(index>=0){event.preventDefault();event.stopImmediatePropagation();show(index);}},true);
  show(0);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();
