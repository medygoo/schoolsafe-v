'use strict';

// Install after all presentation wrappers, before the first connected render.
// This is a presentation guard, never an authorization or signature verifier.
function installOfficialQrGuard(studio) {
  if (typeof studio.ssGenQR === 'function') {
    const generate = studio.ssGenQR;
    studio.ssGenQR = function (elementId, data, ...args) {
      if (!/^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(data || '')) return;
      return generate.call(this, elementId, data, ...args);
    };
  }
  for (const name of ['ssBuildBadge', 'ssBuildCarte']) {
    if (typeof studio[name] !== 'function') throw new Error('STUDIO_GENERATOR_REQUIRED');
  }
  for (const name of ['ssBuildBadge', 'ssBuildCarte']) {
    const build = studio[name];
    studio[name] = function (student, ...args) {
      const result = build.call(this, student, ...args);
      const qr = student?.official_qr || '';
      return { ...result, qr: /^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(qr) ? qr : '' };
    };
  }
}

if (typeof module !== 'undefined' && module.exports) module.exports = { installOfficialQrGuard };
else window.installOfficialQrGuard = installOfficialQrGuard;
