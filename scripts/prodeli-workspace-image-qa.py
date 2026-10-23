#!/usr/bin/env python3
"""Git-only workspace build on the verified recovery image; isolated synthetic QA.
Reuses the proven qualification transport, fixtures and nonce-owned cleanup.
Does not deploy, read production data, or transfer the complete image.
"""
import argparse
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import re
import shlex
import tarfile
import uuid

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('recovery_qa', ROOT / 'scripts/prodeli-recovery-image-qa.py')
qa = importlib.util.module_from_spec(spec)
spec.loader.exec_module(qa)
BASE = 'sha256:01c194f4817731cecbec65476926585c58dced9118423bf7bbbff2ef10c73c34'
BASE_TAG = 'schoolsafe-rosario:recovery-qa-2745039fd3e3'
BASE_COMMIT = '6e90fe5ebc0f3897f7b70aebe19cb2c1dd49b1d6'
PREFIX = 'app/modules/codex-ui-v1/extensions-test-runtime/'
FILES = ['index.php', 'admin-console.php', 'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
         'ProgramFunctions/SchoolSafeWorkspace.fnc.php', 'assets/css/schoolsafe-native-workspace.css',
         'assets/css/schoolsafe-photo-dashboard.css', 'assets/js/schoolsafe-photo-dashboard.js',
         'modules/SchoolSafe/login-blue.inc.php', 'modules/School_Setup/Configuration.php',
         'modules/School_Setup/CopySchool.php', 'modules/misc/Portal.php',
         'plugins/SchoolSafe_Keycloak/Management.php', 'plugins/SchoolSafe_Keycloak/Wizard.php',
         'plugins/SchoolSafe_Keycloak/manage.php', 'plugins/SchoolSafe_Keycloak/central-ui.css',
         'plugins/SchoolSafe_Keycloak/central-ui.js']
NEW_FILES = {'ProgramFunctions/SchoolSafeWorkspace.fnc.php', 'assets/css/schoolsafe-native-workspace.css'}
CONTRACTS = {**qa.CONTRACTS, 'prodeli-workspace-contracts.php': 31}
LABEL = 'prodeli-workspace-20261022'
PATCH_SCOPE = 'prodeli-workspace'
EXTRA_TEST_FILES = []


