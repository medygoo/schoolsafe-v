#!/usr/bin/env python3
"""Offline guards for exactly the two state helper release sources."""
import ast,hashlib,json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1];path=root/'ops/releases/prodeli-state-safety-20261024/deploy.py';text=path.read_text();tree=ast.parse(text)
keys={'BASE','BASE_COMMIT','SOURCE','TEST_SOURCE','IMAGE','DIGEST','PREFIX','FILES','BASE_BRANCH','BRANCH'}
values={n.targets[0].id:ast.literal_eval(n.value) for n in tree.body if isinstance(n,ast.Assign) and isinstance(n.targets[0],ast.Name) and n.targets[0].id in keys}
def git(*a):return subprocess.check_output(['git',*a],cwd=root)
assert values['BASE']=='sha256:1df5f8578805868544f1a7a73d6a1d437c5be0bf0ecf52cd716b41455e35aea8'
assert values['BASE_COMMIT']=='3ecdf9e5dc0558d5fb5c374a12cfd8df00309ad6'
assert values['SOURCE']=='68fe3c3470acad111a085f52c1a8b5be4bd9a0ac'
assert values['TEST_SOURCE']=='108dc5c427d715522410a1df9687cb9ee9317648'
assert values['IMAGE']=='sha256:1705fc9453c0788534273c5ba6a96bd799f15574ade8e02c7ff55351a8d72a3c'
assert values['DIGEST']=='7b22f0a09547a8cf339aefd7090f5a64faa9e0184ae30e418f9ad9efbd6a3188'
assert values['FILES']=={'ProgramFunctions/SchoolSafeSchoolState.fnc.php','ProgramFunctions/SchoolSafeAccountState.fnc.php'}
assert values['BASE_BRANCH']=='deployed/prodeli-access-discovery-20261024'
assert set(git('diff','--name-only',values['BASE_COMMIT'],values['SOURCE'],'--',values['PREFIX']).decode().splitlines())=={values['PREFIX']+f for f in values['FILES']}
assert 'proof[\'unchanged_files\']==2800' in text and "result['checks']==16" in text
assert 'verification[\'fixture_snapshot_unchanged\']' in text and "cleanup['status']=='REMOVED'" in text
assert 'len(expected)==75' in text and 'actual==expected' in text
assert "access.schema=state_schema" in text and "BEGIN READ ONLY;SELECT" in text and 'no implicit grant' in text
assert 'access.prepare(release)' in text and 'tools.deploy(release)' in text
assert 'access.qualification=tools.qualification=qualification' in text
assert "('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')" in text
assert hashlib.sha256(git('show',values['TEST_SOURCE']+':scripts/prodeli-school-state-http.php')).hexdigest()==hashlib.sha256((root/'scripts/prodeli-school-state-http.php').read_bytes()).hexdigest()
for f,h in {'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php':'db4bacd3f15075635c0167b26fbbccc0e0a381aea753b21dd68b26caada831a9','assets/css/schoolsafe-photo-dashboard.css':'bc5ca4f486c3cc9c31b314a44fb0c1279d5a1df6d3402422a7cdc7d72b0cff05','assets/js/schoolsafe-photo-dashboard.js':'3fe54d481b7be891aeac09462865456bcd7e738f98b2e4a739a2f37342a980c5'}.items():
 assert hashlib.sha256(git('show',values['SOURCE']+':'+values['PREFIX']+f)).hexdigest()==h
print(json.dumps({'status':'PASS','checks':19,'scope':'two native state helpers; no dashboard or permission changes'}))
