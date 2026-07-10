<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/optimize_ops.php';
require_auth();

if (is_file(__DIR__ . '/../inc/panel_service_repair.php')) {
    require_once __DIR__ . '/../inc/panel_service_repair.php';
}

$dayOptions = vira_optimize_day_options();
$daysExpireDefault = 90;
$daysUnpaidDefault = 30;
$taskCatalog = vira_optimize_task_catalog();
$defaultTasks = vira_optimize_default_tasks();

$optFlash = null;
$optFlashOk = null;

$postTasks = [];
foreach (array_keys($taskCatalog) as $taskKey) {
    $postTasks['task_' . $taskKey] = !empty($_POST['task_' . $taskKey]);
}
$postTasks['telegram_backup'] = !isset($_POST['telegram_backup']) || $_POST['telegram_backup'] === '1';
$postTasks['days_expire'] = $_POST['days_expire'] ?? $daysExpireDefault;
$postTasks['days_unpaid'] = $_POST['days_unpaid'] ?? $daysUnpaidDefault;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opt_action'])) {
    csrf_check_post();
    if (($_POST['confirm'] ?? '') !== 'yes') {
        $optFlash = 'تأیید عملیات الزامی است.';
        $optFlashOk = false;
    } else {
        $optOptions = vira_optimize_parse_options($postTasks);
        $act = (string) $_POST['opt_action'];
        try {
            if ($act === 'full') {
                @ini_set('max_execution_time', '600');
                $botRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
                $details = vira_optimize_run($pdo, $botRoot, $optOptions);
                $optFlashOk = true;
                $backupNote = !empty($details['telegram_backup']['ok']) ? ' — بکاپ به تلگرام ارسال شد' : '';
                $optFlash = sprintf(
                    'بهینه‌سازی انجام شد — %d مورد حذف شد%s',
                    (int) $details['total_removed'],
                    $backupNote
                );
            } elseif ($act === 'cleanup') {
                $cleanup = vira_optimize_cleanup_payments(
                    $pdo,
                    (int) $optOptions['days_expire'],
                    (int) $optOptions['days_unpaid'],
                    !empty($optOptions['tasks']['failed_payments']),
                    !empty($optOptions['tasks']['unpaid_payments'])
                );
                $n = (int) $cleanup['payments_deleted'] + (int) $cleanup['unpaid_payments_deleted'];
                $optFlashOk = true;
                $optFlash = sprintf('پاکسازی پرداخت — %d رکورد', $n);
            } elseif ($act === 'repair_panel') {
                if (!function_exists('vira_repair_all_missing_panel_services')) {
                    throw new RuntimeException('ماژول بازیابی پنل در دسترس نیست.');
                }
                @ini_set('max_execution_time', '600');
                $repairStats = vira_repair_all_missing_panel_services();
                $optFlashOk = true;
                $optFlash = sprintf(
                    'sync/بازیابی پنل — %d بررسی، %d sync، %d بازیابی، %d خطا',
                    (int) ($repairStats['checked'] ?? 0),
                    (int) ($repairStats['synced'] ?? 0),
                    (int) ($repairStats['repaired'] ?? 0),
                    (int) ($repairStats['errors'] ?? 0)
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

$previewOptions = vira_optimize_parse_options($_SERVER['REQUEST_METHOD'] === 'POST' ? $postTasks : []);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach (array_keys($taskCatalog) as $taskKey) {
        $previewOptions['tasks'][$taskKey] = true;
    }
    $previewOptions['telegram_backup'] = true;
}
$previewDaysExpire = (int) $previewOptions['days_expire'];
$previewDaysUnpaid = (int) $previewOptions['days_unpaid'];
$preview = vira_optimize_preview($pdo, $previewOptions);
$previewTotal = (int) ($preview['selected_total'] ?? 0);
$previewCounts = $preview['counts'] ?? [];

$pageTitle = 'بهینه‌سازی';
$pageLede = 'پیش‌نمایش پیش‌فرض — اجرای واقعی فقط با تأیید دوباره.';
$activeNav = 'optimize';
$extraCss = ['css/optimize.css'];
$extraJs = [];

$footerInlineJs = <<<'JS'
window.ViraOptimizePage = (function () {
    var FULL_STEPS = [
        'ارسال بکاپ دیتابیس به تلگرام',
        'حذف موارد انتخاب‌شده',
        'پاکسازی پرداخت‌های قدیمی',
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

    function getSelectedTasks() {
        var out = { telegram_backup: el('taskTelegramBackup') && el('taskTelegramBackup').checked ? '1' : '0' };
        document.querySelectorAll('[data-opt-task]').forEach(function (cb) {
            if (cb.checked) {
                out['task_' + cb.getAttribute('data-opt-task')] = '1';
            }
        });
        return out;
    }

    function mergeTaskPayload(base) {
        var tasks = getSelectedTasks();
        var k;
        for (k in tasks) {
            if (Object.prototype.hasOwnProperty.call(tasks, k)) {
                base[k] = tasks[k];
            }
        }
        return base;
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
        form.querySelectorAll('[data-form-task]').forEach(function (inp) { inp.remove(); });
        var tasks = getSelectedTasks();
        Object.keys(tasks).forEach(function (k) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = k;
            inp.value = tasks[k];
            inp.setAttribute('data-form-task', '1');
            form.appendChild(inp);
        });
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
                    return window.viraBotTools.post('optimize_run', mergeTaskPayload({
                        confirm: 'yes',
                        days_expire: d.days_expire,
                        days_unpaid: d.days_unpaid
                    }), opts);
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
        var qs = mergeTaskPayload({
            days_expire: d.days_expire,
            days_unpaid: d.days_unpaid
        });
        var url = window.viraBotTools.base() + 'api/bot_tools.php?action=optimize_stats';
        var parts = [];
        Object.keys(qs).forEach(function (k) {
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(qs[k]));
        });
        url += (parts.length ? '?' + parts.join('&') : '');
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.stats) return;
                var st = res.stats;
                document.querySelectorAll('[data-stat]').forEach(function (node) {
                    var key = node.getAttribute('data-stat');
                    if (st[key] !== undefined) node.textContent = st[key];
                });
                if (st.selected_total !== undefined) {
                    var tot = document.querySelector('.opt-preview-total-val');
                    if (tot) tot.textContent = String(st.selected_total);
                }
                if (st.counts) {
                    Object.keys(st.counts).forEach(function (k) {
                        var elc = document.querySelector('[data-count-task="' + k + '"]');
                        if (elc) elc.textContent = String(st.counts[k]);
                    });
                }
            })
            .finally(function () {
                if (statsBox) statsBox.classList.remove('is-loading');
            });
    }

    function runPanelRepair(ev) {
        if (ev && ev.preventDefault) ev.preventDefault();
        var msg = 'سرویس‌هایی که در پنل VPN حذف شده‌اند با همان لینک اشتراک و حجم/زمان باقی‌مانده بازیابی می‌شوند.\n\n'
            + 'این عملیات ممکن است چند دقیقه طول بکشد. ادامه می‌دهید؟';
        askConfirm(msg, 'بازیابی سرویس‌های پنل', function () {
            if (!window.viraBotTools) {
                var form = el('optFormRepairPanel');
                if (form) form.submit();
                return;
            }
            runWithProgress({
                title: 'در حال بازیابی سرویس‌های گم‌شده در پنل…',
                steps: ['بررسی سفارش‌ها', 'اتصال به پنل‌ها', 'بازیابی کاربران حذف‌شده'],
                which: 'repair',
                doneTitle: 'بازیابی انجام شد',
                stepMs: 900
            }, function (opts) {
                return window.viraBotTools.post('repair_panel_services', { confirm: 'yes' }, opts);
            }).then(function (r) {
                if (!r || !r.ok) {
                    showResult((r && r.msg) || 'خطا در بازیابی', false);
                    return;
                }
                var lines = [r.msg || 'انجام شد'];
                if (r.stats) {
                    lines.push('');
                    lines.push('بررسی: ' + (r.stats.checked || 0));
                    lines.push('بازیابی: ' + (r.stats.repaired || 0));
                    lines.push('موجود در پنل: ' + (r.stats.skipped || 0));
                    lines.push('خطا: ' + (r.stats.errors || 0));
                }
                showResult(lines.join('\n'), true);
            }).catch(function (err) {
                showResult('خطا: ' + (err && err.message ? err.message : 'ارتباط با سرور'), false);
            });
        });
        return false;
    }

    function init() {
        var se = el('daysExpireReject');
        var su = el('daysUnpaid');
        if (se) se.addEventListener('change', refreshPreview);
        if (su) su.addEventListener('change', refreshPreview);
        document.querySelectorAll('[data-opt-task], #taskTelegramBackup').forEach(function (cb) {
            cb.addEventListener('change', refreshPreview);
        });
        if (!window.viraBotTools) {
            showJsError('هشدار: اسکریپت API لود نشد. دکمه‌ها در حالت ساده (POST) کار می‌کنند.');
        }
    }

    window.addEventListener('load', init);

    return { runFull: runFull, runFullExecute: runFullExecute, runCleanup: runCleanup, runPanelRepair: runPanelRepair, refreshPreview: refreshPreview, init: init };
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
        <p class="opt-hero-lede">مشاهده وضعیت در ربات = فقط همان سرویس. cron ساعتی = همه سفارش‌ها. بهینه‌سازی با انتخاب ادمین + بکاپ تلگرام.</p>
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
            <p class="field-hint" style="margin-top:8px">بازه: ۷، ۳۰، ۹۰، ۱۸۰ یا ۳۶۵ روز — فقط رکوردهای قدیمی‌تر حذف می‌شوند.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div class="card-title">انتخاب موارد حذف (قابل تنظیم)</div></div>
        <div class="card-body">
            <p class="field-hint" style="margin-top:0">فقط گزینه‌های تیک‌خورده در بهینه‌سازی اجرا می‌شوند. پیش از اجرا بکاپ SQL به تلگرام ارسال می‌شود.</p>
            <div class="opt-task-grid" style="display:grid;gap:10px;margin-top:12px">
                <?php foreach ($taskCatalog as $taskKey => $meta): ?>
                <label class="opt-task-row" style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid var(--bd);border-radius:10px;cursor:pointer">
                    <input type="checkbox" data-opt-task="<?= htmlspecialchars($taskKey) ?>" checked style="margin-top:3px">
                    <span>
                        <strong><?= htmlspecialchars($meta['label']) ?></strong>
                        <span class="field-hint" style="display:block;margin-top:2px">پیش‌نمایش: <span data-count-task="<?= htmlspecialchars($taskKey) ?>"><?= (int) ($previewCounts[$taskKey] ?? 0) ?></span> مورد</span>
                    </span>
                </label>
                <?php endforeach; ?>
                <label class="opt-task-row" style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid var(--ok);border-radius:10px;cursor:pointer;background:rgba(34,197,94,.06)">
                    <input type="checkbox" id="taskTelegramBackup" checked style="margin-top:3px">
                    <span>
                        <strong>ارسال بکاپ دیتابیس به تلگرام قبل از اجرا</strong>
                        <span class="field-hint" style="display:block;margin-top:2px">به کانال گزارش (موضوع backupfile) — اگر ناموفق باشد بهینه‌سازی لغو می‌شود</span>
                    </span>
                </label>
            </div>
            <ul class="opt-list" style="margin-top:16px">
                <li class="is-protected">کاربران، موجودی، تنظیمات، محصولات، پنل‌ها — حذف نمی‌شوند</li>
                <li class="is-protected">سرویس‌های فعال (active، sendedwarn، send_on_hold) — حذف نمی‌شوند</li>
                <li class="is-protected">پرداخت‌های موفق (paid) — حذف نمی‌شوند</li>
            </ul>

            <div class="opt-actions">
                <button type="button" class="btn btn-ghost" id="runPreviewRefresh" onclick="return ViraOptimizePage.refreshPreview && ViraOptimizePage.refreshPreview(event)"><?= icon('settings', 14) ?> بروزرسانی پیش‌نمایش</button>
                <button type="button" class="btn btn-primary" id="runFullOptimize" onclick="return ViraOptimizePage.runFull(event)"><?= icon('check', 14) ?> ادامه به اجرای واقعی</button>
            </div>

            <div id="optExecuteZone" class="opt-execute-zone" hidden>
                <p class="field-hint">بکاپ خودکار به تلگرام ارسال می‌شود (اگر تیک خورده باشد). سپس موارد انتخاب‌شده حذف می‌شوند.</p>
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
            <p class="opt-safe-note">⚠️ حذف برگشت‌ناپذیر است. بکاپ تلگرام را فعال نگه دارید.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">بازیابی سرویس‌های پنل VPN</div>
                <div class="card-subtitle">سرویس‌های حذف‌شده از پنل با همان username و لینک ذخیره‌شده دوباره ساخته می‌شوند</div>
            </div>
        </div>
        <div class="card-body">
            <ul class="opt-list">
                <li>فقط سفارش‌های فعال/قابل بازیابی بررسی می‌شوند (نه سرویس تست)</li>
                <li>اگر کاربر در پنل موجود باشد، رد می‌شود</li>
                <li>از همان اطلاعات سفارش (حجم، زمان، لینک) برای بازیابی استفاده می‌شود</li>
            </ul>
            <p class="field-hint" style="margin-top:10px">cron ساعتی (<code>invoice_panel_sync.php</code>) همه سفارش‌ها را sync می‌کند — جدا از وقتی کاربر وضعیت می‌بیند.</p>
            <div class="opt-actions" style="margin-top:14px">
                <button type="button" class="btn btn-primary" id="runPanelRepair" onclick="return ViraOptimizePage.runPanelRepair && ViraOptimizePage.runPanelRepair(event)"><?= icon('server', 14) ?> بازیابی همه سرویس‌های گم‌شده</button>
            </div>
            <form method="post" id="optFormRepairPanel" style="display:none" aria-hidden="true">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="opt_action" value="repair_panel">
                <input type="hidden" name="confirm" value="yes">
            </form>
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
