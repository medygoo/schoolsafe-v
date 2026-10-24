#!/usr/bin/env python3
"""Pure release guards, no Docker/SSH/database/provider operations."""
import copy,importlib.util,pathlib
root=pathlib.Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('scan_release',root/'ops/releases/school-presence-scan-20261024/deploy.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
passed=0
def check(ok,name):
 global passed
 assert ok,name
 passed+=1
m.signing_key=lambda:'b'*64
check(m.key_valid('b'*64) and not m.key_valid('0'*64) and not m.key_valid('invalid'),'Signing key cannot be zero or malformed')
pre={'Config':{'Env':['EXISTING=same'],'Cmd':['apache2-foreground'],'Entrypoint':['entrypoint'],'User':'root','Healthcheck':{'Test':['CMD','probe']}},'HostConfig':{'Binds':['same:ro'],'NetworkMode':'same','PortBindings':{'80/tcp':[{'HostPort':'18081'}]},'Memory':0}}
current=copy.deepcopy(pre);current['Config']['Env'].append(m.KEY+'='+'b'*64)
snapshot=copy.deepcopy(pre);m.unchanged_runtime(pre,current);check(pre==snapshot,'Guard does not mutate the captured original container')
for kind in ['existing-env','bind','ports','command','duplicate-key','wrong-key']:
 bad=copy.deepcopy(current)
 if kind=='existing-env':bad['Config']['Env'][0]='EXISTING=changed'
 elif kind=='bind':bad['HostConfig']['Binds']=['changed:rw']
 elif kind=='ports':bad['HostConfig']['PortBindings']={}
 elif kind=='command':bad['Config']['Cmd']=['changed']
 elif kind=='duplicate-key':bad['Config']['Env'].append(m.KEY+'='+'b'*64)
 else:bad['Config']['Env'][-1]=m.KEY+'='+'c'*64
 try:m.unchanged_runtime(pre,bad)
 except AssertionError:check(True,'Reject '+kind)
 else:raise AssertionError('Unsafe release allowed '+kind)
check(m.MIGRATIONS[-1]=='043-notify-dispatch-claims.sql' and len(m.MIGRATIONS)==14,'Native claims migration occurs after required expansions')
source=(root/'ops/releases/school-presence-scan-20261024/deploy.py').read_text()
check("no_legacy_dispatchers();cli=tools.db_cli()" in source,'Migration requires a checked legacy drain')
check("'new_worker_started':False" in source and "'provider_activated':False" in source,'Code deployment cannot masquerade as live provider activation')
check('pg_restore\',\'--list' in source and '--clean' not in source,'Backup archive is validated, never restored over production')
print({'status':'PASS','contracts':passed,'scope':'pure release guards only; not deployment'})
