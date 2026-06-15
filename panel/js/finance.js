(function () {
    var root = document.getElementById('financeApp');
    if (!root) return;

    var api = root.dataset.api || 'api/finance.php';
    var csrf = root.dataset.csrf || '';
    var base = document.body.getAttribute('data-panel-base') || '';
    if (base && api.indexOf('/') !== 0) api = base + api;

    var txPage = 1;
    var activeTab = (new URLSearchParams(location.search).get('tab')) || 'pending';
    var searchTimer = null;

    function fmt(n) {
        return Number(n || 0).toLocaleString('fa-IR');
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('action', action);
        Object.keys(data || {}).forEach(function (k) {
            fd.append(k, data[k]);
        });
        return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    function fetchJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    function statusTag(s) {
        var map = {
            paid: ['tag-ok', 'پرداخت شده'],
            Unpaid: ['tag-no', 'پرداخت نشده'],
            waiting: ['tag-warn', 'در انتظار'],
            expire: ['tag-plain', 'منقضی'],
            reject: ['tag-no', 'رد شده'],
            active: ['tag-ok', 'فعال'],
        };
        var m = map[s] || ['tag-plain', s || '—'];
        return '<span class="tag ' + m[0] + '">' + m[1] + '</span>';
    }

    function typeLabel(t) {
        var map = {
            getconfigafterpay: 'خرید سرویس',
            getextenduser: 'تمدید',
            getextravolumeuser: 'حجم اضافه',
            getextratimeuser: 'زمان اضافه',
            '': 'شارژ کیف پول',
        };
        return map[t] || t || 'شارژ / سایر';
    }

    function actionBtns(orderId, canAct) {
        if (!canAct) return '—';
        return '<div class="finance-actions">' +
            '<button type="button" class="btn btn-ok btn-sm" data-approve="' + orderId + '">تأیید</button>' +
            '<button type="button" class="btn btn-no btn-sm" data-reject="' + orderId + '">رد</button>' +
            '</div>';
    }

    function bindActionButtons(container) {
        if (!container) return;
        container.querySelectorAll('[data-approve]').forEach(function (btn) {
            btn.onclick = function () {
                if (!confirm('این پرداخت تأیید شود؟ (مثل تلگرام — موجودی/سرویس اعمال می‌شود)')) return;
                btn.disabled = true;
                post('payment_approve', { id_order: btn.getAttribute('data-approve') }).then(function (d) {
                    if (window.toast) toast(d.msg || (d.ok ? 'تأیید شد' : 'خطا'), d.ok ? 'ok' : 'no');
                    if (d.ok) {
                        loadOverview();
                        switchTab(activeTab);
                    } else btn.disabled = false;
                });
            };
        });
        container.querySelectorAll('[data-reject]').forEach(function (btn) {
            btn.onclick = function () {
                document.getElementById('financeRejectOrder').value = btn.getAttribute('data-reject');
                document.getElementById('financeRejectReason').value = '';
                document.getElementById('financeRejectModal').classList.add('open');
            };
        });
    }

    function loadOverview() {
        fetchJson(api + '?action=overview').then(function (d) {
            if (!d.ok) throw new Error(d.msg || 'خطا');
            var s = d.stats || {};
            root.querySelectorAll('[data-k]').forEach(function (el) {
                var k = el.dataset.k;
                var v = s[k];
                var money = ['total_paid', 'paid_today', 'invoice_revenue'].indexOf(k) >= 0;
                el.innerHTML = fmt(v) + (money ? '<small>تومان</small>' : '');
            });
            var bm = document.getElementById('financeByMethod');
            if (bm) {
                if (!d.by_method || !d.by_method.length) {
                    bm.innerHTML = '<p class="finance-loading">داده‌ای ثبت نشده</p>';
                } else {
                    bm.innerHTML = d.by_method.map(function (row) {
                        return '<div class="method-row"><span>' + (row.label || row.Payment_Method) +
                            ' <small class="cf">(' + row.cnt + ')</small></span><strong class="cn">' + fmt(row.total) + '</strong></div>';
                    }).join('');
                }
            }
        }).catch(function (e) {
            if (window.toast) toast('خطا: ' + e.message, 'no');
        });
    }

    function loadGateways() {
        fetchJson(api + '?action=gateways').then(function (d) {
            if (!d.ok) {
                if (window.toast) toast(d.msg, 'no');
                return;
            }
            var box = document.getElementById('financeGatewayProfiles');
            var smsStatus = document.getElementById('financeSmsStatus');
            if (smsStatus && d.sms) {
                var s = d.sms;
                var pills = [
                    '<span class="finance-pill ' + (s.sms_enabled ? 'is-on' : 'is-off') + '">' +
                    (s.sms_enabled ? 'تأیید SMS فعال' : 'تأیید SMS خاموش') + '</span>'
                ];
                if (s.sms_enabled) {
                    pills.push('<span class="finance-pill ' + (s.group_configured ? 'is-ok' : 'is-warn') + '">' +
                        (s.group_configured ? 'گروه SMS تنظیم شده' : 'گروه SMS تنظیم نشده') + '</span>');
                    if (s.receipt_delay_label) {
                        pills.push('<span class="finance-pill is-dim">تأخیر رسید: ' + s.receipt_delay_label + '</span>');
                    }
                }
                smsStatus.innerHTML = pills.join('');
                smsStatus.classList.remove('hidden');
            } else if (smsStatus) {
                smsStatus.innerHTML = '';
                smsStatus.classList.add('hidden');
            }
            if (box) {
                box.innerHTML = (d.profiles || []).map(function (p) {
                    var fields = (p.fields || []).map(function (f) {
                        var inp = f.input === 'textarea'
                            ? '<textarea class="input gw-f" data-k="' + f.key + '" rows="2">' + (f.value || '') + '</textarea>'
                            : '<input type="text" class="input gw-f" data-k="' + f.key + '" value="' + (f.value || '').replace(/"/g, '&quot;') + '">';
                        return '<label class="field"><span class="field-label">' + f.label + '</span>' + inp + '</label>';
                    }).join('');
                    return '<div class="card finance-gw-card" data-pid="' + p.id + '"><div class="card-head">' +
                        '<div class="card-title">' + p.label + '</div>' +
                        '<button type="button" class="btn btn-sm ' + (p.toggle_on ? 'btn-ok' : 'btn-ghost') + '" data-gw-toggle="' + p.toggle.key + '">' +
                        (p.toggle_on ? 'فعال' : 'غیرفعال') + '</button></div><div class="card-body">' + fields +
                        '<button type="button" class="btn btn-primary btn-sm" data-save-profile="' + p.id + '">ذخیره</button></div></div>';
                }).join('');
                box.querySelectorAll('[data-gw-toggle]').forEach(function (btn) {
                    btn.onclick = function () {
                        post('gateway_toggle', { key: btn.getAttribute('data-gw-toggle') }).then(function (r) {
                            if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
                            if (r.ok) loadGateways();
                        });
                    };
                });
                box.querySelectorAll('[data-save-profile]').forEach(function (btn) {
                    btn.onclick = function () {
                        var card = btn.closest('[data-pid]');
                        var fields = {};
                        card.querySelectorAll('.gw-f').forEach(function (inp) {
                            fields[inp.getAttribute('data-k')] = inp.value;
                        });
                        var fd = new FormData();
                        fd.append('_csrf', csrf);
                        fd.append('action', 'gateway_profile_save');
                        fd.append('profile_id', card.getAttribute('data-pid'));
                        fd.append('fields', JSON.stringify(fields));
                        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
                            if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
                        });
                    };
                });
            }
            var gen = document.getElementById('financeGeneralPay');
            if (gen) {
                gen.innerHTML = (d.general || []).map(function (g) {
                    var hint = g.hint ? '<span class="field-hint">' + g.hint + '</span>' : '';
                    if (g.options) {
                        var opts = Object.keys(g.options).map(function (k) {
                            return '<option value="' + k + '"' + (g.value === k ? ' selected' : '') + '>' + g.options[k] + '</option>';
                        }).join('');
                        return '<label class="field"><span class="field-label">' + g.label + '</span>' + hint +
                            '<select class="select gen-f" data-k="' + g.key + '">' + opts + '</select></label>';
                    }
                    var inp = g.input === 'textarea'
                        ? '<textarea class="input gen-f" data-k="' + g.key + '" rows="2">' + (g.value || '') + '</textarea>'
                        : (g.input === 'number'
                            ? '<input type="number" class="input gen-f" data-k="' + g.key + '" min="1" max="1440" step="1" value="' + (g.value || '10') + '">'
                            : '<input type="text" class="input gen-f" data-k="' + g.key + '" value="' + (g.value || '').replace(/"/g, '&quot;') + '">');
                    return '<label class="field"><span class="field-label">' + g.label + '</span>' + hint + inp + '</label>';
                }).join('');
            }
            var list = document.getElementById('financeCardsList');
            var cards = d.cards || [];
            list.innerHTML = cards.length ? cards.map(function (c) {
                return '<div class="finance-card-row">' +
                    '<code dir="ltr">' + (c.cardnumber || '') + '</code>' +
                    '<span>' + (c.namecard || '') + '</span>' +
                    '<button type="button" class="btn btn-no btn-sm" data-card-del="' + c.cardnumber + '">حذف</button></div>';
            }).join('') : '<p class="cf">کارتی ثبت نشده — از فرم بالا اضافه کنید.</p>';
            list.querySelectorAll('[data-card-del]').forEach(function (btn) {
                btn.onclick = function () {
                    if (!confirm('این کارت حذف شود؟')) return;
                    post('card_delete', { cardnumber: btn.getAttribute('data-card-del') }).then(function (r) {
                        if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
                        if (r.ok) loadGateways();
                    });
                };
            });
        });
    }

    function renderTxRows(items, bodyId, onlyWaiting) {
        var body = document.getElementById(bodyId);
        if (!items.length) {
            body.innerHTML = '<tr><td colspan="7" class="empty">موردی یافت نشد</td></tr>';
            return;
        }
        body.innerHTML = items.map(function (r) {
            var t = r.time_label || r.time || '—';
            var canAct = r.payment_Status === 'waiting';
            return '<tr>' +
                '<td>' + actionBtns(r.id_order, canAct) + '</td>' +
                '<td class="cm">' + (r.id_order || '') + '</td>' +
                '<td class="cm"><a href="user.php?q=' + encodeURIComponent(r.id_user || '') + '">' + (r.id_user || '') + '</a></td>' +
                '<td class="cn">' + fmt(r.price) + '</td>' +
                '<td>' + (r.method_label || '') + '</td>' +
                '<td>' + (onlyWaiting ? typeLabel(r.invoice_type) : statusTag(r.payment_Status)) + '</td>' +
                '<td class="cf">' + t + '</td></tr>';
        }).join('');
        bindActionButtons(body);
    }

    function loadPending() {
        fetchJson(api + '?action=transactions&status=waiting&per_page=50').then(function (d) {
            var body = document.getElementById('financePendingBody');
            if (!d.ok) {
                body.innerHTML = '<tr><td colspan="7">' + (d.msg || 'خطا') + '</td></tr>';
                return;
            }
            renderTxRows(d.items, 'financePendingBody', true);
        });
    }

    function loadTx() {
        var q = (document.getElementById('financeSearch') || {}).value || '';
        var st = (document.getElementById('financeStatusFilter') || {}).value || '';
        var url = api + '?action=transactions&page=' + txPage + '&per_page=25';
        if (q) url += '&q=' + encodeURIComponent(q);
        if (st) url += '&status=' + encodeURIComponent(st);
        fetchJson(url).then(function (d) {
            var body = document.getElementById('financeTxBody');
            if (!d.ok) {
                body.innerHTML = '<tr><td colspan="7">' + (d.msg || 'خطا') + '</td></tr>';
                return;
            }
            renderTxRows(d.items, 'financeTxBody', false);
            document.getElementById('financeTxMeta').textContent = fmt(d.total) + ' تراکنش';
            var pager = document.getElementById('financeTxPager');
            pager.innerHTML = '';
            if (d.pages > 1) {
                for (var p = 1; p <= Math.min(d.pages, 8); p++) {
                    var a = document.createElement('a');
                    a.href = '#';
                    a.textContent = p;
                    if (p === d.page) a.className = 'cur';
                    a.dataset.page = p;
                    pager.appendChild(a);
                }
            }
        });
    }

    function loadInv() {
        var q = (document.getElementById('financeSearch') || {}).value || '';
        var url = api + '?action=invoices&per_page=20';
        if (q) url += '&q=' + encodeURIComponent(q);
        fetchJson(url).then(function (d) {
            var body = document.getElementById('financeInvBody');
            if (!d.ok) {
                body.innerHTML = '<tr><td colspan="5">' + d.msg + '</td></tr>';
                return;
            }
            body.innerHTML = d.items.length ? d.items.map(function (r) {
                return '<tr><td class="cm cf">' + (r.id_invoice || '').slice(0, 14) + '…</td><td class="cm">' + r.id_user +
                    '</td><td>' + (r.name_product || '') + '</td><td class="cn">' + fmt(r.price_product) +
                    '</td><td>' + statusTag(r.Status) + '</td></tr>';
            }).join('') : '<tr><td colspan="5" class="empty">موردی نیست</td></tr>';
        });
    }

    function loadDisc() {
        fetchJson(api + '?action=discounts').then(function (d) {
            var body = document.getElementById('financeDiscBody');
            if (!d.ok) {
                body.innerHTML = '<tr><td colspan="5">' + d.msg + '</td></tr>';
                return;
            }
            body.innerHTML = d.items.length ? d.items.map(function (r) {
                return '<tr><td class="cm">' + (r.code || '') + '</td><td class="cn">' + (r.price || '') +
                    '</td><td>' + (r.limituse || '—') + '</td><td>' + (r.limitused || '0') +
                    '</td><td><button type="button" class="btn btn-ghost btn-sm" data-edit-disc="' + r.id + '">ویرایش</button> ' +
                    '<button type="button" class="btn btn-no btn-sm" data-del-disc="' + r.id + '">حذف</button></td></tr>';
            }).join('') : '<tr><td colspan="5" class="empty">کد تخفیفی نیست</td></tr>';
            body.querySelectorAll('[data-edit-disc]').forEach(function (b) {
                b.onclick = function () {
                    var id = b.getAttribute('data-edit-disc');
                    var row = d.items.find(function (x) { return String(x.id) === id; });
                    if (row) {
                        document.getElementById('disc_id').value = row.id;
                        document.getElementById('disc_code').value = row.code || '';
                        document.getElementById('disc_price').value = row.price || '';
                        document.getElementById('disc_limit').value = row.limituse || '';
                    }
                };
            });
            body.querySelectorAll('[data-del-disc]').forEach(function (b) {
                b.onclick = function () {
                    if (!confirm('حذف کد؟')) return;
                    post('discount_delete', { id: b.getAttribute('data-del-disc') }).then(function (r) {
                        if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
                        if (r.ok) loadDisc();
                    });
                };
            });
        });
    }

    function switchTab(tab) {
        activeTab = tab;
        root.querySelectorAll('.finance-tab').forEach(function (b) {
            b.classList.toggle('active', b.dataset.tab === tab);
        });
        document.getElementById('financeTabPending').classList.toggle('hidden', tab !== 'pending');
        document.getElementById('financeTabTx').classList.toggle('hidden', tab !== 'tx');
        document.getElementById('financeTabGateways').classList.toggle('hidden', tab !== 'gateways');
        document.getElementById('financeTabInv').classList.toggle('hidden', tab !== 'inv');
        document.getElementById('financeTabDisc').classList.toggle('hidden', tab !== 'disc');
        var sf = document.getElementById('financeStatusFilter');
        sf.style.display = tab === 'tx' ? '' : 'none';
        if (tab === 'pending') loadPending();
        if (tab === 'tx') loadTx();
        if (tab === 'gateways') loadGateways();
        if (tab === 'inv') loadInv();
        if (tab === 'disc') loadDisc();
    }

    root.querySelectorAll('.finance-tab').forEach(function (btn) {
        btn.addEventListener('click', function () { switchTab(btn.dataset.tab); });
    });

    document.getElementById('financeSearch').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            txPage = 1;
            if (activeTab === 'tx') loadTx();
            else if (activeTab === 'inv') loadInv();
            else if (activeTab === 'pending') loadPending();
        }, 350);
    });

    document.getElementById('financeStatusFilter').addEventListener('change', function () {
        txPage = 1;
        loadTx();
    });

    document.getElementById('financeTxPager').addEventListener('click', function (e) {
        var a = e.target.closest('a[data-page]');
        if (!a) return;
        e.preventDefault();
        txPage = parseInt(a.dataset.page, 10);
        loadTx();
    });

    document.getElementById('financeRefresh').addEventListener('click', function () {
        loadOverview();
        switchTab(activeTab);
    });

    document.getElementById('financeExport').addEventListener('click', function () {
        post('export', { days: '30' }).then(function (d) {
            if (!d.ok || !d.rows) {
                if (window.toast) toast(d.msg || 'خطا', 'no');
                return;
            }
            var lines = ['id_order,id_user,price,payment_Status,Payment_Method,time,id_invoice'];
            d.rows.forEach(function (row) {
                lines.push([row.id_order, row.id_user, row.price, row.payment_Status, row.Payment_Method, row.time, row.id_invoice || ''].join(','));
            });
            var blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'payments.csv';
            a.click();
        });
    });

    document.getElementById('financeCardForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(e.target);
        post('card_add', {
            cardnumber: fd.get('cardnumber'),
            namecard: fd.get('namecard'),
        }).then(function (r) {
            if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
            if (r.ok) {
                e.target.reset();
                loadGateways();
            }
        });
    });

    document.querySelectorAll('[data-close-reject]').forEach(function (el) {
        el.addEventListener('click', function () {
            document.getElementById('financeRejectModal').classList.remove('open');
        });
    });

    document.getElementById('financeRejectConfirm').addEventListener('click', function () {
        var order = document.getElementById('financeRejectOrder').value;
        var reason = document.getElementById('financeRejectReason').value.trim() || 'رد شده از پنل';
        post('payment_reject', { id_order: order, reason: reason }).then(function (d) {
            if (window.toast) toast(d.msg, d.ok ? 'ok' : 'no');
            if (d.ok) {
                document.getElementById('financeRejectModal').classList.remove('open');
                loadOverview();
                switchTab(activeTab);
            }
        });
    });

    var saveGen = document.getElementById('saveGeneralPay');
    if (saveGen) {
        saveGen.addEventListener('click', function () {
            var fields = {};
            document.querySelectorAll('.gen-f').forEach(function (inp) {
                fields[inp.getAttribute('data-k')] = inp.value;
            });
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('action', 'general_pay_save');
            fd.append('fields', JSON.stringify(fields));
            fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
                if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
            });
        });
    }
    var discForm = document.getElementById('discountForm');
    if (discForm) {
        discForm.addEventListener('submit', function (e) {
            e.preventDefault();
            post('discount_save', {
                id: document.getElementById('disc_id').value,
                code: document.getElementById('disc_code').value,
                price: document.getElementById('disc_price').value,
                limituse: document.getElementById('disc_limit').value,
            }).then(function (r) {
                if (window.toast) toast(r.msg, r.ok ? 'ok' : 'no');
                if (r.ok) { discForm.reset(); document.getElementById('disc_id').value = '0'; loadDisc(); }
            });
        });
    }
    loadOverview();
    switchTab(activeTab);
}());
