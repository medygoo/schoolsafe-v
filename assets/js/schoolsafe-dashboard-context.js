(function () {
    function connect() {
        document.querySelectorAll('[data-native-dashboard-context]').forEach(function (slot) {
            var key = slot.getAttribute('data-native-dashboard-context');
            var nativeSelect = document.querySelector('#menu select[name="' + key + '"]');
            if (!nativeSelect || slot.querySelector('select')) return;
            var select = nativeSelect.cloneNode(true);
            select.removeAttribute('name');
            select.removeAttribute('onchange');
            select.id = 'dashboard-context-' + key;
            select.className = 'ss-dashboard-context-select';
            select.setAttribute('aria-label', key === 'school' ? 'École sélectionnée' : 'Année scolaire');
            select.disabled = nativeSelect.disabled || select.options.length < 2;
            select.value = nativeSelect.value;
            select.addEventListener('change', function () {
                nativeSelect.value = select.value;
                nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            });
            slot.replaceChildren(select);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', connect);
    else connect();
    if (!window.schoolSafeDashboardDailyRefresh) {
        var loadedDay = new Date().toDateString();
        function refreshDaily() {
            if (document.hidden || !document.querySelector('.ss-connected-dashboard')) return;
            if (new Date().toDateString() === loadedDay) return;
            var focused = document.activeElement;
            if (focused && focused.matches('input,select,textarea,[contenteditable="true"]')) return;
            var edited = Array.from(document.querySelectorAll('#body input,#body textarea')).some(function (input) {
                return input.value !== input.defaultValue;
            });
            if (!edited) window.location.reload();
        }
        window.schoolSafeDashboardDailyRefresh = window.setInterval(refreshDaily, 60000);
        document.addEventListener('visibilitychange', refreshDaily);
    }
})();
