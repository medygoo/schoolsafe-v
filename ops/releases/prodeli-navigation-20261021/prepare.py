#!/usr/bin/env python3
"""Inspect exact dashboard-navigation image without database or network."""
from pathlib import Path
import subprocess,json,hashlib,os,re
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/prodeli-navigation-20261021')
BASE='sha256:3daf00a1c5990f6e7bac1564d6a47bdc322746c05f1e8db06fa7144f74e883fb'
def run(args):
    p=subprocess.run(args,capture_output=True,timeout=300)
    if p.returncode:
        (ROOT/'prepare-error.private.log').write_bytes(p.stderr);raise RuntimeError('Preparation failed: '+args[0])
    return p.stdout
if __name__=='__main__':
    assert not (ROOT/'prepare-proof.json').exists(),'Already prepared; inspect existing proof'
    release=json.loads((ROOT/'release.json').read_text());sha=release['commit'];assert re.fullmatch('[0-9a-f]{40}',sha)
    assert run(['docker','image','inspect','--format','{{.Id}}','schoolsafe-rosario:prodeli-communication-90f6238cfee0']).decode().strip()==BASE
    assert (ROOT/'.dockerignore').read_text().startswith('**\n'), 'Build context must exclude private state'
    pre=json.loads(run(['docker','inspect','schoolsafe-rosario']))[0];assert pre['Image']==BASE
    (ROOT/'container-before.private.json').write_text(json.dumps(pre))
    for path,digest in release['files'].items():
        data=(ROOT/'payload'/path).read_bytes();assert hashlib.sha256(data).hexdigest()==digest
        assert data==run(['git','-C','/opt/app-src','show',sha+':app/modules/codex-ui-v1/extensions-test-runtime/'+path])
        current=run(['docker','exec','schoolsafe-rosario','php','-r','echo is_file($argv[1]) ? hash_file("sha256",$argv[1]) : "ABSENT";','/var/www/html/'+path]).decode()
        assert current==release['base_files'][path],'Production source drift'
    tag='schoolsafe-rosario:prodeli-navigation-'+sha[:12]
    log=run(['docker','build','--network','none','--pull=false','--build-arg','SOURCE_COMMIT='+sha,'-t',tag,str(ROOT)])
    (ROOT/'build.log').write_bytes(log)
    image=run(['docker','image','inspect','--format','{{.Id}}',tag]).decode().strip()
    def cli(img,args,extra=[]):
        return run(['docker','run','--rm','--network','none','--read-only','--memory','512m','--cpus','0.75',*extra,'--entrypoint','php',img,*args])
    code='$root="/var/www/html/";$o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile())$o[substr($f->getPathname(),strlen($root))]=[hash_file("sha256",$f->getPathname()),$f->getPerms()&0777,$f->getOwner(),$f->getGroup()];}ksort($o);echo json_encode($o);'
    before=json.loads(cli(BASE,['-r',code]));after=json.loads(cli(image,['-r',code]))
    assert before.keys()==after.keys(),'Unexpected file added or removed'
    changes=[p for p in after if before[p]!=after[p]]
    assert set(changes)==set(release['files']),'Image changes outside approved navigation scope'
    for path,digest in release['files'].items():
        assert after[path][0]==digest
        assert after[path][1]==0o644
        if path.endswith('.php'):cli(image,['-l','/var/www/html/'+path])
    passed=0
    for filename,expected in [('contracts.php',16),('dashboard-contracts.php',12),('communication-contracts.php',28),('navigation-contracts.php',20)]:
        output=cli(image,['/tests/contracts.php'],['-e','SCHOOLSAFE_CONTRACT_PHP_ROOT=/var/www/html','--mount','type=bind,source='+str(ROOT/filename)+',target=/tests/contracts.php,readonly'])
        (ROOT/('image-'+filename+'.log')).write_bytes(output)
        result=json.loads(output.decode().splitlines()[-1]);assert result=={'passed':expected,'failed':0};passed+=expected
    release.update({'image':image,'tag':tag});(ROOT/'release.json').write_text(json.dumps(release,indent=2))
    proof={'status':'PASS','commit':sha,'image':image,'contract_passed':passed,'changed_files':changes,'unchanged_files':len(after)-len(changes),'network':'none','database_used':False,'image_lint':'PASS'}
    (ROOT/'prepare-proof.json').write_text(json.dumps(proof,indent=2));print(json.dumps(proof))
