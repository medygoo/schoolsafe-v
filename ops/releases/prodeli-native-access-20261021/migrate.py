#!/usr/bin/env python3
"""Back up production, then apply additive 018/038 only. Never restore or delete data."""
from pathlib import Path
import subprocess,json,hashlib,os
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/prodeli-native-access-20261021')
def run(args,data=None):
 p=subprocess.run(args,input=data,capture_output=True,timeout=180)
 if p.returncode:
  (ROOT/'migration-error.private.log').write_bytes(p.stderr);raise RuntimeError('Migration prerequisite failed; private diagnostic saved')
 return p.stdout
if __name__=='__main__':
 assert not (ROOT/'migration-proof.json').exists(),'Migration already recorded; inspect proof'
 restore=json.loads((ROOT/'restore-proof.json').read_text());assert restore['status']=='PASS' and restore['disposable_database_removed']
 release=json.loads((ROOT/'release.json').read_text())
 config=json.loads(run(['docker','exec','schoolsafe-rosario','php','-r',"require '/var/www/html/config.inc.php';echo json_encode(['user'=>$DatabaseUsername,'database'=>$DatabaseName]);"]))
 (ROOT/'db-cli.private.json').write_text(json.dumps(config))
 psql=['docker','exec','-i','schoolsafe-rosario-db','psql','-X','-v','ON_ERROR_STOP=1','-U',config['user'],'-d',config['database'],'-qAt']
 before=run(psql,b"SELECT to_regclass('schoolsafe_native_onboarding') IS NULL;").decode().strip()
 # First release only. Unknown pre-existing onboarding requires an explicit review.
 assert before=='t','Onboarding already exists; stop rather than overwrite a prior lifecycle'
 dump=run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',config['user'],'-d',config['database'],'--format=custom'])
 backup=ROOT/'database-before.private.dump';backup.write_bytes(dump)
 listing=run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump)
 assert b'TABLE DATA' in listing and backup.stat().st_size>1000
 (ROOT/'database-before-list.private.txt').write_bytes(listing)
 sql018=(ROOT/'migration-018.sql').read_bytes();sql038=(ROOT/'payload/schoolsafe-remediation/038-native-onboarding.sql').read_bytes()
 assert hashlib.sha256(sql018).hexdigest()==release['migration_018_sha256']
 assert hashlib.sha256(sql038).hexdigest()==release['files']['schoolsafe-remediation/038-native-onboarding.sql']
 def body(data):
  lines=[line for line in data.decode().splitlines() if line.strip() not in ['BEGIN;','COMMIT;']]
  return '\n'.join(lines)
 protected=['staff','students','schools','config','schoolsafe_identity_links','schoolsafe_opening_requests']
 def fingerprints():
  return '\n'.join("SELECT '"+t+"|' || md5(COALESCE(string_agg(row_to_json(x)::text,E'\\n' ORDER BY row_to_json(x)::text),'')) FROM "+t+" x;" for t in protected)
 script="BEGIN ISOLATION LEVEL REPEATABLE READ; SET LOCAL lock_timeout='5s'; SET LOCAL statement_timeout='45s';\nSELECT pg_advisory_xact_lock(hashtext('schoolsafe-native-access-migration'));\n"+fingerprints()+'\n'+body(sql018)+'\n'+body(sql038)+'\n'+fingerprints()+"\nSELECT 'onboarding_rows|' || count(*) FROM schoolsafe_native_onboarding;\nCOMMIT;"
 output=run(psql,script.encode()).decode().splitlines()
 digests=[line for line in output if any(line.startswith(t+'|') for t in protected)]
 assert len(digests)==12 and digests[:6]==digests[6:],'Existing rows changed by migration'
 assert 'onboarding_rows|0' in output
 proof={'status':'PASS','migrations':['018-school-leadership.sql','038-native-onboarding.sql'],'backup_bytes':len(dump),'backup_sha256':hashlib.sha256(dump).hexdigest(),'backup_archive_list_valid':True,'local_synthetic_restore_proven':True,'existing_protected_tables_unchanged':protected,'onboarding_rows':0,'no_existing_accounts_modified':True,'rollback':'Keep additive tables. No automatic database restore or DROP.'}
 (ROOT/'migration-proof.json').write_text(json.dumps(proof,indent=2));print(json.dumps(proof))
