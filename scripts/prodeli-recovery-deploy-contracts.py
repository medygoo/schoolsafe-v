"""Offline gates for the five-file deployment and retained preservation helpers."""
import copy
import importlib.util
from pathlib import Path
import subprocess
import sys

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('recovery_release', ROOT / 'ops/releases/prodeli-recovery-sms-20261022/deploy.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)
count = 0


def check(ok, label):
    global count
    assert ok, label
    count += 1
    print('PASS ' + label)


r = {'commit': 'a' * 40, 'image': release.IMAGE, 'source_digest': release.DIGEST,
     'files': {f: 'b' * 64 for f in release.FILES}, 'recovery_groups': 10,
     'native_groups': 10, 'concurrent_successes': 1, 'concurrent_epoch': 1, 'revocation_failure_rollbacks': 2}
p = {'status': 'READY', 'image': release.IMAGE, 'source_digest': release.DIGEST,
     'renderer_contracts': 92, 'recovery_contracts': 37, 'changed_files': sorted(release.FILES),
     'unchanged_files': 2755, 'internal_network': True, 'read_only_web': True, 'published_ports': False}
i = {'status': 'PASS', 'image': release.IMAGE, 'suites': [{'suite': s, 'exit': 0} for s in ['recovery', 'concurrency', 'native']]}
v = {'status': 'PASS', 'image': release.IMAGE, 'fixture_snapshot_unchanged': True, 'production_containers_unchanged': True}
c = {'status': 'REMOVED', 'only_owned_ephemeral_resources': True}
release.required_qualification(r, p, i, v, c)
check(True, 'exact-image, source digest and all qualification proofs accepted')
for target, key, value, label in [
    (0, 'image', release.BASE, 'old image refused'),
    (0, 'source_digest', '0' * 64, 'other payload refused'),
    (1, 'changed_files', ['Warehouse.php'], 'out-of-scope runtime refused'),
    (1, 'renderer_contracts', 91, 'missing renderer contract refused'),
    (1, 'published_ports', True, 'public QA refused'),
    (2, 'status', 'FAIL', 'failed integration refused'),
    (3, 'fixture_snapshot_unchanged', False, 'fixture drift refused'),
    (4, 'status', 'READY', 'missing QA cleanup refused'),
    (0, 'concurrent_successes', 2, 'native double consumption refused'),
    (0, 'revocation_failure_rollbacks', 0, 'missing rollback-on-failure proof refused'),
]:
    proofs = copy.deepcopy([r, p, i, v, c]); proofs[target][key] = value
    try:
        release.required_qualification(*proofs)
    except AssertionError:
        check(True, label)
    else:
        raise AssertionError(label)

runtime = {'Config': {'Env': ['SYNTHETIC=value'], 'Cmd': ['apache2-foreground'], 'Entrypoint': ['docker-php-entrypoint'], 'User': '', 'Healthcheck': {'Test': ['CMD', 'true']}},
           'HostConfig': {'Binds': ['/synthetic/config:/var/www/html/config.inc.php:ro'], 'PortBindings': {'80/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '18081'}]}, 'NetworkMode': 'fixture'}}
release.unchanged_runtime(runtime, copy.deepcopy(runtime)); check(True, 'original runtime settings accepted')
for section, key, value in [('Config', 'Env', ['ALTERED=value']), ('HostConfig', 'PortBindings', {}),
                            ('HostConfig', 'Binds', []), ('Config', 'Cmd', ['other'])]:
    changed = copy.deepcopy(runtime); changed[section][key] = value
    try:
        release.unchanged_runtime(runtime, changed)
    except AssertionError:
        check(True, section + '.' + key + ' drift refused')
    else:
        raise AssertionError('Runtime drift accepted')

release.preserve.run = lambda args: b'A /tmp/.config\nA /tmp/.config/chromium\nA /tmp/.config/chromium/BrowserMetrics\nA /tmp/.config/chromium/BrowserMetrics/synthetic.pma\n'
release.preserve.check_layer('synthetic'); check(True, 'retained Chromium cache preservation guard accepts its owned path')
for drift in [b'A /tmp/unexpected-file\n', b'C /var/www/html/PasswordReset.php\n', b'A /tmp/.config/other-app/file\n']:
    release.preserve.run = lambda args, output=drift: output
    try:
        release.preserve.check_layer('synthetic')
    except AssertionError:
        check(True, 'unpreserved writable layer/source drift refused')
    else:
        raise AssertionError('Writable drift accepted')

p = subprocess.run([sys.executable, str(ROOT / 'ops/releases/prodeli-recovery-sms-20261022/deploy.py'), '--rollback'], capture_output=True)
check(p.returncode == 2, 'no rollback CLI can start the known-vulnerable old runtime')
check(not hasattr(release, 'rollback'), 'new deployment exposes no automatic rollback function')
spec = importlib.util.spec_from_file_location('canonical_qa', ROOT / 'scripts/prodeli-recovery-image-qa.py')
canonical = importlib.util.module_from_spec(spec); spec.loader.exec_module(canonical)
calls = []
canonical.local = lambda args: calls.append(args) or b'canonical LF\n'
check(canonical.git_source_bytes('a' * 40, 'fixture.php') == b'canonical LF\n'
      and calls[0][-2:] == ['show', 'a' * 40 + ':fixture.php'], 'image payload comes from Git blobs, not Windows normalization')
try:
    canonical.git_source_bytes('HEAD', 'fixture.php')
except AssertionError:
    check(True, 'unresolved or mutable source reference refused')
else:
    raise AssertionError('Unresolved image source accepted')
print(str(count) + ' checks, 0 failures')
