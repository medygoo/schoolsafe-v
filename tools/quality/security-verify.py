"""Qualification self-tests or read-only repository scans. Findings never auto-modify source."""
from pathlib import Path
import subprocess,json,os,sys,secrets,string
ROOT=Path(__file__).resolve().parent;REPO=ROOT.parent.parent
OUT=ROOT/'artifacts/security';OUT.mkdir(parents=True,exist_ok=True)
MODE=sys.argv[1] if len(sys.argv)>1 else 'selftest'
if MODE not in ['selftest','source']:raise SystemExit('Expected selftest|source')
EXE='.exe' if os.name=='nt' else ''
SEMGREP='semgrep/semgrep@sha256:4a34048c04fef049f71665047f074cffb00cc3d89482f965a5cd151d589237af'
ENV={k:v for k,v in os.environ.items() if not k.startswith('GITLEAKS_')}
config=OUT/'gitleaks.toml';config.write_text('[extend]\nuseDefault = true\n')
report={'mode':MODE,'cases':[]}
def run(name,args,expected=0,json_report=None):
    p=subprocess.run(args,cwd=REPO,env=ENV,capture_output=True,timeout=300)
    # Private/ignored workspace evidence only. Never upload scanner output to public CI artifacts.
    (OUT/(name+'.stdout')).write_bytes(p.stdout);(OUT/(name+'.stderr')).write_bytes(p.stderr)
    parsed=None
    if json_report and json_report.exists():parsed=json.loads(json_report.read_text(encoding='utf-8-sig'))
    elif p.stdout.lstrip().startswith(b'{'):parsed=json.loads(p.stdout)
    passed=p.returncode==expected
    report['cases'].append({'name':name,'exit':p.returncode,'expected':expected,'status':'PASS' if passed else 'FAIL'})
    print(name+': '+('PASS' if passed else 'FAIL')+' (exit '+str(p.returncode)+')')
    return passed,parsed
GITLEAKS=str(ROOT/'bin'/('gitleaks'+EXE));TRIVY=str(ROOT/'bin'/('trivy'+EXE))
def gitleaks(name,target,kind,expected=0):
    out=OUT/(name+'.json')
    return run(name,[GITLEAKS,kind,str(target),'--config',str(config),'--redact=100','--no-banner','--report-format=json','--report-path',str(out)],expected,out)
def semgrep(name,target,expected=0):
    container='schoolsafe-quality-semgrep-'+secrets.token_hex(6)
    args=['docker','run','--rm','--name',container,'--pull=never','--network','none','--memory','1g','--cpus','1','--mount','type=bind,source='+str(ROOT/'semgrep.yml')+',target=/rules.yml,readonly']
    paths=[]
    for index,item in enumerate(target):
        assert item.startswith('/work/')
        source=(REPO/item.removeprefix('/work/')).resolve();assert source.is_relative_to(REPO.resolve())
        destination='/scan/target'+str(index)+(source.suffix if source.is_file() else '')
        args+=['--mount','type=bind,source='+str(source)+',target='+destination+',readonly'];paths.append(destination)
    try:
        return run(name,args+[SEMGREP,'semgrep','scan','--metrics=off','--disable-version-check','--no-git-ignore','--error','--json','--config=/rules.yml']+paths,expected)
    finally:
        subprocess.run(['docker','rm','-f',container],capture_output=True,timeout=30)
