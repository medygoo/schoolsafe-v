'use strict';
// Compare the complete PL/pgSQL body, ignoring whitespace only, before invoking
// the existing privileged function. No SELECT grant on the activation table.
const safeCollectorBody = `begin
  if current_database() <> 'schoolsafe_cards_isolated' then
    raise exception 'ISOLATED_DATABASE_REQUIRED';
  end if;
  if (select count(*) from card_bridge_notifications.binding_activation
      where singleton and activated_at <= clock_timestamp()) <> 1 then
    raise exception 'NOTIFICATION_BINDING_INACTIVE';
  end if;
  return 0;
end `;
function startNotificationCollector(pool, { intervalMs = 15000, onState = () => {} } = {}) {
  if (!Number.isSafeInteger(intervalMs) || intervalMs < 1) throw new Error('INVALID_INTERVAL');
  let pending = null;
  let stopped = false;
  function tick() {
    if (stopped || pending) return pending;
    pending = (async () => {
      try {
        // Capture is transactional. Never invoke the legacy late-binding collector.
        const schema = await pool.query(`select
          to_regclass('card_bridge_notifications.binding_activation') is not null as binding_table,
          exists(select 1 from pg_proc where oid=to_regprocedure('card_bridge_notifications.collect()')
            and regexp_replace(prosrc,'[[:space:]]','','g')=regexp_replace($1,'[[:space:]]','','g')) as safe_collector,
          (select count(*)::integer from pg_trigger where not tgisinternal
            and tgname='aa_notification_bind_recipient' and tgenabled='O' and tgtype=7
            and tgfoid=to_regprocedure('card_bridge_notifications.bind_event_recipient()')
            and tgrelid in ('app.security_events'::regclass,'app.fee_control_scans'::regclass,
              'audit.events'::regclass)) as binding_triggers`, [safeCollectorBody]);
        if (schema.rows[0]?.binding_table !== true || schema.rows[0]?.binding_triggers !== 3
            || schema.rows[0]?.safe_collector !== true) {
          onState({ status: 'awaiting_binding_review', collected: 0 });
          return;
        }
        const activation = await pool.query('select card_bridge_notifications.collect() as collected');
        if (activation.rows[0]?.collected !== 0) throw new Error('INVALID_BINDING_STATE');
        onState({ status: 'ready', collected: 0 });
      } catch (_) { onState({ status: 'unavailable' }); }
      finally { pending = null; }
    })();
    return pending;
  }
  const timer = setInterval(tick, intervalMs);
  timer.unref();
  tick();
  return async () => { stopped = true; clearInterval(timer); if (pending) await pending; };
}
module.exports = { startNotificationCollector, safeCollectorBody };
