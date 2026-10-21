#!/usr/bin/env python3
"""Four-file release. No migrations or account actions. Private state stays on VPS."""
from pathlib import Path
import subprocess,json,hashlib,time,os,sys,http.client,socket,tarfile,io,re
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/prodeli-presentation-20261021')
NAME='schoolsafe-rosario';OLD=NAME+'-pre-prodeli-presentation-20261021'
FAILED=NAME+'-failed-prodeli-presentation-20261021'
BASE='sha256:98f978b397fd0c24b5c5f2204ee92665578aa7c88b97f4135a23fb39d8b10832'
BASE_COMMIT='d12a6398e473ea2b5238dba338af19d0ebf7df8a'
BASE_BRANCH='deployed/lot1a-readonly-20261020'
BRANCH='deployed/prodeli-presentation-20261021'
OTHERS=['schoolsafe-app','schoolsafe-rosario-db','schoolsafe-evolution','schoolsafe-evolution-db','schoolsafe-waha']
def run(args,data=None,timeout=120):
    p=subprocess.run(args,input=data,capture_output=True,timeout=timeout)
    if p.returncode:
        with (ROOT/'errors.private.log').open('ab') as f:f.write(p.stderr)
        raise RuntimeError('Operation failed: '+args[0]+'; details private on VPS')
    return p.stdout

def inspect(name):return json.loads(run(['docker','inspect',name]))[0]
def other_state():return {n:(inspect(n)['Id'],inspect(n)['State']['StartedAt']) for n in OTHERS}
def probe(public=False):
    c=http.client.HTTPSConnection('app.shoolsafe.tech',timeout=20) if public else http.client.HTTPConnection('127.0.0.1',18081,timeout=15)
    c.request('GET','/index.php');r=c.getresponse();body=r.read();c.close()
    assert r.status==200 and b'USERNAME' in body and b'PASSWORD' in body,'Login probe failed'

def check_layer(name):
    allowed={'/run','/run/apache2','/run/apache2/apache2.pid','/tmp','/var','/var/www','/var/www/html','/var/www/html/config.inc.php'}
    for line in run(['docker','diff',name]).decode().splitlines():
        p=line[2:]
        assert p in allowed or p.startswith('/tmp/sess_'),'Unpreserved writable data: stop and inspect privately'

def sessions(data):
    result={}
    with tarfile.open(fileobj=io.BytesIO(data)) as t:
        for m in t.getmembers():
            if m.isfile() and Path(m.name).name.startswith('sess_'):
                result[m.name]=(hashlib.sha256(t.extractfile(m).read()).hexdigest(),m.uid,m.gid,m.mode)
    return result

def transfer_sessions(source,target,clear_stale=False):
    data=run(['docker','cp','-a',source+':/tmp/.','-'])
    if clear_stale:
        prior=run(['docker','cp','-a',target+':/tmp/.','-']);out=io.BytesIO()
        with tarfile.open(fileobj=io.BytesIO(prior)) as old,tarfile.open(fileobj=out,mode='w') as empty:
            for m in old.getmembers():
                if m.isfile() and Path(m.name).name.startswith('sess_'):
                    m.size=0;empty.addfile(m,io.BytesIO(b''))
        run(['docker','cp','-a','-',target+':/tmp'],out.getvalue())
    (ROOT/'sessions-latest.private.tar').write_bytes(data)
    run(['docker','cp','-a','-',target+':/tmp'],data)
    actual=sessions(run(['docker','cp','-a',target+':/tmp/.','-']))
    expected=sessions(data)
    for k,v in expected.items():assert actual.get(k)==v,'Session transfer failed'
    if not clear_stale:assert actual==expected,'Unexpected sessions in new container'
    else:
        for k,v in actual.items():
            if k not in expected:assert v[0]==hashlib.sha256(b'').hexdigest(),'Stale authenticated session retained'
    return len(expected)

class UnixHTTP(http.client.HTTPConnection):
    def connect(self):
        self.sock=socket.socket(socket.AF_UNIX,socket.SOCK_STREAM);self.sock.settimeout(30);self.sock.connect('/var/run/docker.sock')
def create(pre,image,commit):
    payload=dict(pre['Config']);payload['Image']=image
    payload['Labels']=dict(payload.get('Labels') or {});payload['Labels'].update({'schoolsafe.patch.commit':commit,'schoolsafe.patch.scope':'prodeli-presentation-only'})
    payload['HostConfig']=dict(pre['HostConfig'])
    if payload['HostConfig'].get('OomKillDisable') is None:payload['HostConfig']['OomKillDisable']=False
    net=pre['HostConfig']['NetworkMode'];endpoint=pre['NetworkSettings']['Networks'][net]
    payload['NetworkingConfig']={'EndpointsConfig':{net:{'Aliases':endpoint.get('Aliases') or []}}}
    c=UnixHTTP('localhost');c.request('POST','/v1.47/containers/create?name='+NAME,body=json.dumps(payload),headers={'Content-Type':'application/json'})
    r=c.getresponse();body=r.read();c.close()
    if r.status!=201:
        (ROOT/'create-error.private.json').write_bytes(body);raise RuntimeError('Container creation failed; private diagnostic saved')
    return json.loads(body)['Id']

