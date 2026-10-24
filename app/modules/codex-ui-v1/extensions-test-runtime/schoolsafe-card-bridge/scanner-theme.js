'use strict';
// Same preference and fallback as the SchoolSafe dashboard; no remote theme state.
(() => {
 const themes=['white','blue','gray','mint','rose','yellow','night'];
 function apply(){
  let saved;
  try{saved=localStorage.getItem('schoolsafe-background');}catch(_){}
  document.documentElement.dataset.ssBackground=themes.includes(saved)?saved:'white';
 }
 apply();
 window.addEventListener('storage',event=>{if(event.key==='schoolsafe-background')apply();});
})();
