#!/usr/bin/env python3
"""Five-file school grading relocation, no DDL or production grading/rights writes.
Reuse the verified workspace rollout and session/cache preservation; strict gates
below are specific to this feature and its exact synthetic integration evidence.
"""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import sys
sys.dont_write_bytecode = True
ROOT = Path('/opt/schoolsafe-releases/prodeli-school-scale-20261022')
source = Path(__file__).resolve().parent/'workspace.py'
if not source.exists(): source=Path(__file__).resolve().parents[1]/'prodeli-workspace-20261022/deploy.py'
spec=importlib.util.spec_from_file_location('workspace_release',source)
w=importlib.util.module_from_spec(spec);spec.loader.exec_module(w)
FILES={'plugins/SchoolSafe_Keycloak/Wizard.php','modules/Grades/Menu.php','modules/Grades/ReportCardGrades.php',
       'modules/Grades/includes/SchoolScale.fnc.php','modules/School_Setup/Schools.php'}
BASE='sha256:59fb6b25e57ea4f53237ff36dbdc0ae1122fdf57be34a6ab4fe5b3bf6fc5bb63'
BASE_COMMIT='8fc3546bc47d23395d5802a9f612185e9193acaa'
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'

def qualification(release):
    assert re.fullmatch('[a-f0-9]{40}',release['commit'])
    manifest=w.load('source-manifest.json');proof=w.load('image-prepare-proof.json')
    assert manifest['source_commit']==release['source_commit'] and manifest['base_image']==BASE
    assert manifest['payload_hashes']==release['files'] and set(release['files'])==FILES
    assert proof['status']=='READY' and proof['image']==release['image']
    assert proof['source_digest']==release['source_digest']==manifest['source_digest']
    assert set(proof['changed_files'])==FILES and proof['unchanged_files']==2758
    assert proof['renderer_contracts']==145 and proof['recovery_contracts']==37
    assert proof['internal_network'] and proof['read_only_web'] and not proof['published_ports']
    integration=w.load('exact-integration-result.json')
    assert integration['status']=='PASS' and integration['image']==release['image']
    assert {s['suite'] for s in integration['suites']}=={'native','scale'} and all(s['exit']==0 for s in integration['suites'])
    for name,count in [('native',13),('scale',5)]:
        result=w.load(name+'-result.json');assert result['status']=='PASS' and result['cleanup'] and len(result['results'])==count
    verify=w.load('image-verify-proof.json');cleanup=w.load('image-cleanup-proof.json')
    assert verify['status']=='PASS' and verify['image']==release['image'] and verify['fixture_snapshot_unchanged'] and verify['production_containers_unchanged']
    assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources']
    for file,digest in release['files'].items():
        assert hashlib.sha256(w.run(['git','-C','/opt/app-src','show',release['commit']+':'+PREFIX+file])).hexdigest()==digest
    for file,path in [('deploy.py','ops/releases/prodeli-school-scale-20261022/deploy.py'),('workspace.py','ops/releases/prodeli-workspace-20261022/deploy.py'),
                      ('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
        assert (ROOT/file).read_bytes()==w.run(['git','-C','/opt/app-src','show',release['commit']+':'+path])
    for file in w.RECOVERY:
        assert w.run(['git','-C','/opt/app-src','diff',BASE_COMMIT,release['commit'],'--',PREFIX+file])==b''
    image=w.inspect(release['image']);assert image['Config']['Labels']['org.opencontainers.image.revision']==release['source_commit']
    assert image['Config']['Labels']['schoolsafe.source.digest']==release['source_digest']

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('action',choices=['prepare','deploy','status'])
    args=parser.parse_args()
    w.ROOT=w.release_tools.ROOT=w.preserve.ROOT=ROOT
    w.BASE=BASE;w.BASE_COMMIT=BASE_COMMIT;w.BASE_BRANCH='deployed/prodeli-workspace-20261022'
    w.FILES=FILES;w.NEW_FILES={'modules/Grades/includes/SchoolScale.fnc.php'};w.qualification=qualification
    release=w.load('release.json');r=w.release_tools
    r.BASE=BASE;r.BASE_COMMIT=BASE_COMMIT;r.BASE_BRANCH=w.BASE_BRANCH;r.FILES=FILES
    r.BRANCH='deployed/prodeli-school-scale-20261022'
    r.OLD='schoolsafe-rosario-pre-prodeli-school-scale-20261022';r.STAGED='schoolsafe-rosario-candidate-prodeli-school-scale-20261022'
    r.IMAGE=release['image'];r.DIGEST=release['source_digest']
    r.source_hashes=w.source_hashes;r.qualification=qualification;r.create_staged=w.create_staged
    if args.action=='prepare':w.prepare(release)
    elif args.action=='deploy':r.deploy(release)
    else:print(json.dumps(r.final_status(release)))

if __name__=='__main__':main()
