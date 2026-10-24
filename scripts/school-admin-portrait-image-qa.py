#!/usr/bin/env python3
"""Exact-image qualification for the native principal portrait upload result check."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('discovery_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:e24ee9e177c3ae48ac6765efd36b1db40fef7ab2ecba653c41b50a8e8fdecd6c'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-98df5d2db024'
qa.BASE_COMMIT='c7c064ad62adc3e2ef9980081fc1a02d07e72285'
qa.FILES=['plugins/SchoolSafe_Keycloak/Wizard.php']
qa.NEW_FILES=set()
qa.PATCH_SCOPE='school-admin-portrait'
if __name__=='__main__':qa.main()
