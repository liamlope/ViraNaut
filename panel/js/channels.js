(function () {
    var root = document.querySelector('[data-csrf]');
    if (!root || !window.mirzaBotTools) return;
    function load() {
        window.mirzaBotTools.get('channels_list').then(function (d) {
            var el = document.getElementById('channelsList');
            if (!d.ok) { el.textContent = d.msg; return; }
            if (!d.items.length) { el.innerHTML = '<p class="cf">کانالی ثبت نشده</p>'; return; }
            el.innerHTML = '<div class="tbl-wrap"><table class="tbl-md"><thead><tr><th>نام</th><th>لینک</th><th></th></tr></thead><tbody>' +
                d.items.map(function (c) {
                    return '<tr><td>' + (c.remark || '') + '</td><td class="cm" dir="ltr">' + (c.link || '') +
                        '</td><td><button type="button" class="btn btn-no btn-sm" data-del="' + encodeURIComponent(c.link) + '">حذف</button></td></tr>';
                }).join('') + '</tbody></table></div>';
            el.querySelectorAll('[data-del]').forEach(function (btn) {
                btn.onclick = function () {
                    if (!confirm('حذف شود؟')) return;
                    window.mirzaBotTools.post('channel_delete', { link: decodeURIComponent(btn.getAttribute('data-del')) }).then(function (r) {
                        if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
                        if (r.ok) load();
                    });
                };
            });
        });
    }
    document.getElementById('channelForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(e.target);
        window.mirzaBotTools.post('channel_add', {
            remark: fd.get('remark'),
            link: fd.get('link'),
            linkjoin: fd.get('linkjoin'),
        }).then(function (r) {
            if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
            if (r.ok) { e.target.reset(); load(); }
        });
    });
    load();
}());
