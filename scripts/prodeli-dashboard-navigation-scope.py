#!/usr/bin/env python3
"""Bound the dashboard navigation patch; forbid account/provider/transport changes."""
from pathlib import Path
import subprocess,json
R=Path(__file__).resolve().parents[1];P='app/modules/codex-ui-v1/extensions-test-runtime/';BASE='90f6238cfee03f81af19a0c3ff4f902f9c134c3e'
def old(p):return subprocess.check_output(['git','-C',str(R),'show',BASE+':'+P+p]).decode().replace('\r\n','\n')
def current(p):return (R/P/p).read_text(encoding='utf-8')
protected=['plugins/SchoolSafe_Keycloak/Management.php','plugins/SchoolSafe_Keycloak/functions.php','ProgramFunctions/SchoolSafeSchoolSetup.fnc.php','ProgramFunctions/FirstLogin.fnc.php','ProgramFunctions/SchoolSafeRecovery.fnc.php','ProgramFunctions/SchoolSafeAccountState.fnc.php','ProgramFunctions/SchoolSafeStudio.fnc.php','ProgramFunctions/SchoolSafeNotify.fnc.php','ProgramFunctions/SchoolSafeSms.fnc.php','ProgramFunctions/SchoolSafeWhatsapp.fnc.php','ProgramFunctions/SchoolSafeBrevo.fnc.php','schoolsafe-notify-dispatch.php','Side.php','modules/School_Setup/CopySchool.php']
for p in protected:assert old(p)==current(p),p+' changed'
w='plugins/SchoolSafe_Keycloak/Wizard.php';assert old(w).split('function sskcWizardAllowed(): bool')[1]==current(w).split('function sskcWizardAllowed(): bool')[1],'Wizard creation/actions changed'
m='plugins/SchoolSafe_Keycloak/manage.php';text=current(m);start=text.index("if(($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['action']??'')==='dashboard_school')");end=text.index("if(isset($_GET['home'])) sskcHomePage();",start)
assert text[:start]+text[end:]==old(m),'Existing managed handlers changed'
portal='modules/misc/Portal.php';text=current(portal);start=text.index('// Central operators can return');end=text.index('$salute = sprintf',start);assert text[:start]+text[end:]==old(portal),'Native portal behavior changed'
paths=[w,m,portal,'plugins/SchoolSafe_Keycloak/central-ui.js','plugins/SchoolSafe_Keycloak/central-ui.css']
changed=subprocess.check_output(['git','-C',str(R),'diff',BASE,'--name-only','--',P]).decode().splitlines();assert set(changed)=={P+p for p in paths},'Runtime scope mismatch'
print(json.dumps({'status':'PASS','protected_components_unchanged':len(protected),'native_school_controller_unchanged':True,'managed_account_handlers_unchanged':True,'runtime_files':paths}))
