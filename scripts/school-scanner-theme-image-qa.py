#!/usr/bin/env python3
"""Scanner layout/theme only, layered on the deployed state-safety image."""
import importlib.util
from pathlib import Path
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('discovery_qa',root/'scripts/prodeli-access-discovery-image-qa.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
qa=module.qa
qa.BASE='sha256:1705fc9453c0788534273c5ba6a96bd799f15574ade8e02c7ff55351a8d72a3c'
qa.BASE_TAG='schoolsafe-rosario:recovery-qa-7b22f0a09547'
qa.BASE_COMMIT='335643ef61cc67ea56d43ba51181e72cd450d1f1'
qa.FILES=['scanner-native.php','schoolsafe-card-bridge/scanner-native.css','schoolsafe-card-bridge/scanner-native.js','schoolsafe-card-bridge/scanner-theme.js']
qa.NEW_FILES={'schoolsafe-card-bridge/scanner-theme.js'}
qa.PATCH_SCOPE='school-scanner-dashboard-theme'
if __name__=='__main__':qa.main()
