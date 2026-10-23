'use strict';
// Optional test-only transport: psql through the existing loopback SSH forward.
// Never changes application SQL. Callers still validate Docker labels/network first.
const assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
module.exports=function fixtureSql(q){
 const cli=process.env.PRODELI_QA_PSQL;if(!cli)return null;
 assert.equal(process.env.PRODELI_QA_PG_PORT,'18103');
 assert.equal(process.env.PRODELI_QA_LABEL,'school-dashboard-stability-20261022');
 const nonce=/^schoolsafe-recovery-qa-db-([a-f0-9]{12})$/.exec(process.env.PRODELI_QA_DB||'')?.[1];assert(nonce,'Nonce-owned fixture DB required');
 assert.equal(process.env.PRODELI_QA_NETWORK,'schoolsafe-recovery-qa-'+nonce);
 assert.match(process.env.PGPASSWORD||'',/^[a-f0-9]{64}$/,'Ephemeral fixture password required');
 return execFileSync(cli,['-h','127.0.0.1','-p','18103','-X','-v','ON_ERROR_STOP=1','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt'],{input:q,encoding:'utf8',timeout:30000,env:{...process.env,PGCLIENTENCODING:'UTF8'}}).trim();
};
