#!/usr/bin/env python3
"""One-image recovery/SMS release. No migration, sends, provisioning or automatic rollback.

Prepare performs read-only preflight and writes private backups. Deploy requires
those proofs plus exact-image QA and preserves the prior runtime/session/cache
contracts. An error after stopping the old service requires explicit recovery;
the known-vulnerable old image is never automatically put back into service.
"""
import argparse
import hashlib
import http.client
import importlib.util
import json
import os
from pathlib import Path
import re
import sys
import time

sys.dont_write_bytecode = True
os.umask(0o077)
ROOT = Path('/opt/schoolsafe-releases/prodeli-recovery-sms-20261022')
NAME = 'schoolsafe-rosario'
OLD = NAME + '-pre-prodeli-recovery-sms-20261022'
STAGED = NAME + '-candidate-prodeli-recovery-sms-20261022'
BASE = 'sha256:36a9e2fbc24159f25f65247cb516f84a43df49b410e35ee208ef3d25e3cf0c78'
IMAGE = 'sha256:b7c276bec9487aa97d4003411d43bc83671d819b72ed3ca94bf2c4c6e2c161e8'
DIGEST = '61eb80821a8a73cf1ccd473c9c42582e59157ee2eae13c3436fa7be0560b363e'
BASE_COMMIT = '3670dad176a4fe6932e69e87a481a5f205131543'
BASE_BRANCH = 'deployed/prodeli-native-access-20261021'
BRANCH = 'deployed/prodeli-recovery-sms-20261022'
WORK_BRANCH = 'work/prodeli-completion-20261021'
PREFIX = 'app/modules/codex-ui-v1/extensions-test-runtime/'
FILES = {'PasswordReset.php', 'compte-recuperation.php', 'plateforme-prodeli-sms.php',
         'ProgramFunctions/SchoolSafeRecovery.fnc.php', 'ProgramFunctions/SchoolSafeSms.fnc.php'}


def preservation_module():
    bundled = Path(__file__).resolve().parent / 'preservation.py'
    source = bundled if bundled.is_file() else Path(__file__).resolve().parents[3] / 'ops/releases/prodeli-native-access-20261021/deploy.py'
    spec = importlib.util.spec_from_file_location('preservation_contract', source)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    module.ROOT = ROOT
    return module


preserve = preservation_module()
run = preserve.run
inspect = preserve.inspect


def load_json(name):
    return json.loads((ROOT / name).read_text())


def write_json(name, data):
    (ROOT / name).write_text(json.dumps(data, indent=2))


def required_qualification(release, prepare, integration, verification, cleanup):
    assert re.fullmatch('[0-9a-f]{40}', release['commit'])
    assert release['image'] == IMAGE and release['source_digest'] == DIGEST
    assert set(release['files']) == FILES
    assert prepare['status'] == 'READY' and prepare['image'] == IMAGE
    assert prepare['source_digest'] == DIGEST and prepare['renderer_contracts'] == 92 and prepare['recovery_contracts'] == 37
    assert set(prepare['changed_files']) == FILES and prepare['unchanged_files'] == 2755
    assert prepare['internal_network'] and prepare['read_only_web'] and not prepare['published_ports']
    assert integration['status'] == 'PASS' and integration['image'] == IMAGE
    assert {s['suite'] for s in integration['suites']} == {'recovery', 'concurrency', 'native'}
    assert all(s['exit'] == 0 for s in integration['suites'])
    assert verification['status'] == 'PASS' and verification['image'] == IMAGE
    assert verification['fixture_snapshot_unchanged'] and verification['production_containers_unchanged']
    assert cleanup['status'] == 'REMOVED' and cleanup['only_owned_ephemeral_resources']
    assert release['recovery_groups'] == 10 and release['native_groups'] == 10
    assert release['concurrent_successes'] == 1 and release['concurrent_epoch'] == 1
    assert release['revocation_failure_rollbacks'] == 2


def qualification(release):
    required_qualification(release, load_json('image-prepare-proof.json'), load_json('exact-integration-result.json'),
                           load_json('image-verify-proof.json'), load_json('image-cleanup-proof.json'))
    for f, digest in release['files'].items():
        assert re.fullmatch('[a-f0-9]{64}', digest)
        assert hashlib.sha256((ROOT / 'payload' / f).read_bytes()).hexdigest() == digest
        actual = run(['git', '-C', '/opt/app-src', 'show', release['commit'] + ':' + PREFIX + f])
        assert hashlib.sha256(actual).hexdigest() == digest, 'Published Git source differs from exact-image payload'
    assert hashlib.sha256((ROOT / 'preservation.py').read_bytes()).hexdigest() == release['preservation_sha256']
    assert (ROOT / 'preservation.py').read_bytes() == run(['git', '-C', '/opt/app-src', 'show', release['commit'] + ':ops/releases/prodeli-native-access-20261021/deploy.py'])
    assert Path(__file__).read_bytes() == run(['git', '-C', '/opt/app-src', 'show', release['commit'] + ':ops/releases/prodeli-recovery-sms-20261022/deploy.py'])


