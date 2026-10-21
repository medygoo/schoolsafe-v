'use strict';
const { randomUUID } = require('node:crypto');
// Allowlist from scratch: never forward raw messages, request data, users, locals,
// URLs, breadcrumbs, attachments, cookies, QR values or arbitrary tags.
function sanitizeEvent(input, component) {
  if (!['php', 'node'].includes(component)) throw new Error('Unknown component');
  const candidate = input?.exception?.values?.[0]?.type;
  const type = ['Error', 'TypeError', 'RuntimeException'].includes(candidate) ? candidate : 'Error';
  return {
    event_id: randomUUID().replaceAll('-', ''),
    timestamp: new Date().toISOString(),
    platform: component === 'php' ? 'php' : 'node',
    level: 'error',
    environment: 'tooling-fixture',
    exception: { values: [{ type, value: 'Application error: sensitive details suppressed' }] },
    tags: { component },
  };
}
module.exports = { sanitizeEvent };
if (require.main === module) {
  let data = ''; process.stdin.setEncoding('utf8'); process.stdin.on('data', d => data += d);
  process.stdin.on('end', () => { const { event, component } = JSON.parse(data); console.log(JSON.stringify(sanitizeEvent(event, component))); });
}
