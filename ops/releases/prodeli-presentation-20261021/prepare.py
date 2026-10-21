#!/usr/bin/env python3
"""Build and inspect the exact four-file image without attaching to any database."""
from pathlib import Path
import subprocess,json,hashlib,os,re
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/prodeli-presentation-20261021')
BASE='sha256:98f978b397fd0c24b5c5f2204ee92665578aa7c88b97f4135a23fb39d8b10832'
def run(args):
    p=subprocess.run(args,capture_output=True,timeout=300)
    if p.returncode:
        (ROOT/'prepare-error.private.log').write_bytes(p.stderr);raise RuntimeError('Preparation failed: '+args[0])
    return p.stdout
if __name__=='__main__':
    assert not (ROOT/'prepare-proof.json').exists(),'Already prepared; inspect existing proof'
    release=json.loads((ROOT/'release.json').read_text());sha=release['commit'];assert re.fullmatch('[0-9a-f]{40}',sha)
    assert run(['docker','image','inspect','--format','{{.Id}}','schoolsafe-rosario:20261020-lot1a-d12a639']).decode().strip()==BASE
    assert (ROOT/'.dockerignore').read_text().startswith('**\n'), 'Build context must exclude private state'
    pre=json.loads(run(['docker','inspect','schoolsafe-rosario']))[0];assert pre['Image']==BASE
    (ROOT/'container-before.private.json').write_text(json.dumps(pre))
    for path,digest in release['files'].items():
        data=(ROOT/'payload'/Path(path).name).read_bytes();assert hashlib.sha256(data).hexdigest()==digest
        assert data==run(['git','-C','/opt/app-src','show',sha+':app/modules/codex-ui-v1/extensions-test-runtime/'+path])
        current=run(['docker','exec','schoolsafe-rosario','php','-r','echo hash_file("sha256",$argv[1]);','/var/www/html/'+path]).decode()
        assert current==release['base_files'][path],'Production source drift'
    tag='schoolsafe-rosario:prodeli-presentation-'+sha[:12]
    log=run(['docker','build','--network','none','--pull=false','--build-arg','SOURCE_COMMIT='+sha,'-t',tag,str(ROOT)])
    (ROOT/'build.log').write_bytes(log)
    image=run(['docker','image','inspect','--format','{{.Id}}',tag]).decode().strip()
    def cli(img,args,extra=[]):
        return run(['docker','run','--rm','--network','none','--read-only','--memory','512m','--cpus','0.75',*extra,'--entrypoint','php',img,*args])
    code='$root="/var/www/html/";$o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile())$o[substr($f->getPathname(),strlen($root))]=[hash_file("sha256",$f->getPathname()),$f->getPerms()&0777,$f->getOwner(),$f->getGroup()];}ksort($o);echo json_encode($o);'
    before=json.loads(cli(BASE,['-r',code]));after=json.loads(cli(image,['-r',code]))
    assert before.keys()==after.keys(),'Unexpected file added/removed'
    changes=[p for p in before if before[p]!=after[p]]
    assert set(changes)==set(release['files']),'Image changes outside approved four-file scope'
    for path,digest in release['files'].items():
        assert after[path][0]==digest
        assert after[path][1]==0o644
        if path.endswith('.php'):cli(image,['-l','/var/www/html/'+path])
    output=cli(image,['/tests/contracts.php'],['-e','SCHOOLSAFE_CONTRACT_PHP_ROOT=/var/www/html','--mount','type=bind,source='+str(ROOT/'contracts.php')+',target=/tests/contracts.php,readonly'])
    (ROOT/'image-contracts.log').write_bytes(output)
    result=json.loads(output.decode().splitlines()[-1]);assert result=={'passed':16,'failed':0}
    release.update({'image':image,'tag':tag});(ROOT/'release.json').write_text(json.dumps(release,indent=2))
    proof={'status':'PASS','commit':sha,'image':image,'contract_passed':16,'changed_files':changes,'unchanged_files':len(before)-4,'network':'none','database_used':False,'image_lint':'PASS'}
    (ROOT/'prepare-proof.json').write_text(json.dumps(proof,indent=2));print(json.dumps(proof))
