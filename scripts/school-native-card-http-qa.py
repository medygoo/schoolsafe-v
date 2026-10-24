#!/usr/bin/env python3
"""Execute synthetic native card HTTP gate ONLY in an existing nonce-owned QA image."""
import argparse,base64,json,pathlib,re,subprocess
p=argparse.ArgumentParser(description=__doc__);p.add_argument('--evidence',required=True,type=pathlib.Path);p.add_argument('--run',required=True);p.add_argument('--delegation',action='store_true');a=p.parse_args()
assert re.fullmatch('[a-z0-9-]+',a.run)
out=a.evidence.resolve();state=json.loads((out/'image-qa-state.json').read_text())
assert re.fullmatch('[a-f0-9]{12}',state['nonce'])
assert state['web']=='schoolsafe-recovery-qa-web-'+state['nonce'] and state['db']=='schoolsafe-recovery-qa-db-'+state['nonce']
script=base64.b64encode((pathlib.Path(__file__).parent/'school-native-card-http.php').read_bytes()).decode()
code='''import subprocess,json,base64
s=STATE
for name in [s['web'],s['db']]:
 d=json.loads(subprocess.check_output(['docker','inspect',name]))[0]
 assert d['Config']['Labels']['schoolsafe.recovery-qa']==s['nonce'] and d['Config']['Labels']['schoolcef.qualification']=='school-scan-20261023'
 assert not d['HostConfig']['PortBindings']
 if name==s['web']:assert d['Image']==s['image'] and d['HostConfig']['ReadonlyRootfs']
d=json.loads(subprocess.check_output(['docker','network','inspect',s['network']]))[0];assert d['Internal']
p=subprocess.run(['docker','exec','-i','-u','www-data','-e','SCHOOLSAFE_SCAN_HTTP_FIXTURE=schoolcef-synthetic-image-20261023','-e','SCHOOLSAFE_NATIVE_DELEGATION_QA='+('1' if DELEGATION else '0'),s['web'],'php'],input=base64.b64decode(SCRIPT),capture_output=True,timeout=120)
print(json.dumps({'exit':p.returncode,'stdout':p.stdout.decode(),'stderr':p.stderr.decode()}))
'''
r=subprocess.run(['ssh','-4','-o','BatchMode=yes','prodeli-hub-01','python3','-'],input=('STATE='+repr(state)+'\nSCRIPT='+repr(script)+'\nDELEGATION='+repr(a.delegation)+'\n'+code).encode(),capture_output=True,timeout=150,check=True)
result=json.loads(r.stdout);target=out/('card-authenticated-http-'+a.run+'.private.json');assert not target.exists();target.write_bytes(r.stdout)
print(result['stdout']);print(result['stderr']);raise SystemExit(result['exit'])
