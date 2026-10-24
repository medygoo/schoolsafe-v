#!/usr/bin/env python3
"""Offline immutable-scope checks before any release staging."""
import ast,json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1];text=(root/'ops/releases/school-scanner-theme-20261024/deploy.py').read_text();tree=ast.parse(text)
keys={'BASE','BASE_COMMIT','SOURCE','TEST_SOURCE','IMAGE','DIGEST','FILES','NEW','BASE_BRANCH','BRANCH','PREFIX'}
v={}
for n in tree.body:
 if isinstance(n,ast.Assign) and isinstance(n.targets[0],ast.Name) and n.targets[0].id in keys:
  v[n.targets[0].id]=set(ast.literal_eval(x) if not isinstance(x,ast.Name) else v[x.id] for x in n.value.elts) if n.targets[0].id=='FILES' else ast.literal_eval(n.value)
assert v['BASE']=='sha256:1705fc9453c0788534273c5ba6a96bd799f15574ade8e02c7ff55351a8d72a3c'
assert v['BASE_COMMIT']=='335643ef61cc67ea56d43ba51181e72cd450d1f1'
assert v['SOURCE']=='abf4145404f1dc45696c9519f7065ea2cf545661'
assert v['TEST_SOURCE']=='1a3adc40a0348824cc5f6decda51c3d6dd69f016'
assert v['IMAGE']=='sha256:14209bb46a9ca9d31f00c9f64ad1dab4301ef8894249f9c39e6373c9373d0183'
assert v['DIGEST']=='8517438552874007cc2b408a3b3638ea7ed6a4ecb46b65fa0ae452351e006426'
assert v['FILES']=={'scanner-native.php','schoolsafe-card-bridge/scanner-native.css','schoolsafe-card-bridge/scanner-native.js','schoolsafe-card-bridge/scanner-theme.js'}
assert v['NEW'] in v['FILES'] and v['BASE_BRANCH']=='deployed/prodeli-state-safety-20261024'
changed=set(subprocess.check_output(['git','-C',str(root),'diff','--name-only',v['BASE_COMMIT'],v['SOURCE'],'--',v['PREFIX']]).decode().splitlines())
assert changed=={v['PREFIX']+f for f in v['FILES']}
for snippet in ["result['checks']==58","browser['checks']==14","all(x['status']=='PASS'","len(expected)==77","actual==expected","release['base_files'][NEW] is False","proof['unchanged_files']==2799","access.prepare(release)","tools.deploy(release)","cleanup['status']=='REMOVED'","proof['production_containers_unchanged']"]:assert snippet in text,snippet
print(json.dumps({'status':'PASS','checks':19,'scope':'four scanner UI files, no dashboard or security helper edits'}))
