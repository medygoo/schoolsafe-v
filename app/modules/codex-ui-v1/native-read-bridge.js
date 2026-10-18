(function (root) {
  'use strict';
  function create(options) {
    options = options || {};
    if (!root.SchoolSafeCodexReads || typeof options.getSession !== 'function' || typeof options.authorizeRead !== 'function') {
      throw new TypeError('Session et controle de lecture requis.');
    }
    var base = new URL(options.apiBase || root.location.origin);
    if (!/^https?:$/.test(base.protocol) || base.username || base.password || base.search || base.hash || base.pathname !== '/') {
      throw new TypeError('Origine API invalide.');
    }
    if (base.origin !== root.location.origin && options.allowCrossOrigin !== true) {
      throw new TypeError('Origine API distincte non autorisee.');
    }
    var fetcher = options.fetch || root.fetch.bind(root);
    function sessionKey(session) {
      if (!session || session.demo || !session.identityKey || !session.schoolId) throw new Error('Session native requise.');
      return JSON.stringify([session.identityKey, session.schoolId, session.revision]);
    }
    return async function read(reader, params, context) {
      context = context || {};
      if (!Object.prototype.hasOwnProperty.call(root.SchoolSafeCodexReads.endpoints, reader)) throw new Error('Lecture inconnue.');
      var session = options.getSession(), key = sessionKey(session);
      if (await options.authorizeRead(reader, session, params || {}) !== true) throw new Error('Lecture non autorisee.');
      if (sessionKey(options.getSession()) !== key) throw new Error('Session modifiee.');
      var adapter = root.SchoolSafeCodexReads.create(async function (path, request) {
        var response = await fetcher(new URL(path, base).href, {
          method: 'GET', credentials: 'include', cache: 'no-store', redirect: 'error',
          headers: { Accept: 'application/json' }, signal: request.signal
        });
        if (sessionKey(options.getSession()) !== key) throw new Error('Session modifiee.');
        if (!response.ok) {
          var error = new Error(response.status === 401 ? 'Session expiree.' : response.status === 403 ? 'Acces refuse.' : 'Lecture serveur impossible.');
          error.status = response.status;
          throw error;
        }
        var data = await response.json();
        if (sessionKey(options.getSession()) !== key) throw new Error('Session modifiee.');
        if (await options.authorizeRead(reader, options.getSession(), params || {}) !== true) throw new Error('Lecture non autorisee.');
        if (sessionKey(options.getSession()) !== key) throw new Error('Session modifiee.');
        return data;
      });
      return adapter(reader, params || {}, context);
    };
  }
  root.SchoolSafeCodexNativeReadBridge = { create: create };
})(window);
