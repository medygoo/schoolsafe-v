'use strict';
const assert=require('node:assert/strict'),cp=require('node:child_process');let called=0,last;
cp.execFileSync=(file,args,options)=>{called++;last={file,args,options};return '\n';};
const sql=require('../app/qa-fixture-sql.cjs');
const keys=['PRODELI_QA_PSQL','PRODELI_QA_PG_PORT','PRODELI_QA_LABEL','PRODELI_QA_DB','PRODELI_QA_NETWORK','PGPASSWORD'];for(const k of keys)delete process.env[k];
assert.equal(sql('SELECT 1'),null);assert.equal(called,0);
const safe={PRODELI_QA_PSQL:'fictitious-psql',PRODELI_QA_PG_PORT:'18103',PRODELI_QA_LABEL:'school-dashboard-stability-20261022',PRODELI_QA_DB:'schoolsafe-recovery-qa-db-012345abcdef',PRODELI_QA_NETWORK:'schoolsafe-recovery-qa-012345abcdef',PGPASSWORD:'0'.repeat(64)};
for(const [key,value] of [['PRODELI_QA_PG_PORT','5432'],['PRODELI_QA_LABEL','production'],['PRODELI_QA_DB','schoolsafe-rosario-db'],['PRODELI_QA_NETWORK','production'],['PGPASSWORD','']]){
 Object.assign(process.env,safe,{[key]:value});assert.throws(()=>sql('SELECT 1'));assert.equal(called,0);
}
Object.assign(process.env,safe);const statement='BEGIN;\nSELECT \'École fictive\';\nCOMMIT;';assert.equal(sql(statement),'');assert.equal(called,1);assert.equal(last.options.input,statement);assert(last.args.includes('127.0.0.1'));assert(last.args.includes('schoolcef_fixture'));assert(!last.args.includes(safe.PGPASSWORD));assert.equal(last.options.env.PGCLIENTENCODING,'UTF8');
let fallback=0;const result=sql('SELECT 1')??(++fallback);assert.equal(result,'');assert.equal(fallback,0);
console.log('8 fixture SQL transport gates PASS (mocked process, no network or credentials)');
