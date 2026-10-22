"""Second design lot: preserve authentication, handoff ownership and theme assets."""
from pathlib import Path
import re
import subprocess
R=Path(__file__).resolve().parents[1]
P='app/modules/codex-ui-v1/extensions-test-runtime/'
BASE='c4c75f77d6d83739596a1e964c598da6d06ba419'
def old(name):return subprocess.check_output(['git','-C',str(R),'show',BASE+':'+P+name]).decode().replace('\r\n','\n')
def new(name):return (R/P/name).read_text(encoding='utf-8')
def check(name,ok):
 assert ok,name
 print('PASS '+name)
name='ProgramFunctions/FirstLogin.fnc.php';a,b=old(name),new(name)
start="if ( ! function_exists( 'FirstLoginFormPasswordChange' ) )"
end="if ( ! function_exists( 'FirstLoginFormFields' ) )"
check('First-access processing and all other native form builders unchanged',a.split(start)[0]==b.split(start)[0] and a.split(end)[1]==b.split(end)[1])
check('First-access form attributes and CSRF input unchanged',re.findall(r'<form\b[^>]*>|<input\b[^>]*>',a)==re.findall(r'<form\b[^>]*>|<input\b[^>]*>',b))
name='modules/SchoolSafe/login-blue.inc.php';a,b=old(name),new(name)
check('Entire native login form, hooks and redirects unchanged',re.findall(r'<form\b.*?</form>',a,re.S)==re.findall(r'<form\b.*?</form>',b,re.S))
name='plugins/SchoolSafe_Keycloak/manage.php';a,b=old(name),new(name)
mark="if(isset($_GET['credential']))"
check('All account POST handlers and existing security headers unchanged',a.split(mark)[0]==b.split(mark)[0])
mark="$id=(int)$_GET['credential'];"
check('Handoff target, actor, expiry, session consumption and audit unchanged',a.split(mark)[1].split("echo '<h2>")[0]==b.split(mark)[1].split("echo '<h1>")[0])
check('Downloaded confidential document generation unchanged',re.search(r'\$document=.*?;\n',a).group()==re.search(r'\$document=.*?;\n',b).group())
mark="$_REQUEST['modname']='Users/User.php';$_ROSARIO['page']='modules';"
check('All ordinary account management markup and controllers unchanged',a.split(mark)[1]==b.split(mark)[1])
for name in ['index.php','PasswordReset.php','compte-recuperation.php','ProgramFunctions/SchoolSafeRecovery.fnc.php','ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php','functions/PDF.php','assets/login/blue/blue.js','Warehouse.php']:
 check(name+' unchanged',old(name)==new(name))
assets=subprocess.check_output(['git','-C',str(R),'diff',BASE,'--name-only','--',P+'assets/themes/'],text=True)
check('All theme foundations, styles, fonts and printing inheritance preserved',not assets.strip())
allowed={'functions/User.fnc.php','functions/Config.fnc.php','ProgramFunctions/Theme.fnc.php','ProgramFunctions/FirstLogin.fnc.php','modules/School_Setup/Configuration.php','modules/Users/Preferences.php','modules/SchoolSafe/login-blue.inc.php','plugins/SchoolSafe_Keycloak/manage.php','assets/css/schoolsafe-access.css'}
paths=subprocess.check_output(['git','-C',str(R),'diff',BASE,'--name-only','--',P],text=True).splitlines()+subprocess.check_output(['git','-C',str(R),'ls-files','--others','--exclude-standard','--',P],text=True).splitlines()
check('Nine-file runtime allowlist',{p.removeprefix(P) for p in paths}<=allowed)
