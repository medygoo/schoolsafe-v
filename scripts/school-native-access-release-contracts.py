#!/usr/bin/env python3
"""Offline guards for this exact qualified five-file release. No Docker/SSH/write."""
import ast,hashlib,json,re,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
path='ops/releases/school-native-access-20261024/deploy.py'
text=(ROOT/path).read_text();tree=ast.parse(text)
constants={n.targets[0].id:ast.literal_eval(n.value) for n in tree.body if isinstance(n,ast.Assign) and isinstance(n.targets[0],ast.Name) and n.targets[0].id in {'BASE','BASE_COMMIT','SOURCE','TEST_SOURCE','IMAGE','DIGEST','FILES','PREFIX'}}
checks=[]
def check(ok,label):
 if not ok:raise AssertionError(label)
 checks.append(label)
def git(*args):return subprocess.check_output(['git',*args],cwd=ROOT)
check(constants['SOURCE']=='5cf4c9cbfaf379a252afc27f76fac17bc6c45d04','runtime is corrected qualified Git commit')
check(constants['BASE_COMMIT']=='e17357bbcf59068d8276c260314d3ffa8a1733a2','previous deployed checkout is pinned')
check(constants['BASE']=='sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628','previous live image is pinned')
check(constants['IMAGE']=='sha256:1b3843de24caa121243f231b147ddf0a9597b155deca7271ed699994f306fb86','candidate is final corrected image')
check(constants['DIGEST']=='c1ed80141175ab4ecc2c5110839a6b48a75a66e13eea79d27dc61936386cdded','qualified image digest pinned')
changed=git('diff','--name-only',constants['BASE_COMMIT'],constants['SOURCE'],'--',constants['PREFIX']).decode().splitlines()
check(set(changed)=={constants['PREFIX']+f for f in constants['FILES']} and len(changed)==5,'runtime delta exactly five intended paths')
check("len(manifest['payload_hashes'])==71" in text and "proof['unchanged_files']==2731" in text,'cumulative payload and unchanged files constrained')
check("proof['renderer_contracts']==387" in text and "proof['recovery_contracts']==37" in text,'contract counts constrained')
check("http_gate('scan-http.private.json',58);http_gate('access-http.private.json',49)" in text,'real Scan and shared writer HTTP proofs required')
check("pg['checks']==55" in text and "browser['checks']==21" in text,'new authority and form regression proofs required')
check("{'native','recovery','concurrency'}" in text and "all(s['exit']==0" in text,'three exact-image suites required')
check("candidate-provenance-and-final-cleanup-proof.json" in text and "for prefix in ['', 'shared-']" in text,'both fixtures restored and removed before deployment')
check("'BEGIN READ ONLY;SELECT '" in text and 'has_table_privilege' in text and 'has_sequence_privilege' in text,'native schema and service privileges checked read-only, never granted')
check(not any(isinstance(n,ast.Constant) and isinstance(n.value,str) and re.match(r'^(GRANT|ALTER|CREATE|DROP)\s',n.value,re.I) for n in ast.walk(tree)),'no schema or grants DDL in wrapper')
check('pg_restore' in text and "'--list'" in text and "target.stat().st_mode&0o777==0o600" in text,'backup archive privately validated, never restored')
check("keys[0]!='0'*64" in text and not any(x in text for x in ['/opt/schoolsafe-secrets','token_hex','urandom']),'durable key preserved, no escrow read or generation')
engine=(ROOT/'ops/releases/prodeli-recovery-sms-20261022/deploy.py').read_text()
check("time.time() - backup['prepared_at'] < 900" in engine and 'preserve.transfer_sessions(NAME, STAGED)' in engine,'existing engine enforces backup freshness and session/cache transfer')
check("tools.qualification=qualification;tools.source_hashes=source_hashes" in text and 'unchanged_runtime(pre, staged)' in engine,'existing environment/runtime preservation engine reused')
check(not git('diff','--name-only',constants['BASE_COMMIT'],constants['SOURCE'],'--','ops/deployment/config.inc.production.php').strip(),'production configuration source unchanged')
for f,h in {'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php':'db4bacd3f15075635c0167b26fbbccc0e0a381aea753b21dd68b26caada831a9','assets/css/schoolsafe-photo-dashboard.css':'bc5ca4f486c3cc9c31b314a44fb0c1279d5a1df6d3402422a7cdc7d72b0cff05','assets/js/schoolsafe-photo-dashboard.js':'3fe54d481b7be891aeac09462865456bcd7e738f98b2e4a739a2f37342a980c5'}.items():
 check(hashlib.sha256(git('show',constants['SOURCE']+':'+constants['PREFIX']+f)).hexdigest()==h,'protected dashboard unchanged: '+f)
print(json.dumps({'status':'PASS','checks':len(checks),'results':checks}))
