(function (root) {
    'use strict';
    function compose(title, content) {
        if (typeof title !== 'string' || typeof content !== 'string'
            || title.length > 200 || content.length > 20000) throw new Error('INVALID_DRAFT');
        var heading = title.trim();
        var body = content.replace(/\r\n?/g, '\n').trim();
        return {title: heading, content: body, text: [heading, body].filter(Boolean).join('\n\n')};
    }
    var api = {compose: compose};
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.SchoolSafeJaspeDraft = api;
})(typeof window === 'object' ? window : globalThis);
