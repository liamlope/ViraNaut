<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/optimize_ops.php';
require_auth();

$dayOptions = vira_optimize_day_options();
$daysExpireDefault = 90;
$daysUnpaidDefault = 30;

$optFlash = null;
$optFlashOk = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opt_action'])) {
    csrf_check_post();
    if (($_POST['confirm'] ?? '') !== 'yes') {
        $optFlash = 'تأیید عملیات الزامی است.';
        $optFlashOk = false;
    } else {
        $daysExpire = vira_optimize_sanitize_days($_POST['days_expire'] ?? $daysExpireDefault, $daysExpireDefault);
        $daysUnpaid = vira_optimize_sanitize_days($_POST['days_unpaid'] ?? $daysUnpaidDefault, $daysUnpaidDefault);
        $act = (string) $_POST['opt_action'];
        try {
            if ($act === 'full') {
                @ini_set('max_execution_time', '300');
                $botRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
                $details = vira_optimize_run($pdo, $botRoot, $daysExpire, $daysUnpaid);
                $optFlashOk = true;
                $optFlash = sprintf(
                    'بهینه‌سازی انجام شد — %d مورد حذف شد (%d سرویس تمام‌شده، %d سفارش بلااستفاده، %d پرداخت قدیمی، %d Unpaid)',
                    (int) $details['total_removed'],
                    (int) $details['expired_deleted'],
                    (int) $details['junk_deleted'],
                    (int) $details['payments_deleted'],
                    (int) $details['unpaid_payments_deleted']
                );
            } elseif ($act === 'cleanup') {
                $cleanup = vira_optimize_cleanup_payments($pdo, $daysExpire, $daysUnpaid);
                $n = (int) $cleanup['payments_deleted'] + (int) $cleanup['unpaid_payments_deleted'];
                $optFlashOk = true;
                $optFlash = sprintf(
                    'پاکسازی پرداخت انجام شد — %d رکورد (منقضی/رد: %d روز، Unpaid: %d روز)',
                    $n,
                    $daysExpire,
                    $daysUnpaid
                );
            } else {
                $optFlash = 'عملیات نامعتبر.';
                $optFlashOk = false;
            }
        } catch (Throwable $e) {
            $optFlash = $e->getMessage();
            $optFlashOk = false;
        }
    }
}

$previewDaysExpire = vira_optimize_sanitize_days($_POST['days_expire'] ?? $daysExpireDefault, $daysExpireDefault);
$previewDaysUnpaid = vira_optimize_sanitize_days($_POST['days_unpaid'] ?? $daysUnpaidDefault, $daysUnpaidDefault);
$preview = vira_optimize_preview($pdo, $previewDaysExpire, $previewDaysUnpaid);
$previewTotal = (int) $preview['expired_services']
    + (int) $preview['junk_orders']
    + (int) $preview['old_payments']
    + (int) $preview['old_unpaid_payments'];

$pageTitle = 'بهینه‌سازی';
$pageLede = 'پیش‌نمایش پیش‌فرض — اجرای واقعی فقط با تأیید دوباره.';
$activeNav = 'optimize';
$extraCss = ['css/optimize.css'];
$extraJs = [];

