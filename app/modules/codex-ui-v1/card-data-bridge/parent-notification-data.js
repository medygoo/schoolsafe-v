(function (root) {
  'use strict';
  const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
  const types = ['student.arrival', 'student.pickup', 'student.fee_control'];
  function timestamp(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/.test(value)
      && Number.isFinite(Date.parse(value));
  }
  function normalizeNotifications(rows, allowedStudents) {
    if (!Array.isArray(rows) || rows.length > 100) throw new Error('INVALID_NOTIFICATIONS');
    const seen = new Set();
    return rows.map(row => {
      const payload = row?.payload;
      if (!row || !uuid.test(row.id || '') || !uuid.test(row.student_id || '')
          || (allowedStudents && !allowedStudents.has(row.student_id)) || seen.has(row.id)
          || !types.includes(row.event_type) || !timestamp(row.created_at)
          || typeof row.student_name !== 'string' || row.student_name.length > 250
          || !payload || typeof payload !== 'object' || Array.isArray(payload)
          || !timestamp(payload.occurredAt)) throw new Error('INVALID_NOTIFICATIONS');
      seen.add(row.id);
      const safe = { occurredAt: payload.occurredAt };
      if (row.event_type === 'student.fee_control') {
        if (['paid', 'partial', 'due', 'exempt', 'no_data'].includes(payload.balanceStatus)
            && payload.feeColor === undefined && payload.feeLabel === undefined) {
          safe.balanceStatus = payload.balanceStatus;
        } else if (payload.balanceStatus === undefined && ['blue', 'yellow', 'red'].includes(payload.feeColor)
            && typeof payload.feeLabel === 'string' && payload.feeLabel.trim() && payload.feeLabel.length <= 500) {
          safe.feeColor = payload.feeColor; safe.feeLabel = payload.feeLabel;
        } else throw new Error('INVALID_NOTIFICATIONS');
      }
      return { id: row.id, student_id: row.student_id, student_name: row.student_name,
        event_type: row.event_type, created_at: row.created_at, payload: safe };
    });
  }
  const api = { normalizeNotifications };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.SchoolSafeNotificationData = api;
})(typeof window === 'undefined' ? globalThis : window);
