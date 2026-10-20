/* SchoolSafe SMS central (recette isolee) : lignes PRODELI, quotas, routage,
 * idempotence, statuts SMSGate simules par stub PHP, permissions, raccord OTP
 * L4 et non-regression L4. Base jetable schoolsafe_l3_recipe (:8084).
 * Usage : node app/qa-sms.cjs
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const DB = 'schoolsafe-app-db-1', PGDATABASE = 'schoolsafe_l3_recipe';
const APP = 'schoolsafe-l3-recipe';
const PGPASSWORD = 'preview-local-jetable-32caracteres-xx';
const CENTRAL_PW = 'L3jetable-a1b2c3d4!';
const DIR_PW = 'SmsQa-Dir9!x';
let passed = 0, failed = 0;
function sql(s) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', 'schoolsafe', '-d', PGDATABASE, '-At', '-c', s], { encoding: 'utf8' }).trim();
}
function php(code) {
  return execFileSync('docker', ['exec', APP, 'php', '-r',
    'chdir("/var/www/html"); require "Warehouse.php"; require "ProgramFunctions/SchoolSafeSms.fnc.php"; ' + code],
    { encoding: 'utf8' }).trim();
}
function phpCrypt(pw) {
  return execFileSync('docker', ['exec', APP, 'php', '-r',
    'echo crypt($argv[1], "$6$".substr(strtr(base64_encode(random_bytes(12)),"+","."),0,16)."$");',
    pw], { encoding: 'utf8' }).trim();
}
async function check(name, fn) {
  try { const r = await fn(); ++passed; console.log('PASS ' + name + (r ? ' — ' + r : '')); }
  catch (e) { ++failed; console.log('FAIL ' + name + ': ' + String((e && e.message) || e).split('\n').slice(0, 6).join(' | ')); }
}
function client() {
  const jar = new Map();
  async function request(path, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (jar.size) headers.Cookie = [...jar].map(([k, v]) => k + '=' + v).join('; ');
    if (options.form) {
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      options = { ...options, body: new URLSearchParams(options.form).toString() };
    }
    const r = await fetch('http://127.0.0.1:8084' + path, { redirect: 'manual', ...options, headers });
    for (const raw of (r.headers.getSetCookie ? r.headers.getSetCookie() : [])) {
      const [pair] = raw.split(';'); const i = pair.indexOf('=');
      const n = pair.slice(0, i).trim(), v = pair.slice(i + 1).trim();
      if (v === '' || /deleted/i.test(raw)) jar.delete(n); else jar.set(n, v);
    }
    return { status: r.status, location: r.headers.get('location') || '', body: await r.text() };
  }
  return { request, async login(u, p) { await request('/index.php'); return request('/index.php', { method: 'POST', form: { USERNAME: u, PASSWORD: p } }); } };
}
const tokenOf = (h) => (h.match(/name="token" value="([^"]+)"/) || [])[1] || '';
async function main() {
  sql(`DELETE FROM schoolsafe_sms_outbox; DELETE FROM schoolsafe_sms_quota_ledger; DELETE FROM schoolsafe_sms_audit;`);
  sql(`DELETE FROM config WHERE TITLE='SMS_SIMULATE';`);
  sql(`UPDATE schoolsafe_sms_lines SET phone_e164='',device_id='',status='disabled',quota_total=0,quota_used=0,alert_threshold=0,conn_state='unknown' WHERE id IN (1,2,3,4);`);
  const dh = phpCrypt(DIR_PW).replace(/'/g, '');
  const ch = phpCrypt(CENTRAL_PW).replace(/'/g, '');
  sql(`UPDATE staff SET PASSWORD='${ch}',LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=0 WHERE STAFF_ID=1 AND SYEAR=2026;`);
  sql(`UPDATE staff SET PASSWORD='${dh}',LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=0 WHERE USERNAME='l3directeur' AND SYEAR=2026;`);
  sql(`UPDATE staff SET FAILED_LOGIN=0 WHERE USERNAME='l3central' AND SYEAR=2026;`);

  await check('S1 migration 036 additive + idempotente (4 lignes, 0 doublon)', async () => {
    const fs = require('node:fs');
    const file = fs.readFileSync('app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-remediation/036-sms-lines.sql', 'utf8');
    execFileSync('docker', ['exec', '-i', '-e', 'PGPASSWORD=' + PGPASSWORD, DB, 'psql', '-U', 'schoolsafe', '-d', PGDATABASE, '-v', 'ON_ERROR_STOP=1'], { input: file, encoding: 'utf8' });
    assert.equal(sql(`SELECT count(*) FROM schoolsafe_sms_lines;`), '4', '4 emplacements');
    assert.equal(sql(`SELECT count(DISTINCT operator) FROM schoolsafe_sms_lines;`), '4', 'un par operateur');
    return 'rejou exit 0';
  });

  await check('S2 hub ferme sans PRODELI, viewport present', async () => {
    const anon = await client().request('/plateforme/prodeli/sms');
    assert.ok(anon.status === 403 || anon.status === 302, 'anon refuse, got ' + anon.status);
    const central = client();
    assert.equal((await central.login('l3central', CENTRAL_PW)).status, 302, 'central connecte');
    const hub = await central.request('/plateforme/prodeli/sms');
    assert.equal(hub.status, 200, 'hub 200');
    assert.match(hub.body, /name="viewport"/, 'viewport');
    for (const op of ['vodacom', 'airtel', 'orange', 'africell']) assert.match(hub.body, new RegExp(op), op);
    return '403 anon, 200 PRODELI';
  });

  await check('S3 directeur non habilite refuse (403)', async () => {
    const d = client();
    assert.equal((await d.login('l3directeur', DIR_PW)).status, 302, 'directeur connecte');
    const hub = await d.request('/plateforme/prodeli/sms');
    assert.equal(hub.status, 403, 'directeur 403, got ' + hub.status);
    return 'perimetre etanche';
  });

  await check('S4 enregistrement : invalide refuse, confirmation exigee, audit', async () => {
    const c = client();
    await c.login('l3central', CENTRAL_PW);
    const tok = tokenOf((await c.request('/plateforme/prodeli/sms')).body);
    assert.ok(tok, 'jeton hub');
    const bad = await c.request('/plateforme/prodeli/sms', { method: 'POST',
      form: { token: tok, sms_action: 'line_save', line_id: '1', phone: 'notaphone', device_id: '', sim_number: '1', quota_total: '100', alert_threshold: '10' } });
    assert.match(bad.body, /\+243/, 'numero invalide refuse');
    const prev = await c.request('/plateforme/prodeli/sms', { method: 'POST',
      form: { token: tok, sms_action: 'line_save', line_id: '1', phone: '+243815000011', device_id: 'dev-voda-1', sim_number: '1', quota_total: '100', alert_threshold: '10' } });
    assert.match(prev.body, /Confirmation requise/, 'previsualisation avant ecriture');
    assert.equal(sql(`SELECT phone_e164 FROM schoolsafe_sms_lines WHERE id=1;`), '', 'rien ecrit sans confirmation');
    const ok = await c.request('/plateforme/prodeli/sms', { method: 'POST',
      form: { token: tok, sms_action: 'line_save', line_id: '1', phone: '+243815000011', device_id: 'dev-voda-1', sim_number: '1', quota_total: '100', alert_threshold: '10', confirm: '1', confirm_box: '1' } });
    assert.match(ok.body, /enregistree/, 'ligne enregistree');
    assert.equal(sql(`SELECT phone_e164||'|'||device_id FROM schoolsafe_sms_lines WHERE id=1;`), '+243815000011|dev-voda-1', 'persiste');
    assert.ok(parseInt(sql(`SELECT count(*) FROM schoolsafe_sms_audit WHERE action='line_update' AND line_id=1;`), 10) >= 1, 'audit');
    return 'validation + confirmation + audit';
  });

  await check('S5 activation : sans materiel refusee, avec quota OK', async () => {
    const c = client();
    await c.login('l3central', CENTRAL_PW);
    const tok = tokenOf((await c.request('/plateforme/prodeli/sms')).body);
    const noDev = await c.request('/plateforme/prodeli/sms', { method: 'POST',
      form: { token: tok, sms_action: 'line_status', line_id: '2', status: 'active', confirm: '1', confirm_box: '1' } });
    assert.match(noDev.body, /numero ET appareil requis/, 'active sans materiel refuse');
    assert.equal(sql(`SELECT status FROM schoolsafe_sms_lines WHERE id=2;`), 'disabled', 'statut inchange');
    const ok = await c.request('/plateforme/prodeli/sms', { method: 'POST',
      form: { token: tok, sms_action: 'line_status', line_id: '1', status: 'active', confirm: '1', confirm_box: '1' } });
    assert.match(ok.body, /active/, 'activee');
    assert.equal(sql(`SELECT status FROM schoolsafe_sms_lines WHERE id=1;`), 'active', 'persiste');
    return 'materiel + quota exiges';
  });

  await check('S6 routage : prefixe prefere, repli, sans ligne = motif explicite', async () => {
    const r = php(`$p=SchoolSafeSmsPickLine('vodacom',1); echo "vodacom=".( $p['line'] ? (string)$p['line']['OPERATOR'] : '-' ).";"; $q=SchoolSafeSmsPickLine('africell',1); echo "repli=".( $q['line'] ? (string)$q['line']['OPERATOR'] : '-' ).";"; echo "net081=".var_export(SchoolSafeSmsNetworkOf('0815000001'),true).";"; echo "net099=".var_export(SchoolSafeSmsNetworkOf('0995000001'),true).";"; echo "net086=".var_export(SchoolSafeSmsNetworkOf('0865000001'),true).";";`);
    assert.match(r, /vodacom=vodacom/, 'prefixe prefere, got ' + r);
    assert.match(r, /repli=vodacom/, 'repli sur operationnelle, got ' + r);
    assert.match(r, /net081='vodacom'/, '081 vodacom');
    assert.match(r, /net099='airtel'/, '099 airtel');
    assert.match(r, /net086=false/, '086 exclu (incertain)');
    sql(`UPDATE schoolsafe_sms_lines SET status='suspended' WHERE id=1;`);
    const b = php(`$p=SchoolSafeSmsPickLine('vodacom',1); echo "err=".$p['error'].";";`);
    assert.match(b, /err=NO_SMS_LINE/, 'aucune ligne = motif, got ' + b);
    sql(`UPDATE schoolsafe_sms_lines SET status='active' WHERE id=1;`);
    return 'routage + repli + motif';
  });

  await check('S7 envoi stub : accepte, quota -1, rejeu sans double', async () => {
    const used0 = parseInt(sql(`SELECT quota_used FROM schoolsafe_sms_lines WHERE id=1;`), 10);
    const key = 'qa-sms-s7-' + Date.now();
    const stub = `$stub=function($m,$u,$usr,$pwd,$b){ return ['http_code'=>202,'body'=>'{"id":"stub-1"}']; }; putenv('SMS_LINE_1_BASE=http://127.0.0.1:9'); putenv('SMS_LINE_1_USER=u'); putenv('SMS_LINE_1_PASS=p');`;
    const a = php(stub + ` $a=SchoolSafeSmsSend('+243815000021','quota test',['dedup_key'=>'` + key + `','purpose'=>'test','actor'=>'qa','http'=>$stub]); echo "a=".json_encode($a).";";`);
    assert.match(a, /"status":"accepted"/, 'accepte, got ' + a);
    assert.match(a, /"via":"smsgate"/, 'via smsgate');
    const used1 = parseInt(sql(`SELECT quota_used FROM schoolsafe_sms_lines WHERE id=1;`), 10);
    assert.equal(used1, used0 + 1, 'quota -1 segment');
    assert.equal(sql(`SELECT count(*) FROM schoolsafe_sms_quota_ledger WHERE line_id=1 AND reason='send';`), '1', 'historise');
    const b = php(stub + ` $b=SchoolSafeSmsSend('+243815000021','quota test',['dedup_key'=>'` + key + `','purpose'=>'test','actor'=>'qa','http'=>$stub]); echo "b=".json_encode($b).";";`);
    assert.match(b, /"via":"dedup"/, 'rejeu detecte, got ' + b);
    const used2 = parseInt(sql(`SELECT quota_used FROM schoolsafe_sms_lines WHERE id=1;`), 10);
    assert.equal(used2, used1, 'aucun re-decompte');
    return 'accepte + quota + idempotence';
  });

  await check('S8 statuts : echec quota 0, echec reseau, polling delivered/processed', async () => {
    sql(`UPDATE schoolsafe_sms_lines SET quota_used=quota_total WHERE id=1;`);
    const blk = php(`$o=SchoolSafeSmsSend('+243815000022','bloque',['dedup_key'=>'qa-sms-s8a-${Date.now()}','purpose'=>'test','actor'=>'qa','http'=>function($m,$u,$a,$b,$c){ return ['http_code'=>202,'body'=>'{}']; }]); echo "s=".$o['status'].":".($o['error'] ?? '').";";`);
    assert.match(blk, /s=blocked:NO_QUOTA_OR_OFFLINE/, 'quota 0 bloque, got ' + blk);
    sql(`UPDATE schoolsafe_sms_lines SET quota_used=0 WHERE id=1;`);
    const net = php(`putenv('SMS_LINE_1_BASE=http://127.0.0.1:9'); putenv('SMS_LINE_1_USER=u'); putenv('SMS_LINE_1_PASS=p'); $o=SchoolSafeSmsSend('+243815000023','reseau',['dedup_key'=>'qa-sms-s8b-${Date.now()}','purpose'=>'test','actor'=>'qa','http'=>function($m,$u,$a,$b,$c){ throw new RuntimeException('down'); }]); echo "s=".$o['status'].":".($o['error'] ?? '').";";`);
    assert.match(net, /s=failed:SMSGATE_NETWORK/, 'panne explicite, got ' + net);
    const pol = php(`putenv('SMS_LINE_1_BASE=http://127.0.0.1:9'); putenv('SMS_LINE_1_USER=u'); putenv('SMS_LINE_1_PASS=p'); $h=function($m,$u,$a,$b,$c){ return ['http_code'=>202,'body'=>'{"id":"mid-9"}']; }; $o=SchoolSafeSmsSend('+243815000024','poll',['dedup_key'=>'qa-sms-s8c-${Date.now()}','purpose'=>'test','actor'=>'qa','http'=>$h]); $id=$o['outbox_id']; $g=function($m,$u,$a,$b,$c){ return ['http_code'=>200,'body'=>'{"state":"delivered"}']; }; $p1=SchoolSafeSmsPoll($id,$g); $p2=SchoolSafeSmsPoll($id,$g); echo "p1=".$p1['status'].":".$p1['via'].";p2=".$p2['status'].":".$p2['via'].";"; $o2=SchoolSafeSmsSend('+243815000025','poll2',['dedup_key'=>'qa-sms-s8d-${Date.now()}','purpose'=>'test','actor'=>'qa','http'=>$h]); $gp=function($m,$u,$a,$b,$c){ return ['http_code'=>200,'body'=>'{"state":"processed"}']; }; $p3=SchoolSafeSmsPoll($o2['outbox_id'],$gp); echo "p3=".$p3['status'].";";`);
    assert.match(pol, /p1=delivered:poll/, 'livre, got ' + pol);
    assert.match(pol, /p2=delivered:cached/, 'stable, got ' + pol);
    assert.match(pol, /p3=accepted/, 'processed indetermine garde, got ' + pol);
    return 'blocage + echec + polling sans renvoi';
  });

  await check('S9 raccord OTP : simule L4 intact, routeur sans ligne = NO_SMS_PROVIDER', async () => {
    sql(`INSERT INTO config (SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES (0,'RECOVERY_SMS_SIMULATE','Y');`);
    let sim='';
    try {
    sim = execFileSync('docker', ['exec', APP, 'php', '-r',
      'chdir("/var/www/html"); require "Warehouse.php"; require "ProgramFunctions/SchoolSafeRecovery.fnc.php";'
      + ' $a=SchoolSafeRecoverySmsSend("+243815000031","code");'
      + ' echo "sim=".($a["sent"]?"1":"0").":".$a["via"].";";'],
      { encoding: 'utf8' }).trim();
    } finally {
    sql(`DELETE FROM config WHERE TITLE='RECOVERY_SMS_SIMULATE';`);
    }
    assert.match(sim, /sim=1:simulated/, 'parcours L4 simule, got ' + sim);
    const real = execFileSync('docker', ['exec', APP, 'php', '-r',
      'chdir("/var/www/html"); require "Warehouse.php"; require "ProgramFunctions/SchoolSafeRecovery.fnc.php";'
      + ' try { SchoolSafeRecoverySmsSend("+243815000032","code"); echo "real=UNEXPECTED;"; }'
      + ' catch (Exception $e) { echo "real=".$e->getMessage().";"; }'],
      { encoding: 'utf8' }).trim();
    assert.match(real, /real=NO_SMS_PROVIDER/, 'sans ligne echec explicite, got ' + real);
    return 'compatibilite OTP';
  });;

  await check('S10 non-regression L4 : 11/11 avec le raccord', async () => {
    sql(`DELETE FROM schoolsafe_sms_outbox; DELETE FROM schoolsafe_sms_quota_ledger; DELETE FROM schoolsafe_sms_audit;`);
    sql(`UPDATE schoolsafe_sms_lines SET phone_e164='',device_id='',status='disabled',quota_total=0,quota_used=0,alert_threshold=0,conn_state='unknown' WHERE id IN (1,2,3,4);`);
    const out = execFileSync('node', ['app/qa-l4-recuperation.cjs'], { encoding: 'utf8' });
    assert.match(out, /11 PASS, 0 FAIL/, 'L4 intact, got ' + out.split('\n').slice(-2).join(' '));
    return 'L4 vert';
  });

  console.log(`\nSMS : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
}
main().catch((e) => { console.error('FATAL', e); process.exit(2); });