$footerInlineJs = <<<'JS'
window.ViraOptimizePage = (function () {
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

    function el(id) { return document.getElementById(id); }

    function getDays() {
        var se = el('daysExpireReject');
        var su = el('daysUnpaid');
        return {
            days_expire: se ? se.value : '90',
            days_unpaid: su ? su.value : '30'
        };
    }

    function showResult(text, ok) {
        var box = el('optimizeResult');
        if (!box) return;
        box.hidden = false;
        box.textContent = text;
        box.style.borderColor = ok ? 'var(--ok, #22c55e)' : 'var(--no, #ef4444)';
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        if (window.toast) toast(String(text).split('\n')[0], ok ? 'ok' : 'no');
    }

    function showJsError(msg) {
        var err = el('optJsError');
        if (err) {
            err.hidden = false;
            err.textContent = msg;
        }
        showResult(msg, false);
    }

    function setBusy(on, which) {
        var fullBtn = el('runFullOptimize');
        var payBtn = el('runCleanupOnly');
        [fullBtn, payBtn].forEach(function (b) {
            if (!b) return;
            b.disabled = !!on;
            b.classList.toggle('is-loading', !!on && (which === 'all' || b === which));
        });
        var se = el('daysExpireReject');
        var su = el('daysUnpaid');
        if (se) se.disabled = !!on;
        if (su) su.disabled = !!on;
    }

    function askConfirm(msg, title, cb) {
        if (typeof window.showConfirm === 'function') {
            window.showConfirm(msg, cb, title || 'تأیید عملیات');
            return;
        }
        if (window.confirm(msg)) cb();
    }

    function runWithProgress(opts, apiCall) {
        setBusy(true, opts.which);
        showResult('⏳ ' + opts.title + '\nلطفاً صبر کنید…', true);
        if (window.viraBotTools && window.viraBotTools.loadBar) {
            window.viraBotTools.loadBar(true);
        }
        var PP = window.PanelProgress;
        var p;
        if (PP && typeof PP.run === 'function') {
            p = PP.run({
                title: opts.title,
                subtitle: (opts.steps && opts.steps[0]) || '',
                steps: opts.steps || [],
                doneTitle: opts.doneTitle || 'انجام شد',
                stepMs: opts.stepMs || 700
            }, function () {
                return apiCall({ silent: true });
            });
        } else {
            p = apiCall({ silent: true });
        }
        return Promise.resolve(p).finally(function () {
            setBusy(false);
            if (window.viraBotTools && window.viraBotTools.loadBar) {
                window.viraBotTools.loadBar(false);
            }
        });
    }

    function submitFallback(action) {
        var d = getDays();
        var form = el(action === 'full' ? 'optFormFull' : 'optFormCleanup');
        if (!form) return;
        var h1 = form.querySelector('[name="days_expire"]');
        var h2 = form.querySelector('[name="days_unpaid"]');
        if (h1) h1.value = d.days_expire;
        if (h2) h2.value = d.days_unpaid;
        form.submit();
    }

    function runFull(ev) {
        if (ev && ev.preventDefault) ev.preventDefault();
        var execZone = el('optExecuteZone');
        if (execZone && execZone.hidden) {
            execZone.hidden = false;
            execZone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            showResult('پیش‌نمایش را بررسی کنید. برای حذف واقعی «اجرای واقعی بهینه‌سازی» را بزنید.', true);
        }
        return false;
    }

    function runFullExecute(ev) {
        if (ev && ev.preventDefault) ev.preventDefault();
        if (!window.viraBotTools) {
            askConfirm('بهینه‌سازی کامل (حالت ساده بدون AJAX) انجام شود؟', 'تأیید', function () {
                submitFallback('full');
            });
            return false;
        }
        var d = getDays();
        askConfirm(
            '⚠️ اجرای واقعی — این عملیات برگشت‌ناپذیر است.\n\n'
            + 'پرداخت منقضی/رد: قدیمی‌تر از ' + d.days_expire + ' روز\n'
            + 'پرداخت Unpaid: قدیمی‌تر از ' + d.days_unpaid + ' روز\n\n'
            + 'ادامه می‌دهید؟',
            'تأیید نهایی بهینه‌سازی',
            function () {
                askConfirm(
                    'آخرین تأیید: حذف داده‌ها انجام شود؟',
                    'اجرای واقعی',
                    function () {
                runWithProgress({
                    title: 'در حال بهینه‌سازی ربات…',
                    steps: FULL_STEPS,
                    which: 'all',
                    doneTitle: 'بهینه‌سازی کامل شد'
                }, function (opts) {
                    return window.viraBotTools.post('optimize_run', {
                        confirm: 'yes',
                        days_expire: d.days_expire,
                        days_unpaid: d.days_unpaid
                    }, opts);
                }).then(function (r) {
                    if (!r || !r.ok) {
                        showResult((r && r.msg) || 'خطا در بهینه‌سازی', false);
                        return;
                    }
                    var lines = [r.msg || 'انجام شد'];
                    if (r.details) {
                        lines.push('');
                        lines.push('سرویس تمام‌شده: ' + (r.details.expired_deleted || 0));
                        lines.push('سفارش بلااستفاده: ' + (r.details.junk_deleted || 0));
                        lines.push('پرداخت قدیمی: ' + (r.details.payments_deleted || 0));
                        lines.push('Unpaid قدیمی: ' + (r.details.unpaid_payments_deleted || 0));
                    }
                    showResult(lines.join('\n'), true);
                    setTimeout(function () { location.reload(); }, 2000);
                }).catch(function (err) {
                    showResult('خطا: ' + (err && err.message ? err.message : 'ارتباط با سرور'), false);
                });
                    });
            }
        );
        return false;
    }

    function runCleanup(ev) {
        if (ev && ev.preventDefault) ev.preventDefault();
        if (!window.viraBotTools) {
            askConfirm('پاکسازی پرداخت‌ها (حالت ساده) انجام شود؟', 'تأیید', function () {
                submitFallback('cleanup');
            });
            return false;
        }
        var d = getDays();
        askConfirm(
            'پرداخت‌های قدیمی حذف شوند؟\n\n'
            + 'منقضی/رد: ' + d.days_expire + ' روز\n'
            + 'Unpaid: ' + d.days_unpaid + ' روز',
            'تأیید پاکسازی',
            function () {
                runWithProgress({
                    title: 'در حال پاکسازی پرداخت‌ها…',
                    steps: PAY_STEPS,
                    which: 'cleanup',
                    doneTitle: 'پاکسازی انجام شد',
                    stepMs: 500
                }, function (opts) {
                    return window.viraBotTools.post('cleanup_expired', {
                        confirm: 'yes',
                        days_expire: d.days_expire,
                        days_unpaid: d.days_unpaid
                    }, opts);
                }).then(function (r) {
                    if (!r) return;
                    showResult(r.msg || (r.ok ? 'انجام شد' : 'خطا'), !!r.ok);
                    if (r.ok) setTimeout(function () { location.reload(); }, 1500);
                }).catch(function (err) {
                    showResult('خطا: ' + (err && err.message ? err.message : 'ارتباط با سرور'), false);
                });
            }
        );
        return false;
    }

    function refreshPreview() {
        if (!window.viraBotTools) return;
        var d = getDays();
        var le = el('lblDaysExpire');
        var lu = el('lblDaysUnpaid');
        if (le) le.textContent = d.days_expire;
        if (lu) lu.textContent = d.days_unpaid;
        var statsBox = el('optStats');
        if (statsBox) statsBox.classList.add('is-loading');
        var url = window.viraBotTools.base() + 'api/bot_tools.php?action=optimize_stats'
            + '&days_expire=' + encodeURIComponent(d.days_expire)
            + '&days_unpaid=' + encodeURIComponent(d.days_unpaid);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.stats) return;
                document.querySelectorAll('[data-stat]').forEach(function (node) {
                    var key = node.getAttribute('data-stat');
                    if (res.stats[key] !== undefined) node.textContent = res.stats[key];
                });
            })
            .finally(function () {
                if (statsBox) statsBox.classList.remove('is-loading');
            });
    }

    function init() {
        var se = el('daysExpireReject');
        var su = el('daysUnpaid');
        if (se) se.addEventListener('change', refreshPreview);
        if (su) su.addEventListener('change', refreshPreview);
        if (!window.viraBotTools) {
            showJsError('هشدار: اسکریپت API لود نشد. دکمه‌ها در حالت ساده (POST) کار می‌کنند.');
        }
    }

    window.addEventListener('load', init);

    return { runFull: runFull, runFullExecute: runFullExecute, runCleanup: runCleanup, refreshPreview: refreshPreview, init: init };
}());
JS;

