#!/usr/bin/env python3
"""Exact-image qualification only: five-file offline build and disposable synthetic QA.

No push, deployment, production DB reads, or production container mutation.
The verified base image stays on its host; never transfer the whole image.
Usage: python scripts/prodeli-recovery-image-qa.py prepare|verify|cleanup --evidence PRIVATE_DIR
"""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import tarfile
import uuid

ROOT = Path(__file__).resolve().parents[1]
SSH = 'prodeli-hub-01'
BASE = 'sha256:36a9e2fbc24159f25f65247cb516f84a43df49b410e35ee208ef3d25e3cf0c78'
BASE_TAG = 'schoolsafe-rosario:prodeli-native-access-3670dad176a4'
BASE_COMMIT = 'fbc17e28b909ea1b1680ba235af7a53e986d0716'
RUNTIME = 'app/modules/codex-ui-v1/extensions-test-runtime/'
FILES = ['PasswordReset.php', 'compte-recuperation.php', 'plateforme-prodeli-sms.php',
         'ProgramFunctions/SchoolSafeRecovery.fnc.php', 'ProgramFunctions/SchoolSafeSms.fnc.php']
CONTRACTS = {'prodeli-account-presentation-contracts.php': 16, 'prodeli-dashboard-contracts.php': 12,
             'prodeli-communication-contracts.php': 28, 'prodeli-dashboard-navigation-contracts.php': 20,
             'prodeli-native-intent-contracts.php': 16}
LABEL = 'prodeli-recovery-20261022'


def local(args, data=None, timeout=90):
    return subprocess.run(args, input=data, capture_output=True, timeout=timeout, check=True).stdout


