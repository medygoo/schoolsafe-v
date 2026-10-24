'use strict';
// School identity is chosen on the server; this module only animates and handles image failure.
(() => {
 const frame=document.querySelector('[data-school-logo]');
 const image=frame?.querySelector('img');
 if(!image)return;
 const fail=()=>{
  if(frame.dataset.logoStage==='school'){
   frame.dataset.logoStage='campus';image.alt='Image par défaut de l’école';image.src=image.dataset.logoCampus;
  }else if(frame.dataset.logoStage==='campus'){
   frame.dataset.logoStage='brand';image.alt='Logo SchoolSafe par défaut';image.src=image.dataset.logoBrand;
  }else{frame.dataset.logoStage='text';image.hidden=true;}
 };
 image.addEventListener('error',fail);
 if(image.complete && image.naturalWidth===0)fail();
})();
