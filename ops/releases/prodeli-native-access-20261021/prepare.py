#!/usr/bin/env python3
"""Build and compare exact native first-access image, no database or network."""
from pathlib import Path
import subprocess,json,hashlib,os,re
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/prodeli-native-access-20261021')
BASE='sha256:c8d10345fc7018098c87bcb56551fc41f253dbe4f1d578a96733382c55afe45b'
def run(args):
 p=subprocess.run(args,capture_output=True,timeout=300)
 if p.returncode:
  (ROOT/'prepare-error.private.log').write_bytes(p.stderr);raise RuntimeError('Preparation failed: '+args[0])
 return p.stdout
if __name__=='__main__':
 assert not (ROOT/'prepare-proof.json').exists(),'Already prepared'
 release=json.loads((ROOT/'release.json').read_text());sha=release['commit'];assert re.fullmatch('[0-9a-f]{40}',sha)
 assert run(['docker','image','inspect','--format','{{.Id}}','schoolsafe-rosario:prodeli-navigation-3eb2e9e35984']).decode().strip()==BASE
 assert (ROOT/'.dockerignore').read_text().startswith('**\n')
 pre=json.loads(run(['docker','inspect','schoolsafe-rosario']))[0];assert pre['Image']==BASE
 (ROOT/'container-before.private.json').write_text(json.dumps(pre))
 for path,digest in release['files'].items():
  data=(ROOT/'payload'/path).read_bytes();assert hashlib.sha256(data).hexdigest()==digest
  assert data==run(['git','-C','/opt/app-src','show',sha+':app/modules/codex-ui-v1/extensions-test-runtime/'+path])
  current=run(['docker','exec','schoolsafe-rosario','php','-r','echo is_file($argv[1]) ? hash_file("sha256",$argv[1]) : "ABSENT";','/var/www/html/'+path]).decode()
  assert current==release['base_files'][path],'Production source drift'
 tag='schoolsafe-rosario:prodeli-native-access-'+sha[:12]
 log=run(['docker','build','--network','none','--pull=false','--build-arg','SOURCE_COMMIT='+sha,'-t',tag,str(ROOT)])
 (ROOT/'build.log').write_bytes(log)
 image=run(['docker','image','inspect','--format','{{.Id}}',tag]).decode().strip()
 def cli(img,args,extra=[]):
  return run(['docker','run','--rm','--network','none','--read-only','--memory','512m','--cpus','0.75',*extra,'--entrypoint','php',img,*args])
 code='$root="/var/www/html/";$o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile())$o[substr($f->getPathname(),strlen($root))]=[hash_file("sha256",$f->getPathname()),$f->getPerms()&0777,$f->getOwner(),$f->getGroup()];}ksort($o);echo json_encode($o);'
 before=json.loads(cli(BASE,['-r',code]));after=json.loads(cli(image,['-r',code]));assert set(before)<=set(after),'File removed'
 changes=[p for p in after if before.get(p)!=after[p]]
 assert set(changes)==set(release['files']),'Image changes outside approved scope'
 for path,digest in release['files'].items():
  assert after[path][0]==digest and after[path][1]==0o644
  if path.endswith('.php'):cli(image,['-l','/var/www/html/'+path])
 passed=0
 for filename,expected in [('contracts.php',16),('dashboard-contracts.php',12),('communication-contracts.php',28),('navigation-contracts.php',20),('native-intent-contracts.php',16)]:
  output=cli(image,['/tests/contracts.php'],['-e','SCHOOLSAFE_CONTRACT_PHP_ROOT=/var/www/html','--mount','type=bind,source='+str(ROOT/filename)+',target=/tests/contracts.php,readonly'])
  (ROOT/('image-'+filename+'.log')).write_bytes(output)
  result=json.loads(output.decode().splitlines()[-1]);assert result=={'passed':expected,'failed':0};passed+=expected
 release.update({'image':image,'tag':tag});(ROOT/'release.json').write_text(json.dumps(release,indent=2))
 proof={'status':'PASS','commit':sha,'image':image,'contract_passed':passed,'changed_files':changes,'unchanged_files':len(after)-len(changes),'added_files':list(set(after)-set(before)),'network':'none','database_used':False,'image_lint':'PASS'}
 (ROOT/'prepare-proof.json').write_text(json.dumps(proof,indent=2));print(json.dumps(proof))
