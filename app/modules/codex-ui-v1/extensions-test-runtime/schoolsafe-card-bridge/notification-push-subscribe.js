'use strict';
(() => {
  const button=document.getElementById('enable-background-notifications');
  if(!button) return;
  button.addEventListener('click',async()=>{
    button.disabled=true;
    try {
      if(!window.isSecureContext||!('serviceWorker' in navigator)||!('PushManager' in window)) throw new Error();
      const configResponse=await fetch('notification-push-subscription.php',{credentials:'same-origin',cache:'no-store'});
      if(!configResponse.ok) throw new Error();
      const config=await configResponse.json();
      if(config.enabled!==true||typeof config.csrf!=='string'||!/^[A-Za-z0-9_-]{87}$/.test(config.publicKey||'')) throw new Error();
      // Permission and registration occur only after a user click and enabled server response.
      if(await Notification.requestPermission()!=='granted') throw new Error();
      const registration=await navigator.serviceWorker.register('/notification-push-sw.js',{scope:'/'});
      const key=Uint8Array.from(atob(config.publicKey.replace(/-/g,'+').replace(/_/g,'/')+'='),c=>c.charCodeAt(0));
      const subscription=await registration.pushManager.getSubscription()||await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
      const saved=await fetch('notification-push-subscription.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':config.csrf},body:JSON.stringify({subscription:subscription.toJSON()})});
      if(!saved.ok) throw new Error();
      button.textContent='Notifications en arriere-plan activees';
    } catch {
      button.textContent='Activation indisponible';
    } finally {button.disabled=false;}
  });
})();
