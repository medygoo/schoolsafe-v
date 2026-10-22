"""Bounded design lot: prove processing/forms and confidential/print sources unchanged."""
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
BASE = '7893bc5d3a5eab7b778170620e2fcca935b23811'
RUNTIME = 'app/modules/codex-ui-v1/extensions-test-runtime/'
def before(name):
    return subprocess.check_output(['git', '-C', str(ROOT), 'show', BASE + ':' + RUNTIME + name]).decode().replace('\r\n', '\n')
def current(name):
    return (ROOT / RUNTIME / name).read_text(encoding='utf-8')
def check(name, condition):
    assert condition, name
    print('PASS ' + name)

name = 'compte-recuperation.php'
old, new = before(name), current(name)
check('Recovery POST, CSRF, OTP, throttling and headers byte-identical', old.split('?>', 1)[0] == new.split('?>', 1)[0])
check('Every native recovery form and input attribute unchanged', re.findall(r'<form\b.*?</form>', old, re.S) == re.findall(r'<form\b.*?</form>', new, re.S))
check('Recovery status/error/simulation branches unchanged', re.findall(r'<\?php.*?\?>', old, re.S) == re.findall(r'<\?php.*?\?>', new, re.S))
check('No public global scripts introduced', '<script' not in new)
for name, old_version, new_version in [
    ('plugins/SchoolSafe_Keycloak/Wizard.php', 'central-ui.css?v=16', 'central-ui.css?v=17'),
    ('ProgramFunctions/SchoolSafeWorkspace.fnc.php', 'schoolsafe-native-workspace.css?v=1', 'schoolsafe-native-workspace.css?v=2'),
    ('ProgramFunctions/SchoolSafePhotoDashboard.fnc.php', 'schoolsafe-photo-dashboard.css?v=workspace-20261022', 'schoolsafe-photo-dashboard.css?v=presentation-20261022'),
]:
    check(name + ': CSS cache key only', before(name).replace(old_version, new_version) == current(name))
for name in ['PasswordReset.php', 'ProgramFunctions/SchoolSafeRecovery.fnc.php',
             'modules/School_Setup/Configuration.php', 'plugins/SchoolSafe_Keycloak/manage.php',
             'ProgramFunctions/FirstLogin.fnc.php', 'functions/PDF.php']:
    check(name + ': existing handler/confidential/print source unchanged', before(name) == current(name))
allowed = {'compte-recuperation.php', 'assets/css/schoolsafe-recovery.css',
           'assets/css/schoolsafe-native-workspace.css', 'assets/css/schoolsafe-photo-dashboard.css',
           'plugins/SchoolSafe_Keycloak/central-ui.css', 'plugins/SchoolSafe_Keycloak/Wizard.php',
           'ProgramFunctions/SchoolSafeWorkspace.fnc.php', 'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php'}
changed = subprocess.check_output(['git', '-C', str(ROOT), 'diff', BASE, '--name-only', '--', RUNTIME], text=True).splitlines()
untracked = subprocess.check_output(['git', '-C', str(ROOT), 'ls-files', '--others', '--exclude-standard', '--', RUNTIME], text=True).splitlines()
check('Runtime change allowlist', {p.removeprefix(RUNTIME) for p in changed + untracked} <= allowed)
