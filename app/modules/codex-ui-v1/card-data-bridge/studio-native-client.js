(function (root) {
  'use strict';
  async function loadNativeStudioCard(nativeStudentId, options) {
    const id = String(nativeStudentId);
    if (!/^[1-9][0-9]{0,18}$/.test(id)) throw new Error('INVALID_NATIVE_STUDENT_ID');
    const response = await fetch('/card-bridge-prepare.php?resource=studio-input&student_id=' + encodeURIComponent(id),
      { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: options?.signal });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'CARD_BRIDGE_UNAVAILABLE');
    const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
    if (!result.data || result.data.issuanceAllowed !== false
        || result.data.nativeReference?.studentId !== id
        || !uuid.test(result.data.targetReference?.studentId || '')
        || !uuid.test(result.data.targetReference?.schoolId || '')
        || result.data.input?.record?.school_id !== result.data.targetReference?.schoolId
        || result.data.input?.record?.id !== result.data.targetReference?.studentId) {
      throw new Error('STUDIO_BRIDGE_RESULT_INVALID');
    }
    if (!root.SchoolSafeCardAdapter?.adaptRosarioCard) throw new Error('STUDIO_ADAPTER_REQUIRED');
    return { ...result.data, view: root.SchoolSafeCardAdapter.adaptRosarioCard(result.data.input) };
  }
  root.SchoolSafeStudioNativeBridge = { loadNativeStudioCard };
})(typeof window === 'undefined' ? globalThis : window);
