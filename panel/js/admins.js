(function () {
    if (!window.viraBotTools) return;
    function load() {
        window.viraBotTools.get('admins_list').then(function (d) {
            var el = document.getElementById('adminsList');
            if (!d.ok) { el.textContent = d.msg; return; }
            el.innerHTML = '<table class="tbl-md"><thead><tr><th>آیدی</th><th>کاربری</th><th>سطح</th><th></th></tr></thead><tbody>' +
                d.items.map(function (a) {
                    return '<tr><td class="cm">' + a.id_admin + '</td><td>' + a.username + '</td><td>' + a.rule +
                        '</td><td><button class="btn btn-no btn-sm" data-id="' + a.id_admin + '">حذف</button></td></tr>';
                }).join('') + '</tbody></table>';
            el.querySelectorAll('[data-id]').forEach(function (b) {
                b.onclick = function () {
                    if (!confirm('حذف ادمین؟')) return;
                    window.viraBotTools.post('admin_delete', { id_admin: b.getAttribute('data-id') }).then(function (r) {
                        if (r.ok) load();
                        if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
                    });
                };
            });
        });
    }
    document.getElementById('adminForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(e.target);
        window.viraBotTools.post('admin_add', {
            id_admin: fd.get('id_admin'),
            username: fd.get('username'),
            password: fd.get('password'),
            rule: fd.get('rule'),
        }).then(function (r) {
            if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
            if (r.ok) { e.target.reset(); load(); }
        });
    });
    load();
}());
