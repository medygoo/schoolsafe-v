#!/usr/bin/env python3
"""Two-file account + school state exact-image QA; no production deployment."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('school_state_qa',root/'scripts/prodeli-school-state-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.FILES=['ProgramFunctions/SchoolSafeSchoolState.fnc.php','ProgramFunctions/SchoolSafeAccountState.fnc.php']
qa.PATCH_SCOPE='prodeli-state-audit-atomicity'
qa.EXTRA_TEST_FILES=['scripts/prodeli-school-state-probe.php','scripts/prodeli-account-state-probe.php']
if __name__=='__main__':qa.main()
