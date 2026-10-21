from pathlib import Path
import subprocess,json,secrets,time,re
ROOT=Path(__file__).resolve().parent;OUT=ROOT/'artifacts/zap';OUT.mkdir(parents=True,exist_ok=True)
NET='schoolsafe-tooling-zap-net';ZAP='schoolsafe-tooling-zap';TARGET='schoolsafe-tooling-zap-fixture'
IMAGE='ghcr.io/zaproxy/zaproxy@sha256:0cbc40e0fa1562d5170a18a247eca579c4d155ad869f8944c3b8526d4a58c64c'
report={'tool':'OWASP ZAP','cases':[]};created=[]
def run(args,data=None,timeout=60):
    p=subprocess.run(args,input=data,capture_output=True,timeout=timeout)
    if p.returncode:raise RuntimeError('Tool command failed: '+args[0]+'; no credential-bearing command output printed')
    return p.stdout
key=secrets.token_hex(32)
def api(path,params=None):
    # Transport via stdin: the API key never appears in the command line or reports.
    script="import json,sys,urllib.request,urllib.parse; x=json.load(sys.stdin); u='http://schoolsafe-tooling-zap:8080'+x['path']+'?'+urllib.parse.urlencode(x['params']); print(urllib.request.urlopen(u,timeout=15).read().decode())"
    return json.loads(run(['docker','exec','-i',TARGET,'python','-c',script],json.dumps({'path':path,'params':dict(params or {},apikey=key)}).encode(),timeout=25))
try:
    for kind,name in [('container',ZAP),('container',TARGET),('network',NET)]:
        assert subprocess.run(['docker',kind,'inspect',name],capture_output=True).returncode!=0,'Dedicated resource exists'
    obj=json.loads(run(['docker','image','inspect',IMAGE]))[0];report['image']=obj['Id'];report['digests']=obj.get('RepoDigests',[])
    run(['docker','network','create','--internal',NET]);created.append(('network',NET))
    run(['docker','run','-d','--pull=never','--name',TARGET,'--network',NET,'--memory','128m','--cpus','0.5','--mount','type=bind,source='+str(ROOT/'zap-fixture.py')+',target=/fixture.py,readonly','--entrypoint','python','bugsink/bugsink@sha256:c5e3657451f4ba1ddb145a5118a4347314206cc5ba98f40e5f268c6c0975963e','/fixture.py']);created.append(('container',TARGET))
    target_ip=json.loads(run(['docker','inspect',TARGET]))[0]['NetworkSettings']['Networks'][NET]['IPAddress']
    run(['docker','run','-d','--pull=never','--name',ZAP,'--network',NET,'--memory','1536m','--cpus','1',IMAGE,'zap.sh','-daemon','-host','0.0.0.0','-port','8080','-config','api.key='+key,'-config','api.addrs.addr.name=^('+re.escape(target_ip)+'|schoolsafe-tooling-zap|localhost|127\\.0\\.0\\.1|::1)$','-config','api.addrs.addr.regex=true','-config','autoupdate.checkOnStart=false','-config','autoupdate.downloadNewRelease=false','-config','autoupdate.installAddonUpdates=false']);created.append(('container',ZAP))
    for _ in range(60):
        state=json.loads(run(['docker','inspect',ZAP]))[0]['State']
        if not state['Running']:raise RuntimeError('ZAP container exited during startup')
        try:report['version']=api('/JSON/core/view/version/')['version'];break
        except (RuntimeError,KeyError,ValueError):time.sleep(2)
    else:raise RuntimeError('ZAP did not become ready')
    for name in ['clean','negative']:
        url='http://'+TARGET+':8018/'+name
        api('/JSON/core/action/accessUrl/',{'url':url,'followRedirects':'false'})
        for _ in range(60):
            if int(api('/JSON/pscan/view/recordsToScan/')['recordsToScan'])==0:break
            time.sleep(1)
        else:raise RuntimeError('Passive scanner did not drain')
        alerts=api('/JSON/core/view/alerts/',{'baseurl':url,'start':0,'count':100})['alerts']
        missing=[a for a in alerts if a.get('pluginId')=='10021']
        assert bool(missing)==(name=='negative'), 'Missing nosniff header must distinguish fixtures'
        report['cases'].append({'fixture':name,'nosniff_findings':len(missing),'alert_ids':sorted(set(a.get('pluginId') for a in alerts)),'status':'PASS'})
    report['status']='GREEN';report['scope']='passive header scanner, isolated fictional target only; active attack scanner not qualified'
except Exception as e:report['status']='RED';report['error']=str(e)
finally:
    if ('container',ZAP) in created:
        diagnostic=subprocess.run(['docker','logs',ZAP],capture_output=True,timeout=20)
        (OUT/'startup-redacted.log').write_bytes((diagnostic.stdout+diagnostic.stderr).replace(key.encode(),b'REDACTED'))
    for kind,name in reversed(created):
        subprocess.run(['docker','rm','-f','-v',name] if kind=='container' else ['docker','network','rm',name],capture_output=True,timeout=30)
    (OUT/'verification.json').write_text(json.dumps(report,indent=2));print(json.dumps(report,indent=2))
raise SystemExit(0 if report['status']=='GREEN' else 1)
