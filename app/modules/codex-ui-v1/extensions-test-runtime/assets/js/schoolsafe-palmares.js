(function () {
    'use strict';
    var panels=document.querySelectorAll('[data-palmares-parent]');
    if(!panels.length || window.schoolSafePalmaresPolling) return;
    window.schoolSafePalmaresPolling=true;
    var seen=new Set(),loaded=false,busy=false;
    async function refresh() {
        if(document.hidden || busy) return;
        busy=true;
        try {
            var response=await fetch('palmares-parent.php',{credentials:'same-origin',cache:'no-store'});
            if(!response.ok) throw new Error('UNAVAILABLE');
            var result=await response.json();
            if(!Array.isArray(result.badges)||!Array.isArray(result.notifications)) throw new Error('INVALID');
            var added=result.notifications.filter(function(n){return !seen.has(n.id);});
            panels.forEach(function(panel){
                var list=panel.querySelector('[data-palmares-badges]');list.replaceChildren();
                result.badges.forEach(function(b){
                    var item=document.createElement('li'),label=document.createElement('strong');
                    label.textContent=b.scope==='ecole'?"Top 10 de l'ecole":'Top 10 de sa classe · '+b.class;
                    item.append(label,document.createTextNode(' · '+b.student_name+' · '+b.period));list.append(item);
                });
                panel.querySelector('[data-palmares-event]').textContent=loaded&&added.length?added.map(function(n){return n.payload.message;}).join(' '):'';
            });
            if(loaded&&added.length) document.dispatchEvent(new CustomEvent('schoolsafe:palmares',{detail:{notifications:added}}));
            result.notifications.forEach(function(n){seen.add(n.id);});loaded=true;
        } catch(error) {
            panels.forEach(function(panel){panel.querySelector('[data-palmares-badges]').replaceChildren();panel.querySelector('[data-palmares-event]').textContent='Distinctions momentanement indisponibles.';});
        } finally {busy=false;}
    }
    setInterval(refresh,15000);document.addEventListener('visibilitychange',refresh);refresh();
})();
