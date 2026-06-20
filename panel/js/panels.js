(function () {
    var P = window.MirzaInboundPicker;
    var base = document.body && document.body.getAttribute('data-panel-base') || '';

    window.PANEL_TYPE_LABELS = {
        marzban: 'Marzban',
        pasarguard: 'Pasarguard',
        'x-ui_single': '3x-ui',
        'x-ui': '3x-ui',
        alireza_single: 'Alireza',
        hiddify: 'Hiddify'
    };

    window.panelTypeLabel = function (type, version) {
        if (type === 'marzban' && String(version) === '1') {
            return 'Pasarguard';
        }
        return window.PANEL_TYPE_LABELS[type] || type || '—';
    };

    function apiUrl(action, query) {
        var q = query || '';
        return base + 'api/panels.php?action=' + encodeURIComponent(action) + (q ? '&' + q : '');
    }

    function csrfInput() {
        var el = document.querySelector('[name="_csrf"]');
        return el ? el.value : '';
    }

    function isXuiType(type) {
        return ['x-ui_single', 'x-ui', 'alireza_single'].indexOf(type || '') >= 0;
    }

    function setConnBadge(el, data) {
        if (!el) return;
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
            el.textContent = 'آفلайن';
            el.className = 'tag tag-no';
            el.title = (data && data.msg) || '';
        }
    }

    window.testPanelConnection = function (name, badgeId) {
        var el = document.getElementById(badgeId || ('conn-' + name));
        if (el) {
            el.textContent = 'در حال تست…';
            el.className = 'tag';
        }
        return fetch(apiUrl('test', 'name=' + encodeURIComponent(name)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setConnBadge(el, data);
                return data;
            })
            .catch(function () {
                if (el) {
                    el.textContent = 'خطا';
                    el.className = 'tag tag-no';
                }
                return null;
            });
    };

    function pctMem(stats) {
        if (!stats || !stats.mem) return '—';
        var c = parseFloat(stats.mem.current || 0);
        var t = parseFloat(stats.mem.total || 0);
        if (t <= 0) return '—';
        return Math.round((c / t) * 1000) / 10;
    }

    window.openPanelHub = function (name) {
        openModal('panelHubModal');
        var body = document.getElementById('hubModalBody');
        if (body) body.innerHTML = '<p class="cf">در حال بارگذاری…</p>';
        fetch(apiUrl('dashboard', 'name=' + encodeURIComponent(name)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    if (body) body.innerHTML = '<p class="tag tag-no">' + ((data && data.msg) || 'خطا') + '</p>';
                    return;
                }
                renderHub(data, name);
            })
            .catch(function () {
                if (body) body.innerHTML = '<p class="tag tag-no">خطا در ارتباط</p>';
            });
    };

    function renderHub(data, name) {
        var title = document.getElementById('hubModalTitle');
        if (title) title.textContent = name;
        var body = document.getElementById('hubModalBody');
        if (!body) return;
        var p = data.panel || {};
        var online = !!data.online;
        var cpu = data.stats && data.stats.cpu != null ? Math.round(parseFloat(data.stats.cpu) * 10) / 10 : '—';
        var mem = pctMem(data.stats);
        var xray = (data.stats && data.stats.xray) ? (data.stats.xray.state || '—') : '—';
        var ib = (data.inbounds || []).join(', ') || 'انتخاب نشده';
        var panelUrl = p.url_panel || '';
        var subUrl = p.linksubx || '';
        var html = '';
        html += '<div class="hub-status-row">';
        html += '<span class="tag ' + (online ? 'tag-ok' : 'tag-no') + '">' + (online ? 'متصل' : 'قطع') + '</span>';
        html += '<span class="field-hint">احراز: ' + (data.auth_mode === 'token' ? 'توکن API' : 'نام کاربری/رمز') + '</span>';
        html += '</div>';
        if (online && data.stats) {
            html += '<div class="hub-bars">';
            html += '<div class="hub-bar-item"><span>CPU</span><div class="hub-bar"><div class="hub-bar-fill" style="width:' + Math.min(100, cpu) + '%"></div></div><span>' + cpu + '%</span></div>';
            html += '<div class="hub-bar-item"><span>RAM</span><div class="hub-bar"><div class="hub-bar-fill hub-bar-mem" style="width:' + Math.min(100, mem === '—' ? 0 : mem) + '%"></div></div><span>' + mem + '%</span></div>';
            html += '<div class="hub-meta">Xray: ' + xray + '</div></div>';
        } else if (data.msg) {
            html += '<p class="hub-err">' + data.msg + '</p>';
        }
        html += '<div class="hub-links">';
        if (panelUrl) {
            html += '<div class="hub-link-row"><span>آدرس پنل</span><a href="' + panelUrl + '" target="_blank" rel="noopener">' + panelUrl + '</a>';
            html += '<button type="button" class="btn btn-ghost btn-sm hub-copy" data-copy="' + panelUrl + '">کپی</button></div>';
        }
        if (subUrl) {
            html += '<div class="hub-link-row"><span>لینک ساب</span><a href="' + subUrl + '" target="_blank" rel="noopener">باز کردن</a>';
            html += '<button type="button" class="btn btn-ghost btn-sm hub-copy" data-copy="' + subUrl + '">کپی</button></div>';
        }
        html += '</div>';
        html += '<p class="field-hint">اینباندها: <code>' + ib + '</code></p>';
        html += '<div class="hub-actions">';
        html += '<button type="button" class="btn btn-ghost btn-sm" id="hubBtnTest">تست اتصال</button>';
        html += '<button type="button" class="btn btn-ghost btn-sm" id="hubBtnRefresh">بروزرسانی</button>';
        html += '<button type="button" class="btn btn-primary btn-sm" onclick="openEditPanelModal(' + JSON.stringify(name) + ')">ویرایش</button>';
        html += '</div>';
        body.innerHTML = html;
        body.querySelectorAll('.hub-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var t = btn.getAttribute('data-copy') || '';
                if (navigator.clipboard && t) {
                    navigator.clipboard.writeText(t).then(function () {
                        btn.textContent = '✓';
                        setTimeout(function () { btn.textContent = 'کپی'; }, 1500);
                    });
                }
            });
        });
        var tBtn = document.getElementById('hubBtnTest');
        if (tBtn) tBtn.addEventListener('click', function () {
            testPanelConnection(name, null).then(function () { openPanelHub(name); });
        });
        var rBtn = document.getElementById('hubBtnRefresh');
        if (rBtn) rBtn.addEventListener('click', function () { openPanelHub(name); });
    }

    var typeDefsCache = null;
    function loadTypeDefs() {
        if (typeDefsCache) return Promise.resolve(typeDefsCache);
        return fetch(apiUrl('types'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                typeDefsCache = (data && data.types) ? data.types : [];
                return typeDefsCache;
            });
    }

    function agentSelectHtml(name, val) {
        var v = val || 'all';
        return '<select name="' + name + '" class="select">' +
            '<option value="all"' + (v === 'all' ? ' selected' : '') + '>همه</option>' +
            '<option value="f"' + (v === 'f' ? ' selected' : '') + '>عادی</option>' +
            '<option value="n"' + (v === 'n' ? ' selected' : '') + '>نماینده</option>' +
            '<option value="n2"' + (v === 'n2' ? ' selected' : '') + '>نماینده پیشرفته</option>' +
            '</select>';
    }

    function sensitiveInputHtml(name, value, dir, req, placeholder) {
        var ph = placeholder ? (' placeholder="' + String(placeholder).replace(/"/g, '&quot;') + '"') : '';
        return '<div class="input-with-toggle">' +
            '<input type="password" name="' + name + '" class="input sensitive-input"' + dir + req + ph +
            ' value="' + String(value || '').replace(/"/g, '&quot;') + '">' +
            '<button type="button" class="btn btn-ghost btn-sm input-eye-toggle" data-eye-target="' + name + '" aria-label="نمایش/مخفی‌سازی">👁</button>' +
            '</div>';
    }

    function buildDynamicFields(typeDef, panel, idPrefix) {
        var prefix = idPrefix || 'add_dyn';
        var html = '<input type="hidden" name="panel_type" value="' + (typeDef.type || '') + '">';
        (typeDef.fields || []).forEach(function (f) {
            if (f.type === 'inbounds') {
                html += '<div class="field full"><label>' + f.label + ' *</label>';
                html += '<input type="hidden" name="panel_inbounds" id="' + prefix + '_inbounds" value="">';
                html += '<div id="' + prefix + '_inbound_picker" class="inbound-picker-box"></div></div>';
                return;
            }
            if (f.type === 'agent') {
                html += '<div class="field"><label>' + f.label + '</label>' + agentSelectHtml(f.key, panel && panel.agent) + '</div>';
                return;
            }
            var dir = f.dir ? ' dir="' + f.dir + '"' : '';
            var req = f.required ? ' required' : '';
            var val = panel ? (panel[f.key] || '') : (f.default || '');
            if (f.type === 'password' && panel) val = '';
            var inputType = f.type === 'password' ? 'password' : (f.type === 'number' ? 'number' : 'text');
            if (f.key === 'limit_panel') {
                inputType = 'text';
            }
            if (f.type === 'url') inputType = 'url';
            html += '<div class="field' + (f.key === 'name_panel' || f.key === 'url_panel' || f.key === 'linksubx' || f.key === 'xui_api_token' || f.key === 'secret_code' ? ' full' : '') + '">';
            html += '<label>' + f.label + (f.required ? ' *' : '') + '</label>';
            if (f.key === 'xui_api_token') {
                html += sensitiveInputHtml(f.key, val, dir, req, 'توکن API');
            } else if (f.type === 'password') {
                html += sensitiveInputHtml(f.key, val, dir, req, 'رمز پنل');
            } else {
                html += '<input type="' + inputType + '" name="' + f.key + '" class="input"' + dir + req + ' value="' + String(val).replace(/"/g, '&quot;') + '"'
                    + (f.key === 'limit_panel' ? ' placeholder="0 یا unlimited = نامحدود" inputmode="numeric"' : '') + '>';
            }
            html += '</div>';
        });
        return html;
    }

    function bindSensitiveToggles(scopeEl) {
        var root = scopeEl || document;
        root.querySelectorAll('.input-eye-toggle').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                var wrap = btn.closest('.input-with-toggle');
                var input = wrap && wrap.querySelector('.sensitive-input');
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? '🙈' : '👁';
            });
        });
    }

    window.openAddPanelWizard = function () {
        loadTypeDefs().then(function (types) {
            var sel = document.getElementById('addPanelTypeSelect');
            if (!sel) return;
            sel.innerHTML = types.map(function (t) {
                return '<option value="' + t.type + '">' + t.label + '</option>';
            }).join('');
            onAddTypeChange();
            openModal('addPanelModal');
        });
    };

    function onAddTypeChange() {
        var sel = document.getElementById('addPanelTypeSelect');
        var wrap = document.getElementById('addPanelDynamicFields');
        if (!sel || !wrap) return;
        var type = sel.value;
        loadTypeDefs().then(function (types) {
            var def = types.find(function (t) { return t.type === type; });
            if (!def) return;
            wrap.innerHTML = buildDynamicFields(def, null, 'add_dyn');
            bindSensitiveToggles(wrap);
            if (P && document.getElementById('add_dyn_inbound_picker')) {
                var nameInput = wrap.querySelector('[name="name_panel"]');
                var reload = function () {
                    var n = nameInput && nameInput.value.trim();
                    if (n) P.loadPicker('add_dyn_inbound_picker', null, 'add_dyn_inbounds', '', n);
                };
                if (nameInput) nameInput.addEventListener('blur', reload);
            }
        });
    }

    var editFormBound = false;

    window.openEditPanelModal = function (name) {
        closeModal('panelHubModal');
        openModal('editPanelModal');
        fetch(apiUrl('get', 'name=' + encodeURIComponent(name)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.panel) {
                    alert((data && data.msg) || 'خطا');
                    closeModal('editPanelModal');
                    return;
                }
                fillEditForm(data.panel);
            });
    };

    function panelEffectiveType(p) {
        if (p.type === 'marzban' && String(p.version_panel) === '1') {
            return 'pasarguard';
        }
        var aliases = {
            'x-ui': 'x-ui_single',
            '3x-ui': 'x-ui_single',
            '3xui': 'x-ui_single',
            'x_ui_single': 'x-ui_single'
        };
        return aliases[p.type] || p.type;
    }

    function fillEditForm(p) {
        document.getElementById('edit_panel_name').value = p.name_panel || '';
        document.getElementById('edit_panel_name_ro').textContent = p.name_panel || '';
        var stEl = document.getElementById('edit_status');
        if (stEl) {
            var st = p.status || '';
            stEl.value = (st === 'deactive' || st === 'disable' || st === 'inactive' || st === 'deactivepanel') ? 'inactive' : 'active';
        }
        var wrap = document.getElementById('editDynamicFields');
        var effType = panelEffectiveType(p);
        loadTypeDefs().then(function (types) {
            var def = types.find(function (t) { return t.type === effType || t.type === p.type; });
            if (!def || !wrap) return;
            var clone = JSON.parse(JSON.stringify(def));
            clone.fields = (clone.fields || []).filter(function (f) { return f.key !== 'name_panel'; });
            wrap.innerHTML = buildDynamicFields(clone, p, 'edit_dyn');
            bindSensitiveToggles(wrap);
            if (P) {
                var ib = p.inbounds || '';
                if (ib === '' || ib === 'null') ib = p.inboundid ? String(p.inboundid) : '';
                P.loadPicker('edit_dyn_inbound_picker', null, 'edit_dyn_inbounds', ib, p.name_panel);
                var editUrlInput = wrap.querySelector('[name="url_panel"]');
                var editTokenInput = wrap.querySelector('[name="xui_api_token"]');
                var reloadProbe = function () {
                    var u = editUrlInput && editUrlInput.value ? editUrlInput.value.trim() : '';
                    var t = editTokenInput && editTokenInput.value ? editTokenInput.value.trim() : '';
                    if (!u || !t) return;
                    P.loadPicker('edit_dyn_inbound_picker', null, 'edit_dyn_inbounds', (document.getElementById('edit_dyn_inbounds') || {}).value || '', p.name_panel, {
                        required: true,
                        probe: { url_panel: u, xui_api_token: t }
                    });
                };
                if (editUrlInput) editUrlInput.addEventListener('blur', reloadProbe);
                if (editTokenInput) editTokenInput.addEventListener('blur', reloadProbe);
            }
        });
        testPanelConnection(p.name_panel, 'edit_conn_status');
    }

    function bindEditForm() {
        if (editFormBound) return;
        editFormBound = true;
        var addTypeSel = document.getElementById('addPanelTypeSelect');
        if (addTypeSel) addTypeSel.addEventListener('change', onAddTypeChange);
        var addForm = document.getElementById('addPanelForm');
        if (addForm) {
            addForm.addEventListener('submit', function (ev) {
                ev.preventDefault();
                if (P && document.getElementById('add_dyn_inbound_picker')) {
                    P.syncHiddenInput(document.getElementById('add_dyn_inbound_picker'), document.getElementById('add_dyn_inbounds'), false);
                }
                var fd = new FormData(addForm);
                fd.append('action', 'add');
                fetch(base + 'api/panels.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) location.reload();
                        else alert((data && data.msg) || 'خطا');
                    });
            });
        }
        var form = document.getElementById('editPanelForm');
        if (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                if (P && document.getElementById('edit_dyn_inbound_picker')) {
                    var ep = document.getElementById('edit_dyn_inbound_picker');
                    var eh = document.getElementById('edit_dyn_inbounds');
                    if (ep.querySelector('.inbound-id-cb')) {
                        if (!P.validateBeforeSubmit('edit_dyn_inbound_picker', 'edit_dyn_inbounds', true)) {
                            return;
                        }
                        P.syncHiddenInput(ep, eh, false);
                    }
                }
                var fd = new FormData(form);
                fd.append('action', 'update');
                fetch(base + 'api/panels.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) location.reload();
                        else alert((data && data.msg) || 'خطا');
                    });
            });
        }
        var testBtn = document.getElementById('btnTestPanelConn');
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                testPanelConnection(document.getElementById('edit_panel_name').value, 'edit_conn_status');
            });
        }
        var delForm = document.getElementById('deletePanelForm');
        if (delForm) {
            delForm.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var fd = new FormData(delForm);
                fd.append('action', 'delete');
                fetch(base + 'api/panels.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) location.reload();
                        else alert((data && data.msg) || 'خطا');
                    });
            });
        }
    }

    window.openDeletePanelModal = function (name) {
        document.getElementById('delete_panel_name').value = name;
        document.getElementById('delete_panel_label').textContent = name;
        document.getElementById('delete_confirm_name').value = '';
        openModal('deletePanelModal');
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
