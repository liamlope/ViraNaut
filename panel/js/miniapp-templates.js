(function () {
    var root = document.querySelector('.miniapp-tpl-page');
    if (!root) return;

    var csrf = root.getAttribute('data-csrf');
    var selected = root.getAttribute('data-current') || 'midnight';
    var domain = root.getAttribute('data-domain') || '';
    var api = 'api/miniapp_templates.php';
    var base = document.body.getAttribute('data-panel-base') || '';
    if (base && api.indexOf('/') !== 0) api = base + api;

    var applyBtn = document.getElementById('applyTemplateBtn');
    var modal = document.getElementById('previewModal');
    var modalFrame = document.getElementById('previewModalFrame');
    var modalTitle = document.getElementById('previewModalTitle');

    function previewUrl(id) {
        if (!domain) return '';
        return 'https://' + domain + '/app/?tpl_preview=' + encodeURIComponent(id) + '&demo=1';
    }

    function setSelected(id) {
        selected = id;
        document.querySelectorAll('.miniapp-tpl-card').forEach(function (card) {
            var cid = card.getAttribute('data-id');
            card.classList.toggle('is-selected', cid === id);
        });
        if (applyBtn) {
            applyBtn.disabled = id === root.getAttribute('data-current');
        }
    }

    function postApply(id) {
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('action', 'apply');
        fd.append('template_id', id);
        return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    function applyTemplate(id) {
        var card = document.querySelector('.miniapp-tpl-card[data-id="' + id + '"]');
        var title = card ? (card.querySelector('.card-title') || {}).textContent : id;
        if (!confirm('قالب «' + (title || id) + '» برای همه کاربران مینی‌اپ اعمال شود؟')) return;
        postApply(id).then(function (d) {
            if (window.toast) toast(d.msg, d.ok ? 'ok' : 'no');
            if (d.ok) {
                root.setAttribute('data-current', id);
                document.querySelectorAll('.miniapp-tpl-card').forEach(function (card) {
                    var cid = card.getAttribute('data-id');
                    card.classList.toggle('is-active', cid === id);
                    var tag = card.querySelector('.tag-ok');
                    if (cid === id && !tag) {
                        var h = card.querySelector('.miniapp-tpl-head');
                        if (h) {
                            var sp = document.createElement('span');
                            sp.className = 'tag tag-ok';
                            sp.textContent = 'فعال';
                            h.appendChild(sp);
                        }
                    } else if (cid !== id && tag) {
                        tag.remove();
                    }
                });
                var labels = { midnight: 'نیمه‌شب', aurora: 'شفق قطبی', emerald: 'زمرد', sunset: 'غروب', ocean: 'اقیانوس' };
                var el = document.getElementById('activeTemplateLabel');
                if (el) el.textContent = labels[id] || id;
                setSelected(id);
                if (applyBtn) applyBtn.disabled = true;
            }
        });
    }

    document.querySelectorAll('.btn-select-tpl').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setSelected(btn.getAttribute('data-id'));
        });
    });

    document.querySelectorAll('.btn-preview-tpl').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            setSelected(id);
            var url = previewUrl(id);
            if (!url) {
                if (window.toast) toast('دامنه در config.php تنظیم نشده', 'no');
                return;
            }
            modalTitle.textContent = 'پیش‌نمایش — ' + id;
            modalFrame.src = url;
            modal.classList.add('open');
        });
    });

    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            applyTemplate(selected);
        });
    }

    var applyModal = document.getElementById('applyFromModal');
    if (applyModal) {
        applyModal.addEventListener('click', function () {
            applyTemplate(selected);
            modal.classList.remove('open');
        });
    }

    document.querySelectorAll('[data-close-preview]').forEach(function (b) {
        b.addEventListener('click', function () {
            modal.classList.remove('open');
            modalFrame.src = 'about:blank';
        });
    });

    setSelected(selected);
}());
