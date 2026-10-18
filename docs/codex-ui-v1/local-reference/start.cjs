const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');
const workspace = path.resolve(__dirname, '../../..');
const state = path.join(workspace, '.codex-local-reference-v1');
const source = 'C:/Users/PC/Documents/rosario';
const names = ['codex-ref-v1-db', 'codex-ref-v1-rosario', 'codex-ref-v1-appsmith'];
const appsmith = 'appsmith/appsmith-ce@sha256:b9b44edb71de4638d7b662e6dc0fc30bf5ef0e1f95353afb03e68ee518c8b460';
function run(args, capture = false) {
  const result = spawnSync('docker', args, { encoding: 'utf8', stdio: capture ? 'pipe' : 'inherit' });
  if (result.status !== 0) throw new Error('Commande Docker echouee: ' + args[0]);
  return result.stdout;
}
function exists(name) { return spawnSync('docker', ['inspect', name], { stdio: 'ignore' }).status === 0; }
function secret() { return crypto.randomBytes(32).toString('hex'); }
(async () => {
  if (!fs.existsSync(path.join(source, 'Warehouse.php'))) throw new Error('Source Rosario absente.');
  if (!fs.readFileSync(path.join(source, 'Warehouse.php'), 'utf8').includes("'12.9.4'")) throw new Error('Version Rosario inattendue.');
  if (names.some(exists)) throw new Error('Un conteneur de reference existe deja. Ne pas ecraser; inspecter son etat.');
  fs.mkdirSync(state, { recursive: true });
  const dbEnv = path.join(state, 'db.env'), appEnv = path.join(state, 'appsmith.env');
  if (!fs.existsSync(dbEnv)) fs.writeFileSync(dbEnv, 'POSTGRES_USER=rosario_test\nPOSTGRES_DB=rosario_test\nPOSTGRES_PASSWORD=' + secret() + '\n', { mode: 0o600 });
  if (!fs.existsSync(appEnv)) fs.writeFileSync(appEnv, 'APPSMITH_ENCRYPTION_PASSWORD=' + secret() + '\nAPPSMITH_ENCRYPTION_SALT=' + secret() + '\nAPPSMITH_DISABLE_TELEMETRY=true\n', { mode: 0o600 });
  run(['build', '-t', 'codex-ref-v1-php:local', __dirname]);
  run(['pull', 'postgres:16']);
  run(['pull', appsmith]);
  if (!exists('codex-ref-v1-network')) {
    const inspect = spawnSync('docker', ['network', 'inspect', 'codex-ref-v1-network'], { stdio: 'ignore' });
    if (inspect.status !== 0) run(['network', 'create', '--internal', 'codex-ref-v1-network']);
  }
  if (spawnSync('docker', ['network', 'inspect', 'codex-ref-v1-web'], { stdio: 'ignore' }).status !== 0) {
    run(['network', 'create', 'codex-ref-v1-web']);
  }
  for (const volume of ['codex-ref-v1-pg', 'codex-ref-v1-appsmith-data']) {
    if (spawnSync('docker', ['volume', 'inspect', volume], { stdio: 'ignore' }).status === 0) throw new Error('Volume existant: validation manuelle requise avant reutilisation.');
    run(['volume', 'create', volume]);
  }
  run(['run', '-d', '--name', names[0], '--network', 'codex-ref-v1-network', '--memory', '512m', '--env-file', dbEnv,
    '--mount', 'type=volume,source=codex-ref-v1-pg,target=/var/lib/postgresql/data',
    '--mount', `type=bind,source=${source}/rosariosis.sql,target=/docker-entrypoint-initdb.d/01-rosario.sql,readonly`, 'postgres:16']);
  let ready = false;
  for (let i = 0; i < 90; i++) {
    const result = spawnSync('docker', ['exec', names[0], 'psql', '-U', 'rosario_test', '-d', 'rosario_test', '-Atc', "SELECT count(*) FROM config WHERE title='VERSION'"], { encoding: 'utf8' });
    if (result.status === 0 && result.stdout.trim() === '1') { ready = true; break; }
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  if (!ready) throw new Error('Initialisation de la base non confirmee. Ne pas poursuivre.');
  run(['run', '-d', '--name', names[1], '--network', 'codex-ref-v1-network', '--memory', '768m', '-p', '127.0.0.1:18082:80', '--env-file', dbEnv,
    '--mount', `type=bind,source=${source},target=/var/www/html,readonly`,
    '--mount', `type=bind,source=${path.join(__dirname, 'config.inc.php')},target=/var/www/html/config.inc.php,readonly`, 'codex-ref-v1-php:local']);
  run(['network', 'connect', 'codex-ref-v1-web', names[1]]);
  run(['run', '-d', '--name', names[2], '--memory', '4g', '-p', '127.0.0.1:18083:80', '--env-file', appEnv,
    '--mount', 'type=volume,source=codex-ref-v1-appsmith-data,target=/appsmith-stacks', appsmith]);
  console.log('Conteneurs de test crees. Rosario: http://127.0.0.1:18082 ; Appsmith: http://127.0.0.1:18083. Verification HTTP et initialisation Appsmith encore requises.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
