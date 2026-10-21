/* Keep the central route after the native shell normalizes its module URL. */
(()=>{
    'use strict';
    const shell=document.querySelector('.sskc-shell[data-central-route]');
    if(!shell)return;
    const route=shell.dataset.centralRoute;
    if(!/^\/schoolsafe-administrators\.php(?:\?(?:home=1|opening=1|accounts=central|wizard=1&step=[1-6]))?$/.test(route))return;
    const accountAnchor=/^#sskc-(?:(?:account|request)-\d+|communications-title|schools-title|followup-title)$/.test(location.hash)?location.hash:'';
    const preserve=()=>{if(location.pathname+location.search!==route)history.replaceState(history.state,'',route+accountAnchor);};
    const ready=()=>setTimeout(preserve,0);
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready,{once:true});else ready();
    window.addEventListener('load',preserve,{once:true});
    const focusAccount=()=>{const target=accountAnchor&&document.getElementById(accountAnchor.slice(1));if(target){target.scrollIntoView({block:'center'});target.focus({preventScroll:true});}};
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',focusAccount,{once:true});else focusAccount();
    shell.querySelectorAll('.sskc-top nav a').forEach(link=>{
        const url=new URL(link.href,location.href);
        if(url.pathname+url.search===route)link.setAttribute('aria-current','page');
    });
    // Open the application's existing assistant route and presentation. No second
    // avatar, conversation engine, token or permission implementation lives here.
    const jaspeLaunch=shell.querySelector('#sskc-jaspe-launch');
    if(jaspeLaunch)jaspeLaunch.addEventListener('click',event=>{
        if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
        event.preventDefault();event.stopPropagation();
        const nativeLaunch=document.getElementById('ss-jaspe-button');
        if(nativeLaunch){nativeLaunch.click();return;}
        let dialog=document.getElementById('ss-jaspe-dialog');
        if(!dialog){
            const styles=document.createElement('link');
            styles.rel='stylesheet';styles.href='/assets/themes/SchoolSafeCodex/mobile-navigation.css?v=19';
            document.head.appendChild(styles);
            dialog=document.createElement('dialog');dialog.id='ss-jaspe-dialog';
            dialog.setAttribute('aria-label','JASPE, assistante SchoolSafe');
            const close=document.createElement('button');
            close.type='button';close.className='ss-jaspe-close';close.textContent='Fermer';
            close.setAttribute('aria-label','Fermer JASPE');
            close.addEventListener('click',()=>dialog.close());
            const notice=document.createElement('p');notice.className='ss-jaspe-unavailable';
            notice.setAttribute('role','status');notice.textContent='Ouverture de JASPE…';
            const frame=document.createElement('iframe');frame.title='Conversation avec JASPE';
            frame.hidden=true;frame.src=jaspeLaunch.href;
            frame.addEventListener('load',()=>{
                const doc=frame.contentDocument;
                if(!doc?.getElementById('jaspe-native')){
                    notice.hidden=false;frame.hidden=true;
                    notice.textContent='JASPE n’est pas disponible pour cette session. Actualisez la page ou vérifiez votre accès.';
                    return;
                }
                frame.hidden=false;notice.hidden=true;
                doc.documentElement.dataset.ssConversationTheme=document.documentElement.dataset.ssBackground||'blue';
                doc.addEventListener('keydown',key=>{if(key.key==='Escape'){key.preventDefault();dialog.close();}});
                if(matchMedia('(pointer:fine)').matches)doc.getElementById('jaspe-question')?.focus();
            });
            dialog.append(close,notice,frame);document.body.appendChild(dialog);
            dialog.addEventListener('close',()=>{jaspeLaunch.setAttribute('aria-expanded','false');jaspeLaunch.focus();});
            document.addEventListener('keydown',key=>{if(key.key==='Escape'&&dialog.open)dialog.close();});
        }
        if(!dialog.open)dialog.show();
        jaspeLaunch.setAttribute('aria-expanded','true');
    });
    shell.querySelectorAll('[data-central-section]').forEach(link=>link.addEventListener('click',event=>{
        const target=document.getElementById(link.dataset.centralSection);if(!target)return;
        event.preventDefault();event.stopPropagation();
        if(target.tagName==='DETAILS')target.open=true;
        target.scrollIntoView({block:'start',behavior:'auto'});
        (target.querySelector('summary')||target).focus({preventScroll:true});
    }));
    const schoolSearch=shell.querySelector('#sskc-school-search'),directory=shell.querySelector('#sskc-school-directory');
    if(schoolSearch&&directory){
        const rows=Array.from(directory.querySelectorAll('[data-school-entry]'));
        const status=shell.querySelector('#sskc-school-search-status'),empty=shell.querySelector('#sskc-school-search-empty');
        const normalizeSchool=text=>text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase();
        schoolSearch.addEventListener('input',()=>{
            const value=normalizeSchool(schoolSearch.value.trim());let visible=0;
            rows.forEach(row=>{const match=normalizeSchool(row.querySelector('[data-school-title]').textContent).includes(value);row.hidden=!match;if(match)visible++;});
            if(status)status.textContent=visible+' établissement(s) affiché(s) sur '+rows.length+'.';
            if(empty)empty.hidden=visible!==0;
        });
    }
    const table=shell.querySelector('#sskc-accounts'),search=shell.querySelector('#sskc-account-search');
    if(table&&search){
        const headers=Array.from(table.querySelectorAll('thead th')).map(h=>h.textContent.trim());
        const rows=Array.from(table.querySelectorAll('tbody tr'));
        rows.forEach(row=>Array.from(row.cells).forEach((cell,i)=>cell.dataset.label=headers[i]||''));
        const empty=document.createElement('p');empty.className='sskc-search-empty';empty.setAttribute('role','status');empty.hidden=true;
        empty.textContent='Aucun administrateur ne correspond à votre recherche.';table.parentElement.after(empty);
        const normalize=text=>text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase();
        search.addEventListener('input',()=>{
            const value=normalize(search.value.trim());let count=0;
            rows.forEach(row=>{const match=normalize(Array.from(row.cells).slice(0,3).map(c=>c.textContent).join(' ')).includes(value);row.hidden=!match;if(match)count++;});
            empty.hidden=count!==0;
        });
    }
})();
