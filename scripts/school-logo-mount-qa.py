#!/usr/bin/env python3
"""QA only: owned disposable bind, synthetic JPEG, restart to prove persistence."""
import argparse,json,pathlib,re,subprocess
p=argparse.ArgumentParser();p.add_argument('--evidence',required=True,type=pathlib.Path);a=p.parse_args()
out=a.evidence.resolve();state=json.loads((out/'image-qa-state.json').read_text());assert re.fullmatch('[a-f0-9]{12}',state['nonce'])
script=r'''import json,subprocess,pathlib,os
s=STATE
assert json.loads(subprocess.check_output(['docker','inspect',s['web']]))[0]['Image']==s['image']
root=pathlib.Path('/var/tmp/schoolsafe-logo-mount-qa-'+s['nonce']);assert not root.exists();root.mkdir(mode=0o770);os.chown(root,0,33);os.chmod(root,0o2770)
container=['docker','run','--rm','--pull=never','--network','none','--read-only','--tmpfs','/tmp:rw,nosuid,nodev','-u','www-data','--mount','type=bind,src='+str(root)+',dst=/var/www/html/assets/school-logos','--entrypoint','php',s['image'],'-r']
create='$i=imagecreatetruecolor(24,12);imagefill($i,0,0,imagecolorallocate($i,33,99,156));$p="/var/www/html/assets/school-logos/school_logo_987654321.jpg";if(!imagejpeg($i,$p))exit(2);echo hash_file("sha256",$p);'
read='$p="/var/www/html/assets/school-logos/school_logo_987654321.jpg";if(!is_file($p))exit(3);echo hash_file("sha256",$p);'
delete='$p="/var/www/html/assets/school-logos/school_logo_987654321.jpg";if(!unlink($p))exit(4);echo "removed";'
try:
 first=subprocess.check_output(container+[create],timeout=60).decode();second=subprocess.check_output(container+[read],timeout=60).decode()
 assert len(first)==64 and first==second
 assert subprocess.check_output(container+[delete],timeout=60)==b'removed'
 assert not list(root.iterdir())
 print(json.dumps({'status':'PASS','checks':4,'read_write_as_www_data':True,'persisted_across_disposable_containers':True,'synthetic_file_removed':True,'production_untouched':True}))
finally:
 if root.is_dir() and not list(root.iterdir()):root.rmdir()
'''
r=subprocess.run(['ssh','-4','-o','BatchMode=yes','-o','ConnectTimeout=20','prodeli-hub-01','python3','-'],input=('STATE='+repr(state)+'\n'+script).encode(),capture_output=True,timeout=170)
if r.returncode:raise RuntimeError('Disposable bind proof failed; inspect private evidence; no production change')
result=json.loads(r.stdout);(out/'logo-mount-qa-proof.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
