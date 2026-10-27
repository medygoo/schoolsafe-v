'use strict';
const CACHE='schoolsafe-public-v5';
const PUBLIC=['/schoolsafe-offline.html','/schoolsafe-offline.css','/schoolsafe-pwa-192.png','/schoolsafe-pwa-512.png','/pwa-payment-drafts.html','/pwa-payment-drafts.css','/pwa-payment-drafts.js','/pwa-payment-vault.js','/pwa-client.js'];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(PUBLIC.map(path=>new Request(new URL(path,self.location.origin),{cache:'reload'}))))));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>(key.startsWith('schoolsafe-public-')||key.startsWith('schoolsafe-static-'))&&key!==CACHE&&key!==STATIC_CACHE).map(key=>caches.delete(key))))));
self.addEventListener('fetch',event=>{
  const request=event.request,url=new URL(request.url);
  if(request.method!=='GET'||url.origin!==self.location.origin)return;
  if(PUBLIC.includes(url.pathname)&&!url.search){
    event.respondWith(caches.open(CACHE).then(async cache=>{
      try{
        const response=await fetch(request,{cache:'no-cache'});
        if(response.ok)await cache.put(url.pathname,response.clone()).catch(()=>{});
        return response;
      }catch{return cache.match(url.pathname);}
    }));
  }else if(request.mode==='navigate'){
    // Never persist authenticated HTML, financial responses or form submissions.
    event.respondWith(fetch(request).catch(()=>caches.open(CACHE).then(cache=>cache.match('/schoolsafe-offline.html'))));
  }
});
// ===== Cache des bibliotheques et fichiers statiques (ajout 2026-10-27) =====
// Strategies type Workbox en vanilla (StaleWhileRevalidate / CacheFirst) :
// meme comportement, sans dependance au CDN Workbox (ferme).
const STATIC_CACHE='schoolsafe-static-v1';
self.addEventListener('fetch',event=>{
  const url=new URL(event.request.url);
  if(event.request.method!=='GET'||url.origin!==self.location.origin)return;
  const p=url.pathname;
  const swr=p.startsWith('/assets/libs/pdfjs/')||p.startsWith('/assets/libs/maplibre/')||p.startsWith('/assets/css/')||p.startsWith('/assets/js/')||p.startsWith('/assets/themes/');
  const immuable=p.startsWith('/assets/libs/tesseract/');
  if(!swr&&!immuable)return;
  event.respondWith((async()=>{
    const cache=await caches.open(STATIC_CACHE);
    const hit=await cache.match(event.request);
    if(immuable&&hit)return hit;
    const frais=fetch(event.request).then(reponse=>{
      if(reponse&&reponse.ok)cache.put(event.request,reponse.clone()).catch(()=>{});
      return reponse;
    }).catch(()=>hit);
    return hit||frais;
  })());
});
self.addEventListener('push',event=>{
  event.waitUntil(self.registration.showNotification('SchoolSafe',{
    body:'Une nouvelle notification est disponible.',tag:'schoolsafe-notifications',
    icon:'/schoolsafe-pwa-192.png',data:{url:'/parent-notifications-view.php'}
  }));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();
  event.waitUntil(self.clients.openWindow('/parent-notifications-view.php'));
});
