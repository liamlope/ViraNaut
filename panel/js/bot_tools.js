(function () {
    function base() {
        var b = document.body && document.body.getAttribute('data-panel-base');
        if (b) return b;
        var p = window.location.pathname || '';
        return p.replace(/[^/]*$/, '');
    }

    function csrf() {
        var el = document.querySelector('[data-csrf]');
        return el ? el.getAttribute('data-csrf') : '';
    }

    function loadBar(on) {
        if (window.panelLoadBar && typeof window.panelLoadBar[on ? 'start' : 'done'] === 'function') {
            window.panelLoadBar[on ? 'start' : 'done']();
        }
    }

    function parseResponse(r) {
        return r.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                var snippet = (text || '').replace(/\s+/g, ' ').trim().slice(0, 120);
                return {
                    ok: false,
                    msg: 'پاسخ سرور نامعتبر (HTTP ' + r.status + ')' + (snippet ? ': ' + snippet : '')
                };
            }
        });
    }

    function post(action, data, opts) {
        opts = opts || {};
        if (!opts.silent) loadBar(true);
        var fd = new FormData();
        fd.append('_csrf', csrf());
        fd.append('action', action);
        Object.keys(data || {}).forEach(function (k) {
            fd.append(k, data[k]);
        });
        return fetch(base() + 'api/bot_tools.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(parseResponse)
            .finally(function () {
                if (!opts.silent) loadBar(false);
            });
    }

    function get(action, opts) {
        opts = opts || {};
        if (!opts.silent) loadBar(true);
        return fetch(base() + 'api/bot_tools.php?action=' + encodeURIComponent(action), { credentials: 'same-origin' })
            .then(parseResponse)
            .finally(function () {
                if (!opts.silent) loadBar(false);
            });
    }

    window.viraBotTools = { base: base, csrf: csrf, post: post, get: get, loadBar: loadBar };
}());
