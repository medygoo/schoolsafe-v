#!/usr/bin/env python3
"""Qualify the five-file grading-setting relocation using the workspace QA engine."""
import importlib.util
from pathlib import Path
import sys
sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('workspace_image',ROOT/'scripts/prodeli-workspace-image-qa.py')
qa = importlib.util.module_from_spec(spec); spec.loader.exec_module(qa)
qa.BASE = 'sha256:59fb6b25e57ea4f53237ff36dbdc0ae1122fdf57be34a6ab4fe5b3bf6fc5bb63'
qa.BASE_TAG = 'schoolsafe-rosario:recovery-qa-682a8e878979'
qa.BASE_COMMIT = '9abe7b17f0e61316dd4c3015347e9604cc32e836'
qa.FILES = ['plugins/SchoolSafe_Keycloak/Wizard.php','modules/Grades/Menu.php',
            'modules/Grades/ReportCardGrades.php','modules/Grades/includes/SchoolScale.fnc.php','modules/School_Setup/Schools.php']
qa.NEW_FILES = {'modules/Grades/includes/SchoolScale.fnc.php'}
qa.CONTRACTS = {**qa.CONTRACTS,'prodeli-school-scale-contracts.php':22}
if __name__ == '__main__': qa.main()
