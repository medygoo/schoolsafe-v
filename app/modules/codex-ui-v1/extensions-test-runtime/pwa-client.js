'use strict';
(() => {
  if (!window.isSecureContext || !('serviceWorker' in navigator)) return;
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/notification-push-sw.js', {scope:'/'}).catch(() => {
      console.warn('SchoolSafe: installation hors connexion indisponible.');
    });
  }, {once:true});
})();
