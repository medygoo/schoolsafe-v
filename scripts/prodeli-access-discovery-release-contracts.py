#!/usr/bin/env python3
"""Offline release guards; no Docker, SSH, credentials or production mutation."""
import ast
import hashlib
import json
import subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
text=(ROOT/'ops/releases/prodeli-access-discovery-20261024/deploy.py').read_text()
tree=ast.parse(text)
names={'BASE','BASE_COMMIT','SOURCE','TEST_SOURCE','IMAGE','DIGEST','FILES','PREFIX','BASE_BRANCH','BRANCH'}
constants={n.targets[0].id:ast.literal_eval(n.value) for n in tree.body if isinstance(n,ast.Assign) and isinstance(n.targets[0],ast.Name) and n.targets[0].id in names}
checks=[]
def check(ok,label):
    if not ok:raise AssertionError(label)
    checks.append(label)
def git(*args):return subprocess.check_output(['git',*args],cwd=ROOT)
check(constants['BASE']=='sha256:1b3843de24caa121243f231b147ddf0a9597b155deca7271ed699994f306fb86','current qualified production image pinned')
check(constants['BASE_COMMIT']=='44e246fdd037318c647ee25e8447bb83e3b88337','current deployed checkout pinned')
check(constants['BASE_BRANCH']=='deployed/school-native-access-20261024','current deployed branch pinned')
check(constants['SOURCE']=='b6f588da5f4c79875f9abaca0e1ec0e15f02fc2e','candidate Git provenance pinned')
check(constants['TEST_SOURCE']=='6ffde525b4d1bd3ea57ead0a8049a5256f77a8f0','final explicit-reader HTTP test pinned')
check(constants['IMAGE']=='sha256:1df5f8578805868544f1a7a73d6a1d437c5be0bf0ecf52cd716b41455e35aea8','exact qualified image pinned')
check(constants['DIGEST']=='e41c8515d0b7c5161685b192333ca33b5c0110c853c38a3e58e0e656ffaa0ea6','exact qualified digest pinned')
check(constants['FILES']=={'plugins/SchoolSafe_Keycloak/Wizard.php','plugins/SchoolSafe_Keycloak/manage.php'},'only two discovery files allowed')
delta=git('diff','--name-only',constants['BASE_COMMIT'],constants['SOURCE'],'--',constants['PREFIX']).decode().splitlines()
check(set(delta)=={constants['PREFIX']+p for p in constants['FILES']},'candidate runtime delta exactly the two allowed files')
check("proof['renderer_contracts']==178" in text and "proof['recovery_contracts']==37" in text,'exact image contract counts enforced')
check("proof['unchanged_files']==2800" in text,'unchanged image contents constrained')
check("result['checks']==55" in text and "result['error'] is None" in text,'successful authenticated HTTP proof required')
check("browser['checks']==48" in text,'responsive synthetic browser evidence required')
check("len(base['payload_hashes'])==71" in text and 'assert actual==expected' in text,'71 previous and two new runtime sources verified')
check("cleanup['status']=='REMOVED'" in text and "final['both_nonces_containers_and_networks_absent']" in text,'fixture restoration and owned resource removal required')
check("SCHOOLSAFE_CARD_HMAC_SECRET=" in text and '/opt/schoolsafe-secrets' not in text,'no signing secret read from escrow or embedded in candidate')
check('access.prepare(release)' in text and 'tools.deploy(release)' in text,'existing preparation and cutover engines reused')
check('access.qualification=tools.qualification=qualification' in text,'every cutover rechecks the new qualification gate')
check("('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')" in text and "(ROOT/local).read_bytes()==git(release['commit'],path)" in text,'all bundled engines checked byte for byte against release Git')
engine=(ROOT/'ops/releases/prodeli-recovery-sms-20261022/deploy.py').read_text()
prepare=(ROOT/'ops/releases/school-native-access-20261024/deploy.py').read_text()
check("time.time() - backup['prepared_at'] < 900" in engine,'backup freshness enforced before cutover')
check("'--list'" in prepare and 'target.stat().st_mode&0o777==0o600' in prepare,'fresh database archive validated privately, never restored')
check('preserve.transfer_sessions(NAME, STAGED)' in engine and 'unchanged_runtime(pre, staged)' in engine,'sessions cache and runtime configuration retained')
check('schema(cli)' in prepare and 'BEGIN READ ONLY' in prepare,'existing read-only native schema checks retained')
check(not git('diff','--name-only',constants['BASE_COMMIT'],constants['SOURCE'],'--','ops/deployment/config.inc.production.php').strip(),'production config source unchanged')
for f,h in {'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php':'db4bacd3f15075635c0167b26fbbccc0e0a381aea753b21dd68b26caada831a9','assets/css/schoolsafe-photo-dashboard.css':'bc5ca4f486c3cc9c31b314a44fb0c1279d5a1df6d3402422a7cdc7d72b0cff05','assets/js/schoolsafe-photo-dashboard.js':'3fe54d481b7be891aeac09462865456bcd7e738f98b2e4a739a2f37342a980c5'}.items():
    check(hashlib.sha256(git('show',constants['SOURCE']+':'+constants['PREFIX']+f)).hexdigest()==h,'protected dashboard unchanged: '+f)
print(json.dumps({'status':'PASS','checks':len(checks),'results':checks}))