def source_hashes(name):
    code = '$o=[];foreach(json_decode($argv[1],true)as$p)$o[$p]=hash_file("sha256","/var/www/html/".$p);echo json_encode($o);'
    return json.loads(run(['docker', 'exec', name, 'php', '-r', code, json.dumps(sorted(FILES))]))


def configuration_hash(name):
    return run(['docker', 'exec', name, 'php', '-r', 'echo hash_file("sha256","/var/www/html/config.inc.php");']).decode().strip()


def unchanged_runtime(pre, current):
    assert current['Config']['Env'] == pre['Config']['Env']
    assert current['HostConfig']['Binds'] == pre['HostConfig']['Binds']
    for key in ['PortBindings', 'NetworkMode', 'RestartPolicy', 'Privileged', 'CapAdd', 'CapDrop', 'SecurityOpt',
                'Memory', 'NanoCpus', 'LogConfig', 'ReadonlyRootfs', 'Tmpfs']:
        assert current['HostConfig'].get(key) == pre['HostConfig'].get(key), 'Runtime setting changed: ' + key
    assert current['Config']['Cmd'] == pre['Config']['Cmd']
    assert current['Config']['Entrypoint'] == pre['Config']['Entrypoint']
    assert current['Config'].get('User') == pre['Config'].get('User')
    assert current['Config'].get('Healthcheck') == pre['Config'].get('Healthcheck')


def db_cli():
    config = json.loads(run(['docker', 'exec', NAME, 'php', '-r', "require '/var/www/html/config.inc.php';echo json_encode(['user'=>$DatabaseUsername,'database'=>$DatabaseName]);"]))
    write_json('db-cli.private.json', config)
    return ['docker', 'exec', '-i', 'schoolsafe-rosario-db', 'psql', '-X', '-v', 'ON_ERROR_STOP=1', '-U', config['user'], '-d', config['database'], '-qAt']


def preflight_schema(release, cli):
    names = sorted(release['schema_columns'])
    assert all(re.fullmatch('[a-z_]+', x) for x in names)
    quoted = ','.join("'" + x + "'" for x in names)
    query = "BEGIN READ ONLY; SELECT json_object_agg(table_name,cols) FROM (SELECT table_name,json_object_agg(column_name,udt_name) AS cols FROM information_schema.columns WHERE table_schema='public' AND table_name IN (" + quoted + ") GROUP BY table_name) x; SELECT json_object_agg(conname,pg_get_constraintdef(oid)) FROM pg_constraint WHERE conrelid='public.schoolsafe_recovery_otp'::regclass; SELECT count(*) FROM config WHERE title IN ('RECOVERY_SMS_SIMULATE','SMS_SIMULATE') AND config_value='Y'; COMMIT;"
    lines = run(cli, query.encode()).decode().splitlines()
    columns, constraints, simulation_count = json.loads(lines[0]), json.loads(lines[1]), int(lines[2])
    write_json('schema-metadata.private.json', {'columns': columns, 'otp_constraints': constraints})
    assert simulation_count == 0, 'Production simulation is unexpectedly enabled'
    for table, expected in release['schema_columns'].items():
        for column, udt in expected.items():
            assert columns.get(table, {}).get(column) == udt, 'Required schema column/type mismatch: ' + table + '.' + column
    for name, definition in release['otp_constraints'].items():
        assert constraints.get(name) == definition, 'OTP constraint drift'
    write_json('schema-proof.json', {'status': 'PASS', 'metadata_only': True, 'read_only': True,
                                     'tables': names, 'otp_constraints': sorted(release['otp_constraints']),
                                     'simulation_disabled': True, 'migrations': []})


