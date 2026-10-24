#!/usr/bin/env python3
"""Qualified Scan software cutover. No worker activation, provider send or child submission.

Reuse the established runtime/session preservation engine. Additive schema only;
never restore a database or clear uncertain delivery claims on rollback.
"""
import argparse,contextlib,hashlib,importlib.util,io,json,os,re,secrets,subprocess,time
from pathlib import Path
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/school-presence-scan-20261024')
BASE='sha256:8770153af9759e423008028268248973a230c47327c76b371f76a1f12028cac6'
BASE_COMMIT='4065f9406a3a525f6bd64478b5207dd732c2d498'
BASE_BRANCH='deployed/school-dashboard-composition-20261023'
SOURCE='6e996637d3397fbc174e8bde01685c1f05a0ac09'
IMAGE='sha256:d4359b7b3b174f70ea02aef6202c98e41381814960dedcf2df576994dac10b9d'
DIGEST='08b4cc08ed85b84cbb1d7821ebf1b0e1d6fa0462df0d016b4700ccd460648581'
SCOPE='d6d52a414ef6bd445ba5484d3eb48b219a265691861c5500f6de2a3235c3410c'
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
KEY='SCHOOLSAFE_CARD_HMAC_SECRET'
KEY_FILE=Path('/opt/schoolsafe-secrets/card-hmac.hex')
MIGRATIONS=['020-school-cards.sql','021-entry-records.sql','022-pickup-guardians.sql','023-fee-consults.sql','024-notify-events.sql','025-notify-distributions.sql','026-notify-push.sql','031-delivery-status.sql','037-whatsapp.sql','039-scan-passage-scope.sql','040-fee-control-context.sql','041-entry-request-receipts.sql','042-scan-financial-notification.sql','043-notify-dispatch-claims.sql']
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
previous=Path(__file__).parent/'recovery.py'
if not previous.exists():previous=Path(__file__).resolve().parents[1]/'prodeli-recovery-sms-20261022/deploy.py'
tools=module('scan_runtime_preservation',previous);preserve=tools.preserve
tools.ROOT=preserve.ROOT=ROOT
run=preserve.run;inspect=preserve.inspect
original_runtime=tools.unchanged_runtime

def load(name):return json.loads((ROOT/name).read_text())
def write(name,data):(ROOT/name).write_text(json.dumps(data,indent=2))
def files():return set(load('source-manifest.json')['payload_hashes'])
def source_hashes(name):
 code='$o=[];foreach(json_decode($argv[1],true)as$p)$o[$p]=is_file("/var/www/html/".$p)?hash_file("sha256","/var/www/html/".$p):false;echo json_encode($o);'
 return json.loads(run(['docker','exec',name,'php','-r',code,json.dumps(sorted(files()))]))
def key_valid(value):return bool(re.fullmatch('[a-f0-9]{64}',value or '')) and value!='0'*64
def signing_key():
 value=KEY_FILE.read_text().strip();assert key_valid(value),'Invalid escrowed signing key';return value

def no_legacy_dispatchers():
 pattern=re.compile(r'schoolsafe-notify-(?:dispatch|recover)\.php');pids=[];schedules=[]
 for p in Path('/proc').glob('[0-9]*/cmdline'):
  try:
   text=p.read_bytes().replace(b'\0',b' ').decode(errors='replace')
   if pattern.search(text) and 'python3 -c' not in text:pids.append(int(p.parent.name))
  except OSError:pass
 paths=[Path('/etc/crontab')]
 for folder in ['/etc/cron.d','/etc/cron.daily','/etc/cron.hourly','/var/spool/cron/crontabs','/etc/systemd/system','/usr/lib/systemd/system']:
  paths.extend(Path(folder).glob('*'))
 for p in paths:
  if p.is_file():
   try:
    lines=p.read_text(errors='replace').splitlines()
    if any(not l.lstrip().startswith('#') and pattern.search(l) for l in lines):schedules.append(str(p))
   except OSError:pass
 result={'status':'PASS' if not pids and not schedules else 'BLOCKED','legacy_pids':pids,'scheduled_files':schedules,'new_worker_started':False,'checked_at':time.time()}
 write('dispatcher-drain-proof.json',result)
 assert result['status']=='PASS','Drain the explicitly identified legacy jobs before migration043; never blind-kill or release claims'
 return result

