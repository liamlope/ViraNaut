(function (global) {
    function parseInboundValue(raw) {
        if (!raw || raw === 'null') {
            return { ids: [], valid: false };
        }
        var s = String(raw).trim();
        if (s === '' || s === '-') {
            return { ids: [], valid: false };
        }
        try {
            if (s[0] === '[') {
                var arr = JSON.parse(s);
                if (Array.isArray(arr)) {
                    var ids = arr.map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; });
                    return { ids: ids, valid: ids.length > 0 };
                }
            }
        } catch (e) { /* ignore */ }
        if (s.indexOf(',') >= 0) {
            var list = s.split(',').map(function (x) { return parseInt(x.trim(), 10); }).filter(function (n) { return n > 0; });
            return { ids: list, valid: list.length > 0 };
        }
        var n = parseInt(s, 10);
        return { ids: n > 0 ? [n] : [], valid: n > 0 };
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

    function renderPicker(container, items, selectedIds, emptyMsg, required) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        var hint = document.createElement('p');
        hint.className = 'field-hint inbound-picker-hint';
        hint.textContent = required
            ? 'حداقل یک اینباند را انتخاب کنید — بدون اینباند کانفیگ ساخته نمی‌شود.'
            : 'اینباندها را انتخاب کنید.';
        container.appendChild(hint);

        var listWrap = document.createElement('div');
        listWrap.className = 'inbound-check-list';
        var emptyText = emptyMsg || 'اینباندی دریافت نشد — اتصال پنل یا توکن API را بررسی کنید.';
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
                if (selectedIds.indexOf(ib.id) >= 0) {
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
    }

    function syncHiddenInput(container, hiddenInput, required) {
        if (!container || !hiddenInput) {
            return { valid: !required };
        }
        var ids = [];
        container.querySelectorAll('.inbound-id-cb:checked').forEach(function (c) {
            var id = parseInt(c.value, 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        hiddenInput.value = ids.length ? JSON.stringify(ids) : '';
        return { valid: !required || ids.length > 0 };
    }

    function bindPickerEvents(picker, hidden, required) {
        picker.addEventListener('change', function () {
            syncHiddenInput(picker, hidden, required);
        });
    }

    function loadPicker(pickerId, panelSelectId, hiddenId, initialRaw, panelNameOverride, options) {
        var opts = options || {};
        var required = opts.required !== false;
        var picker = document.getElementById(pickerId);
        var hidden = document.getElementById(hiddenId);
        var panelSel = panelSelectId ? document.getElementById(panelSelectId) : null;
        if (!picker || !hidden) {
            return;
        }
        var panel = panelNameOverride
            ? String(panelNameOverride).trim()
            : (panelSel && panelSel.value ? panelSel.value.trim() : '');
        var parsed = parseInboundValue(initialRaw || '');
        if (!panel) {
            renderPicker(picker, [], parsed.ids, 'ابتدا پنل را انتخاب کنید.', required);
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
                    picker.innerHTML = '<span class="field-hint">' + (data.msg || 'این پنل ۳x-ui نیست.') + '</span>';
                    hidden.value = '';
                    return;
                }
                var items = data.items || [];
                renderPicker(picker, items, parsed.ids, data.msg, required);
                bindPickerEvents(picker, hidden, required);
                if (!items.length && parsed.valid) {
                    hidden.value = JSON.stringify(parsed.ids);
                    var keep = document.createElement('p');
                    keep.className = 'field-hint inbound-kept-msg';
                    keep.textContent = 'اینباند فعلی حفظ شد: #' + parsed.ids.join(', #')
                        + ' — لیست از پنل بارگذاری نشد؛ پس از ذخیرهٔ آدرس، «تست اتصال» بزنید و در صورت نیاز اینباند را عوض کنید.';
                    picker.appendChild(keep);
                } else {
                    syncHiddenInput(picker, hidden, required);
                }
            })
            .catch(function (err) {
                picker.innerHTML = '<span class="tag tag-no">خطا در دریافت اینباندها' + (err && err.message ? ' (' + err.message + ')' : '') + '</span>';
            });
    }

    function validateBeforeSubmit(pickerId, hiddenId, required) {
        var picker = document.getElementById(pickerId);
        var hidden = document.getElementById(hiddenId);
        if (!picker || !hidden) {
            return true;
        }
        var result = syncHiddenInput(picker, hidden, required !== false);
        if (!result.valid) {
            alert('حداقل یک اینباند انتخاب کنید.');
            return false;
        }
        return true;
    }

    global.MirzaInboundPicker = {
        parseInboundValue: parseInboundValue,
        panelApiBase: panelApiBase,
        renderPicker: renderPicker,
        syncHiddenInput: syncHiddenInput,
        bindPickerEvents: bindPickerEvents,
        loadPicker: loadPicker,
        validateBeforeSubmit: validateBeforeSubmit
    };
}(typeof window !== 'undefined' ? window : this));