def prepare(release):
    assert not (ROOT / 'backup-proof.json').exists(), 'Already prepared; inspect existing proof before another backup'
    run(['git', '-C', '/opt/app-src', 'fetch', '--no-tags', 'origin', 'refs/heads/' + WORK_BRANCH])
    assert run(['git', '-C', '/opt/app-src', 'rev-parse', 'FETCH_HEAD']).decode().strip() == release['commit']
    qualification(release)
    assert run(['git', '-C', '/opt/app-src', 'rev-parse', 'HEAD']).decode().strip() == BASE_COMMIT
    assert run(['git', '-C', '/opt/app-src', 'branch', '--show-current']).decode().strip() == BASE_BRANCH
    assert not run(['git', '-C', '/opt/app-src', 'status', '--porcelain']).strip()
    changed = run(['git', '-C', '/opt/app-src', 'diff', '--name-only', BASE_COMMIT, release['commit'], '--', PREFIX]).decode().splitlines()
    assert set(changed) == {PREFIX + f for f in FILES}
    assert run(['git', '-C', '/opt/app-src', 'diff', '--name-only', BASE_COMMIT, release['commit'], '--', 'ops/deployment/config.inc.production.php']).strip() == b''
    pre = inspect(NAME)
    assert pre['Image'] == BASE and pre['State']['Running'] and pre['State'].get('Health', {}).get('Status') == 'healthy'
    assert source_hashes(NAME) == release['base_files']
    assert len(pre['Mounts']) == 1 and pre['Mounts'][0]['Destination'] == '/var/www/html/config.inc.php' and not pre['Mounts'][0]['RW']
    for name in [OLD, STAGED]:
        import subprocess
        assert subprocess.run(['docker', 'inspect', name], capture_output=True).returncode != 0
    assert inspect(IMAGE)['Id'] == IMAGE
    preserve.check_layer(NAME)
    preserve.probe(); preserve.probe(True)
    write_json('container-before.private.json', pre)
    write_json('other-services-before.json', preserve.other_state())
    (ROOT / 'config-before.sha256').write_text(configuration_hash(NAME))
    cli = db_cli()
    preflight_schema(release, cli)
    config = load_json('db-cli.private.json')
    dump = run(['docker', 'exec', 'schoolsafe-rosario-db', 'pg_dump', '-U', config['user'], '-d', config['database'], '-Fc'], timeout=180)
    assert len(dump) > 1000
    backup = ROOT / 'database-before.private.dump'; backup.write_bytes(dump)
    listing = run(['docker', 'exec', '-i', 'schoolsafe-rosario-db', 'pg_restore', '--list'], dump)
    assert b'TABLE DATA' in listing
    (ROOT / 'database-before-list.private.txt').write_bytes(listing)
    for f in FILES:
        target = ROOT / 'source-before' / f; target.parent.mkdir(parents=True, exist_ok=True)
        content = run(['docker', 'exec', NAME, 'php', '-r', 'echo file_get_contents($argv[1]);', '/var/www/html/' + f])
        assert hashlib.sha256(content).hexdigest() == release['base_files'][f]
        target.write_bytes(content)
    result = {'status': 'PASS', 'commit': release['commit'], 'image': IMAGE, 'backup_bytes': len(dump),
              'backup_sha256': hashlib.sha256(dump).hexdigest(), 'archive_list_valid': True,
              'backup_mode': oct(backup.stat().st_mode & 0o777), 'source_backups': 5,
              'migrations': [], 'restored_on_production': False, 'prepared_at': time.time()}
    assert result['backup_mode'] == '0o600'
    write_json('backup-proof.json', result)
    print(json.dumps(result))


def create_staged(pre, release):
    payload = dict(pre['Config']); payload['Image'] = IMAGE
    payload['Labels'] = dict(payload.get('Labels') or {})
    payload['Labels'].update({'schoolsafe.patch.commit': release['commit'], 'schoolsafe.patch.scope': 'prodeli-recovery-sms', 'schoolsafe.patch.source-digest': DIGEST})
    payload['HostConfig'] = dict(pre['HostConfig'])
    if payload['HostConfig'].get('OomKillDisable') is None:
        payload['HostConfig']['OomKillDisable'] = False
    net = pre['HostConfig']['NetworkMode']; endpoint = pre['NetworkSettings']['Networks'][net]
    payload['NetworkingConfig'] = {'EndpointsConfig': {net: {'Aliases': endpoint.get('Aliases') or []}}}
    connection = preserve.UnixHTTP('localhost')
    connection.request('POST', '/v1.47/containers/create?name=' + STAGED, body=json.dumps(payload), headers={'Content-Type': 'application/json'})
    response = connection.getresponse(); body = response.read(); connection.close()
    if response.status != 201:
        (ROOT / 'create-error.private.json').write_bytes(body)
        raise RuntimeError('Candidate creation failed; production has not been stopped')
    staged = inspect(STAGED)
    assert not staged['State']['Running'] and staged['Image'] == IMAGE
    unchanged_runtime(pre, staged)
    return staged['Id']


def protected_routes():
    for route in ['/schoolsafe-administrators.php', '/schoolsafe-administrators.php?accounts=central', '/admin-console.php', '/plateforme/prodeli/sms', '/plateforme/prodeli/whatsapp']:
        c = http.client.HTTPConnection('127.0.0.1', 18081, timeout=15)
        c.request('GET', route); response = c.getresponse(); response.read(); c.close()
        assert response.status in [302, 303, 403], 'Anonymous protected route opened'