def http_qualification(envelope):
 assert envelope['exit']==0 and envelope['stderr']=='','HTTP fixture process failed'
 result=json.loads(envelope['stdout'])
 assert result['status']=='PASS' and result['checks']==50 and len(result['results'])==50 and result['error'] is None
 return result

def qualification(release):
 assert release['source_commit']==SOURCE and release['image']==IMAGE and release['source_digest']==DIGEST
 assert re.fullmatch('[a-f0-9]{40}',release['commit'])
 manifest=load('source-manifest.json');scope=files()
 assert len(scope)==65 and hashlib.sha256('\n'.join(sorted(scope)).encode()).hexdigest()==SCOPE
 assert manifest['source_commit']==SOURCE and manifest['base_image']==BASE and manifest['source_digest']==DIGEST
 assert release['files']==manifest['payload_hashes']
 proof=load('image-prepare-proof.json')
 assert proof['status']=='READY' and proof['image']==IMAGE and proof['source_digest']==DIGEST
 assert set(proof['changed_files'])==scope and proof['unchanged_files']==2734
 assert proof['renderer_contracts']==353 and proof['recovery_contracts']==37
 assert proof['internal_network'] and proof['read_only_web'] and not proof['published_ports']
 integration=load('exact-integration-result.json')
 assert integration['status']=='PASS' and integration['image']==IMAGE and integration['source_commit']==SOURCE
 assert {s['suite'] for s in integration['suites']}=={'native','recovery','concurrency'} and all(s['exit']==0 for s in integration['suites'])
 http_qualification(load('card-authenticated-http-final.private.json'))
 for name in ['image-verify-proof.json','image-cleanup-proof.json']:
  result=load(name)
  if name.startswith('image-verify'):assert result['status']=='PASS' and result['image']==IMAGE and result['fixture_snapshot_unchanged'] and result['production_containers_unchanged']
  else:assert result['status']=='REMOVED' and result['only_owned_ephemeral_resources'] and result['production_containers_unchanged']
 for f,digest in release['files'].items():
  for commit in [SOURCE,release['commit']]:assert hashlib.sha256(run(['git','-C','/opt/app-src','show',commit+':'+PREFIX+f])).hexdigest()==digest
 for local,source in [('deploy.py','ops/releases/school-presence-scan-20261024/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
  assert (ROOT/local).read_bytes()==run(['git','-C','/opt/app-src','show',release['commit']+':'+source])
 image=inspect(IMAGE);assert image['Config']['Labels']['org.opencontainers.image.revision']==SOURCE and image['Config']['Labels']['schoolsafe.source.digest']==DIGEST
 assert not any(e.startswith(KEY+'=') for e in image['Config'].get('Env',[])),'Never bake the fixture zero key or production key into an image'

def unchanged_runtime(pre,current):
 expected=dict(pre['Config']);actual=dict(current['Config'])
 before=[v for v in expected['Env'] if not v.startswith(KEY+'=')];after=[v for v in actual['Env'] if not v.startswith(KEY+'=')]
 assert before==after,'Existing environment changed'
 values=[v.split('=',1)[1] for v in actual['Env'] if v.startswith(KEY+'=')]
 assert values==[signing_key()],'Signing key not preserved'
 # Every old runtime invariant is still checked; only the separately guarded key is excluded.
 left=dict(pre);right=dict(current);left['Config']=expected;right['Config']=actual
 left['Config']['Env']=before;right['Config']['Env']=after;original_runtime(left,right)

def prepare(release):
 assert not (ROOT/'backup-proof.json').exists(),'Already prepared'
 run(['git','-C','/opt/app-src','fetch','--no-tags','origin','refs/heads/feat/presence-scan-qr-20261023'])
 assert run(['git','-C','/opt/app-src','rev-parse','FETCH_HEAD']).decode().strip()==release['commit']
 qualification(release)
 assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip()==BASE_COMMIT
 assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip()==BASE_BRANCH
 assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
 pre=inspect(tools.NAME);assert pre['Image']==BASE and pre['State']['Health']['Status']=='healthy'
 assert source_hashes(tools.NAME)==release['base_files']
 assert len(pre['Mounts'])==1 and pre['Mounts'][0]['Destination']=='/var/www/html/config.inc.php' and not pre['Mounts'][0]['RW']
 for name in [tools.OLD,tools.STAGED]:assert subprocess.run(['docker','inspect',name],capture_output=True).returncode!=0
 preserve.check_layer(tools.NAME);preserve.probe();preserve.probe(True);no_legacy_dispatchers()
 write('container-before.private.json',pre);write('other-services-before.json',preserve.other_state())
 (ROOT/'config-before.sha256').write_text(tools.configuration_hash(tools.NAME))
 cli=tools.db_cli();cfg=load('db-cli.private.json')
 dump=run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',cfg['user'],'-d',cfg['database'],'-Fc'],timeout=180)
 assert len(dump)>1000 and b'TABLE DATA' in run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump)
 target=ROOT/'database-before.private.dump';target.write_bytes(dump);assert target.stat().st_mode&0o777==0o600
 env=dict(e.split('=',1) for e in pre['Config']['Env'] if '=' in e);value=env.get(KEY)
 if value is not None:assert key_valid(value),'Configured key is invalid; never silently rotate it'
 elif run(cli,b"BEGIN READ ONLY; SELECT to_regclass('public.schoolsafe_student_cards') IS NULL; COMMIT;").decode().strip()!='t':
  assert run(cli,b'BEGIN READ ONLY; SELECT count(*) FROM schoolsafe_student_cards; COMMIT;').decode().strip()=='0','Missing key with existing cards requires explicit key recovery'
 KEY_FILE.parent.mkdir(mode=0o700,parents=True,exist_ok=True)
 assert KEY_FILE.parent.stat().st_mode&0o077==0,'Signing directory is not private'
 if KEY_FILE.exists():
  assert KEY_FILE.stat().st_mode&0o077==0
  if value is not None:assert signing_key()==value,'Existing escrow differs; never rotate'
 else:
  with KEY_FILE.open('x')as f:f.write(value if value is not None else secrets.token_hex(32))
 assert key_valid(signing_key())
 result={'status':'PASS','commit':release['commit'],'image':IMAGE,'backup_bytes':len(dump),'backup_sha256':hashlib.sha256(dump).hexdigest(),'backup_mode':'0o600','archive_list_valid':True,'prepared_at':time.time(),'signing_key_existing_env':value is not None,'signing_key_values_output':False,'production_database_restored':False}
 write('backup-proof.json',result);print(json.dumps(result))

def migration_cli():
 cli=tools.db_cli();cfg=load('db-cli.private.json')
 env=dict(e.split('=',1) for e in inspect('schoolsafe-rosario-db')['Config']['Env'] if '=' in e)
 admin=env.get('POSTGRES_USER','postgres');assert re.fullmatch('[A-Za-z_][A-Za-z0-9_]*',admin)
 cli[cli.index('-U')+1]=admin
 assert run(cli,b'BEGIN READ ONLY; SELECT rolsuper FROM pg_roles WHERE rolname=current_user; COMMIT;').decode().strip()=='t','DDL needs the existing database administrator, not the application role'
 return cli,cfg['user']

def service_grants(cli,role):
 # Only this feature's known tables/sequences. No ownership or end-user role change.
 assert re.fullmatch('[A-Za-z_][A-Za-z0-9_]*',role)
 tables=['schoolsafe_student_cards','entry_exit_checkpoints','entry_exit_student_records','schoolsafe_pickup_guardians','schoolsafe_pickup_authorizations','schoolsafe_pickup_proofs','schoolsafe_fee_consults','schoolsafe_notify_migrations','schoolsafe_notify_events','schoolsafe_notify_templates','schoolsafe_notify_deliveries','schoolsafe_notification_push_subscriptions','schoolsafe_wa_sessions','schoolsafe_wa_outbox','schoolsafe_wa_consents','schoolsafe_wa_audit','schoolsafe_entry_requests']
 for table in tables:
  assert run(cli,("SELECT to_regclass('public."+table+"') IS NOT NULL;").encode()).decode().strip()=='t','Required feature schema missing'
  run(cli,('GRANT SELECT,INSERT,UPDATE,DELETE ON TABLE public.'+table+' TO "'+role+'";').encode())
  query="SELECT DISTINCT pg_get_serial_sequence('public."+table+"',attname) FROM pg_attribute WHERE attrelid='public."+table+"'::regclass AND attnum>0 AND NOT attisdropped;"
  for seq in run(cli,query.encode()).decode().splitlines():
   if seq:
    assert re.fullmatch('[A-Za-z_][A-Za-z0-9_.]*',seq)
    quoted='.'.join('"'+part+'"' for part in seq.split('.'))
    run(cli,('GRANT USAGE,SELECT ON SEQUENCE '+quoted+' TO "'+role+'";').encode())
 write('service-schema-access-proof.json',{'status':'PASS','application_role':role,'scoped_schema_grants_only':True,'ownership_changed':False,'end_user_permissions_changed':False})

def migrate(release):
 qualification(release);backup=load('backup-proof.json');assert backup['status']=='PASS' and time.time()-backup['prepared_at']<900
 assert not (ROOT/'schema-proof.json').exists(),'Schema expansion already recorded'
 assert inspect(tools.NAME)['Image']==BASE;no_legacy_dispatchers();cli,app_role=migration_cli()
 for name in MIGRATIONS:
  sql=run(['git','-C','/opt/app-src','show',SOURCE+':'+PREFIX+'schoolsafe-remediation/'+name]);run(cli,sql)
 service_grants(cli,app_role)
 result={'status':'PASS','migrations':MIGRATIONS,'legacy_dispatchers_drained':True,'new_worker_started':False,'uncertain_claims_released':False,'data_reassigned':False}
 write('schema-proof.json',result);print(json.dumps(result))

def enable_native_module():
 # Enable the requested module, never grant a profile/task or change another module.
 cli=tools.db_cli();before=run(cli,b"BEGIN READ ONLY; SELECT config_value FROM config WHERE school_id=0 AND title='MODULES'; COMMIT;").decode().strip()
 code='$m=unserialize(stream_get_contents(STDIN),["allowed_classes"=>false]);if(!is_array($m))exit(2);$m["SchoolSafe"]=true;echo serialize($m);'
 after=run(['docker','exec','-i',tools.NAME,'php','-r',code],before.encode()).decode()
 changed=before!=after
 if changed:
  quote=lambda v:"'"+v.replace("'","''")+"'"
  sql="BEGIN; UPDATE config SET config_value="+quote(after)+" WHERE school_id=0 AND title='MODULES' AND config_value="+quote(before)+" RETURNING school_id; COMMIT;"
  assert run(cli,sql.encode()).decode().strip()=='0','Concurrent module edit; inspect without replacing another configuration'
 write('module-activation-proof.json',{'status':'PASS','global_native_module_enabled':True,'changed':changed,'profile_permissions_modified':False,'other_modules_modified':False,'school_overrides_modified':False})

def create_staged(pre,release):
 payload=dict(pre['Config']);payload['Image']=IMAGE;payload['Env']=[e for e in pre['Config']['Env'] if not e.startswith(KEY+'=')]+[KEY+'='+signing_key()]
 payload['Labels']=dict(payload.get('Labels')or{});payload['Labels'].update({'schoolsafe.patch.commit':release['commit'],'schoolsafe.patch.scope':'school-presence-scan-qr','schoolsafe.patch.source-digest':DIGEST})
 payload['HostConfig']=dict(pre['HostConfig'])
 if payload['HostConfig'].get('OomKillDisable') is None:payload['HostConfig']['OomKillDisable']=False
 network=pre['HostConfig']['NetworkMode'];endpoint=pre['NetworkSettings']['Networks'][network]
 payload['NetworkingConfig']={'EndpointsConfig':{network:{'Aliases':endpoint.get('Aliases')or[]}}}
 c=preserve.UnixHTTP('localhost');c.request('POST','/v1.47/containers/create?name='+tools.STAGED,body=json.dumps(payload),headers={'Content-Type':'application/json'});r=c.getresponse();body=r.read();c.close()
 if r.status!=201:(ROOT/'create-error.private.json').write_bytes(body);raise RuntimeError('Staging failed before production stop')
 staged=inspect(tools.STAGED);assert not staged['State']['Running'] and staged['Image']==IMAGE;unchanged_runtime(pre,staged);return staged['Id']

def main():
 action=argparse.ArgumentParser(description=__doc__);action.add_argument('action',choices=['prepare','migrate','deploy','activate-module','status']);args=action.parse_args();release=load('release.json')
 tools.BASE=BASE;tools.BASE_COMMIT=BASE_COMMIT;tools.BASE_BRANCH=BASE_BRANCH;tools.BRANCH='deployed/school-presence-scan-20261024'
 tools.OLD='schoolsafe-rosario-pre-school-presence-scan-20261024';tools.STAGED='schoolsafe-rosario-candidate-school-presence-scan-20261024'
 tools.IMAGE=IMAGE;tools.DIGEST=DIGEST;tools.FILES=files();tools.qualification=qualification;tools.source_hashes=source_hashes;tools.create_staged=create_staged;tools.unchanged_runtime=unchanged_runtime
 if args.action=='prepare':prepare(release)
 elif args.action=='migrate':migrate(release)
 elif args.action=='deploy':
  no_legacy_dispatchers();schema=load('schema-proof.json');assert schema['status']=='PASS' and schema['legacy_dispatchers_drained'] and not schema['new_worker_started']
  with contextlib.redirect_stdout(io.StringIO()):tools.deploy(release)
  result=load('deployment-proof.json');result.pop('runtime_settings_unchanged',None)
  result.update({'status':'SOFTWARE_DEPLOYED_MODULE_PENDING','database_migrations':MIGRATIONS,'existing_runtime_settings_preserved':True,'existing_environment_preserved':True,'signing_key_addition_only':True,'workers_held':True,'physical_camera_qualified':False,'provider_activated':False,'official_print_qualified':False,'production_child_submissions':False})
  write('deployment-proof.json',result);enable_native_module();result['status']='SOFTWARE_DEPLOYED_PROVIDER_HELD'
  write('deployment-proof.json',result);print(json.dumps(result))
 elif args.action=='activate-module':
  assert inspect(tools.NAME)['Image']==IMAGE and load('deployment-proof.json')['status']=='SOFTWARE_DEPLOYED_MODULE_PENDING'
  enable_native_module();result=load('deployment-proof.json');result['status']='SOFTWARE_DEPLOYED_PROVIDER_HELD';write('deployment-proof.json',result);print(json.dumps({'status':result['status']}))
 else:
  result=tools.final_status(release);result.pop('runtime_settings_unchanged',None);result.update({'existing_runtime_settings_preserved':True,'provider_activated':False,'workers_held':True});print(json.dumps(result))
if __name__=='__main__':main()
