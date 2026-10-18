'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const {classifyFee,decimalCents}=require('./fee-policy.cjs');
const evaluate=paid=>classifyFee({threshold:'150',tolerancePercent:10,paid,hasData:true});
test('exact thresholds and tolerance boundaries',()=>{
 assert.equal(evaluate('150').color,'blue');assert.equal(evaluate('135').color,'yellow');
 assert.equal(evaluate('134.99').color,'red');assert.equal(evaluate('0').color,'red');
});
test('absent and malformed data are not unpaid',()=>{
 assert.equal(classifyFee({threshold:'70',tolerancePercent:10,hasData:false}).color,'yellow');
 assert.equal(evaluate('-1').color,'yellow');assert.equal(evaluate('NaN').color,'yellow');
});
test('manual tolerance, cents precision and no financial disclosure',()=>{
 assert.equal(classifyFee({threshold:'70',tolerancePercent:20,paid:'56',hasData:true}).color,'yellow');
 assert.equal(decimalCents('1.01'),101n);
 assert.deepEqual(Object.keys(evaluate('150')).sort(),['color','label','reason']);
 assert.throws(()=>classifyFee({threshold:'0',tolerancePercent:10,paid:'0',hasData:true}));
 assert.throws(()=>classifyFee({threshold:'70',tolerancePercent:101,paid:'70',hasData:true}));
});
