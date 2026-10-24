#!/usr/bin/env python3
"""Git-only native access candidate; isolated fixture, never a production mutation."""
import importlib.util
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('school_access_scan_qa',ROOT/'scripts/school-scan-image-qa.py')
scan=importlib.util.module_from_spec(spec);spec.loader.exec_module(scan)
qa=scan.qa
qa.FILES=list(qa.FILES)+['ProgramFunctions/SchoolSafeScanDelegation.fnc.php',
 'ProgramFunctions/SchoolSafeSchoolAccess.fnc.php','ProgramFunctions/SchoolSafeSchoolAccessView.fnc.php',
 'ProgramFunctions/SchoolSafeSchoolScope.fnc.php','admin-console.php','ecole-profils.php']
qa.NEW_FILES={f for f in qa.FILES if not qa.qa.local(['git','-C',str(ROOT),'ls-tree','--name-only',qa.BASE_COMMIT,'--',qa.PREFIX+f]).decode().strip()}
qa.CONTRACTS=dict(qa.CONTRACTS,**{'school-scan-delegation-contracts.php':19,'school-access-policy-contracts.php':15})
original=qa.preparation_code
def preparation_code():
 code=original()
 needle="assert production()['schoolsafe-rosario']['image']==MANIFEST['base_image']"
 assert code.count(needle)==1
 return code.replace(needle,"assert production()['schoolsafe-rosario']['image']=='sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628'")
qa.preparation_code=preparation_code
if __name__=='__main__':qa.main()
