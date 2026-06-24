(function () {
    var base = document.body && document.body.getAttribute('data-panel-base') || '';

    var BTN_STYLES = [
        { v: '', l: 'پیش\u200cفرض' },
        { v: 'primary', l: 'آبی' },
        { v: 'success', l: 'سبز' },
        { v: 'danger', l: 'قرمز' }
    ];

    function styleSelectHtml(selected) {
        var html = '<select class="select select-sm prod-btn-style-select cat-style-select">';
        BTN_STYLES.forEach(function (s) {
            var sel = String(selected || '') === s.v ? ' selected' : '';
            html += '<option value="' + escapeHtml(s.v) + '"' + sel + '>' + escapeHtml(s.l) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function syncCategorySelects(items) {
        ['add_cat', 'edit_cat'].forEach(function (id) {
            var sel = document.getElementById(id);
            if (!sel) {
                return;
            }
            var cur = sel.value;
            sel.innerHTML = '<option value="">— بدون دسته —</option>';
            (items || []).forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.remark;
                opt.textContent = c.remark;
                sel.appendChild(opt);
            });
            if (cur) {
                sel.value = cur;
            }
        });
    }

    function renderCatRows(items) {
        var body = document.getElementById('catListBody');
        if (!body) {
            return;
        }
        body.innerHTML = '';
        (items || []).forEach(function (c) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-cat-id', String(c.id));
            tr.innerHTML =
                '<td><input type="text" class="input cat-remark-input vira-emoji-field" data-emoji-max="1" value="' + escapeHtml(c.remark) + '" style="font-size:.82rem"></td>' +
                '<td>' + styleSelectHtml(c.btn_style || '') + '</td>' +
                '<td style="white-space:nowrap">' +
                '<button type="button" class="btn btn-ghost btn-sm cat-save-btn">ذخیره</button> ' +
                '<button type="button" class="btn btn-no btn-sm cat-del-btn">حذف</button>' +
                '</td>';
            body.appendChild(tr);
        });
        bindCatRowButtons();
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function loadCategories() {
        return fetch(base + 'api/categories.php?action=list', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok && data.items) {
                    syncCategorySelects(data.items);
                    renderCatRows(data.items);
                }
                return data;
            });
    }

    function postCategory(action, payload) {
        var fd = new FormData();
        Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });
        fd.append('action', action);
        return fetch(base + 'api/categories.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function bindCatRowButtons() {
        document.querySelectorAll('.cat-save-btn').forEach(function (btn) {
            btn.onclick = function () {
                var tr = btn.closest('tr');
                var id = tr.getAttribute('data-cat-id');
                var remark = tr.querySelector('.cat-remark-input').value.trim();
                var styleEl = tr.querySelector('.cat-style-select');
                var btnStyle = styleEl ? styleEl.value : '';
                var csrf = document.querySelector('#catAddForm [name="_csrf"]');
                postCategory('edit', { id: id, remark: remark, btn_style: btnStyle, _csrf: csrf ? csrf.value : '' })
                    .then(function (data) {
                        if (data && data.ok) {
                            loadCategories();
                        } else {
                            alert((data && data.msg) || 'خطا');
                        }
                    });
            };
        });
        document.querySelectorAll('.cat-del-btn').forEach(function (btn) {
            btn.onclick = function () {
                if (!confirm('حذف این دسته؟')) {
                    return;
                }
                var tr = btn.closest('tr');
                var id = tr.getAttribute('data-cat-id');
                var csrf = document.querySelector('#catAddForm [name="_csrf"]');
                postCategory('delete', { id: id, _csrf: csrf ? csrf.value : '' })
                    .then(function (data) {
                        if (data && data.ok) {
                            loadCategories();
                        } else {
                            alert((data && data.msg) || 'خطا');
                        }
                    });
            };
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindCatRowButtons();
        var addForm = document.getElementById('catAddForm');
        if (addForm) {
            addForm.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var fd = new FormData(addForm);
                fd.append('action', 'add');
                fetch(base + 'api/categories.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            addForm.reset();
                            loadCategories();
                        } else {
                            alert((data && data.msg) || 'خطا');
                        }
                    });
            });
        }
    });
}());
