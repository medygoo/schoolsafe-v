'use strict';
const assert = require('node:assert/strict');
const { sanitizeEvent } = require('./telemetry-filter.cjs');
const marker = 'SYNTHETIC_PRIVATE_MARKER_DO_NOT_SEND';
const input = {
  message: marker,
  user: { email: marker },
  request: { url: marker, cookies: marker, headers: { authorization: marker }, data: marker },
  extra: { token: marker, qr: marker, student: marker },
  breadcrumbs: [{ message: marker }],
  exception: { values: [{ type: 'TypeError', value: marker, stacktrace: { frames: [{ vars: { password: marker }, filename: marker }] } }] },
  tags: { student: marker },
};
function assertNoPrivateMarker(event) { assert.ok(!JSON.stringify(event).includes(marker)); }
assert.throws(() => assertNoPrivateMarker(input), assert.AssertionError, 'Unsanitized input must fail the privacy assertion');
for (const component of ['php', 'node']) {
  const clean = sanitizeEvent(input, component);
  assertNoPrivateMarker(clean);
  assert.deepEqual(Object.keys(clean).sort(), ['environment','event_id','exception','level','platform','tags','timestamp']);
  assert.deepEqual(clean.tags, { component });
  assert.match(clean.event_id, /^[0-9a-f]{32}$/);
  assert.equal(clean.exception.values[0].type, 'TypeError');
  assert.equal(sanitizeEvent({ exception: { values: [{ type: marker }] } }, component).exception.values[0].type, 'Error');
}
assert.throws(() => sanitizeEvent(input, marker), /Unknown component/);
console.log('Telemetry allowlist PASS: both platform tags, poisoned metadata rejection, negative privacy assertion. No production SDK integration claimed.');