def rollback(release):
    assert inspect(NAME)['Image']==release['image'] and inspect(OLD)['Image']==BASE
    assert subprocess.run(['docker','inspect',FAILED],capture_output=True).returncode!=0
    check_layer(NAME)
    run(['docker','stop','-t','30',NAME])
    try:
        check_layer(NAME);transfer_sessions(NAME,OLD,True)
    except Exception:
        run(['docker','start',NAME]);raise
    run(['docker','rename',NAME,FAILED]);run(['docker','rename',OLD,NAME])
    run(['git','-C','/opt/app-src','switch',BASE_BRANCH]);run(['docker','start',NAME])
    for _ in range(45):
        try:probe();break
        except Exception:time.sleep(1)
    else:raise RuntimeError('Rollback started; health not confirmed')
    probe(True);print('Rollback verified; no database restoration performed')

if __name__=='__main__':
    release=json.loads((ROOT/'release.json').read_text());sha=release['commit'];image=release['image']
    assert re.fullmatch('[0-9a-f]{40}',sha) and re.fullmatch('sha256:[0-9a-f]{64}',image)
    if '--rollback' in sys.argv:rollback(release);sys.exit(0)
    assert not (ROOT/'deployment-proof.json').exists(),'Already deployed'
    proof=json.loads((ROOT/'prepare-proof.json').read_text());assert proof['status']=='PASS' and proof['image']==image and proof['contract_passed']==16
    ui=json.loads((ROOT/'local-ui-tests.json').read_text());assert ui['cleanup'] and len(ui['results'])==6 and all(x['status']=='PASS' for x in ui['results'])
    pre=json.loads((ROOT/'container-before.private.json').read_text());current=inspect(NAME)
    assert current['Image']==BASE and current['Id']==pre['Id'] and current['Config']==pre['Config'] and current['HostConfig']==pre['HostConfig']
    assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip()==BASE_COMMIT
    assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip()==BASE_BRANCH
    assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
    assert subprocess.run(['docker','inspect',OLD],capture_output=True).returncode!=0
    assert subprocess.run(['docker','inspect',FAILED],capture_output=True).returncode!=0
    assert inspect(image)['Id']==image
    check_layer(NAME);probe();probe(True);others=other_state()
    stopped=renamed=created=switched=started_new=False;start=time.monotonic()
    try:
        run(['git','-C','/opt/app-src','switch','-c',BRANCH,sha]);switched=True
        run(['docker','stop','-t','30',NAME]);stopped=True
        check_layer(NAME)
        run(['docker','rename',NAME,OLD]);renamed=True
        create(pre,image,sha);created=True
        count=transfer_sessions(OLD,NAME)
        run(['docker','start',NAME]);started_new=True
        for _ in range(60):
            state=inspect(NAME)['State']
            if state.get('Health',{}).get('Status')=='healthy':break
            if not state['Running']:raise RuntimeError('PHP container stopped')
            time.sleep(1)
        else:raise RuntimeError('Healthcheck not healthy')
        probe();probe(True)
        for route in ['/schoolsafe-administrators.php','/schoolsafe-administrators.php?accounts=central','/admin-console.php']:
            c=http.client.HTTPConnection('127.0.0.1',18081,timeout=15);c.request('GET',route);response=c.getresponse();response.read();c.close()
            assert response.status in [302,303,403],'Unauthenticated protected route opened'
        for path,digest in release['files'].items():
            actual=run(['docker','exec',NAME,'php','-r','echo hash_file("sha256", $argv[1]);','/var/www/html/'+path]).decode().strip()
            assert actual==digest,'Served source mismatch'
        new=inspect(NAME)
        assert new['Config']['Env']==pre['Config']['Env'] and new['HostConfig']['Binds']==pre['HostConfig']['Binds']
        for key in ['PortBindings','NetworkMode','RestartPolicy','Privileged','CapAdd','CapDrop','SecurityOpt','Memory','NanoCpus','LogConfig']:
            assert new['HostConfig'][key]==pre['HostConfig'][key], 'Runtime setting changed: '+key
        assert other_state()==others,'Unrelated service changed'
        result={'status':'DEPLOYED','commit':sha,'image':image,'files':release['files'],'scope':'presentation only','session_files_preserved':count,'other_services_unchanged':True,'public_login':200,'unauthenticated_routes_protected':True,'database_migration':False,'production_account_mutations_tested':False,'rollback_container':OLD,'rollback_command':'python3 '+str(ROOT/'deploy.py')+' --rollback','elapsed_seconds':round(time.monotonic()-start,2)}
        (ROOT/'deployment-proof.json').write_text(json.dumps(result,indent=2));print(json.dumps(result))
    except Exception:
        if created:
            run(['docker','stop','-t','15',NAME])
            try:
                check_layer(NAME)
                if started_new:transfer_sessions(NAME,OLD,True)
            except Exception:
                run(['docker','start',NAME])
                (ROOT/'manual-recovery-required.json').write_text(json.dumps({'reason':'Preservation check blocked rollback; candidate restarted rather than discard data'}))
                raise
            run(['docker','rename',NAME,FAILED])
        if renamed:run(['docker','rename',OLD,NAME])
        if switched:run(['git','-C','/opt/app-src','switch',BASE_BRANCH])
        if stopped:run(['docker','start',NAME])
        (ROOT/'deployment-failure.json').write_text(json.dumps({'automatic_rollback_attempted':True}))
        raise
