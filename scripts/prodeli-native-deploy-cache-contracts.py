"""Offline regression for strict writable-layer and Chromium cache preservation."""
import importlib.util
import io
import pathlib
import sys
import tarfile

sys.dont_write_bytecode = True
path = pathlib.Path(__file__).resolve().parents[1] / 'ops/releases/prodeli-native-access-20261021/deploy.py'
spec = importlib.util.spec_from_file_location('release', path)
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)
release.run = lambda args: b'A /tmp/.config\nA /tmp/.config/chromium\nA /tmp/.config/chromium/BrowserMetrics\nA /tmp/.config/chromium/BrowserMetrics/test.pma\n'
release.check_layer('synthetic')
print('PASS known Chromium runtime cache accepted')
for drift in [b'A /tmp/unexpected-file\n', b'C /var/www/html/index.php\n', b'A /tmp/.config/other-app/file\n']:
    release.run = lambda args, value=drift: value
    try:
        release.check_layer('synthetic')
    except AssertionError:
        pass
    else:
        raise AssertionError('Unexpected write allowed')
print('PASS unrelated runtime/source changes still rejected')


def archive(content):
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w') as tar:
        for name, data in [('./sess_fixture', b'private synthetic'), ('./.config/chromium/BrowserMetrics/test.pma', content)]:
            entry = tarfile.TarInfo(name)
            entry.size = len(data)
            entry.mode = 0o600
            entry.uid = 33
            entry.gid = 33
            tar.addfile(entry, io.BytesIO(data))
    return output.getvalue()


original = archive(b'synthetic metrics')
changed = archive(b'altered metrics')
assert release.chromium_artifacts(original) == release.chromium_artifacts(original)
assert release.chromium_artifacts(original) != release.chromium_artifacts(changed)
assert len(release.chromium_artifacts(original)) == 1
assert release.sessions(original) == release.sessions(changed)
print('PASS cache bytes/ownership/mode manifest distinguishes changes; session contract unchanged')
