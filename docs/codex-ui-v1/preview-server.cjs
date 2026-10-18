/* Read-only preview server. Loopback only; no backend proxy or mutations. */
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../app');
const types = {'.html':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.png':'image/png','.jpg':'image/jpeg','.svg':'image/svg+xml','.woff2':'font/woff2','.json':'application/json; charset=utf-8'};
const server = http.createServer((req, res) => {
  if (!['GET','HEAD'].includes(req.method)) { res.writeHead(405); res.end(); return; }
  let file;
  try { file = path.resolve(root, '.' + decodeURIComponent(new URL(req.url, 'http://127.0.0.1').pathname)); }
  catch { res.writeHead(400); res.end(); return; }
  if (!file.startsWith(root + path.sep) || !types[path.extname(file)] || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
    res.writeHead(404); res.end('Not found'); return;
  }
  res.writeHead(200, {'Content-Type':types[path.extname(file)],'Cache-Control':'no-store','X-Content-Type-Options':'nosniff'});
  if (req.method === 'HEAD') res.end(); else fs.createReadStream(file).pipe(res);
});
server.listen(Number(process.argv[2] || 5187), '127.0.0.1', () => console.log('SchoolSafe preview: http://127.0.0.1:' + server.address().port + '/modules/codex-ui-v1/preview.html'));
