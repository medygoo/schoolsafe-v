#!/usr/bin/env python3
"""Fail if this presentation lot changes communication handlers or any transport."""
from pathlib import Path
import subprocess,hashlib,json
ROOT=Path(__file__).resolve().parents[1]
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
BASE='38856445143050895b6502c51be18285e9309042'
def old(path):return subprocess.check_output(['git','-C',str(ROOT),'show',BASE+':'+path])
files=['plateforme-prodeli-sms.php','plateforme-prodeli-whatsapp.php']
for f in files:
    before=old(PREFIX+f).decode().replace('\r\n','\n');after=(ROOT/PREFIX/f).read_text(encoding='utf-8')
    assert before.split('?>\n<!doctype html>')[0]==after.split('// View selection only;')[0],f+' handlers changed'
    for action in (['line_save','line_status','quota_add','test_send'] if f==files[0] else ['session_save','session_status','consent_set','test_send']):
        assert before.count('value="'+action+'"')==after.count('value="'+action+'"'),action+' form removed'
protected=['ProgramFunctions/SchoolSafeStudio.fnc.php','ProgramFunctions/SchoolSafeNotify.fnc.php','ProgramFunctions/SchoolSafeSms.fnc.php','ProgramFunctions/SchoolSafeWhatsapp.fnc.php','ProgramFunctions/SchoolSafeBrevo.fnc.php','ProgramFunctions/SchoolSafeWebPush.fnc.php','schoolsafe-notify-dispatch.php','schoolsafe-notify-recover.php']
for f in protected:assert old(PREFIX+f).replace(b'\r\n',b'\n')==(ROOT/PREFIX/f).read_bytes().replace(b'\r\n',b'\n'),f+' changed'
changed=subprocess.check_output(['git','-C',str(ROOT),'diff',BASE,'--name-only','--',PREFIX]).decode().splitlines()
allowed={PREFIX+f for f in files+[ 'assets/css/prodeli-communication.css' ]}
assert set(changed)<=allowed,'Runtime scope expanded'
print(json.dumps({'status':'PASS','page_handlers_unchanged':2,'protected_components_unchanged':len(protected),'runtime_allowlist':sorted(allowed)}))
