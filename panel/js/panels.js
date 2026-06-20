(function () {
    var P = window.MirzaInboundPicker;
    var base = document.body && document.body.getAttribute('data-panel-base') || '';

    window.PANEL_TYPE_LABELS = {
        marzban: 'Marzban',
        pasarguard: 'Pasarguard',
        mirza_agent: 'Mirza Agent',
        ilan: 'Ilan',
        marzneshin: 'Marzneshin',
        'x-ui_single': '3x-ui',
        'x-ui': '3x-ui',
        s_ui: 'S-UI',
        alireza_single: 'Alireza',
        alireza: 'Alireza',
        wgdashboard: 'WG',
        hiddify: 'Hiddify',
        ibsng: 'IBSng',
        mikrotik: 'Mikrotik',
        Manualsale: 'Manual'
    };

    window.panelTypeLabel = function (type) {
        return window.PANEL_TYPE_LABELS[type] || type || '—';
    };

    function apiUrl(action, query) {
        var q = query || '';
        return base + 'api/panels.php?action=' + encodeURIComponent(action) + (q ? '&' + q : '');
    }

    var editFormBound = false;

    window.openEditPanelModal = function (name) {
        openModal('editPanelModal');
        fetch(apiUrl('get', 'name=' + encodeURIComponent(name)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.panel) {
                    alert((data && data.msg) || 'خطا در دریافت پنل');
                    closeModal('editPanelModal');
                    return;
                }
                fillEditForm(data.panel);
            })
            .catch(function () {
                alert('خطا در ارتباط با سرور');
                closeModal('editPanelModal');
            });
    };

    function fillEditForm(p) {
        document.getElementById('edit_panel_name').value = p.name_panel || '';
        document.getElementById('edit_panel_name_ro').textContent = p.name_panel || '';
        document.getElementById('edit_url_panel').value = p.url_panel || '';
        document.getElementById('edit_linksubx').value = p.linksubx || '';
        document.getElementById('edit_username_panel').value = p.username_panel || '';
        document.getElementById('edit_password_panel').value = '';
        document.getElementById('edit_xui_api_token').value = p.xui_api_token || '';
        document.getElementById('edit_limit_panel').value = p.limit_panel || '0';
        document.getElementById('edit_agent').value = p.agent || 'all';
        var st = (p.status || '');
        document.getElementById('edit_status').value =
            (st === 'deactive' || st === 'disable' || st === 'inactive' || st === 'deactivepanel') ? 'inactive' : 'active';
        var ib = p.inbounds || '';
        if (ib === '' || ib === 'null') {
            ib = p.inboundid ? String(p.inboundid) : '';
        }
        document.getElementById('edit_panel_inbounds').value = ib;
        var xuiOnly = document.getElementById('edit_xui_fields');
        if (xuiOnly) {
            var isXui = ['x-ui_single', 'x-ui', 'alireza_single'].indexOf(p.type || '') >= 0;
            xuiOnly.style.display = isXui ? '' : 'none';
        }
        if (P) {
            P.loadPicker('edit_panel_inbound_picker', null, 'edit_panel_inbounds', ib, p.name_panel);
        }
        testPanelConnection(p.name_panel, 'edit_conn_status');
    }

    function bindEditForm() {
        if (editFormBound) {
            return;
        }
        editFormBound = true;
        var form = document.getElementById('editPanelForm');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (P) {
                P.syncHiddenInput(
                    document.getElementById('edit_panel_inbound_picker'),
                    document.getElementById('edit_panel_inbounds')
                );
            }
            var fd = new FormData(form);
            fd.append('action', 'update');
            fetch(base + 'api/panels.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        window.location.reload();
                    } else {
                        alert((data && data.msg) || 'خطا در ذخیره');
                    }
                })
                .catch(function () { alert('خطا در ذخیره'); });
        });
        var testBtn = document.getElementById('btnTestPanelConn');
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                testPanelConnection(document.getElementById('edit_panel_name').value, 'edit_conn_status');
            });
        }
    }

    window.testPanelConnection = function (name, badgeId) {
        var el = document.getElementById(badgeId || ('conn-' + name));
        if (el) {
            el.textContent = 'در حال تست…';
            el.className = 'tag';
        }
        fetch(apiUrl('test', 'name=' + encodeURIComponent(name)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!el) {
                    return;
                }
                if (data && data.skipped) {
                    el.textContent = '—';
                    el.className = 'tag tag-plain';
                    el.title = data.msg || '';
                    return;
                }
                if (data && data.online) {
                    el.textContent = 'آنلاین';
                    el.className = 'tag tag-ok';
                    el.title = data.msg || '';
                } else {
                    el.textContent = 'آفلاین';
                    el.className = 'tag tag-no';
                    el.title = (data && data.msg) || '';
                }
            })
            .catch(function () {
                if (el) {
                    el.textContent = 'خطا';
                    el.className = 'tag tag-no';
                }
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindEditForm();
        document.querySelectorAll('[data-panel-test]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                testPanelConnection(btn.getAttribute('data-panel-test'), 'conn-' + btn.getAttribute('data-panel-test'));
            });
        });
    });
}());