def remote(code, evidence, timeout=240):
    p = subprocess.run(['ssh', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10', SSH, 'python3', '-'],
                       input=code.encode(), capture_output=True, timeout=timeout)
    if p.returncode:
        (evidence / 'image-qa-error.private.log').write_bytes(p.stderr)
        raise RuntimeError('Isolated image QA failed; private diagnostic saved')
    return p.stdout


def git_source_bytes(commit, path):
    """Canonical published bytes; never normalize from the Windows worktree."""
    assert re.fullmatch('[a-f0-9]{40}', commit)
    return local(['git', '-C', str(ROOT), 'show', commit + ':' + path])


REMOTE_COMMON = r'''
import subprocess,json,pathlib,hashlib,time,secrets,tarfile
ROOT=pathlib.Path(STATE['root']);WEB=STATE['web'];DB=STATE['db'];NET=STATE['network'];NONCE=STATE['nonce']
def run(args,data=None,timeout=90):
 p=subprocess.run(args,input=data,capture_output=True,timeout=timeout)
 if p.returncode:
  (ROOT/'error.private.log').write_bytes(p.stderr)
  raise RuntimeError('Contained QA command failed: '+args[0])
 return p.stdout
def inspect(name):return json.loads(run(['docker','inspect',name]))[0]
def production():
 out={}
 for name in ['schoolsafe-rosario','schoolsafe-rosario-db']:
  d=inspect(name);out[name]={'id':d['Id'],'image':d['Image'],'started':d['State']['StartedAt'],
   'environment_digest':hashlib.sha256(json.dumps(d['Config']['Env'],sort_keys=True).encode()).hexdigest()}
 return out
def cleanup():
 for name in [WEB,DB]:
  p=subprocess.run(['docker','inspect',name],capture_output=True)
  if p.returncode:continue
  d=json.loads(p.stdout)[0];assert d['Config']['Labels'].get('schoolsafe.recovery-qa')==NONCE
  assert not d['HostConfig']['PortBindings']
  run(['docker','rm','-f','-v',name])
 p=subprocess.run(['docker','network','inspect',NET],capture_output=True)
 if p.returncode==0:
  d=json.loads(p.stdout)[0];assert d['Labels'].get('schoolsafe.recovery-qa')==NONCE and not d['Containers']
  run(['docker','network','rm',NET])
def sql(q):return run(['docker','exec','-i',DB,'psql','-X','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt','-v','ON_ERROR_STOP=1'],q.encode()).decode().strip()
TABLES=['staff','schools','config','user_profiles','profile_exceptions','staff_exceptions',
 'schoolsafe_sms_lines','schoolsafe_sms_outbox','schoolsafe_recovery_otp','schoolsafe_account_state','schoolsafe_account_audit']
def snapshot():
 return {table:sql('SELECT md5(COALESCE(string_agg(row_to_json(t)::text,\'\' ORDER BY row_to_json(t)::text),\'\')) FROM '+table+' t') for table in TABLES}
'''

PREPARE = r'''
created=False
try:
 assert production()['schoolsafe-rosario']['image']==MANIFEST['base_image']
 assert run(['docker','image','inspect','--format','{{.Id}}',MANIFEST['base_tag']]).decode().strip()==MANIFEST['base_image']
 before_production=production()
 for name in [WEB,DB]:assert subprocess.run(['docker','inspect',name],capture_output=True).returncode!=0
 assert subprocess.run(['docker','network','inspect',NET],capture_output=True).returncode!=0
 with tarfile.open(ROOT/'input.private.tar.gz') as archive:
  assert set(archive.getnames())==set(MANIFEST['bundle_files'])
  for member in archive.getmembers():
   assert member.isfile() and member.size<6_000_000 and '..' not in pathlib.PurePosixPath(member.name).parts
   data=archive.extractfile(member).read();assert hashlib.sha256(data).hexdigest()==MANIFEST['bundle_files'][member.name]
   p=ROOT/member.name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data);p.chmod(0o600)
 for f in MANIFEST['payload_hashes']:(ROOT/'payload'/f).chmod(0o644)
 (ROOT/'fixture-config.php').chmod(0o644)
 tag='schoolsafe-rosario:recovery-qa-'+MANIFEST['source_digest'][:12]
 log=run(['docker','build','--network','none','--pull=false','-t',tag,str(ROOT)],timeout=120)
 (ROOT/'build.log').write_bytes(log)
 image=run(['docker','image','inspect','--format','{{.Id}}',tag]).decode().strip()
 def cli(img,args,extra=[]):
  return run(['docker','run','--rm','--pull=never','--network','none','--read-only','--memory','256m','--cpus','0.5',*extra,'--entrypoint','php',img,*args])
 code='$root="/var/www/html/";$o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile())$o[substr($f->getPathname(),strlen($root))]=[hash_file("sha256",$f->getPathname()),$f->getPerms()&0777,$f->getOwner(),$f->getGroup()];}ksort($o);echo json_encode($o);'
 before=json.loads(cli(MANIFEST['base_image'],['-r',code]));after=json.loads(cli(image,['-r',code]))
 assert set(before)==set(after),'No file may be added or removed by this patch'
 changes=[f for f in after if after[f]!=before[f]]
 assert set(changes)==set(MANIFEST['payload_hashes']),'Only the five scoped runtime files may differ'
 for f,h in MANIFEST['payload_hashes'].items():
  assert before[f][0]==MANIFEST['base_hashes'][f],'Base image source differs from the recorded Git baseline'
  assert after[f][0]==h and after[f][1]==0o644
  cli(image,['-l','/var/www/html/'+f])
 passed=0
 for name,expected in MANIFEST['contracts'].items():
  output=cli(image,['/tests/contracts.php'],['-e','SCHOOLSAFE_CONTRACT_PHP_ROOT=/var/www/html','--mount','type=bind,source='+str(ROOT/'tests'/name)+',target=/tests/contracts.php,readonly'])
  (ROOT/(name+'.log')).write_bytes(output)
  assert json.loads(output.decode().splitlines()[-1])=={'passed':expected,'failed':0};passed+=expected
 password=secrets.token_hex(32)
 run(['docker','network','create','--internal','--label','schoolsafe.recovery-qa='+NONCE,NET]);created=True
 db_image=before_production['schoolsafe-rosario-db']['image']
 run(['docker','run','-d','--pull=never','--name',DB,'--label','schoolsafe.recovery-qa='+NONCE,'--label','schoolcef.qualification='+MANIFEST['qa_label'],
  '--network',NET,'--network-alias','schoolcef-harmonization-pg','--memory','512m','--cpus','0.5',
  '--tmpfs','/var/lib/postgresql/data:rw,nosuid,nodev,size=512m','-e','PGDATA=/var/lib/postgresql/data/pgdata',
  '-e','POSTGRES_USER=schoolcef_fixture','-e','POSTGRES_DB=schoolcef_fixture','-e','POSTGRES_PASSWORD='+password,db_image])
 for _ in range(45):
  if subprocess.run(['docker','exec',DB,'pg_isready','-h','127.0.0.1','-U','schoolcef_fixture','-d','schoolcef_fixture'],capture_output=True).returncode==0:break
  time.sleep(1)
 else:raise RuntimeError('Synthetic PG did not start')
 run(['docker','exec','-i',DB,'pg_restore','-U','schoolcef_fixture','-d','schoolcef_fixture','--no-owner','--no-acl','--exit-on-error'],(ROOT/'fixture.private.dump').read_bytes())
 run(['docker','run','-d','--pull=never','--name',WEB,'--label','schoolsafe.recovery-qa='+NONCE,'--label','schoolcef.qualification='+MANIFEST['qa_label'],
  '--network',NET,'--memory','768m','--cpus','0.75','--read-only','--tmpfs','/tmp:rw,nosuid,nodev,size=64m','--tmpfs','/run:rw,nosuid,nodev,size=16m',
  '--mount','type=bind,source='+str(ROOT/'fixture-config.php')+',target=/var/www/html/config.inc.php,readonly',
  '-e','POSTGRES_USER=schoolcef_fixture','-e','POSTGRES_DB=schoolcef_fixture','-e','POSTGRES_PASSWORD='+password,
  '-e','SCHOOLSAFE_DB_HOST=schoolcef-harmonization-pg','-e','SCHOOLSAFE_DEFAULT_SYEAR=2026',image])
 web=inspect(WEB);assert web['Image']==image and web['HostConfig']['ReadonlyRootfs'] and not web['HostConfig']['PortBindings']
 network=json.loads(run(['docker','network','inspect',NET]))[0];assert network['Internal']
 for _ in range(30):
  response=run(['docker','exec',WEB,'php','-r','$c=curl_init("http://127.0.0.1/index.php");curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>3]);$b=curl_exec($c);echo curl_getinfo($c,CURLINFO_HTTP_CODE)==200&&strpos((string)$b,"USERNAME")!==false?"READY":"WAIT";'])
  if response==b'READY':break
  time.sleep(1)
 else:raise RuntimeError('Synthetic HTTP did not start')
 recovery=(ROOT/'tests/prodeli-recovery-sms-contracts.php').read_bytes()
 run(['docker','exec','-i',WEB,'php','-r','file_put_contents("/tmp/recovery-contracts.php",stream_get_contents(STDIN));'],recovery)
 output=run(['docker','exec','-e','SCHOOLSAFE_CONTRACT_PHP_ROOT=/var/www/html',WEB,'php','/tmp/recovery-contracts.php'])
 (ROOT/'recovery-contracts.log').write_bytes(output);assert b'37 checks, 0 failures' in output
 STATE.update({'image':image,'ip':web['NetworkSettings']['Networks'][NET]['IPAddress'],'label':MANIFEST['qa_label']})
 (ROOT/'qa-state.json').write_text(json.dumps(STATE));(ROOT/'production-before.json').write_text(json.dumps(before_production));(ROOT/'fixture-before.json').write_text(json.dumps(snapshot()))
 assert production()==before_production
 proof={'status':'READY','base_image':MANIFEST['base_image'],'image':image,'source_digest':MANIFEST['source_digest'],
  'changed_files':changes,'unchanged_files':len(after)-len(changes),'renderer_contracts':passed,'recovery_contracts':37,
  'internal_network':True,'read_only_web':True,'published_ports':False,'production_containers_unchanged':True,'state':STATE}
 (ROOT/'prepare-proof.json').write_text(json.dumps(proof,indent=2));print(json.dumps(proof))
except Exception:
 if created:cleanup()
 raise
'''

VERIFY = r'''
web=inspect(WEB);assert web['Image']==STATE['image'] and not web['HostConfig']['PortBindings'] and web['HostConfig']['ReadonlyRootfs']
assert production()==json.loads((ROOT/'production-before.json').read_text())
assert snapshot()==json.loads((ROOT/'fixture-before.json').read_text()),'Synthetic fixture drift after browser tests'
assert sql("SELECT count(*) FROM pg_namespace WHERE nspname LIKE 'qa_recovery_%'")=='0'
assert sql("SELECT count(*) FROM staff WHERE username LIKE 'qa.recovery.%' OR username LIKE 'qa.reset.%'")=='0'
print(json.dumps({'status':'PASS','image':STATE['image'],'fixture_snapshot_unchanged':True,'production_containers_unchanged':True,'no_test_accounts_or_schemas':True}))
'''


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['prepare', 'verify', 'diagnose', 'cleanup'])
    parser.add_argument('--evidence', required=True, type=Path)
    parser.add_argument('--source-commit', help='Full Git SHA for canonical image payload (defaults to HEAD)')
    args = parser.parse_args()
    evidence = args.evidence.resolve()
    evidence.mkdir(parents=True, exist_ok=True)
    state_file = evidence / 'image-qa-state.json'
    if args.action == 'prepare':
        assert not state_file.exists(), 'Use a fresh evidence directory for each exact-image run'
        nonce = uuid.uuid4().hex[:12]
        state = {'nonce': nonce, 'root': '/var/tmp/schoolsafe-recovery-qa-' + nonce,
                 'web': 'schoolsafe-recovery-qa-web-' + nonce, 'db': 'schoolsafe-recovery-qa-db-' + nonce,
                 'network': 'schoolsafe-recovery-qa-' + nonce}
        source_commit = args.source_commit or local(['git', '-C', str(ROOT), 'rev-parse', 'HEAD']).decode().strip()
        assert re.fullmatch('[a-f0-9]{40}', source_commit)
        # Git blobs, never Windows working-tree bytes: autocrlf must not change
        # the relation between published source and the qualified image.
        git_bytes = lambda f: git_source_bytes(source_commit, f)
        entries = {'payload/' + f: git_bytes(RUNTIME + f) for f in FILES}
        base_hashes = {f: hashlib.sha256(local(['git', '-C', str(ROOT), 'show', BASE_COMMIT + ':' + RUNTIME + f])).hexdigest() for f in FILES}
        payload_hashes = {f: hashlib.sha256(entries['payload/' + f]).hexdigest() for f in FILES}
        digest = hashlib.sha256(json.dumps({'base_image': BASE, 'payload_hashes': payload_hashes}, sort_keys=True).encode()).hexdigest()
        dockerfile = 'FROM ' + BASE_TAG + '\nLABEL schoolsafe.patch.scope="prodeli-recovery-sms" schoolsafe.source.digest="' + digest + '" org.opencontainers.image.revision="' + source_commit + '"\n'
        dockerfile += ''.join('COPY --chmod=0644 payload/' + f + ' /var/www/html/' + f + '\n' for f in FILES)
        entries['Dockerfile'] = dockerfile.encode()
        entries['.dockerignore'] = ('**\n!Dockerfile\n!payload/\n!payload/**\n').encode()
        for name in [*CONTRACTS, 'prodeli-recovery-sms-contracts.php']:
            entries['tests/' + name] = git_bytes('scripts/' + name)
        entries['fixture-config.php'] = git_bytes('ops/deployment/config.inc.production.php')
        assert local(['docker', 'inspect', '--format', '{{index .Config.Labels "schoolcef.qualification"}}', 'schoolcef-harmonization-pg']).decode().strip() == 'harmonization-20261021'
        entries['fixture.private.dump'] = local(['docker', 'exec', 'schoolcef-harmonization-pg', 'pg_dump', '-U', 'schoolcef_fixture', '-d', 'schoolcef_fixture', '-Fc', '--no-owner', '--no-acl'])
        assert len(entries['fixture.private.dump']) < 5_000_000, 'Unexpected fixture size'
        manifest = {'base_image': BASE, 'base_tag': BASE_TAG, 'base_commit': BASE_COMMIT, 'source_commit': source_commit, 'source_digest': digest,
                    'payload_hashes': payload_hashes, 'base_hashes': base_hashes, 'contracts': CONTRACTS,
                    'qa_label': LABEL, 'bundle_files': {f: hashlib.sha256(b).hexdigest() for f, b in entries.items()}}
        bundle = io.BytesIO()
        with tarfile.open(fileobj=bundle, mode='w:gz') as archive:
            for name, data in entries.items():
                info = tarfile.TarInfo(name); info.size = len(data); info.mode = 0o600
                archive.addfile(info, io.BytesIO(data))
        assert len(bundle.getvalue()) < 6_000_000
        create = 'import pathlib,sys;p=pathlib.Path(' + repr(state['root']) + ');p.mkdir(mode=0o700);(p/"input.private.tar.gz").write_bytes(sys.stdin.buffer.read())'
        local(['ssh', '-o', 'BatchMode=yes', SSH, 'python3 -c ' + shlex.quote(create)], bundle.getvalue(), 45)
        state_file.write_text(json.dumps(state, indent=2))
        (evidence / 'source-manifest.json').write_text(json.dumps(manifest, indent=2))
        code = 'STATE=' + repr(state) + '\nMANIFEST=' + repr(manifest) + '\n' + REMOTE_COMMON + PREPARE
        proof = json.loads(remote(code, evidence))
        state_file.write_text(json.dumps(proof['state'], indent=2))
        (evidence / 'image-prepare-proof.json').write_text(json.dumps(proof, indent=2))
        print(json.dumps(proof))
    else:
        state = json.loads(state_file.read_text())
        assert re.fullmatch('[a-f0-9]{12}', state['nonce'])
        assert state['root'] == '/var/tmp/schoolsafe-recovery-qa-' + state['nonce']
        for field, prefix in [('web', 'schoolsafe-recovery-qa-web-'), ('db', 'schoolsafe-recovery-qa-db-'), ('network', 'schoolsafe-recovery-qa-')]:
            assert state[field] == prefix + state['nonce']
        code = 'STATE=' + repr(state) + '\n' + REMOTE_COMMON
        if args.action == 'verify':
            code += VERIFY
        elif args.action == 'diagnose':
            code += "\nsummary={}\nfor name in [WEB,DB]:\n d=inspect(name);assert d['Config']['Labels'].get('schoolsafe.recovery-qa')==NONCE\n p=subprocess.run(['docker','logs','--tail','160',name],capture_output=True)\n (ROOT/(name+'.private.log')).write_bytes(p.stdout+p.stderr)\n summary[name]={'running':d['State']['Running'],'oom_killed':d['State']['OOMKilled'],'restarts':d['RestartCount']}\nprint(json.dumps(summary))\n"
        else:
            code += "cleanup()\nassert production()==json.loads((ROOT/'production-before.json').read_text())\nprint(json.dumps({'status':'REMOVED','only_owned_ephemeral_resources':True,'production_containers_unchanged':True}))\n"
        proof = remote(code, evidence, 90)
        (evidence / ('image-' + args.action + '-proof.json')).write_bytes(proof)
        print(proof.decode())


if __name__ == '__main__':
    main()
