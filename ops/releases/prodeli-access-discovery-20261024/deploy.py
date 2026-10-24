#!/usr/bin/env python3
"""Qualified two-file discovery release; reuse unchanged preservation engines.

No real grants, account changes, migrations, provider activation or dump restore.
The production GO authorizes only this immutable UI candidate.
"""
import argparse
import hashlib
import importlib.util
import json
import os
import re
from pathlib import Path
os.umask(0o077)
ROOT = Path('/opt/schoolsafe-releases/prodeli-access-discovery-20261024')
BASE = 'sha256:1b3843de24caa121243f231b147ddf0a9597b155deca7271ed699994f306fb86'
BASE_COMMIT = '44e246fdd037318c647ee25e8447bb83e3b88337'
SOURCE = 'b6f588da5f4c79875f9abaca0e1ec0e15f02fc2e'
TEST_SOURCE = '6ffde525b4d1bd3ea57ead0a8049a5256f77a8f0'
IMAGE = 'sha256:1df5f8578805868544f1a7a73d6a1d437c5be0bf0ecf52cd716b41455e35aea8'
DIGEST = 'e41c8515d0b7c5161685b192333ca33b5c0110c853c38a3e58e0e656ffaa0ea6'
PREFIX = 'app/modules/codex-ui-v1/extensions-test-runtime/'
FILES = {'plugins/SchoolSafe_Keycloak/Wizard.php', 'plugins/SchoolSafe_Keycloak/manage.php'}
BASE_BRANCH = 'deployed/school-native-access-20261024'
BRANCH = 'deployed/prodeli-access-discovery-20261024'

def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result

bundled = Path(__file__).parent/'native-access.py'
access = module('discovery_native_access', bundled if bundled.exists() else Path(__file__).resolve().parents[1]/'school-native-access-20261024/deploy.py')
tools = access.tools
preserve = tools.preserve
run = preserve.run
inspect = preserve.inspect

def load(name):
    return json.loads((ROOT/name).read_text())

def git(commit, path):
    return run(['git','-C','/opt/app-src','show',commit+':'+path])

def qualification(release):
    assert re.fullmatch('[a-f0-9]{40}', release['commit'])
    assert release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
    assert set(release['files'])==FILES and set(release['base_files'])==FILES
    manifest = load('source-manifest.json')
    proof = load('image-prepare-proof.json')
    assert manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST and manifest['base_image']==BASE
    assert set(manifest['payload_hashes'])==FILES and manifest['payload_hashes']==release['files']
    assert manifest['base_hashes']==release['base_files']
    assert proof['status']=='READY' and proof['image']==IMAGE and proof['source_digest']==DIGEST
    assert proof['renderer_contracts']==178 and proof['recovery_contracts']==37 and proof['unchanged_files']==2800
    assert set(proof['changed_files'])==FILES and proof['internal_network'] and proof['read_only_web'] and not proof['published_ports']
    for f, digest in release['files'].items():
        assert hashlib.sha256(git(SOURCE,PREFIX+f)).hexdigest()==digest
        assert hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
        assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==release['base_files'][f]
    http = load('access-http.private.json')
    assert http['exit']==0 and http['stderr']==''
    result = json.loads(http['stdout'])
    assert result['status']=='PASS' and result['checks']==55 and len(result['results'])==55 and result['error'] is None
    browser = load('discovery-browser.json')
    assert browser['status']=='PASS' and browser['checks']==48 and len(browser['results'])==48
    verification = load('image-verify-proof.json')
    cleanup = load('image-cleanup-proof.json')
    assert verification['status']=='PASS' and verification['image']==IMAGE and verification['fixture_snapshot_unchanged'] and verification['production_containers_unchanged']
    assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources'] and cleanup['production_containers_unchanged']
    final = load('final-provenance-and-cleanup.json')
    assert final['status']=='PASS' and final['image']==IMAGE and final['source_commit']==SOURCE and final['source_digest']==DIGEST
    assert final['http_test_commit']==TEST_SOURCE and final['http_checks']==55
    assert final['both_nonces_containers_and_networks_absent'] and final['image_payload_matches_committed_git'] and final['image_labels_verified']
    assert final['production_git_clean_unchanged'] and final['production_image_healthy_unchanged'] and final['fixture_snapshot_restored']
    assert final['http_test_sha256']==hashlib.sha256(git(TEST_SOURCE,'scripts/school-access-native-http.php')).hexdigest()
    base = load('base-native-manifest.json')
    assert len(base['payload_hashes'])==71 and not (set(base['payload_hashes']) & FILES)
    for f,digest in base['payload_hashes'].items():
        assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==digest
        assert hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
    expected = dict(base['payload_hashes'], **release['files'])
    code = '$o=[];foreach(json_decode($argv[1],true)as$f)$o[$f]=hash_file("sha256","/var/www/html/".$f);echo json_encode($o);'
    actual = json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','--entrypoint','php',IMAGE,'-r',code,json.dumps(sorted(expected))]))
    assert actual==expected
    labels = inspect(IMAGE)['Config']
    assert labels['Labels']['org.opencontainers.image.revision']==SOURCE and labels['Labels']['schoolsafe.source.digest']==DIGEST
    assert not any(e.startswith('SCHOOLSAFE_CARD_HMAC_SECRET=') for e in labels.get('Env',[]))
    for local,path in [('deploy.py','ops/releases/prodeli-access-discovery-20261024/deploy.py'),
                       ('native-access.py','ops/releases/school-native-access-20261024/deploy.py'),
                       ('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),
                       ('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
        assert (ROOT/local).read_bytes()==git(release['commit'],path)

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action',choices=['prepare','deploy','status'])
    action=parser.parse_args().action
    release=load('release.json')
    access.ROOT=tools.ROOT=preserve.ROOT=ROOT
    access.BASE=tools.BASE=BASE
    access.IMAGE=tools.IMAGE=IMAGE
    access.DIGEST=tools.DIGEST=DIGEST
    access.BASE_COMMIT=tools.BASE_COMMIT=BASE_COMMIT
    access.FILES=tools.FILES=FILES
    tools.BASE_BRANCH=BASE_BRANCH
    tools.BRANCH=BRANCH
    tools.OLD='schoolsafe-rosario-pre-prodeli-access-discovery-20261024'
    tools.STAGED='schoolsafe-rosario-candidate-prodeli-access-discovery-20261024'
    access.qualification=tools.qualification=qualification
    tools.source_hashes=access.source_hashes
    if action=='prepare':
        access.prepare(release)
    elif action=='deploy':
        tools.deploy(release)
    else:
        print(json.dumps(tools.final_status(release)))

if __name__=='__main__':
    main()
