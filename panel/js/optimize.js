(function () {
    function init() {
        if (!window.mirzaBotTools) {
            console.error('mirzaBotTools not loaded');
            return;
        }

        var fullBtn = document.getElementById('runFullOptimize');
        var payBtn = document.getElementById('runCleanupOnly');
        var resultEl = document.getElementById('optimizeResult');
        var selExpire = document.getElementById('daysExpireReject');
        var selUnpaid = document.getElementById('daysUnpaid');
        var lblDaysExpire = document.getElementById('lblDaysExpire');
        var lblDaysUnpaid = document.getElementById('lblDaysUnpaid');
        var statsBox = document.getElementById('optStats');

        var FULL_STEPS = [
            'حذف سرویس‌های تمام‌شده',
            'حذف سفارش‌های بلااستفاده',
            'پاکسازی پرداخت‌های منقضی و رد شده',
            'پاکسازی پرداخت‌های Unpaid',
            'پاکسازی درخواست‌های لغو',
            'بهینه‌سازی فایل‌های لاگ',
            'بهینه‌سازی جداول دیتابیس'
        ];

        var PAY_STEPS = [
            'بررسی بازهٔ زمانی',
            'حذف پرداخت‌های منقضی/رد شده',
            'حذف پرداخت‌های Unpaid'
        ];

        function getDays() {
            return {
                days_expire: selExpire ? selExpire.value : '90',
                days_unpaid: selUnpaid ? selUnpaid.value : '30'
            };
        }

        function updateDayLabels() {
            var d = getDays();
            if (lblDaysExpire) lblDaysExpire.textContent = d.days_expire;
            if (lblDaysUnpaid) lblDaysUnpaid.textContent = d.days_unpaid;
        }

        function setStatsLoading(on) {
            if (statsBox) statsBox.classList.toggle('is-loading', !!on);
        }

        function setButtonsBusy(busy, which) {
            [fullBtn, payBtn].forEach(function (b) {
                if (!b) return;
                b.disabled = !!busy;
                b.classList.toggle('is-loading', !!busy && (which === 'all' || b === which));
            });
            if (selExpire) selExpire.disabled = !!busy;
            if (selUnpaid) selUnpaid.disabled = !!busy;
        }

        function refreshPreview() {
            var d = getDays();
            updateDayLabels();
            setStatsLoading(true);
            var url = window.mirzaBotTools.base() + 'api/bot_tools.php?action=optimize_stats'
                + '&days_expire=' + encodeURIComponent(d.days_expire)
                + '&days_unpaid=' + encodeURIComponent(d.days_unpaid);
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok || !res.stats) return;
                    var s = res.stats;
                    document.querySelectorAll('[data-stat]').forEach(function (el) {
                        var key = el.getAttribute('data-stat');
                        if (s[key] !== undefined) el.textContent = s[key];
                    });
                })
                .finally(function () {
                    setStatsLoading(false);
                });
        }

        if (selExpire) selExpire.addEventListener('change', refreshPreview);
        if (selUnpaid) selUnpaid.addEventListener('change', refreshPreview);

        function showResult(text, ok) {
            if (!resultEl) return;
            resultEl.hidden = false;
            resultEl.textContent = text;
            resultEl.style.borderColor = ok ? 'var(--ok, #22c55e)' : 'var(--no, #ef4444)';
            resultEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            if (window.toast) toast(String(text).split('\n')[0], ok ? 'ok' : 'no');
        }

        function showWorking(msg) {
            showResult(msg, true);
        }

        function runWithProgress(opts, apiCall) {
            setButtonsBusy(true, opts.which);
            showWorking('⏳ ' + opts.title + '\nلطفاً صبر کنید…');
            window.mirzaBotTools.loadBar(true);

            var PP = window.PanelProgress;
            var promise;
            if (PP && typeof PP.run === 'function') {
                promise = PP.run({
                    title: opts.title,
                    subtitle: opts.steps[0] || '',
                    steps: opts.steps,
                    doneTitle: opts.doneTitle || 'انجام شد',
                    stepMs: opts.stepMs || 700
                }, function () {
                    return apiCall({ silent: true });
                });
            } else {
                promise = apiCall({ silent: true });
            }

            return promise.finally(function () {
                setButtonsBusy(false);
                window.mirzaBotTools.loadBar(false);
            });
        }

        if (fullBtn) {
            fullBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var d = getDays();
                if (!confirm(
                    'بهینه‌سازی کامل انجام شود؟\n\n'
                    + 'پرداخت منقضی/رد: قدیمی‌تر از ' + d.days_expire + ' روز\n'
                    + 'پرداخت Unpaid: قدیمی‌تر از ' + d.days_unpaid + ' روز\n\n'
                    + 'سرویس‌های فعال و پرداخت‌های موفق حفظ می‌شوند.'
                )) {
                    return;
                }
                var payload = { confirm: 'yes', days_expire: d.days_expire, days_unpaid: d.days_unpaid };
                runWithProgress({
                    title: 'در حال بهینه‌سازی ربات…',
                    steps: FULL_STEPS,
                    which: 'all',
                    doneTitle: 'بهینه‌سازی کامل شد'
                }, function (opts) {
                    return window.mirzaBotTools.post('optimize_run', payload, opts);
                }).then(function (r) {
                    if (!r || !r.ok) {
                        showResult((r && r.msg) || 'خطا در بهینه‌سازی', false);
                        return;
                    }
                    var lines = [r.msg || 'انجام شد'];
                    if (r.details) {
                        lines.push('');
                        lines.push('بازه منقضی/رد: ' + (r.details.days_expire_reject || d.days_expire) + ' روز');
                        lines.push('بازه Unpaid: ' + (r.details.days_unpaid || d.days_unpaid) + ' روز');
                        lines.push('سرویس تمام‌شده: ' + (r.details.expired_deleted || 0));
                        lines.push('سفارش بلااستفاده: ' + (r.details.junk_deleted || 0));
                        lines.push('پرداخت قدیمی: ' + (r.details.payments_deleted || 0));
                        lines.push('Unpaid قدیمی: ' + (r.details.unpaid_payments_deleted || 0));
                        lines.push('درخواست لغو: ' + (r.details.cancel_deleted || 0));
                        if (r.details.tables_optimized && r.details.tables_optimized.length) {
                            lines.push('جداول: ' + r.details.tables_optimized.join('، '));
                        }
                    }
                    showResult(lines.join('\n'), true);
                    setTimeout(function () { location.reload(); }, 2000);
                }).catch(function (err) {
                    showResult('خطا: ' + (err && err.message ? err.message : 'ارتباط با سرور'), false);
                });
            });
        }

        if (payBtn) {
            payBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var d = getDays();
                if (!confirm(
                    'پرداخت‌های قدیمی حذف شوند؟\n\n'
                    + 'منقضی/رد: ' + d.days_expire + ' روز\n'
                    + 'Unpaid: ' + d.days_unpaid + ' روز'
                )) {
                    return;
                }
                runWithProgress({
                    title: 'در حال پاکسازی پرداخت‌ها…',
                    steps: PAY_STEPS,
                    which: payBtn,
                    doneTitle: 'پاکسازی پرداخت انجام شد',
                    stepMs: 500
                }, function (opts) {
                    return window.mirzaBotTools.post('cleanup_expired', {
                        confirm: 'yes',
                        days_expire: d.days_expire,
                        days_unpaid: d.days_unpaid
                    }, opts);
                }).then(function (r) {
                    if (!r) return;
                    showResult(r.msg || (r.ok ? 'انجام شد' : 'خطا'), !!r.ok);
                    if (r.ok) refreshPreview();
                }).catch(function (err) {
                    showResult('خطا: ' + (err && err.message ? err.message : 'ارتباط با سرور'), false);
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
