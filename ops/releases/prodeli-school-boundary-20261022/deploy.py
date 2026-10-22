#!/usr/bin/env python3
"""School-boundary delivery. No schema change, account mutation, message or automatic rollback.
Reuse the previously qualified session/cache/runtime preservation implementation.
"""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time

sys.dont_write_bytecode = True
os.umask(0o077)
ROOT = Path('/opt/schoolsafe-releases/prodeli-school-boundary-20261022')
BASE_COMMIT = '8a969d5bcb4f9a97eda4181165d7db5dd2af2f38'
BASE_BRANCH = 'deployed/prodeli-presentation-20261022'
BASE = 'sha256:a4a3cbf821083f60629031075c46dafdec8615fb91d591835408fa4afb260bf3'
PREFIX = 'app/modules/codex-ui-v1/extensions-test-runtime/'
FILES = {'Bottom.php','Warehouse.php','Side.php','admin-console.php',
 'ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php','ProgramFunctions/SchoolSafeSchoolScope.fnc.php',
 'ProgramFunctions/SchoolSafeWorkspace.fnc.php','ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
 'modules/School_Setup/Configuration.php','plugins/SchoolSafe_Keycloak/Management.php',
 'plugins/SchoolSafe_Keycloak/Wizard.php','plugins/SchoolSafe_Keycloak/manage.php'}
NEW_FILES = set()
RECOVERY = {'PasswordReset.php','plateforme-prodeli-sms.php',
 'ProgramFunctions/SchoolSafeRecovery.fnc.php','ProgramFunctions/SchoolSafeSms.fnc.php',
 'ProgramFunctions/FirstLogin.fnc.php','functions/Password.php','index.php',
 'modules/Grades','modules/School_Setup/Schools.php','modules/School_Setup/CopySchool.php'}


def load(name): return json.loads((ROOT / name).read_text())
def write(name, data): (ROOT / name).write_text(json.dumps(data, indent=2))
def module(name, source):
    spec = importlib.util.spec_from_file_location(name, source)
    value = importlib.util.module_from_spec(spec); spec.loader.exec_module(value)
    return value


previous = Path(__file__).resolve().parent / 'recovery.py'
if not previous.exists(): previous = Path(__file__).resolve().parents[1] / 'prodeli-recovery-sms-20261022/deploy.py'
release_tools = module('recovery_release', previous)
preserve = release_tools.preserve
release_tools.ROOT = preserve.ROOT = ROOT
run = preserve.run
inspect = preserve.inspect


def source_hashes(name):
    code = '$o=[];foreach(json_decode($argv[1],true)as$p)$o[$p]=is_file("/var/www/html/".$p)?hash_file("sha256","/var/www/html/".$p):null;echo json_encode($o);'
    return json.loads(run(['docker','exec',name,'php','-r',code,json.dumps(sorted(FILES))]))


