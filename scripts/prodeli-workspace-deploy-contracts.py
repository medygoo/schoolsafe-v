#!/usr/bin/env python3
"""Offline qualification gates: fabricated proofs, no SSH, Docker or production."""
import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('workspace_deploy',ROOT/'ops/releases/prodeli-workspace-20261022/deploy.py')
d = importlib.util.module_from_spec(spec); spec.loader.exec_module(d)

class Gates(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(); self.addCleanup(self.tmp.cleanup)
        d.ROOT = Path(self.tmp.name)
        self.content = {d.PREFIX+f:('synthetic '+f).encode() for f in d.FILES}
        files = {f:hashlib.sha256(self.content[d.PREFIX+f]).hexdigest() for f in d.FILES}
        self.release = {'source_commit':'a'*40,'commit':'a'*40,'image':'sha256:'+'b'*64,'source_digest':'c'*64,'files':files}
        self.proofs = {
            'source-manifest.json':{'source_commit':'a'*40,'base_image':d.BASE,'payload_hashes':files,'source_digest':'c'*64},
            'image-prepare-proof.json':{'status':'READY','image':self.release['image'],'source_digest':'c'*64,
                'changed_files':list(d.FILES),'unchanged_files':2746,'renderer_contracts':123,'recovery_contracts':37,
                'read_only_web':True,'internal_network':True,'published_ports':False},
            'exact-integration-result.json':{'status':'PASS','image':self.release['image'],
                'suites':[{'suite':x,'exit':0} for x in ['workspace','native','recovery','concurrency']]},
            'workspace-result.json':{'status':'PASS','cleanup':True,'results':['passed']*14},
            'native-result.json':{'status':'PASS','cleanup':True,'results':['passed']*13},
            'image-verify-proof.json':{'status':'PASS','image':self.release['image'],'fixture_snapshot_unchanged':True,'production_containers_unchanged':True},
            'image-cleanup-proof.json':{'status':'REMOVED','only_owned_ephemeral_resources':True}}
        for local,path in [('deploy.py','ops/releases/prodeli-workspace-20261022/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
            data=('canonical '+path).encode(); (d.ROOT/local).write_bytes(data); self.content[path]=data
        d.load=lambda name:copy.deepcopy(self.proofs[name])
        d.run=lambda args: b'' if 'diff' in args else self.content[args[-1].split(':',1)[1]]
        d.inspect=lambda _: {'Config':{'Labels':{'org.opencontainers.image.revision':'a'*40,'schoolsafe.source.digest':'c'*64}}}
    def test_valid(self): d.qualification(self.release)
    def test_rejects_bad_proofs(self):
        cases=[('image-prepare-proof.json','read_only_web',False),('image-prepare-proof.json','internal_network',False),
          ('image-prepare-proof.json','published_ports',True),('image-prepare-proof.json','unchanged_files',2745),
          ('image-prepare-proof.json','renderer_contracts',122),('image-prepare-proof.json','recovery_contracts',36),
          ('image-prepare-proof.json','image','sha256:'+'d'*64),('native-result.json','results',['passed']*12),
          ('workspace-result.json','cleanup',False),('image-verify-proof.json','fixture_snapshot_unchanged',False),
          ('image-verify-proof.json','production_containers_unchanged',False),('image-cleanup-proof.json','status','PENDING')]
        for filename,key,value in cases:
            with self.subTest(filename=filename,key=key):
                old=self.proofs[filename][key];self.proofs[filename][key]=value
                with self.assertRaises(AssertionError): d.qualification(self.release)
                self.proofs[filename][key]=old
    def test_rejects_missing_or_failed_suite(self):
        self.proofs['exact-integration-result.json']['suites'][0]['exit']=1
        with self.assertRaises(AssertionError): d.qualification(self.release)
        self.proofs['exact-integration-result.json']['suites'].pop()
        with self.assertRaises(AssertionError): d.qualification(self.release)
    def test_rejects_changed_recovery(self):
        original=d.run;d.run=lambda args:b'drift' if 'diff' in args else original(args)
        with self.assertRaises(AssertionError): d.qualification(self.release)
    def test_rejects_payload_drift(self):
        self.content[d.PREFIX+'index.php']=b'drift'
        with self.assertRaises(AssertionError): d.qualification(self.release)

if __name__ == '__main__': unittest.main()
