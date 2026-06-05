(function (global) {
    function parseInboundValue(raw) {
        if (!raw || raw === 'null') {
            return { useDefault: true, ids: [] };
        }
        var s = String(raw).trim();
        if (s === '' || s === '-') {
            return { useDefault: true, ids: [] };
        }
        try {
            if (s[0] === '[') {
                var arr = JSON.parse(s);
                if (Array.isArray(arr)) {
                    return {
                        useDefault: false,
                        ids: arr.map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; })
                    };
                }
            }
        } catch (e) { /* ignore */ }
        if (s.indexOf(',') >= 0) {
            return {
                useDefault: false,
                ids: s.split(',').map(function (x) { return parseInt(x.trim(), 10); }).filter(function (n) { return n > 0; })
            };
        }
        var n = parseInt(s, 10);
        return { useDefault: false, ids: n > 0 ? [n] : [] };
    }

    function panelApiBase() {
        var base = document.body && document.body.getAttribute('data-panel-base');
        if (base) {
            return base;
        }
        var p = window.location.pathname || '';
        var i = p.lastIndexOf('/');
        return i >= 0 ? p.substring(0, i + 1) : '/panel/';
    }

    function renderPicker(container, items, selectedIds, useDefault, emptyMsg) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        var hint = document.createElement('p');
        hint.className = 'field-hint inbound-picker-hint';
        hint.textContent = 'تیک «پیش‌فرض پنل» = همان اینباندهای ذخیره‌شده در تنظیمات پنل. برای محدودیت جدا، تیک را بردارید و اینباندها را انتخاب کنید.';
        container.appendChild(hint);

        var defRow = document.createElement('label');
        defRow.className = 'inbound-default-row';
        var defCb = document.createElement('input');
        defCb.type = 'checkbox';
        defCb.className = 'inbound-use-default';
        defCb.checked = useDefault;
        defRow.appendChild(defCb);
        defRow.appendChild(document.createTextNode(' استفاده از پیش‌فرض پنل'));
        container.appendChild(defRow);

        var listWrap = document.createElement('div');
        listWrap.className = 'inbound-check-list';
        var emptyText = emptyMsg || 'اینباندی دریافت نشد — پنل ۳x-ui را انتخاب کنید یا اتصال/توکن را چک کنید.';
        if (!items.length) {
            listWrap.innerHTML = '<span class="cf inbound-empty-msg">' + emptyText + '</span>';
        } else {
            items.forEach(function (ib) {
                var lab = document.createElement('label');
                lab.className = 'inbound-check-item';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'inbound-id-cb';
                cb.value = String(ib.id);
                if (!useDefault && selectedIds.indexOf(ib.id) >= 0) {
                    cb.checked = true;
                }
                var title = '#' + ib.id;
                if (ib.remark) {
                    title += ' ' + ib.remark;
                }
                if (ib.protocol) {
                    title += ' (' + ib.protocol + (ib.port ? ':' + ib.port : '') + ')';
                }
                lab.appendChild(cb);
                lab.appendChild(document.createTextNode(' ' + title));
                listWrap.appendChild(lab);
            });
        }
        container.appendChild(listWrap);

        function syncDisabled() {
            var disabled = defCb.checked;
            listWrap.querySelectorAll('.inbound-id-cb').forEach(function (c) {
                c.disabled = disabled;
                if (disabled) {
                    c.checked = false;
                }
            });
            listWrap.style.opacity = disabled ? '0.45' : '1';
        }
        defCb.addEventListener('change', syncDisabled);
        syncDisabled();
    }

    function syncHiddenInput(container, hiddenInput) {
        if (!container || !hiddenInput) {
            return;
        }
        var defCb = container.querySelector('.inbound-use-default');
        if (!defCb || defCb.checked) {
            hiddenInput.value = '';
            return;
        }
        var ids = [];
        container.querySelectorAll('.inbound-id-cb:checked').forEach(function (c) {
            var id = parseInt(c.value, 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        hiddenInput.value = ids.length ? JSON.stringify(ids) : '';
    }

    function bindPickerEvents(picker, hidden) {
        picker.addEventListener('change', function () {
            syncHiddenInput(picker, hidden);
        });
    }

    function loadPicker(pickerId, panelSelectId, hiddenId, initialRaw, panelNameOverride) {
        var picker = document.getElementById(pickerId);
        var hidden = document.getElementById(hiddenId);
        var panelSel = document.getElementById(panelSelectId);
        if (!picker || !hidden) {
            return;
        }
        var panel = panelNameOverride
            ? String(panelNameOverride).trim()
            : (panelSel && panelSel.value ? panelSel.value.trim() : '');
        var parsed = parseInboundValue(initialRaw || '');
        if (!panel) {
            renderPicker(picker, [], [], true);
            hidden.value = '';
            return;
        }
        picker.innerHTML = '<span class="cf">در حال بارگذاری…</span>';
        var apiUrl = panelApiBase() + 'api/inbounds_options.php?panel=' + encodeURIComponent(panel);
        fetch(apiUrl, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    var errMsg = (data && data.msg) ? data.msg : 'خطا در دریافت اینباندها';
                    picker.innerHTML = '<span class="tag tag-no">' + errMsg + '</span>';
                    return;
                }
                if (data.note === 'not_xui') {
                    renderPicker(picker, [], [], true, data.msg);
                    bindPickerEvents(picker, hidden);
                    syncHiddenInput(picker, hidden);
                    return;
                }
                var items = data.items || [];
                renderPicker(picker, items, parsed.ids, parsed.useDefault, data.msg);
                bindPickerEvents(picker, hidden);
                syncHiddenInput(picker, hidden);
            })
            .catch(function (err) {
                picker.innerHTML = '<span class="tag tag-no">خطا در دریافت اینباندها' + (err && err.message ? ' (' + err.message + ')' : '') + '</span>';
            });
    }

    global.MirzaInboundPicker = {
        parseInboundValue: parseInboundValue,
        panelApiBase: panelApiBase,
        renderPicker: renderPicker,
        syncHiddenInput: syncHiddenInput,
        bindPickerEvents: bindPickerEvents,
        loadPicker: loadPicker
    };
}(typeof window !== 'undefined' ? window : this));
