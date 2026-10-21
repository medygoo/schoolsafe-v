'use strict';
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const { chromium } = require('playwright-core');
const mode = process.argv[2];
if (!['playwright','axe','lighthouse'].includes(mode)) throw new Error('Expected playwright|axe|lighthouse');
const ROOT = path.join(__dirname, 'artifacts', mode);
fs.mkdirSync(ROOT, { recursive: true });
function html(broken) {
  return `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Qualification fictive SchoolSafe</title><style>body{color:#111;background:#fff;font:18px Arial;margin:30px}button{font:inherit;color:#111;background:#eee;padding:12px;border:2px solid #111}</style></head><body><main><h1>Qualification fictive</h1><p>Aucune donnée réelle.</p><button type="button">${broken ? '' : 'Valider le contrôle'}</button></main></body></html>`;
}
const result = { tool: mode, cases: [] };
const run = (args, cwd) => new Promise((resolve, reject) => {
  const p = spawn(process.execPath, args, { cwd, stdio: ['ignore','pipe','pipe'] });
  let log = ''; p.stdout.on('data', d => log += d); p.stderr.on('data', d => log += d);
  const timer = setTimeout(() => { p.kill(); reject(new Error('CLI timed out after 150 seconds')); }, 150000);
  p.on('error', reject); p.on('close', code => { clearTimeout(timer); resolve({ code, log }); });
});
(async () => {
  let browser, server;
  try {
    if (mode === 'playwright') {
      result.version = require('playwright-core/package.json').version;
      browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || undefined });
      for (const width of [1280, 390]) {
        for (const broken of [false, true]) {
          const context = await browser.newContext({ offline: true, serviceWorkers: 'block', viewport: { width, height: 850 } });
          try {
            const page = await context.newPage(); await context.route('**/*', r => r.abort());
            const action = broken ? '' : ' onclick="document.querySelector(\'output\').textContent=\'Validé\'"';
            await page.setContent(html(false).replace('<button type="button">', '<button type="button"' + action + '>').replace('</main>', '<output>En attente</output></main>'));
            await page.locator('button').click();
            let detected = false;
            try { assert.equal(await page.locator('output').textContent(), 'Validé'); }
            catch (e) { if (!(e instanceof assert.AssertionError)) throw e; detected = true; }
            assert.equal(detected, broken, 'Button assertion must distinguish fixtures');
            result.cases.push({ width, fixture: broken ? 'negative' : 'positive', defectDetected: detected, status: 'PASS' });
          } finally { await context.close(); }
        }
      }
    } else if (mode === 'axe') {
      const { AxeBuilder } = require('@axe-core/playwright');
      result.version = JSON.parse(fs.readFileSync(path.join(__dirname, 'node_modules/@axe-core/playwright/package.json'), 'utf8')).version;
      browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || undefined });
      for (const broken of [false, true]) {
        const context = await browser.newContext({ offline: true, serviceWorkers: 'block' });
        await context.route('**/*', r => r.abort());
        const page = await context.newPage(); await page.setContent(html(broken));
        const report = await new AxeBuilder({ page }).analyze();
        fs.writeFileSync(path.join(ROOT, broken ? 'negative.json' : 'positive.json'), JSON.stringify(report, null, 2));
        if (broken) assert.ok(report.violations.some(v => v.id === 'button-name'), 'Nameless button must be detected');
        else assert.equal(report.violations.length, 0, 'Clean fixture must have no violations');
        result.cases.push({ fixture: broken ? 'negative' : 'positive', violations: report.violations.map(v => v.id), status: 'PASS' });
        await context.close();
      }
    } else {
      result.version = require('@lhci/cli/package.json').version;
      const chromePath = process.env.CHROME_PATH || chromium.executablePath();
      assert.ok(fs.existsSync(chromePath), 'Pinned Chromium headless shell required');
      server = http.createServer((req,res) => {
        if (!['/positive','/negative'].includes(req.url)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', 'text/html; charset=utf-8'); res.end(html(req.url === '/negative'));
      });
      await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
      const cli = require.resolve('@lhci/cli/src/cli.js');
      for (const broken of [false, true]) {
        const name = broken ? 'negative' : 'positive'; const dir = path.join(ROOT, name); fs.mkdirSync(dir, { recursive: true });
        const config = { ci: { collect: { url: [`http://127.0.0.1:${server.address().port}/${name}`], numberOfRuns: 1, chromePath, settings: { onlyCategories: ['accessibility','performance','best-practices'], chromeFlags: '--headless --disable-background-networking --disable-component-update' + (process.env.GITHUB_ACTIONS === 'true' ? ' --no-sandbox' : '') } }, assert: { assertions: { 'categories:accessibility': ['error', { minScore: 1 }] } } } };
        fs.writeFileSync(path.join(dir, 'lighthouserc.json'), JSON.stringify(config, null, 2));
        const collect = await run([cli, 'collect'], dir); fs.writeFileSync(path.join(dir,'collect.log'), collect.log);
        // Only this anonymous loopback fixture is audited; no application context is logged.
        if (collect.code !== 0) result.collectionDiagnostic = collect.log.slice(-12000);
        assert.equal(collect.code, 0, 'Lighthouse collection must succeed');
        const check = await run([cli, 'assert'], dir); fs.writeFileSync(path.join(dir,'assert.log'), check.log);
        assert.equal(check.code, broken ? 1 : 0, 'Accessibility assertion must distinguish fixtures');
        result.cases.push({ fixture: name, collectionExit: collect.code, assertionExit: check.code, status: 'PASS' });
      }
    }
    result.status = 'GREEN';
  } catch (e) { result.status = 'RED'; result.error = e.message; process.exitCode = 1; }
  finally {
    if (browser) await browser.close();
    if (server) await new Promise(resolve => server.close(resolve));
    fs.writeFileSync(path.join(ROOT,'verification.json'), JSON.stringify(result,null,2));
    console.log(JSON.stringify(result,null,2));
  }
})();