def qualification(release):
    assert re.fullmatch('[a-f0-9]{40}', release['commit'])
    manifest = load('source-manifest.json'); proof = load('image-prepare-proof.json')
    assert manifest['source_commit'] == release['source_commit'] and manifest['base_image'] == BASE
    assert re.fullmatch('[a-f0-9]{40}',release['source_commit'])
    assert manifest['payload_hashes'] == release['files'] and set(release['files']) == FILES
    assert proof['status'] == 'READY' and proof['image'] == release['image']
    assert proof['source_digest'] == release['source_digest'] == manifest['source_digest']
    assert set(proof['changed_files']) == FILES and proof['unchanged_files'] == 2752
    assert proof['renderer_contracts'] == 156 and proof['recovery_contracts'] == 37
    assert proof['native_transaction_contracts'] == 57
    assert proof['read_only_web'] and proof['internal_network'] and not proof['published_ports']
    result = load('exact-integration-result.json')
    assert result['status'] == 'PASS' and result['image'] == release['image']
    assert {s['suite'] for s in result['suites']} == {'native','recovery','concurrency'}
    assert all(s['exit'] == 0 for s in result['suites'])
    for filename, groups in [('native-result.json',21)]:
        result = load(filename); assert result['status'] == 'PASS' and result['cleanup'] and len(result['results']) == groups
    retained = ROOT/'retained-results-proof.json'
    if retained.exists():
        provenance = load(retained.name)
        assert provenance['image'] == release['image'] and provenance['source_digest'] == release['source_digest']
        assert provenance['tests_unchanged'] and set(provenance['retained_files']) == {'native-result.json','mobile-audit.json'}
        for filename,digest in provenance['retained_files'].items():
            assert filename in ['native-result.json','mobile-audit.json']
            assert hashlib.sha256((ROOT/filename).read_bytes()).hexdigest() == digest
    mobile = load('mobile-audit.json')
    assert mobile['status'] == 'PASS' and mobile['image'] == release['image'] and mobile['cleanup']
    assert len(mobile['results']) == 24 and {(x['key'],x['width']) for x in mobile['results']} == {
        (key,width) for key in ['home','accounts','wizard','opening','console','sms','whatsapp','preferences']
        for width in [1280,390,360]}
    assert all(x['headingVisible'] and x['overflow'] <= 1 for x in mobile['results'])
    verify = load('image-verify-proof.json'); cleanup = load('image-cleanup-proof.json')
    assert verify['status'] == 'PASS' and verify['image'] == release['image'] and verify['fixture_snapshot_unchanged'] and verify['production_containers_unchanged']
    assert cleanup['status'] == 'REMOVED' and cleanup['only_owned_ephemeral_resources']
    for path, digest in release['files'].items():
        assert hashlib.sha256(run(['git','-C','/opt/app-src','show',release['commit']+':'+PREFIX+path])).hexdigest() == digest
    for local, git_path in [('deploy.py','ops/releases/prodeli-school-boundary-20261022/deploy.py'),
                            ('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),
                            ('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
        assert (ROOT/local).read_bytes() == run(['git','-C','/opt/app-src','show',release['commit']+':'+git_path])
    for path in RECOVERY:
        assert run(['git','-C','/opt/app-src','diff',BASE_COMMIT,release['commit'],'--',PREFIX+path]) == b''
    image = inspect(release['image'])
    assert image['Config']['Labels']['org.opencontainers.image.revision'] == release['source_commit']
    assert image['Config']['Labels']['schoolsafe.source.digest'] == release['source_digest']


def prepare(release):
    assert not (ROOT/'backup-proof.json').exists(), 'Already prepared'
    run(['git','-C','/opt/app-src','fetch','--no-tags','origin','refs/heads/fix/prodeli-school-boundary-20261022'])
    assert run(['git','-C','/opt/app-src','rev-parse','FETCH_HEAD']).decode().strip() == release['commit']
    qualification(release)
    assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip() == BASE_COMMIT
    assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip() == BASE_BRANCH
    assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
    changed = run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--',PREFIX]).decode().splitlines()
    assert set(changed) == {PREFIX+f for f in FILES}
    pre = inspect('schoolsafe-rosario')
    assert pre['Image'] == BASE and pre['State']['Running'] and pre['State']['Health']['Status'] == 'healthy'
    assert source_hashes('schoolsafe-rosario') == release['base_files']
    assert len(pre['Mounts']) == 1 and pre['Mounts'][0]['Destination'] == '/var/www/html/config.inc.php' and not pre['Mounts'][0]['RW']
    for name in [release_tools.OLD,release_tools.STAGED]:
        assert subprocess.run(['docker','inspect',name],capture_output=True).returncode != 0
    preserve.check_layer('schoolsafe-rosario'); preserve.probe(); preserve.probe(True)
    write('container-before.private.json',pre); write('other-services-before.json',preserve.other_state())
    (ROOT/'config-before.sha256').write_text(release_tools.configuration_hash('schoolsafe-rosario'))
    # Metadata only; no production identity, OTP, user or school rows read here.
    cli = release_tools.db_cli()
    schema = run(cli,b"BEGIN READ ONLY; SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_name IN ('schools','staff','schoolsafe_native_onboarding','schoolsafe_recovery_otp'); COMMIT;").decode().strip()
    assert schema == '4'
    write('schema-proof.json',{'status':'PASS','read_only':True,'migrations':[]})
    config = load('db-cli.private.json')
    dump = run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',config['user'],'-d',config['database'],'-Fc'],timeout=180)
    assert len(dump) > 1000
    backup = ROOT/'database-before.private.dump'; backup.write_bytes(dump)
    listing = run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump)
    assert b'TABLE DATA' in listing
    (ROOT/'database-before-list.private.txt').write_bytes(listing)
    for path in FILES - NEW_FILES:
        target = ROOT/'source-before'/path; target.parent.mkdir(parents=True,exist_ok=True)
        data = run(['docker','exec','schoolsafe-rosario','php','-r','echo file_get_contents($argv[1]);','/var/www/html/'+path])
        assert hashlib.sha256(data).hexdigest() == release['base_files'][path]
        target.write_bytes(data)
    result = {'status':'PASS','commit':release['commit'],'image':release['image'],'backup_bytes':len(dump),
              'backup_sha256':hashlib.sha256(dump).hexdigest(),'archive_list_valid':True,
              'backup_mode':oct(backup.stat().st_mode & 0o777),'source_backups':len(FILES-NEW_FILES),
              'migrations':[],'prepared_at':time.time()}
    assert result['backup_mode'] == '0o600'
    write('backup-proof.json',result); print(json.dumps(result))


def create_staged(pre, release):
    payload = dict(pre['Config']); payload['Image'] = release['image']
    payload['Labels'] = dict(payload.get('Labels') or {})
    payload['Labels'].update({'schoolsafe.patch.commit':release['commit'],'schoolsafe.patch.scope':'prodeli-school-boundary',
                             'schoolsafe.patch.source-digest':release['source_digest']})
    payload['HostConfig'] = dict(pre['HostConfig'])
    if payload['HostConfig'].get('OomKillDisable') is None: payload['HostConfig']['OomKillDisable'] = False
    network = pre['HostConfig']['NetworkMode']; endpoint = pre['NetworkSettings']['Networks'][network]
    payload['NetworkingConfig'] = {'EndpointsConfig':{network:{'Aliases':endpoint.get('Aliases') or []}}}
    connection = preserve.UnixHTTP('localhost')
    connection.request('POST','/v1.47/containers/create?name='+release_tools.STAGED,
                       body=json.dumps(payload),headers={'Content-Type':'application/json'})
    response = connection.getresponse(); body = response.read(); connection.close()
    if response.status != 201:
        (ROOT/'create-error.private.json').write_bytes(body)
        raise RuntimeError('Candidate creation failed before stopping production')
    staged = inspect(release_tools.STAGED)
    assert not staged['State']['Running'] and staged['Image'] == release['image']
    release_tools.unchanged_runtime(pre,staged)
    return staged['Id']


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['prepare','deploy','status'])
    args = parser.parse_args(); release = load('release.json')
    release_tools.BASE = BASE; release_tools.BASE_COMMIT = BASE_COMMIT; release_tools.BASE_BRANCH = BASE_BRANCH
    release_tools.BRANCH = 'deployed/prodeli-school-boundary-20261022'
    release_tools.OLD = 'schoolsafe-rosario-pre-prodeli-school-boundary-20261022'
    release_tools.STAGED = 'schoolsafe-rosario-candidate-prodeli-school-boundary-20261022'
    release_tools.IMAGE = release['image']; release_tools.DIGEST = release['source_digest']; release_tools.FILES = FILES
    release_tools.source_hashes = source_hashes; release_tools.qualification = qualification
    release_tools.create_staged = create_staged
    if args.action == 'prepare': prepare(release)
    elif args.action == 'deploy': release_tools.deploy(release)
    else: print(json.dumps(release_tools.final_status(release)))


if __name__ == '__main__': main()