def preparation_code():
    code = qa.PREPARE.replace("assert set(before)==set(after),'No file may be added or removed by this patch'",
        "assert set(after)-set(before)==set(MANIFEST['new_files']) and not set(before)-set(after), 'Unexpected runtime additions/removals'")
    code = code.replace('if after[f]!=before[f]', 'if f not in before or after[f]!=before[f]')
    code = code.replace("assert before[f][0]==MANIFEST['base_hashes'][f]", "assert f not in before or before[f][0]==MANIFEST['base_hashes'][f]")
    code = code.replace("assert json.loads(output.decode().splitlines()[-1])=={'passed':expected,'failed':0};passed+=expected",
        "result=json.loads(output.decode().splitlines()[-1]);assert result['passed']==expected and result['failed']==0;passed+=expected")
    build = ''' log=run(['docker','build','--network','none','--pull=false','-t',tag,str(ROOT)],timeout=120)
 (ROOT/'build.log').write_bytes(log)
 image=run(['docker','image','inspect','--format','{{.Id}}',tag]).decode().strip()
'''
    reuse = ''' if MANIFEST.get('existing_image'):
  image=MANIFEST['existing_image'];d=inspect(image)
  assert d['Id']==image and d['Config']['Labels']['schoolsafe.source.digest']==MANIFEST['source_digest']
  assert d['Config']['Labels']['org.opencontainers.image.revision']==MANIFEST['source_commit']
  (ROOT/'build.log').write_text('Reusing verified immutable image '+image)
 else:
  log=run(['docker','build','--network','none','--pull=false','-t',tag,str(ROOT)],timeout=120)
  (ROOT/'build.log').write_bytes(log)
  image=run(['docker','image','inspect','--format','{{.Id}}',tag]).decode().strip()
'''
    assert code.count(build) == 1
    code = code.replace(build, reuse)
    return code.replace('Only the five scoped runtime files may differ', 'Only the declared workspace runtime files may differ')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['prepare', 'verify', 'diagnose', 'cleanup'])
    parser.add_argument('--evidence', required=True, type=Path)
    parser.add_argument('--source-commit')
    parser.add_argument('--existing-image', help='Reuse this immutable image ID after label/full payload checks; no rebuild')
    args = parser.parse_args()
    if args.existing_image:
        assert args.action == 'prepare' and re.fullmatch('sha256:[a-f0-9]{64}', args.existing_image)
    evidence = args.evidence.resolve(); evidence.mkdir(parents=True, exist_ok=True)
    state_file = evidence / 'image-qa-state.json'
    if args.action == 'prepare':
        assert not state_file.exists(), 'Fresh evidence directory required'
        commit = args.source_commit or qa.local(['git', '-C', str(ROOT), 'rev-parse', 'HEAD']).decode().strip()
        assert re.fullmatch('[a-f0-9]{40}', commit)
        changed = qa.local(['git', '-C', str(ROOT), 'diff', '--name-only', BASE_COMMIT, commit, '--', PREFIX]).decode().splitlines()
        assert set(changed) == {PREFIX + f for f in FILES}, 'Commit runtime scope differs from the release allowlist'
        git = lambda p: qa.git_source_bytes(commit, p)
        entries = {'payload/' + f: git(PREFIX + f) for f in FILES}
        hashes = {f: hashlib.sha256(entries['payload/' + f]).hexdigest() for f in FILES}
        baseline = {f: hashlib.sha256(qa.git_source_bytes(BASE_COMMIT, PREFIX + f)).hexdigest() for f in FILES if f not in NEW_FILES}
        digest = hashlib.sha256(json.dumps({'base_image': BASE, 'payload_hashes': hashes}, sort_keys=True).encode()).hexdigest()
        dockerfile = 'FROM ' + BASE_TAG + '\nLABEL schoolsafe.patch.scope="' + PATCH_SCOPE + '" schoolsafe.source.digest="' + digest + '" org.opencontainers.image.revision="' + commit + '"\n'
        dockerfile += ''.join('COPY --chmod=0644 payload/' + f + ' /var/www/html/' + f + '\n' for f in FILES)
        entries['Dockerfile'] = dockerfile.encode()
        entries['.dockerignore'] = b'**\n!Dockerfile\n!payload/\n!payload/**\n'
        for name in [*CONTRACTS, 'prodeli-recovery-sms-contracts.php']:
            entries['tests/' + name] = git('scripts/' + name)
        for path in EXTRA_TEST_FILES:
            entries['extra-tests/' + Path(path).name] = git(path)
        entries['fixture-config.php'] = git('ops/deployment/config.inc.production.php')
        assert qa.local(['docker', 'inspect', '--format', '{{index .Config.Labels "schoolcef.qualification"}}', 'schoolcef-harmonization-pg']).decode().strip() == 'harmonization-20261021'
        entries['fixture.private.dump'] = qa.local(['docker', 'exec', 'schoolcef-harmonization-pg', 'pg_dump', '-U', 'schoolcef_fixture', '-d', 'schoolcef_fixture', '-Fc', '--no-owner', '--no-acl'])
        assert len(entries['fixture.private.dump']) < 5_000_000
        nonce = uuid.uuid4().hex[:12]
        state = {'nonce': nonce, 'root': '/var/tmp/schoolsafe-recovery-qa-' + nonce,
                 'web': 'schoolsafe-recovery-qa-web-' + nonce, 'db': 'schoolsafe-recovery-qa-db-' + nonce,
                 'network': 'schoolsafe-recovery-qa-' + nonce}
        manifest = {'base_image': BASE, 'base_tag': BASE_TAG, 'base_commit': BASE_COMMIT, 'source_commit': commit,
                    'source_digest': digest, 'payload_hashes': hashes, 'base_hashes': baseline,
                    'new_files': sorted(NEW_FILES), 'contracts': CONTRACTS, 'qa_label': LABEL,
                    'existing_image': args.existing_image,
                    'bundle_files': {f: hashlib.sha256(b).hexdigest() for f, b in entries.items()}}
        bundle = io.BytesIO()
        with tarfile.open(fileobj=bundle, mode='w:gz') as archive:
            for name, data in entries.items():
                info = tarfile.TarInfo(name); info.size = len(data); info.mode = 0o600
                archive.addfile(info, io.BytesIO(data))
        assert len(bundle.getvalue()) < 6_000_000
        create = 'import pathlib,sys;p=pathlib.Path(' + repr(state['root']) + ');p.mkdir(mode=0o700);(p/"input.private.tar.gz").write_bytes(sys.stdin.buffer.read())'
        qa.local(['ssh', '-4', '-o', 'BatchMode=yes', qa.SSH, 'python3 -c ' + shlex.quote(create)], bundle.getvalue(), 45)
        state_file.write_text(json.dumps(state, indent=2))
        (evidence / 'source-manifest.json').write_text(json.dumps(manifest, indent=2))
        proof = json.loads(qa.remote('STATE=' + repr(state) + '\nMANIFEST=' + repr(manifest) + '\n' + qa.REMOTE_COMMON + preparation_code(), evidence))
        state_file.write_text(json.dumps(proof['state'], indent=2))
    else:
        state = json.loads(state_file.read_text())
        assert re.fullmatch('[a-f0-9]{12}', state['nonce'])
        for field, prefix in [('root','/var/tmp/schoolsafe-recovery-qa-'),('web','schoolsafe-recovery-qa-web-'),('db','schoolsafe-recovery-qa-db-'),('network','schoolsafe-recovery-qa-')]:
            assert state[field] == prefix + state['nonce']
        code = 'STATE=' + repr(state) + '\n' + qa.REMOTE_COMMON
        if args.action == 'verify': code += qa.VERIFY
        elif args.action == 'cleanup': code += "cleanup()\nassert production()==json.loads((ROOT/'production-before.json').read_text())\nprint(json.dumps({'status':'REMOVED','only_owned_ephemeral_resources':True,'production_containers_unchanged':True}))"
        else: code += "print(json.dumps({n:{'running':inspect(n)['State']['Running'],'restarts':inspect(n)['RestartCount']} for n in [WEB,DB]}))"
        proof = json.loads(qa.remote(code, evidence, 120))
    (evidence / ('image-' + args.action + '-proof.json')).write_text(json.dumps(proof, indent=2))
    print(json.dumps(proof))


if __name__ == '__main__': main()
