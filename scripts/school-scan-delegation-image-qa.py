#!/usr/bin/env python3
"""Isolated Git-only candidate: original Scan payload plus native profile-owner handoff.
Does not alter the historical65-file release recipe or any production configuration.
"""
import importlib.util
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('school_scan_handoff_qa',ROOT/'scripts/school-scan-image-qa.py')
scan=importlib.util.module_from_spec(spec);spec.loader.exec_module(scan)
qa=scan.qa
helper='ProgramFunctions/SchoolSafeScanDelegation.fnc.php'
qa.FILES=list(qa.FILES)+[helper]
qa.NEW_FILES=set(qa.NEW_FILES)|{helper}
qa.CONTRACTS=dict(qa.CONTRACTS,**{'school-scan-delegation-contracts.php':19})
# The immutable build base remains the protected dashboard. Production has since
# advanced to the qualified Scan image; keep an exact independent precondition.
original_prepare=qa.preparation_code
def preparation_code():
    code=original_prepare()
    needle="assert production()['schoolsafe-rosario']['image']==MANIFEST['base_image']"
    assert code.count(needle)==1
    return code.replace(needle,"assert production()['schoolsafe-rosario']['image']=='sha256:d4359b7b3b174f70ea02aef6202c98e41381814960dedcf2df576994dac10b9d'")
qa.preparation_code=preparation_code
if __name__=='__main__':qa.main()
