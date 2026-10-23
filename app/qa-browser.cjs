'use strict';
const assert=require('node:assert/strict');
module.exports=async function qaBrowser(chromium){
 const cdp=process.env.PRODELI_QA_CDP;
 if(cdp){const u=new URL(cdp);assert.equal(u.protocol,'http:');assert.equal(u.hostname,'127.0.0.1');assert.equal(u.port,'19227');assert(!u.username&&!u.password);return chromium.connectOverCDP(cdp);}
 return chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
};
