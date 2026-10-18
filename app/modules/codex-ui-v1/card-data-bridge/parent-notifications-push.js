(function (root) {
  'use strict';
  // Foreground transport is opt-in only; the view never enables it automatically.
  function createBrowserTransport(browser) {
    if (browser?.isSecureContext !== true || typeof browser.Notification !== 'function'
        || browser.Notification.permission !== 'granted') throw new Error('NATIVE_PUSH_UNAVAILABLE');
    return message => {
      if (browser.Notification.permission !== 'granted') throw new Error('NATIVE_PUSH_UNAVAILABLE');
      const notice = new browser.Notification('SchoolSafe', { body: 'Une nouvelle notification est disponible.',
        tag: 'schoolsafe:' + message.id });
      notice.onclick = () => { browser.focus(); browser.location.assign('/parent-notifications-view.php'); notice.close(); };
    };
  }
  function createNotificationPush({ deliver, enabled = false } = {}) {
    let primed = false;
    const seen = new Set();
    function observe(rows) {
      const normalize = typeof module !== 'undefined' && module.exports
        ? require('./parent-notification-data.js').normalizeNotifications
        : root.SchoolSafeNotificationData.normalizeNotifications;
      const items = normalize(rows);
      const fresh = primed ? items.filter(item => !seen.has(item.id)) : [];
      if (!primed || enabled !== true || typeof deliver !== 'function') {
        for (const item of items) seen.add(item.id);
      }
      primed = true;
      if (enabled !== true || typeof deliver !== 'function') {
        while (seen.size > 1000) seen.delete(seen.values().next().value);
        return { delivered: 0, pending: fresh.length };
      }
      let delivered = 0;
      for (const item of fresh) {
        try {
          deliver({ id: item.id, type: item.event_type, title: 'SchoolSafe',
            body: 'Une nouvelle notification est disponible.', url: '/parent-notifications-view.php' });
          seen.add(item.id);
          delivered++;
        } catch (_) { /* No child payload in transport diagnostics. */ }
      }
      while (seen.size > 1000) seen.delete(seen.values().next().value);
      return { delivered, pending: fresh.length - delivered };
    }
    return { observe, reset() { primed = false; seen.clear(); } };
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = { createNotificationPush, createBrowserTransport };
  else root.SchoolSafeNotificationPush = { createNotificationPush, createBrowserTransport };
})(typeof window === 'undefined' ? globalThis : window);
