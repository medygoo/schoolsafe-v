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
if __name__=='__main__':qa.main()