try:
    assert subprocess.check_output([GITLEAKS,'version'],text=True).strip()=='8.30.1'
    assert '0.76.0' in subprocess.check_output([TRIVY,'--version'],text=True)
    if MODE=='selftest':
        fixture=ROOT/'fixtures/security'
        for n in ['clean','secret','eval','root','deps']:(fixture/n).mkdir(parents=True,exist_ok=True)
        (fixture/'clean/file.txt').write_text('This fixture contains no credential.\n')
        (fixture/'secret/file.txt').write_text('synthetic_token = "ghp_'+''.join(secrets.SystemRandom().sample(string.ascii_letters+string.digits,36))+'"\n')
        ok,clean=gitleaks('gitleaks-clean',fixture/'clean','dir');assert ok and clean==[]
        ok,bad=gitleaks('gitleaks-negative',fixture/'secret','dir',1);assert ok and len(bad)==1 and bad[0]['RuleID']=='github-pat' and bad[0]['Secret']=='REDACTED'
        (fixture/'clean/sample.php').write_text('<?php echo htmlspecialchars($input, ENT_QUOTES, "UTF-8");\n')
        (fixture/'eval/sample.php').write_text('<?php eval($input); // Never executed.\n')
        ok,data=semgrep('semgrep-clean',['/work/tools/quality/fixtures/security/clean']);assert ok and data['results']==[] and not data.get('errors')
        ok,data=semgrep('semgrep-negative',['/work/tools/quality/fixtures/security/eval'],1);assert ok and len(data['results'])==1 and not data.get('errors') and data['results'][0]['check_id'].endswith('schoolsafe.no-eval-php')
        (fixture/'clean/Dockerfile').write_text('FROM alpine:3.22.1\nUSER 10001\nCMD ["true"]\n')
        (fixture/'root/Dockerfile').write_text('FROM alpine:3.22.1\nUSER root\nCMD ["true"]\n')
        common=['--cache-dir',str(ROOT/'cache/trivy'),'--config=','--ignorefile=','--disable-telemetry','--skip-version-check','--format','json','--exit-code','1','--severity','HIGH,CRITICAL']
        for name,expected in [('clean',0),('root',1)]:
            ok,data=run('trivy-'+name,[TRIVY,'config','--skip-check-update']+common+[str(fixture/name)],expected);assert ok and data is not None
            ids=[m['ID'] for result in data.get('Results',[]) for m in result.get('Misconfigurations',[])]
            assert ('DS-0002' in ids) if expected else not ids
        lock={'name':'fictional-fixture','version':'1.0.0','lockfileVersion':3,'packages':{'':{'name':'fictional-fixture','version':'1.0.0','dependencies':{'lodash':'4.17.20'}},'node_modules/lodash':{'version':'4.17.20'}}}
        (fixture/'deps/package-lock.json').write_text(json.dumps(lock))
        ok,data=run('trivy-vulnerable-dependency',[TRIVY,'fs','--scanners','vuln','--pkg-types','library']+common+[str(fixture/'deps')],1)
        assert ok and any(v.get('PkgName')=='lodash' for result in data.get('Results',[]) for v in result.get('Vulnerabilities',[]))
    else:
        gitleaks('gitleaks-repository-history',REPO,'git')
        semgrep('semgrep-source',['/work/server/src','/work/app/modules/codex-ui-v1/extensions-test-runtime/ProgramFunctions','/work/app/modules/codex-ui-v1/extensions-test-runtime/ecole-profils.php'])
        run('trivy-source',[TRIVY,'fs','--scanners','vuln,misconfig','--cache-dir',str(ROOT/'cache/trivy'),'--config=','--ignorefile=','--disable-telemetry','--skip-version-check','--format','json','--exit-code','1','--severity','HIGH,CRITICAL','--skip-dirs','**/node_modules','--skip-dirs','**/.git','--skip-dirs','tools/quality',str(REPO)])
    report['status']='GREEN' if all(x['status']=='PASS' for x in report['cases']) else 'RED'
except Exception as e:report['status']='RED';report['error']=type(e).__name__+': '+str(e)
finally:
    # Remove the deliberately credential-shaped fixture, not user files or shared caches.
    fake=ROOT/'fixtures/security/secret/file.txt'
    if fake.exists():fake.unlink()
    (OUT/(MODE+'-summary.json')).write_text(json.dumps(report,indent=2))
    print('Summary: '+report['status']+'; detailed scanner output stays private/ignored.')
raise SystemExit(0 if report['status']=='GREEN' else 1)
