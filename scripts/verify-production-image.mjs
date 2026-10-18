#!/usr/bin/env node
/**
 * SchoolSafe — preuve sur l'IMAGE DE PRODUCTION.
 *
 * Une correction validée sur le conteneur de qualification ne prouve pas qu'elle
 * fonctionne dans l'environnement final. Ce script rejoue la même vérification
 * contre une image construite depuis `Dockerfile.vps` (PHP 8.3 durci) et
 * `config.inc.production.php`, comme sur le VPS.
 *
 * Étapes :
 *   1. `php -l` sur les fichiers PHP du runtime modifiés (git HEAD + nouveaux) ;
 *   2. construction de l'image (cache Docker réutilisé) ;
 *   3. conteneur jetable sur 127.0.0.1:<port libre>, même base QA, même réseau ;
 *   4. attente d'une réponse HTTP 200 sur /index.php ;
 *   5. exécution des suites `qa-*.cjs` demandées (par défaut celle du lot L1) ;
 *   6. arrêt et suppression du conteneur jetable, code de sortie propagé.
 *
 * INVARIANT — aucune migration n'est appliquée ici : les migrations s'appliquent
 * uniquement pendant un déploiement explicitement autorisé (voir
 * ops/deployment/VPS-DEPLOY.md). Ce script ne touche ni à la pile locale
 * existante, ni à la base, ni à un environnement distant.
 *
 * Usage :
 *   node scripts/verify-production-image.mjs
 *   node scripts/verify-production-image.mjs --suite app/qa-l1-account-state.cjs --port 8099
 *
 * Variables (facultatives) :
 *   SCHOOLSAFE_QA_DB_PASSWORD  mot de passe de la base QA locale
 *   SCHOOLSAFE_QA_NETWORK      réseau Docker de la pile (défaut schoolsafe-app_backend)
 *   SCHOOLSAFE_QA_DB_HOST      alias du service PostgreSQL (défaut db)
 *   SCHOOLSAFE_QA_DB_NAME / SCHOOLSAFE_QA_DB_USER
 */
import { spawnSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { existsSync } from 'node:fs';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const runtimePrefix = 'app/modules/codex-ui-v1/extensions-test-runtime/';
const runtimeDir = path.join(repoRoot, runtimePrefix);
const dockerfile = path.join(runtimeDir, 'Dockerfile.vps');
const productionConfig = path.join(repoRoot, 'ops/deployment/config.inc.production.php');

const argv = process.argv.slice(2);
const option = (name, fallback = '') => {
  const index = argv.indexOf('--' + name);
  return index >= 0 && argv[index + 1] ? argv[index + 1] : fallback;
};

// `--suite` peut être répété ; par défaut, toutes les suites du lot courant.
const requestedSuites = argv
  .map((value, index) => (value === '--suite' ? argv[index + 1] : ''))
  .filter(Boolean);
const suites = requestedSuites.length
  ? requestedSuites
  : ['app/qa-l1-account-state.cjs', 'app/qa-dashboard-periods.cjs', 'app/qa-school-calendar.cjs'];
const port = Number(option('port', '0')) || 0;
const image = option('image', 'schoolsafe-verify-prod:local');
const network = process.env.SCHOOLSAFE_QA_NETWORK || 'schoolsafe-app_backend';
const dbHost = process.env.SCHOOLSAFE_QA_DB_HOST || 'db';
const dbPort = process.env.SCHOOLSAFE_QA_DB_PORT || '5432';
const dbName = process.env.SCHOOLSAFE_QA_DB_NAME || 'schoolsafe';
const dbUser = process.env.SCHOOLSAFE_QA_DB_USER || 'schoolsafe';
const dbPassword = process.env.SCHOOLSAFE_QA_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const containerName = 'schoolsafe-verify-prod-' + process.pid;

// Jeton de service de la console (création d'école) : propre à chaque exécution,
// transmis au conteneur jetable ET à la suite QA. Ce n'est pas un secret de
// production : le conteneur vit quelques minutes et n'écoute que sur loopback.
const consoleToken = process.env.SCHOOLSAFE_CONSOLE_TOKEN || randomUUID().replace(/-/g, '');

function run(command, args, options = {}) {
  return spawnSync(command, args, { encoding: 'utf8', ...options });
}

function must(command, args, options = {}) {
  const result = run(command, args, options);
  if (result.error || result.status !== 0) {
    const detail = (result.stderr || result.stdout || result.error?.message || '').trim();
    throw new Error(command + ' ' + args.join(' ') + ' a échoué (code ' + result.status + ')\n' + detail);
  }
  return result.stdout || '';
}

function availablePort() {
  if (port) return Promise.resolve(port);
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
      const { port: free } = server.address();
      server.close(() => resolve(free));
    });
  });
}

