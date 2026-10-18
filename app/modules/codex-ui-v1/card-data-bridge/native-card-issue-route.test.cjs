const test = require('node:test');
const assert = require('node:assert/strict');
const { registerNativeCardIssueRoute } = require('./native-card-issue-route.cjs');
test('issuance rejects browser origins and unsigned bodies before resolving identity', async () => {
  let handler;
  registerNativeCardIssueRoute({post(url, options, fn) { handler = fn; }}, {});
  for (const request of [{headers:{origin:'http://example.test'},query:{},body:{}},
    {headers:{},query:{},body:{student_id:'2'}}]) {
    let status;
    const reply = {header(){return this;},code(value){status=value;return this;},send(value){return value;}};
    const result = await handler(request, reply);
    assert.equal(status,400); assert.equal(result.error,'INVALID_REQUEST');
  }
});
