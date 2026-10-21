"""Guard the extracted presentation-only release against accidental mutation changes."""
from pathlib import Path
import subprocess
r=Path(__file__).resolve().parents[1]
prefix='app/modules/codex-ui-v1/extensions-test-runtime/'
base='d12a6398e473ea2b5238dba338af19d0ebf7df8a'
changed=set(subprocess.check_output(['git','-C',str(r),'diff',base,'--name-only','--',prefix],text=True).splitlines())
allowed={prefix+'plugins/SchoolSafe_Keycloak/'+f for f in ['Management.php','manage.php','Wizard.php','central-ui.js']}
assert changed==allowed
files={}
for f in ['Management.php','manage.php','Wizard.php','central-ui.js']:
 p=prefix+'plugins/SchoolSafe_Keycloak/'+f
 old=subprocess.check_output(['git','-C',str(r),'show',base+':'+p]).decode('utf-8').replace('\r\n','\n')
 new=(r/p).read_text(encoding='utf-8');files[f]=(old,new)
 for forbidden in ['native_suspend','native_resume','sskcNativeLifecycleActions','school_logo_failed']:
  assert forbidden not in new, (f,forbidden)
old,new=files['Management.php'];marker='function sskcCanRead(): bool'
assert old[old.index(marker):]==new[new.index(marker):], 'Existing mutation/permission handlers changed'
old,new=files['Wizard.php'];marker='function sskcWizardDraft(): array'
assert old[old.index(marker):]==new[new.index(marker):], 'School creation/logo workflow changed'
old,new=files['manage.php'];start="if(isset($_GET['home']))";end="sskcUiStart("
assert old[old.index(start):old.index(end)]==new[new.index(start):new.index(end)], 'POST/credential processing changed'
print('PASS: four-file allowlist, no native commands, existing handlers and school workflow unchanged')
