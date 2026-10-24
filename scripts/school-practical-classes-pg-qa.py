#!/usr/bin/env python3
"""Run TP counting on disposable exact-image PostgreSQL fixture only."""
import argparse,base64,json,pathlib,re,subprocess
p=argparse.ArgumentParser();p.add_argument('--evidence',required=True,type=pathlib.Path);p.add_argument('--run',required=True);a=p.parse_args();assert re.fullmatch('[a-z0-9-]+',a.run)
out=a.evidence.resolve();state=json.loads((out/'image-qa-state.json').read_text());assert re.fullmatch('[a-f0-9]{12}',state['nonce'])
script=base64.b64encode((pathlib.Path(__file__).parent/'school-practical-classes-pg.php').read_bytes()).decode()
code='''import subprocess,json,base64
s=STATE
for name in [s['web'],s['db']]:
 d=json.loads(subprocess.check_output(['docker','inspect',name]))[0]
 assert d['Config']['Labels']['schoolsafe.recovery-qa']==s['nonce'] and d['Config']['Labels']['schoolcef.qualification']=='school-scan-20261023'
 assert not d['HostConfig']['PortBindings']
 if name==s['web']:assert d['Image']==s['image'] and d['HostConfig']['ReadonlyRootfs']
assert json.loads(subprocess.check_output(['docker','network','inspect',s['network']]))[0]['Internal']
p=subprocess.run(['docker','exec','-i','-u','www-data','-e','SCHOOLSAFE_PRACTICALS_FIXTURE=synthetic-only',s['web'],'php'],input=base64.b64decode(SCRIPT),capture_output=True,timeout=90)
print(json.dumps({'exit':p.returncode,'stdout':p.stdout.decode(),'stderr':p.stderr.decode()}))
'''
r=subprocess.run(['ssh','-4','-o','BatchMode=yes','-o','ConnectTimeout=20','prodeli-hub-01','python3','-'],input=('STATE='+repr(state)+'\nSCRIPT='+repr(script)+'\n'+code).encode(),capture_output=True,timeout=130)
if r.returncode:raise RuntimeError('Fixture transport failed; inspect stderr privately')
target=out/('practical-pg-'+a.run+'.private.json');assert not target.exists();target.write_bytes(r.stdout)
result=json.loads(r.stdout);print(result['stdout']);print(result['stderr']);raise SystemExit(result['exit'])