include __DIR__ . '/inc/layout_head.php';
?>
<script>
window.ViraOptimizePage = window.ViraOptimizePage || {
    runFull: function (e) {
        if (e && e.preventDefault) e.preventDefault();
        alert('صفحه هنوز در حال بارگذاری است — چند ثانیه صبر کنید و دوباره کلیک کنید.');
        return false;
    },
    runCleanup: function (e) {
        if (e && e.preventDefault) e.preventDefault();
        alert('صفحه هنوز در حال بارگذاری است — چند ثانیه صبر کنید و دوباره کلیک کنید.');
        return false;
    }
};
</script>

<div class="opt-page fade-up" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($optFlash !== null): ?>
        <div class="opt-result" style="margin-bottom:14px;border-color:<?= $optFlashOk ? 'var(--ok,#22c55e)' : 'var(--no,#ef4444)' ?>">
            <?= htmlspecialchars($optFlash) ?>
        </div>
    <?php endif; ?>

    <div id="optJsError" class="opt-result" hidden style="margin-bottom:14px;border-color:var(--warn,#f59e0b)"></div>

    <div class="card opt-hero">
        <div class="opt-hero-title">بهینه‌سازی ربات</div>
        <p class="opt-hero-lede">سرویس‌های تمام‌شده و سفارش‌های بلااستفاده حذف می‌شوند. بازهٔ پاکسازی پرداخت‌ها قابل انتخاب است.</p>
    </div>

    <div class="card opt-preview-card">
        <div class="card-head">
            <div>
                <div class="card-title">پیش‌نمایش (Dry-run)</div>
                <div class="card-subtitle">تغییر بازهٔ زمانی، اعداد را به‌روز می‌کند — هنوز چیزی حذف نشده</div>
            </div>
            <div class="opt-preview-total">
                <span class="opt-preview-total-val"><?= (int) $previewTotal ?></span>
                <span class="opt-preview-total-label">مورد قابل حذف</span>
            </div>
        </div>
    </div>

    <div class="opt-stats opt-stats-lg" id="optStats">
        <div class="opt-stat is-warn">
            <div class="opt-stat-val" data-stat="expired_services"><?= (int) $preview['expired_services'] ?></div>
            <div class="opt-stat-label">سرویس تمام‌شده</div>
        </div>
        <div class="opt-stat is-warn">
            <div class="opt-stat-val" data-stat="junk_orders"><?= (int) $preview['junk_orders'] ?></div>
            <div class="opt-stat-label">سفارش بلااستفاده</div>
        </div>
        <div class="opt-stat is-pay">
            <div class="opt-stat-val" data-stat="old_payments"><?= (int) $preview['old_payments'] ?></div>
            <div class="opt-stat-label" id="lblOldPayments">پرداخت منقضی/رد (<span id="lblDaysExpire"><?= $previewDaysExpire ?></span>+ روز)</div>
        </div>
        <div class="opt-stat is-pay">
            <div class="opt-stat-val" data-stat="old_unpaid_payments"><?= (int) $preview['old_unpaid_payments'] ?></div>
            <div class="opt-stat-label" id="lblOldUnpaid">پرداخت Unpaid (<span id="lblDaysUnpaid"><?= $previewDaysUnpaid ?></span>+ روز)</div>
        </div>
        <div class="opt-stat is-safe">
            <div class="opt-stat-val" data-stat="active_invoices"><?= (int) $preview['active_invoices'] ?></div>
            <div class="opt-stat-label">سرویس فعال (حفظ)</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div class="card-title">تنظیم بازهٔ پاکسازی پرداخت</div></div>
        <div class="card-body">
            <div class="opt-days-row">
                <label class="opt-field">
                    <span class="field-label">پرداخت منقضی / رد شده (expire, reject)</span>
                    <select class="input" id="daysExpireReject" name="days_expire">
                        <?php foreach ($dayOptions as $d): ?>
                            <option value="<?= (int) $d ?>"<?= $d === $previewDaysExpire ? ' selected' : '' ?>><?= (int) $d ?> روز</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="opt-field">
                    <span class="field-label">پرداخت Unpaid (پرداخت‌نشده)</span>
                    <select class="input" id="daysUnpaid" name="days_unpaid">
                        <?php foreach ($dayOptions as $d): ?>
                            <option value="<?= (int) $d ?>"<?= $d === $previewDaysUnpaid ? ' selected' : '' ?>><?= (int) $d ?> روز</option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <p class="field-hint" style="margin-top:8px">گزینه‌ها: ۷، ۳۰ یا ۹۰ روز — فقط رکوردهای قدیمی‌تر از بازه انتخابی حذف می‌شوند.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div class="card-title">چه چیزهایی پاک می‌شود؟</div></div>
        <div class="card-body">
            <ul class="opt-list">
                <li>سرویس‌های تمام‌شده: پایان زمان، پایان حجم، removeTime، removevolume</li>
                <li>سفارش‌های پرداخت‌نشده، غیرفعال، حذف‌شده توسط ادمین/کاربر</li>
                <li>اکانت‌های تست غیرفعال</li>
                <li>تراکنش‌های منقضی/رد شده قدیمی‌تر از بازهٔ انتخابی</li>
                <li>درخواست پرداخت Unpaid قدیمی‌تر از بازهٔ انتخابی</li>
                <li>درخواست‌های لغو سرویس (تأیید/رد شده)</li>
                <li>کوتاه‌کردن فایل‌های error_log و log.txt (اگر بزرگ باشند)</li>
                <li>بهینه‌سازی جداول invoice، Payment_report، user</li>
            </ul>
            <ul class="opt-list" style="margin-top:14px">
                <li class="is-protected">کاربران، موجودی کیف پول، تنظیمات، محصولات، پنل‌ها</li>
                <li class="is-protected">سرویس‌های فعال (active، sendedwarn، send_on_hold)</li>
                <li class="is-protected">پرداخت‌های موفق (paid) — حفظ می‌شوند</li>
            </ul>

            <div class="opt-actions">
                <button type="button" class="btn btn-ghost" id="runPreviewRefresh" onclick="return ViraOptimizePage.refreshPreview && ViraOptimizePage.refreshPreview(event)"><?= icon('settings', 14) ?> بروزرسانی پیش‌نمایش</button>
                <button type="button" class="btn btn-primary" id="runFullOptimize" onclick="return ViraOptimizePage.runFull(event)"><?= icon('check', 14) ?> ادامه به اجرای واقعی</button>
            </div>

            <div id="optExecuteZone" class="opt-execute-zone" hidden>
                <p class="field-hint">پیش از اجرا از <a href="backup.php">پشتیبان‌گیری</a> بکاپ بگیرید.</p>
                <div class="opt-actions">
                    <button type="button" class="btn btn-no" id="runFullExecute" onclick="return ViraOptimizePage.runFullExecute(event)"><?= icon('trash', 14) ?> اجرای واقعی بهینه‌سازی</button>
                    <button type="button" class="btn btn-no btn-sm" id="runCleanupOnly" onclick="return ViraOptimizePage.runCleanup(event)">فقط پاکسازی پرداخت‌ها</button>
                </div>
            </div>

            <form method="post" id="optFormFull" style="display:none" aria-hidden="true">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="opt_action" value="full">
                <input type="hidden" name="confirm" value="yes">
                <input type="hidden" name="days_expire" value="<?= (int) $previewDaysExpire ?>">
                <input type="hidden" name="days_unpaid" value="<?= (int) $previewDaysUnpaid ?>">
            </form>
            <form method="post" id="optFormCleanup" style="display:none" aria-hidden="true">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="opt_action" value="cleanup">
                <input type="hidden" name="confirm" value="yes">
                <input type="hidden" name="days_expire" value="<?= (int) $previewDaysExpire ?>">
                <input type="hidden" name="days_unpaid" value="<?= (int) $previewDaysUnpaid ?>">
            </form>

            <div id="optimizeResult" class="opt-result" hidden></div>
            <p class="opt-safe-note">⚠️ این عملیات قابل بازگشت نیست. قبل از اجرا از منوی پشتیبان‌گیری، بکاپ بگیرید.</p>
        </div>
    </div>
</div>

<div id="panelProgressVeil" class="pp-veil" hidden aria-live="polite">
    <div class="pp-card">
        <div class="pp-head">
            <div class="pp-spinner" aria-hidden="true"></div>
            <div>
                <h3 class="pp-title" id="panelProgressTitle">در حال انجام…</h3>
                <p class="pp-sub" id="panelProgressSub"></p>
            </div>
        </div>
        <div class="pp-bar-wrap"><div class="pp-bar-fill" id="panelProgressFill"></div></div>
        <ul class="pp-steps" id="panelProgressSteps"></ul>
        <p class="pp-hint">لطفاً صبر کنید — پنجره را نبندید.</p>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php';