/**
 * Fichiers PHP du runtime touchés par la correction : modifications en cours,
 * fichiers non suivis, ET fichiers du dernier commit. Sans ce dernier point, une
 * vérification lancée sur un arbre propre (cas normal avant un déploiement) ne
 * contrôlerait plus rien.
 */
function changedRuntimePhpFiles() {
  const list = args => must('git', ['-C', repoRoot, ...args])
    .split('\n').map(line => line.trim()).filter(Boolean);

  const files = [
    ...list(['diff', '--name-only', 'HEAD']),
    ...list(['ls-files', '--others', '--exclude-standard']),
    ...list(['show', '--name-only', '--format=', 'HEAD']),
  ];

  return [...new Set(files)]
    .filter(file => /\.php$/.test(file))
    .filter(file => file.startsWith(runtimePrefix))
    .filter(file => existsSync(path.join(repoRoot, file)))
    .map(file => './' + file.slice(runtimePrefix.length));
}

async function runSuites(freePort) {
  const url = 'http://127.0.0.1:' + freePort;
  let status = 0;

  console.log('RUN  conteneur jetable ' + containerName + ' sur ' + url);
  must('docker', ['run', '-d', '--name', containerName,
    '--network', network,
    '-p', '127.0.0.1:' + freePort + ':80',
    '-e', 'POSTGRES_DB=' + dbName,
    '-e', 'POSTGRES_USER=' + dbUser,
    '-e', 'POSTGRES_PASSWORD=' + dbPassword,
    '-e', 'SCHOOLSAFE_DB_HOST=' + dbHost,
    '-e', 'SCHOOLSAFE_DB_PORT=' + dbPort,
    '-e', 'SCHOOLSAFE_DEFAULT_SYEAR=' + (process.env.SCHOOLSAFE_DEFAULT_SYEAR || '2026'),
    '-e', 'SCHOOLSAFE_CONSOLE_TOKEN=' + consoleToken,
    '-v', productionConfig + ':/var/www/html/config.inc.php:ro',
    image], { stdio: 'ignore' });

  try {
    let ready = false;
    for (let attempt = 0; attempt < 40 && !ready; attempt++) {
      const probe = await fetch(url + '/index.php').catch(() => null);
      ready = probe?.status === 200;
      if (!ready) await new Promise(resolve => setTimeout(resolve, 500));
    }

    if (!ready) {
      const logs = run('docker', ['logs', containerName]);
      throw new Error('l\'image de production ne répond pas 200 sur /index.php\n'
        + (logs.stdout || '') + (logs.stderr || ''));
    }

    for (const suite of suites) {
      const suitePath = path.isAbsolute(suite) ? suite : path.join(repoRoot, suite);
      if (!existsSync(suitePath)) throw new Error('suite absente : ' + suite);
      console.log('QA   ' + suite + ' contre l\'image de production');
      const result = run(process.execPath, [suitePath], {
        cwd: repoRoot,
        stdio: 'inherit',
        env: { ...process.env, SCHOOLSAFE_L1_URL: url, SCHOOLSAFE_CONSOLE_TOKEN: consoleToken },
      });
      if (result.status !== 0) status = result.status || 1;
    }

    if (!status) console.log('PASS image de production : ' + suites.join(', '));
    return status;
  } finally {
    // Seul le conteneur jetable est retiré : pile locale, base et migrations intactes.
    run('docker', ['rm', '-f', containerName]);
  }
}

async function main() {
  for (const file of [dockerfile, productionConfig]) {
    if (!existsSync(file)) throw new Error('fichier requis absent : ' + file);
  }

  must('docker', ['info'], { stdio: 'ignore' });

  console.log('BUILD image de production (' + image + ') depuis Dockerfile.vps');
  must('docker', ['build', '-f', dockerfile, '-t', image, '.'], { cwd: runtimeDir, stdio: 'inherit' });

  const phpVersion = must('docker', ['run', '--rm', '--entrypoint', 'php', image, '-v'])
    .split('\n')[0];
  console.log('PHP  ' + phpVersion);

  const lintTargets = changedRuntimePhpFiles();
  if (lintTargets.length) {
    must('docker', ['run', '--rm', '--entrypoint', 'sh', image, '-c',
      'for f in "$@"; do php -l "$f" || exit 1; done', 'lint', ...lintTargets], { stdio: 'inherit' });
  } else {
    console.log('LINT aucun fichier PHP du runtime modifié');
  }

  return runSuites(await availablePort());
}

main()
  .then(status => { process.exitCode = status || 0; })
  .catch(error => {
    console.error('FAIL ' + (error?.message || error));
    process.exitCode = 1;
  });
