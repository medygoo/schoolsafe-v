(function() {
  'use strict';
  var tabs = [document.getElementById('profile-tab'),document.getElementById('user-tab')];
  function activate(index, focus) {
    tabs.forEach(function(tab,i) {
      tab.setAttribute('aria-selected',String(i===index)); tab.tabIndex=i===index?0:-1;
      document.getElementById(tab.getAttribute('aria-controls')).hidden=i!==index;
    });
    if(focus)tabs[index].focus();
  }
  tabs.forEach(function(tab,index) {
    tab.addEventListener('click',function(){activate(index,false);});
    tab.addEventListener('keydown',function(event) {
      var next;
      if(event.key==='ArrowRight'||event.key==='ArrowLeft')next=1-index;
      else if(event.key==='Home')next=0;
      else if(event.key==='End')next=1;
      else return;
      event.preventDefault();activate(next,true);
    });
  });
  if(window.lucide)window.lucide.createIcons();
})();
