(function (root) {
  'use strict';
  var endpoints = {
    teacherBoard: { path: '/native/scan-control/board/teacher' },
    schoolBoard: { path: '/native/scan-control/board/school' },
    onSiteStudent: { path: '/native/scan-control/on-site', parameter: 'student_id' },
    onSiteClass: { path: '/native/scan-control/on-site', parameter: 'class_id' },
    onSiteSchool: { path: '/native/scan-control/on-site' },
    scanAudit: { path: '/native/scan-control/audit', parameter: 'student_id' }
  };
  function createReadAdapter(request) {
    if (typeof request !== 'function') throw new TypeError('Transport authentifie requis');
    return async function (reader, params, context) {
      var endpoint = endpoints[reader];
      if (!endpoint) throw new Error('Lecture inconnue');
      var url = endpoint.path;
      if (endpoint.parameter) {
        var value = params && params[endpoint.parameter];
        if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value || '')) throw new Error('Selectionnez un dossier autorise.');
        url += '?' + new URLSearchParams([[endpoint.parameter, value]]).toString();
      }
      var response = await request(url, { method: 'GET', signal: context.signal });
      if (!response || !Object.prototype.hasOwnProperty.call(response, 'data')) throw new Error('Reponse serveur invalide.');
      return response.data;
    };
  }
  root.SchoolSafeCodexReads = { endpoints: endpoints, create: createReadAdapter };
})(window);