def final_status(release):
    pre = load_json('container-before.private.json'); current = inspect(NAME)
    assert current['Image'] == IMAGE and current['State']['Running'] and current['State'].get('Health', {}).get('Status') == 'healthy'
    assert current['Config']['Labels']['schoolsafe.patch.commit'] == release['commit']
    unchanged_runtime(pre, current)
    assert source_hashes(NAME) == release['files']
    assert configuration_hash(NAME) == (ROOT / 'config-before.sha256').read_text()
    expected = {k: tuple(v) for k, v in load_json('other-services-before.json').items()}
    assert preserve.other_state() == expected
    assert inspect(OLD)['Image'] == BASE and not inspect(OLD)['State']['Running']
    assert run(['git', '-C', '/opt/app-src', 'rev-parse', 'HEAD']).decode().strip() == release['commit']
    assert run(['git', '-C', '/opt/app-src', 'branch', '--show-current']).decode().strip() == BRANCH
    assert not run(['git', '-C', '/opt/app-src', 'status', '--porcelain']).strip()
    preserve.probe(); preserve.probe(True); protected_routes()
    return {'status': 'PASS', 'commit': release['commit'], 'image': IMAGE, 'runtime_settings_unchanged': True,
            'config_unchanged': True, 'other_services_unchanged': True, 'source_hashes_match': True,
            'old_container_preserved_stopped': True, 'public_login': 200, 'anonymous_routes_protected': True}


def deploy(release):
    assert not (ROOT / 'deployment-proof.json').exists(), 'Deployment already recorded'
    qualification(release)
    backup = load_json('backup-proof.json')
    assert backup['status'] == 'PASS' and backup['commit'] == release['commit'] and time.time() - backup['prepared_at'] < 900
    assert hashlib.sha256((ROOT / 'database-before.private.dump').read_bytes()).hexdigest() == backup['backup_sha256']
    assert load_json('schema-proof.json')['status'] == 'PASS'
    pre = load_json('container-before.private.json'); current = inspect(NAME)
    assert current['Id'] == pre['Id'] and current['Image'] == BASE
    assert current['Config'] == pre['Config'] and current['HostConfig'] == pre['HostConfig']
    assert source_hashes(NAME) == release['base_files']
    assert not run(['git', '-C', '/opt/app-src', 'status', '--porcelain']).strip()
    assert run(['git', '-C', '/opt/app-src', 'rev-parse', 'HEAD']).decode().strip() == BASE_COMMIT
    preserve.check_layer(NAME)
    create_staged(pre, release)
    # The only source bind is the unchanged configuration file, not this Git tree.
    run(['git', '-C', '/opt/app-src', 'switch', '-c', BRANCH, release['commit']])
    assert configuration_hash(NAME) == (ROOT / 'config-before.sha256').read_text()
    began = time.monotonic(); stopped = renamed = started = False
    try:
        run(['docker', 'stop', '-t', '30', NAME]); stopped = True
        preserve.check_layer(NAME)
        count = preserve.transfer_sessions(NAME, STAGED)
        run(['docker', 'rename', NAME, OLD]); renamed = True
        run(['docker', 'rename', STAGED, NAME])
        run(['docker', 'start', NAME]); started = True
        for _ in range(60):
            state = inspect(NAME)['State']
            if state.get('Health', {}).get('Status') == 'healthy':
                break
            if not state['Running']:
                raise RuntimeError('Candidate stopped')
            time.sleep(1)
        else:
            raise RuntimeError('Candidate health check failed')
        result = final_status(release)
        result.update({'status': 'DEPLOYED', 'files': release['files'], 'source_digest': DIGEST,
                       'session_files_preserved': count, 'chromium_cache_preserved': True,
                       'database_migrations': [], 'production_account_mutations_tested': False,
                       'external_message_tested': False, 'old_container': OLD,
                       'automatic_rollback': False, 'elapsed_seconds': round(time.monotonic() - began, 2)})
        write_json('deployment-proof.json', result)
        print(json.dumps(result))
    except Exception:
        write_json('manual-recovery-required.json', {'old_stopped': stopped, 'old_renamed': renamed,
                    'candidate_started': started, 'reason': 'No automatic fallback to the known-vulnerable prior recovery runtime. Preserve all containers, sessions and data; inspect before action.'})
        raise


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['prepare', 'deploy', 'status'])
    action = parser.parse_args().action
    release = load_json('release.json')
    if action == 'prepare':
        prepare(release)
    elif action == 'deploy':
        deploy(release)
    else:
        print(json.dumps(final_status(release)))


if __name__ == '__main__':
    main()
