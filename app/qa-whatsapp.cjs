/* SchoolSafe WhatsApp central (recette isolee) : WAHA principal, Evolution
 * secours, sessions PRODELI, consentement, confidentialite, idempotence,
 * statuts simules par stub PHP, miroir Studio et SMS conserve.
 * Base jetable schoolsafe_l3_recipe (:8084). Usage : node app/qa-whatsapp.cjs
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
    'chdir("/var/www/html"); require "Warehouse.php"; require "ProgramFunctions/SchoolSafeSms.fnc.php"; require "ProgramFunctions/SchoolSafeWhatsapp.fnc.php"; ' + code],
    { encoding: 'utf8' }).trim();
}
function phpCrypt(pw) {
  return execFileSync('docker', ['exec', APP, 'php', '-r',
    'echo crypt($argv[1], "$6$".substr(strtr(base64_encode(random_bytes(12)),"+","."),0,16)."$");',
    pw], { encoding: 'utf8' }).trim();
}
function dispatch(args) {
  return execFileSync('docker', ['exec', APP, 'php', '/var/www/html/schoolsafe-notify-dispatch.php', ...args],
    { encoding: 'utf8' }).trim();
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
const CREDS = `putenv('WAHA_BASE=http://127.0.0.1:9'); putenv('WAHA_KEY=k1'); putenv('EVOLUTION_BASE=http://127.0.0.1:9'); putenv('EVOLUTION_KEY=k2');`;
async function main() {
  sql(`DELETE FROM schoolsafe_wa_outbox; DELETE FROM schoolsafe_wa_audit; DELETE FROM schoolsafe_wa_consents;`);
  sql(`DELETE FROM schoolsafe_notify_deliveries WHERE event_id IN (SELECT id FROM schoolsafe_notify_events WHERE dedup_key LIKE 'qa-wa-%');`);
  sql(`DELETE FROM schoolsafe_notify_events WHERE dedup_key LIKE 'qa-wa-%';`);
  sql(`DELETE FROM config WHERE TITLE='WHATSAPP_SIMULATE';`);
  sql(`UPDATE schoolsafe_wa_sessions SET phone_e164='',status='disabled',conn_state='unknown' WHERE session_name IN ('schoolsafe-waha-1','schoolsafe-evolution-1');`);
  const dh = phpCrypt(DIR_PW).replace(/'/g, '');
  const ch = phpCrypt(CENTRAL_PW).replace(/'/g, '');
  sql(`UPDATE staff SET PASSWORD='${ch}',LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=0 WHERE USERNAME='l3central' AND SYEAR=2026;`);
  sql(`UPDATE staff SET PASSWORD='${dh}',LAST_LOGIN=CURRENT_TIMESTAMP,FAILED_LOGIN=0 WHERE USERNAME='l3directeur' AND SYEAR=2026;`);

  await check('W1 migration 037 additive + idempotente (2 sessions, noms distincts)', async () => {
    const fs = require('node:fs');
    const file = fs.readFileSync('app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-remediation/037-whatsapp.sql', 'utf8');
    execFileSync('docker', ['exec', '-i', '-e', 'PGPASSWORD=' + PGPASSWORD, DB, 'psql', '-U', 'schoolsafe', '-d', PGDATABASE, '-v', 'ON_ERROR_STOP=1'], { input: file, encoding: 'utf8' });
    assert.equal(sql(`SELECT count(*) FROM schoolsafe_wa_sessions;`), '2', '2 sessions');
    assert.equal(sql(`SELECT count(DISTINCT session_name) FROM schoolsafe_wa_sessions;`), '2', 'noms distincts');
    assert.equal(sql(`SELECT count(*) FROM schoolsafe_sms_lines;`), '4', 'SMS 036 intact');
    return 'rejou exit 0';
  });

  await check('W2 hub ferme sans PRODELI, viewport present', async () => {
    const anon = await client().request('/plateforme/prodeli/whatsapp');
    assert.ok(anon.status === 403 || anon.status === 302, 'anon refuse, got ' + anon.status);
    const central = client();
    assert.equal((await central.login('l3central', CENTRAL_PW)).status, 302, 'central connecte');
    const hub = await central.request('/plateforme/prodeli/whatsapp');
    assert.equal(hub.status, 200, 'hub 200, got ' + hub.status);
    assert.match(hub.body, /name="viewport"/, 'viewport');
    assert.match(hub.body, /waha/, 'moteur principal');
    assert.match(hub.body, /evolution/, 'moteur secours');
    return '403 anon, 200 PRODELI';
  });

  await check('W3 directeur non habilite refuse (403)', async () => {
    const d = client();
    assert.equal((await d.login('l3directeur', DIR_PW)).status, 302, 'directeur connecte');
    const hub = await d.request('/plateforme/prodeli/whatsapp');
    assert.equal(hub.status, 403, 'directeur 403, got ' + hub.status);
    return 'perimetre etanche';
  });

  await check('W4 sessions : nom invalide/partage refuses, confirmation exigee, audit', async () => {
    const c = client();
    await c.login('l3central', CENTRAL_PW);
    const tok = tokenOf((await c.request('/plateforme/prodeli/whatsapp')).body);
    assert.ok(tok, 'jeton hub');
    const bad = await c.request('/plateforme/prodeli/whatsapp', { method: 'POST',
      form: { token: tok, wa_action: 'session_save', session_id: '1', session_name: 'INVALIDE!!', phone: '+243815000011' } });
    assert.match(bad.body, /Nom de session invalide/, 'nom invalide refuse');
    const shared = await c.request('/plateforme/prodeli/whatsapp', { method: 'POST',
      form: { token: tok, wa_action: 'session_save', session_id: '1', session_name: 'schoolsafe-evolution-1', phone: '+243815000011' } });
    assert.match(shared.body, /partage interdit/, 'partage interdit, got ' + shared.body.slice(shared.body.indexOf('role='), shared.body.indexOf('role=') + 120));
    const prev = await c.request('/plateforme/prodeli/whatsapp', { method: 'POST',
      form: { token: tok, wa_action: 'session_save', session_id: '1', session_name: 'schoolsafe-waha-1', phone: '+243815000011' } });
    assert.match(prev.body, /Confirmation requise/, 'previsualisation avant ecriture');
    assert.equal(sql(`SELECT phone_e164 FROM schoolsafe_wa_sessions WHERE id=1;`), '', 'rien ecrit sans confirmation');
    const ok = await c.request('/plateforme/prodeli/whatsapp', { method: 'POST',
      form: { token: tok, wa_action: 'session_save', session_id: '1', session_name: 'schoolsafe-waha-1', phone: '+243815000011', confirm: '1', confirm_box: '1' } });
    assert.match(ok.body, /enregistree/, 'session enregistree');
    assert.ok(parseInt(sql(`SELECT count(*) FROM schoolsafe_wa_audit WHERE action='session_save';`), 10) >= 1, 'audit');
    return 'validation + anti-partage + confirmation + audit';
  });

  await check('W5 activation : sans numero refusee, avec numero OK', async () => {
    const c = client();
    await c.login('l3central', CENTRAL_PW);
    const tok = tokenOf((await c.request('/plateforme/prodeli/whatsapp')).body);
    const noPhone = await c.request('/plateforme/prodeli/whatsapp', { method: 'POST',
      form: { token: tok, wa_action: 'session_status', session_id: '2', status: 'active', confirm: '1', confirm_box: '1' } });
    assert.match(noPhone.body, /numero ET session requis/, 'active sans numero refuse');
    assert.equal(sql(`SELECT status FROM schoolsafe_wa_sessions WHERE id=2;`), 'disabled', 'statut inchange');
    const ok = await c.request('/plateforme/prodeli/whatsapp', { method: 'POST',
      form: { token: tok, wa_action: 'session_status', session_id: '1', status: 'active', confirm: '1', confirm_box: '1' } });
    assert.match(ok.body, /active/, 'activee');
    assert.equal(sql(`SELECT status FROM schoolsafe_wa_sessions WHERE id=1;`), 'active', 'persiste');
    return 'materiel verifie exige';
  });

  await check('W6 consentement : sans opt-in = CONSENT_REQUIRED, avec Y = accepte', async () => {
    const stub = `$waha=function($m,$u,$k,$b){ return ['http_code'=>200,'body'=>'{"id":"w-1"}']; };`;
    const noc = php(CREDS + stub + ` $o=SchoolSafeWaSend('+243815000021','bonjour ecole',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'qa-wa-w6a-${Date.now()}','http_waha'=>$waha]); echo "s=".$o['status'].":".($o['error'] ?? '').";";`);
    assert.match(noc, /s=blocked:CONSENT_REQUIRED/, 'sans consentement bloque, got ' + noc);
    sql(`INSERT INTO schoolsafe_wa_consents(school_id,staff_id,phone_e164,consented) VALUES (1,3,'+243815000021','Y');`);
    const okc = php(CREDS + stub + ` $o=SchoolSafeWaSend('+243815000021','bonjour ecole',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'qa-wa-w6b-${Date.now()}','http_waha'=>$waha]); echo "s=".$o['status'].":".($o['via'] ?? '').";";`);
    assert.match(okc, /s=accepted:waha/, 'avec consentement accepte, got ' + okc);
    return 'opt-in par ecole';
  });

  await check('W7 confidentialite : mot de passe/lien secret/code bloques, rien en outbox', async () => {
    const n0 = parseInt(sql(`SELECT count(*) FROM schoolsafe_wa_outbox;`), 10);
    const cases = [
      'voici votre mot de passe : abc123',
      'ouvrez ce lien secret https://x.test/reset?token=abc pour continuer',
      'votre code de récupération est 482913',
    ];
    for (let i = 0; i < cases.length; i++) {
      const t = cases[i].replace(/'/g, '');
      const r = php(`$o=SchoolSafeWaSend('+243815000021','${t}',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','dedup_key'=>'qa-wa-w7-${Date.now()}-${i}']); echo "s=".$o['status'].":".($o['error'] ?? '').";";`);
      assert.match(r, /s=failed:SENSITIVE_BLOCKED/, 'bloque cas ' + i + ', got ' + r);
    }
    assert.equal(parseInt(sql(`SELECT count(*) FROM schoolsafe_wa_outbox;`), 10), n0, 'aucune trace ecrite');
    return '3 copies sensibles bloquees';
  });

  await check('W8 envoi WAHA stub : accepte, rejeu sans re-emission', async () => {
    const key = 'qa-wa-w8-' + Date.now();
    const stub = `$waha=function($m,$u,$k,$b){ return ['http_code'=>201,'body'=>'{"id":"w-8"}']; };`;
    const a = php(CREDS + stub + ` $o=SchoolSafeWaSend('+243815000021','info ecole',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'` + key + `','http_waha'=>$waha]); echo "a=".json_encode($o).";";`);
    assert.match(a, /"status":"accepted"/, 'accepte, got ' + a);
    assert.match(a, /"via":"waha"/, 'via waha');
    const n1 = parseInt(sql(`SELECT count(*) FROM schoolsafe_wa_outbox WHERE idempotency_key='${key}';`), 10);
    assert.equal(n1, 1, '1 ligne');
    const b = php(CREDS + stub + ` $o=SchoolSafeWaSend('+243815000021','info ecole',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'` + key + `','http_waha'=>$waha]); echo "b=".json_encode($o).";";`);
    assert.match(b, /"via":"dedup"/, 'rejeu detecte, got ' + b);
    const n2 = parseInt(sql(`SELECT count(*) FROM schoolsafe_wa_outbox WHERE idempotency_key='${key}';`), 10);
    assert.equal(n2, 1, 'aucun doublon');
    return 'accepte + idempotence';
  });

  await check('W9 bascule : 500 WAHA -> Evolution 1 seule fois ; 400 sans secours ; sans secours = motif', async () => {
    sql(`UPDATE schoolsafe_wa_sessions SET phone_e164='+243815000099',status='active',conn_state='unknown' WHERE id=2;`);
    const t = Date.now();
    const fb = php(CREDS + ` $calls=['waha'=>0,'evo'=>0];`
      + ` $waha=function($m,$u,$k,$b) use (&$calls){ $calls['waha']++; return ['http_code'=>500,'body'=>'down']; };`
      + ` $evo=function($m,$u,$k,$b) use (&$calls){ $calls['evo']++; return ['http_code'=>200,'body'=>'{"id":"e-9"}']; };`
      + ` $o=SchoolSafeWaSend('+243815000021','alerte ecole',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'qa-wa-w9a-${t}','http_waha'=>$waha,'http_evo'=>$evo]);`
      + ` echo "s=".$o['status'].":".($o['via'] ?? $o['error']).":waha=".$calls['waha'].":evo=".$calls['evo'].";";`
      + ` echo " eng=".DBGet("SELECT ENGINE FROM schoolsafe_wa_outbox WHERE idempotency_key='qa-wa-w9a-${t}'")[1]['ENGINE'].";";`);
    assert.match(fb, /s=accepted:evolution-fallback:waha=1:evo=1/, 'secours unique, got ' + fb);
    assert.match(fb, /eng=evolution/, 'moteur secours trace');
    assert.ok(parseInt(sql(`SELECT count(*) FROM schoolsafe_wa_audit WHERE action='fallback';`), 10) >= 1, 'bascule journalisee');
    const def = php(CREDS + ` $calls=0;`
      + ` $waha=function($m,$u,$k,$b){ return ['http_code'=>400,'body'=>'bad']; };`
      + ` $evo=function($m,$u,$k,$b) use (&$calls){ $calls++; return ['http_code'=>200,'body'=>'{}']; };`
      + ` $o=SchoolSafeWaSend('+243815000021','info',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'qa-wa-w9b-${t}','http_waha'=>$waha,'http_evo'=>$evo]);`
      + ` echo "s=".$o['status'].":".($o['error'] ?? '').":evo=".$calls.";";`);
    assert.match(def, /s=failed:WAHA_ERROR:400:evo=0/, '400 definitive sans secours, got ' + def);
    sql(`UPDATE schoolsafe_wa_sessions SET status='suspended' WHERE id=2;`);
    const nofb = php(CREDS + ` $waha=function($m,$u,$k,$b){ return ['http_code'=>502,'body'=>'x']; };`
      + ` $o=SchoolSafeWaSend('+243815000021','info',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'qa-wa-w9c-${t}','http_waha'=>$waha]);`
      + ` echo "s=".$o['status'].":".($o['error'] ?? '').";";`);
    assert.match(nofb, /s=failed:WAHA_FAILED_NO_FALLBACK/, 'sans secours motif explicite, got ' + nofb);
    sql(`UPDATE schoolsafe_wa_sessions SET status='disabled' WHERE id=2;`);
    return 'bascule securisee, zero double envoi';
  });

  await check('W10 statuts : polling delivered, indetermine garde sans renvoi', async () => {
    const t = Date.now();
    const pol = php(CREDS + ` $h=function($m,$u,$k,$b){ return ['http_code'=>200,'body'=>'{"id":"w-10"}']; };`
      + ` $o=SchoolSafeWaSend('+243815000021','suivi',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'qa-wa-w10a-${t}','http_waha'=>$h]); $id=$o['outbox_id'];`
      + ` $g=function($m,$u,$k,$b){ return ['http_code'=>200,'body'=>'{"state":"delivered"}']; };`
      + ` $p1=SchoolSafeWaPoll($id,$g); $p2=SchoolSafeWaPoll($id,$g); echo "p1=".$p1['status'].":".$p1['via'].";p2=".$p2['status'].":".$p2['via'].";";`
      + ` $o2=SchoolSafeWaSend('+243815000021','suivi2',['school_id'=>1,'staff_id'=>3,'actor'=>'qa','purpose'=>'notify','dedup_key'=>'qa-wa-w10b-${t}','http_waha'=>$h]);`
      + ` $gp=function($m,$u,$k,$b){ return ['http_code'=>200,'body'=>'{"state":"processed"}']; };`
      + ` $p3=SchoolSafeWaPoll($o2['outbox_id'],$gp); echo "p3=".$p3['status'].";";`);
    assert.match(pol, /p1=delivered:poll/, 'livre, got ' + pol);
    assert.match(pol, /p2=delivered:cached/, 'stable, got ' + pol);
    assert.match(pol, /p3=accepted/, 'indetermine garde, got ' + pol);
    return 'polling sans renvoi';
  });

  await check('W11 Studio : canal whatsapp emis, miroir push/email, dispatcher simule sans double', async () => {
    const t = Date.now();
    const emit = php(`require "ProgramFunctions/SchoolSafeNotify.fnc.php";`
      + ` $db=['get'=>function($q){ return DBGet(DBQuery($q)); },'exec'=>function($q){ return DBQuery($q); }];`
      + ` $r=SchoolSafeNotifyEmit(['school_id'=>1,'syear'=>2026,'event_type'=>'security.entry','dedup_key'=>'qa-wa-${t}-emit','student_id'=>1,'payload'=>[]],`
      + ` [['audience'=>'parent','staff_id'=>3,'channel'=>'whatsapp']],$db); echo "emit=".json_encode($r).";";`);
    assert.match(emit, /"created":true/, 'canal whatsapp accepte, got ' + emit);
    sql(`INSERT INTO schoolsafe_notify_events(school_id,syear,event_type,dedup_key,student_id,payload) VALUES (1,2026,'security.entry','qa-wa-${t}-ev',1,'{}');`);
    sql(`INSERT INTO schoolsafe_notify_deliveries(event_id,school_id,syear,audience,recipient_staff_id,channel) SELECT id,1,2026,'parent',3,'push' FROM schoolsafe_notify_events WHERE dedup_key='qa-wa-${t}-ev';`);
    const pushBefore = sql(`SELECT status FROM schoolsafe_notify_deliveries WHERE event_id=(SELECT id FROM schoolsafe_notify_events WHERE dedup_key='qa-wa-${t}-ev') AND channel='push';`);
    sql(`INSERT INTO config (SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES (0,'WHATSAPP_SIMULATE','Y');`);
    let d1 = '';
    try {
      sql(`UPDATE staff SET custom_200000001='+243815000021' WHERE staff_id=3;`);
      d1 = dispatch(['--once', '--channel=whatsapp']);
    } finally {
      sql(`DELETE FROM config WHERE TITLE='WHATSAPP_SIMULATE';`);
      sql(`UPDATE staff SET custom_200000001='' WHERE staff_id=3;`);
    }
    assert.match(d1, /DISPATCH/, 'dispatcher ok, got ' + d1);
    const waRow = sql(`SELECT status||':'||provider_msg_id FROM schoolsafe_notify_deliveries WHERE event_id=(SELECT id FROM schoolsafe_notify_events WHERE dedup_key='qa-wa-${t}-ev') AND channel='whatsapp';`);
    assert.match(waRow, /accepted:simulated/, 'miroir whatsapp simule, got ' + waRow);
    const pushAfter = sql(`SELECT status FROM schoolsafe_notify_deliveries WHERE event_id=(SELECT id FROM schoolsafe_notify_events WHERE dedup_key='qa-wa-${t}-ev') AND channel='push';`);
    assert.equal(pushAfter, pushBefore, 'push intouche (regle inchangee)');
    sql(`INSERT INTO config (SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES (0,'WHATSAPP_SIMULATE','Y');`);
    let d2 = '';
    try { d2 = dispatch(['--once', '--channel=whatsapp']); }
    finally { sql(`DELETE FROM config WHERE TITLE='WHATSAPP_SIMULATE';`); }
    assert.match(d2, /sent=0/, 'second passage sans double, got ' + d2);
    return 'miroir + simulate + zero double';
  });

  await check('W12 SMS conserve : qa-sms.cjs toujours vert', async () => {
    const out = execFileSync('node', ['app/qa-sms.cjs'], { encoding: 'utf8' });
    assert.match(out, /10 PASS, 0 FAIL/, 'SMS intact, got ' + out.split('\n').slice(-2).join(' '));
    return '4 reseaux intacts';
  });

  sql(`DELETE FROM schoolsafe_wa_outbox; DELETE FROM schoolsafe_wa_audit; DELETE FROM schoolsafe_wa_consents;`);
  sql(`DELETE FROM schoolsafe_notify_deliveries WHERE event_id IN (SELECT id FROM schoolsafe_notify_events WHERE dedup_key LIKE 'qa-wa-%');`);
  sql(`DELETE FROM schoolsafe_notify_events WHERE dedup_key LIKE 'qa-wa-%';`);
  sql(`DELETE FROM config WHERE TITLE='WHATSAPP_SIMULATE';`);
  sql(`UPDATE schoolsafe_wa_sessions SET phone_e164='',status='disabled',conn_state='unknown' WHERE session_name IN ('schoolsafe-waha-1','schoolsafe-evolution-1');`);

  console.log(`\nWHATSAPP : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
}
main().catch((e) => { console.error('FATAL', e); process.exit(2); });
