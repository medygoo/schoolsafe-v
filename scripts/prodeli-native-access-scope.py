#!/usr/bin/env python3
"""Bound the native first-access patch against the exact deployed source."""
from pathlib import Path
import subprocess,json
R=Path(__file__).resolve().parents[1];P='app/modules/codex-ui-v1/extensions-test-runtime/';BASE='3eb2e9e35984de9c40e22c571b212e727b13a2bb'
def old(p):return subprocess.check_output(['git','-C',str(R),'show',BASE+':'+P+p]).decode().replace('\r\n','\n')
def current(p):return (R/P/p).read_text(encoding='utf-8')
protected=['plugins/SchoolSafe_Keycloak/functions.php','plugins/SchoolSafe_Keycloak/Opening.php',
 'ProgramFunctions/SchoolSafeRecovery.fnc.php','ProgramFunctions/SchoolSafeAccountState.fnc.php',
 'ProgramFunctions/SchoolSafeStudio.fnc.php','ProgramFunctions/SchoolSafeNotify.fnc.php',
 'ProgramFunctions/SchoolSafeSms.fnc.php','ProgramFunctions/SchoolSafeWhatsapp.fnc.php',
 'ProgramFunctions/SchoolSafeBrevo.fnc.php','schoolsafe-notify-dispatch.php','Side.php',
 'PasswordReset.php','compte-recuperation.php','plateforme-prodeli-sms.php','plateforme-prodeli-whatsapp.php',
 'plugins/SchoolSafe_Keycloak/central-ui.js','plugins/SchoolSafe_Keycloak/central-ui.css']
for p in protected:assert old(p)==current(p),p+' changed'
m='plugins/SchoolSafe_Keycloak/Management.php';marker='function sskcBeforeNativeCreate(): void'
assert old(m).split(marker)[1]==current(m).split(marker)[1],'Managed mutation handlers changed'
w='plugins/SchoolSafe_Keycloak/Wizard.php';marker='function sskcWizardDraft(): array'
assert old(w).split(marker)[1]==current(w).split(marker)[1],'School wizard bodies or managed hooks changed'
paths=['ProgramFunctions/FirstLogin.fnc.php','ProgramFunctions/SchoolSafeSchoolSetup.fnc.php',
 'ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php','Warehouse.php','index.php',
 'modules/School_Setup/CopySchool.php',m,w,'plugins/SchoolSafe_Keycloak/manage.php',
 'schoolsafe-remediation/038-native-onboarding.sql']
changed=subprocess.check_output(['git','-C',str(R),'diff',BASE,'--name-only','--',P]).decode().splitlines()
new=subprocess.check_output(['git','-C',str(R),'ls-files','--others','--exclude-standard','--',P]).decode().splitlines()
assert set(changed+new)=={P+p for p in paths},'Unexpected runtime scope'
print(json.dumps({'status':'PASS','base':BASE,'runtime_files':paths,'protected_components_unchanged':len(protected),
 'managed_account_handlers_unchanged':True,'wizard_creation_bodies_unchanged':True,'communication_unchanged':True}))
