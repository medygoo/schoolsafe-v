'use strict';
const { withNativeScanContext } = require('./native-scan-context.cjs');
const { normalizeNotifications } = require('./parent-notification-data.js');
function registerNativeNotificationRoute(app, dependencies) {
  app.post('/bridge/scanner/notifications', { bodyLimit: 20000 }, async (request, reply) => {
    reply.header('Cache-Control', 'no-store');
    if (request.headers.origin || Object.keys(request.query || {}).length) return reply.code(400).send({ error: 'INVALID_REQUEST' });
    try {
      const data = await withNativeScanContext(request.body, dependencies, async ({ client, claim }) => {
        const ids = claim.input.nativeStudentIds;
        if (claim.operation !== 'notifications' || Object.keys(claim.input).join(',') !== 'nativeStudentIds'
            || !Array.isArray(ids) || ids.length > 500 || new Set(ids).size !== ids.length
            || ids.some(id => typeof id !== 'string' || !/^[1-9][0-9]{0,18}$/.test(id))) throw new Error('INVALID_INPUT');
        const mapped = await dependencies.mappingPool.query(`select native_entity_id::text, target_entity_id from card_bridge.entity_map
          where source_instance=$1 and native_school_id=$2 and entity_type='student'
          and native_entity_id=any($3::bigint[])`, [claim.sourceInstance, claim.nativeSchoolId, ids]);
        const scope = await client.query('select card_bridge_notifications.principal_scope($1::uuid[]) as ids',
          [mapped.rows.map(row => row.target_entity_id)]);
        if (!Array.isArray(scope.rows[0]?.ids)) throw new Error('PRINCIPAL_SCOPE_UNAVAILABLE');
        const principalIds = new Set(scope.rows[0].ids);
        const allowedTargets = mapped.rows.filter(row => principalIds.has(row.target_entity_id)).map(row => row.target_entity_id);
        const result = allowedTargets.length
          ? await client.query('select card_bridge_notifications.read_own($1::uuid[]) as notifications', [allowedTargets])
          : {rows:[{notifications:[]}]};
        return { notifications: normalizeNotifications(result.rows[0]?.notifications, new Set(allowedTargets)),
          principalStudentIds: mapped.rows.filter(row => principalIds.has(row.target_entity_id)).map(row => row.native_entity_id) };
      });
      return { data };
    } catch (error) {
      const status = ['INVALID_SIGNATURE','REPLAY_REJECTED'].includes(error.message) ? 401
        : error.code === '42501' || ['NATIVE_MAPPING_REQUIRED','SCHOOL_ACCESS_DENIED'].includes(error.message) ? 403
        : ['INVALID_INPUT','INVALID_SCAN_CLAIM','INVALID_SCAN_ENVELOPE'].includes(error.message) ? 400 : 503;
      return reply.code(status).send({ error: 'NOTIFICATIONS_UNAVAILABLE' });
    }
  });
}
module.exports = { registerNativeNotificationRoute };
