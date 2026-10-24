#!/usr/bin/env python3
"""Deliver only the two qualified native state helpers; preserve current prod runtime."""
import argparse,hashlib,importlib.util,json,os,re
from pathlib import Path
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/prodeli-state-safety-20261024')
BASE='sha256:1df5f8578805868544f1a7a73d6a1d437c5be0bf0ecf52cd716b41455e35aea8'
BASE_COMMIT='3ecdf9e5dc0558d5fb5c374a12cfd8df00309ad6'
SOURCE='68fe3c3470acad111a085f52c1a8b5be4bd9a0ac'
TEST_SOURCE='108dc5c427d715522410a1df9687cb9ee9317648'
IMAGE='sha256:1705fc9453c0788534273c5ba6a96bd799f15574ade8e02c7ff55351a8d72a3c'
DIGEST='7b22f0a09547a8cf339aefd7090f5a64faa9e0184ae30e418f9ad9efbd6a3188'
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
FILES={'ProgramFunctions/SchoolSafeSchoolState.fnc.php','ProgramFunctions/SchoolSafeAccountState.fnc.php'}
BASE_BRANCH='deployed/prodeli-access-discovery-20261024'
BRANCH='deployed/prodeli-state-safety-20261024'
def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);obj=importlib.util.module_from_spec(spec);spec.loader.exec_module(obj);return obj
bundled=Path(__file__).parent/'native-access.py'
access=module('state_native_access',bundled if bundled.exists() else Path(__file__).resolve().parents[1]/'school-native-access-20261024/deploy.py')
tools=access.tools;preserve=tools.preserve;run=preserve.run;inspect=preserve.inspect
def load(name):return json.loads((ROOT/name).read_text())
def git(commit,path):return run(['git','-C','/opt/app-src','show',commit+':'+path])
def qualification(release):
 assert re.fullmatch('[a-f0-9]{40}',release['commit'])
 assert release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
 assert set(release['files'])==FILES and set(release['base_files'])==FILES
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json')
 assert manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST and manifest['base_image']==BASE
 assert set(manifest['payload_hashes'])==FILES and manifest['payload_hashes']==release['files'] and manifest['base_hashes']==release['base_files']
 assert proof['status']=='READY' and proof['image']==IMAGE and proof['source_digest']==DIGEST
 assert proof['renderer_contracts']==178 and proof['recovery_contracts']==37 and proof['unchanged_files']==2800
 assert set(proof['changed_files'])==FILES and proof['internal_network'] and proof['read_only_web'] and not proof['published_ports'] and proof['production_containers_unchanged']
 for f,digest in release['files'].items():
  assert hashlib.sha256(git(SOURCE,PREFIX+f)).hexdigest()==digest
  assert hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
  assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==release['base_files'][f]
 http=load('state-http.private.json');assert http['exit']==0 and http['stderr']==''
 result=json.loads(http['stdout']);assert result['status']=='PASS' and result['checks']==16 and len(result['results'])==16 and result['error'] is None
 assert http['test_sha256']==hashlib.sha256(git(TEST_SOURCE,'scripts/prodeli-school-state-http.php')).hexdigest()
 verification=load('image-verify-proof.json');cleanup=load('image-cleanup-proof.json')
 assert verification['status']=='PASS' and verification['image']==IMAGE and verification['fixture_snapshot_unchanged'] and verification['production_containers_unchanged'] and verification['no_test_accounts_or_schemas']
 assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources'] and cleanup['production_containers_unchanged']
 base=load('base-native-manifest.json');discovery=load('base-discovery-manifest.json')
 assert len(base['payload_hashes'])==71 and len(discovery['payload_hashes'])==2
 assert not (set(base['payload_hashes'])&set(discovery['payload_hashes'])) and not (set(base['payload_hashes'])&FILES) and not (set(discovery['payload_hashes'])&FILES)
 expected=dict(base['payload_hashes'],**discovery['payload_hashes'],**release['files']);assert len(expected)==75
 for f,digest in dict(base['payload_hashes'],**discovery['payload_hashes']).items():
  assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==digest and hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
 code='$o=[];foreach(json_decode($argv[1],true)as$f)$o[$f]=hash_file("sha256","/var/www/html/".$f);echo json_encode($o);'
 actual=json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','--entrypoint','php',IMAGE,'-r',code,json.dumps(sorted(expected))]))
 assert actual==expected
 labels=inspect(IMAGE)['Config'];assert labels['Labels']['org.opencontainers.image.revision']==SOURCE and labels['Labels']['schoolsafe.source.digest']==DIGEST
 assert not any(e.startswith('SCHOOLSAFE_CARD_HMAC_SECRET=') for e in labels.get('Env',[]))
 for local,path in [('deploy.py','ops/releases/prodeli-state-safety-20261024/deploy.py'),('native-access.py','ops/releases/school-native-access-20261024/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
  assert (ROOT/local).read_bytes()==git(release['commit'],path)
original_schema=access.schema
def state_schema(cli):
 original_schema(cli)
 checks=["has_table_privilege('public."+table+"','"+priv+"')"
         for table,privs in {'schoolsafe_account_state':['SELECT','INSERT','UPDATE'],
                              'schoolsafe_account_audit':['INSERT'],
                              'schoolsafe_school_state':['SELECT','INSERT','UPDATE'],
                              'schoolsafe_school_audit':['INSERT']}.items() for priv in privs]
 checks.append("has_sequence_privilege(pg_get_serial_sequence('public.schoolsafe_school_audit','id'),'USAGE')")
 for table,columns in {'schoolsafe_account_state':['person_key','school_id','session_epoch','until'],
                       'schoolsafe_school_state':['school_id','state','until'],
                       'schoolsafe_school_audit':['school_id','action','actor']}.items():
  names=','.join("'"+x+"'" for x in columns)
  count=run(cli,("BEGIN READ ONLY;SELECT count(*) FROM information_schema.columns WHERE table_schema='public' AND table_name='"+table+"' AND column_name IN ("+names+");COMMIT;").encode()).decode().strip()
  assert int(count)==len(columns),'State schema not ready'
 assert run(cli,('BEGIN READ ONLY;SELECT '+' AND '.join(checks)+';COMMIT;').encode()).decode().strip()=='t','State writer privileges unavailable; no implicit grant'
 proof=load('schema-proof.json');proof['state_write_privileges_read_only_verified']=True;(ROOT/'schema-proof.json').write_text(json.dumps(proof,indent=2))
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('action',choices=['prepare','deploy','status']);action=parser.parse_args().action
 release=load('release.json')
 access.ROOT=tools.ROOT=preserve.ROOT=ROOT
 access.BASE=tools.BASE=BASE;access.IMAGE=tools.IMAGE=IMAGE;access.DIGEST=tools.DIGEST=DIGEST
 access.BASE_COMMIT=tools.BASE_COMMIT=BASE_COMMIT;access.FILES=tools.FILES=FILES
 tools.BASE_BRANCH=BASE_BRANCH;tools.BRANCH=BRANCH
 tools.OLD='schoolsafe-rosario-pre-prodeli-state-safety-20261024';tools.STAGED='schoolsafe-rosario-candidate-prodeli-state-safety-20261024'
 access.qualification=tools.qualification=qualification;tools.source_hashes=access.source_hashes
 access.schema=state_schema
 if action=='prepare':access.prepare(release)
 elif action=='deploy':tools.deploy(release)
 else:print(json.dumps(tools.final_status(release)))
if __name__=='__main__':main()
