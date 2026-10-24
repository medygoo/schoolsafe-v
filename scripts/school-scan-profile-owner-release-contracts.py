#!/usr/bin/env python3
"""Offline recipe guards with synthetic proofs and mocked Docker/Git reads; no deployment."""
import importlib.util,json,pathlib,tempfile,subprocess,shutil,argparse,copy
repo=pathlib.Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--evidence',required=True,type=pathlib.Path);a=p.parse_args()
spec=importlib.util.spec_from_file_location('recipe',repo/'ops/releases/school-scan-profile-owner-20261024/deploy.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
count=0
with tempfile.TemporaryDirectory(prefix='scan-owner-contract-')as tmp:
 m.ROOT=pathlib.Path(tmp)
 names=['source-manifest.json','image-prepare-proof.json','exact-integration-result.json','browser-cleanup-proof.json','card-authenticated-http-delegation-r1.private.json','image-verify-proof.json','image-cleanup-proof.json']
 for name in names:shutil.copyfile(a.evidence/name,m.ROOT/name)
 paths={'deploy.py':'ops/releases/school-scan-profile-owner-20261024/deploy.py','recovery.py':'ops/releases/prodeli-recovery-sms-20261022/deploy.py','preservation.py':'ops/releases/prodeli-native-access-20261021/deploy.py'}
 for name,path in paths.items():shutil.copyfile(repo/path,m.ROOT/name)
 manifest=m.load('source-manifest.json')
 release={'commit':'a'*40,'source_commit':m.SOURCE,'image':m.IMAGE,'source_digest':m.DIGEST,'files':{f:manifest['payload_hashes'][f]for f in m.FILES},'base_files':{f:False for f in m.FILES}}
 def run(command):
  assert command[:4]==['git','-C','/opt/app-src','show']
  commit,path=command[4].split(':',1)
  if path in paths.values():return (repo/path).read_bytes()
  if commit=='a'*40:commit=m.SOURCE
  return subprocess.check_output(['git','show',commit+':'+path],cwd=repo)
 m.run=run
 image={'Config':{'Labels':{'org.opencontainers.image.revision':m.SOURCE,'schoolsafe.source.digest':m.DIGEST},'Env':[]}}
 m.inspect=lambda _:image
 m.qualification(release);count+=1
 for name,change in [
  ('image-prepare-proof.json',lambda v:v.update(renderer_contracts=371)),
  ('image-prepare-proof.json',lambda v:v.update(published_ports=True)),
  ('image-verify-proof.json',lambda v:v.update(fixture_snapshot_unchanged=False)),
  ('image-cleanup-proof.json',lambda v:v.update(status='UNKNOWN')),
  ('exact-integration-result.json',lambda v:v['suites'][0].update(exit=1)),
  ('browser-cleanup-proof.json',lambda v:v.update(owned_browser_absent=False)),
  ('card-authenticated-http-delegation-r1.private.json',lambda v:v.update(exit=1)),
  ('card-authenticated-http-delegation-r1.private.json',lambda v:v.update(stderr='failure')),
  ('card-authenticated-http-delegation-r1.private.json',lambda v:v.update(stdout=json.dumps({'status':'PASS','checks':50,'results':[],'error':None})))]:
  before=(m.ROOT/name).read_bytes();value=json.loads(before);change(value);m.write(name,value)
  try:m.qualification(release)
  except (AssertionError,KeyError):count+=1
  else:raise AssertionError('Guard failed: '+name)
  finally:(m.ROOT/name).write_bytes(before)
 image['Config']['Env']=['SCHOOLSAFE_CARD_HMAC_SECRET='+'0'*64]
 try:m.qualification(release)
 except AssertionError:count+=1
 else:raise AssertionError('Image-baked key accepted')
 before={'Config':{'Env':['SCHOOLSAFE_CARD_HMAC_SECRET=synthetic-only'],'Cmd':[],'Entrypoint':[]},'HostConfig':{'Binds':[]}}
 m.tools.unchanged_runtime(before,copy.deepcopy(before));count+=1
 after=copy.deepcopy(before);after['Config']['Env']=['SCHOOLSAFE_CARD_HMAC_SECRET=changed']
 try:m.tools.unchanged_runtime(before,after)
 except AssertionError:count+=1
 else:raise AssertionError('Signing key rotation accepted')
print(json.dumps({'status':'PASS','checks':count,'scope':'Offline release guards, mocked Docker and Git recipe bytes; no production mutation'}))
