#!/usr/bin/env python3
"""Isolated birthday image qualification over exact deployed toolbar image."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('image_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:0f002b209ec308325b1171ad6a921a396903aef2a091ab675b4a1f8126c107b0'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-18e0460059fd'
qa.BASE_COMMIT='a1c03ff2977d406781786b2abae9de93be3aae9b'
qa.FILES=['ProgramFunctions/SchoolSafeDashboardNative.fnc.php']
qa.NEW_FILES=set();qa.PATCH_SCOPE='school-birthdays'
if __name__=='__main__':qa.main()
