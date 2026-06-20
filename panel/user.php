<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/user_manage_ops.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: users.php');
    exit;
}

$user = db_fetch($pdo, "SELECT * FROM user WHERE id = ?", [$id]);
if (!$user) {
    flash('error', 'کاربر یافت نشد.');
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $adminUser = (string) ($_SESSION['admin_user'] ?? 'panel');
    $result = um_handle_post($pdo, $id, $_POST, $adminUser);
    flash($result['ok'] ? 'success' : 'error', $result['msg']);
    header("Location: user.php?id=$id");
    exit;
}

$invoices = [];
$payments = [];
$referrals = [];

try {
    $invoices = db_fetchAll($pdo, "SELECT * FROM invoice WHERE id_user = ? ORDER BY time_sell DESC LIMIT 30", [$id]);
} catch (Exception $e) {
}

try {
    $payments = db_fetchAll($pdo, "SELECT * FROM Payment_report WHERE id_user = ? ORDER BY time DESC LIMIT 20", [$id]);
} catch (Exception $e) {
}

try {
    $referrals = db_fetchAll($pdo, "SELECT id, username, namecustom, Balance, register, agent FROM user WHERE affiliates = ? ORDER BY register DESC LIMIT 20", [$id]);
} catch (Exception $e) {
}

$balance = (int) ($user['Balance'] ?? 0);
$totalSpent = array_sum(array_column($invoices, 'price_product'));
$activeServices = count(array_filter($invoices, fn($inv) => ($inv['Status'] ?? '') === 'active'));
$expiredServices = count(array_filter($invoices, fn($inv) => in_array($inv['Status'] ?? '', ['end_of_time', 'end_of_volume', 'expired'])));
$paidCount = count(array_filter($payments, fn($p) => in_array($p['payment_Status'] ?? '', ['paid', 'success'])));
$convRate = count($payments) > 0 ? round($paidCount / count($payments) * 100) : 0;

$agent = $user['agent'] ?? 'f';
$isBlocked = ($user['User_Status'] ?? '') === 'block';
$fullName = $user['namecustom'] ?? '';
if ($fullName === 'none')
    $fullName = '';
$username = $user['username'] ?? '';
if ($username === 'none')
    $username = '';
$initials = mb_strtoupper(mb_substr($fullName ?: ($username ?: 'U'), 0, 1, 'UTF-8'), 'UTF-8');

$isVerified = ((string) ($user['verify'] ?? '0') === '1');
$cardShown = ((string) ($user['cardpayment'] ?? '0') === '1');
$channelOk = (($user['joinchannel'] ?? '') === 'active');
$pricediscount = (int) ($user['pricediscount'] ?? 0);
$limitUsertest = (string) ($user['limit_usertest'] ?? '—');
$rollStatus = (string) ($user['roll_Status'] ?? '');
$rollLabels = ['1' => 'تأیید شده', '0' => 'رد شده'];
$rollLabel = $rollLabels[$rollStatus] ?? '—';

$isAgent = in_array($agent, ['n', 'n2'], true);

$csrf = csrf_token();
$actionBase = 'user_action.php?_csrf=' . urlencode($csrf) . '&id=' . $id . '&back=user.php';

$pageTitle = $fullName ?: ($username ? '@' . $username : 'کاربر #' . $id);
$activeNav = 'users';
$showPageHead = false;
$extraCss = ['css/user-manage.css'];
$extraJs = ['js/user-manage.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div id="um-user-meta" data-user-balance="<?= (int) $balance ?>" hidden></div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px"
    class="fade-up">
    <a href="users.php" class="btn btn-ghost btn-sm"><?= icon('arrow-left', 14) ?> فهرست کاربران</a>
    <?php if ($username): ?>
        <a href="https://t.me/<?= htmlspecialchars($username) ?>" target="_blank" rel="noopener"
            class="btn btn-ghost btn-sm">
            <?= icon('eye', 13) ?> تلگرام
        </a>
    <?php endif; ?>
</div>

<div class="stats u-stats fade-up" style="margin-bottom:18px">
    <div class="stat fade-up">
        <div class="stat-label">موجودی</div>
        <div class="stat-num"><?= number_format($balance) ?><small>ت</small></div>
        <div class="stat-meta">کیف پول</div>
    </div>
    <div class="stat ok fade-up d1">
        <div class="stat-label">مجموع خرید</div>
        <div class="stat-num">
            <?= $totalSpent >= 1_000_000
                ? number_format($totalSpent / 1_000_000, 1) . '<small>M ت</small>'
                : number_format($totalSpent) . '<small>ت</small>' ?>
        </div>
        <div class="stat-meta"><?= count($invoices) ?> سفارش</div>
    </div>
    <div class="stat warn fade-up d2">
        <div class="stat-label">سرویس فعال</div>
        <div class="stat-num"><?= $activeServices ?></div>
        <div class="stat-meta"><?= $expiredServices ?> منقضی</div>
    </div>
    <div class="stat fade-up d3">
        <div class="stat-label">نرخ پرداخت</div>
        <div class="stat-num"><?= $convRate ?>%</div>
        <div class="stat-meta"><?= $paidCount ?> موفق از <?= count($payments) ?></div>
    </div>
</div>

<div class="profile-grid u-profile-grid">

    <div class="u-sidebar" style="display:flex;flex-direction:column;gap:12px">

        <div class="card fade-up">
            <div class="profile-head">
                <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="profile-name"><?= htmlspecialchars($fullName ?: 'بدون نام') ?></div>
                <?php if ($username): ?>
                    <div class="profile-handle">@<?= htmlspecialchars($username) ?></div>
                <?php endif; ?>
                <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                    <span class="tag <?= $isBlocked ? 'tag-no' : 'tag-ok' ?>">
                        <?= $isBlocked ? 'مسدود' : 'فعال' ?>
                    </span>
                    <span class="tag <?= user_role_tag($agent) ?>">
                        <?= user_role_label($agent) ?>
                    </span>
                </div>
            </div>

            <div class="kv-list">
                <div class="kv">
                    <span class="kv-key">آیدی تلگرام</span>
                    <span class="kv-val cm"><?= htmlspecialchars($user['id']) ?></span>
                </div>
                <?php if ($fullName): ?>
                    <div class="kv">
                        <span class="kv-key">نام سفارشی</span>
                        <span class="kv-val"><?= htmlspecialchars($fullName) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['number']) && $user['number'] !== 'none'): ?>
                    <div class="kv">
                        <span class="kv-key">شماره</span>
                        <span class="kv-val cm"><?= htmlspecialchars($user['number']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="kv">
                    <span class="kv-key">موجودی</span>
                    <span class="kv-val" style="color:var(--ac)"><?= number_format($balance) ?> ت</span>
                </div>
                <div class="kv">
                    <span class="kv-key">گروه کاربری</span>
                    <span class="kv-val">
                        <span class="tag <?= user_role_tag($agent) ?>"><?= user_role_label($agent) ?></span>
                        <span class="cm cf"
                            style="margin-right:6px;font-size:.72rem"><?= htmlspecialchars($agent) ?></span>
                    </span>
                </div>
                <div class="kv">
                    <span class="kv-key">ثبت‌نام</span>
                    <span class="kv-val"><?= safe_date($user['register'] ?? null) ?></span>
                </div>
                <?php if (!empty($user['affiliates']) && $user['affiliates'] !== '0'): ?>
                    <div class="kv">
                        <span class="kv-key">معرف</span>
                        <span class="kv-val cm" style="color:var(--ac)"><?= htmlspecialchars($user['affiliates']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['affiliatescount'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key">زیرمجموعه</span>
                        <span class="kv-val"><?= number_format((int) $user['affiliatescount']) ?> نفر</span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['score'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key">امتیاز</span>
                        <span class="kv-val" style="color:var(--warn)">⭐ <?= number_format((int) $user['score']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['expire'])): ?>
                    <div class="kv">
                        <span class="kv-key">انقضای حساب</span>
                        <span class="kv-val"
                            style="<?= is_numeric($user['expire']) && (int) $user['expire'] < time() ? 'color:var(--no)' : '' ?>">
                            <?= safe_date($user['expire']) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['codeInvitation'])): ?>
                    <div class="kv">
                        <span class="kv-key">کد دعوت</span>
                        <span class="kv-val cm"
                            style="color:var(--ac)"><?= htmlspecialchars($user['codeInvitation']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['message_count'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key">تعداد پیام</span>
                        <span class="kv-val cn"><?= number_format((int) $user['message_count']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="kv">
                    <span class="kv-key">احراز هویت</span>
                    <span class="kv-val"><span class="tag <?= $isVerified ? 'tag-ok' : 'tag-plain' ?>"><?= $isVerified ? 'احراز شده' : 'احراز نشده' ?></span></span>
                </div>
                <div class="kv">
                    <span class="kv-key">درصد تخفیف</span>
                    <span class="kv-val cn"><?= $pricediscount ?>٪</span>
                </div>
                <div class="kv">
                    <span class="kv-key">محدودیت تست</span>
                    <span class="kv-val cn"><?= htmlspecialchars($limitUsertest) ?></span>
                </div>
                <div class="kv">
                    <span class="kv-key">نمایش کارت</span>
                    <span class="kv-val"><span class="tag <?= $cardShown ? 'tag-ok' : 'tag-plain' ?>"><?= $cardShown ? 'فعال' : 'غیرفعال' ?></span></span>
                </div>
                <div class="kv">
                    <span class="kv-key">عضویت کانال</span>
                    <span class="kv-val"><span class="tag <?= $channelOk ? 'tag-ok' : 'tag-warn' ?>"><?= $channelOk ? 'تأیید شده' : 'تأیید نشده' ?></span></span>
                </div>
                <?php if ($rollLabel !== '—'): ?>
                    <div class="kv">
                        <span class="kv-key">تأیید قوانین</span>
                        <span class="kv-val"><?= htmlspecialchars($rollLabel) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card fade-up d1">
            <div class="card-head">
                <div class="card-title">دسترسی سریع</div>
            </div>
            <div style="padding:12px;display:flex;flex-direction:column;gap:6px">
                <button class="btn btn-primary btn-sm" style="justify-content:center" onclick="umOpenWallet('add')">
                    <?= icon('plus', 13) ?> مدیریت موجودی
                </button>
                <a href="service.php?q=<?= urlencode((string) $id) ?>" class="btn btn-ghost btn-sm" style="justify-content:center">
                    <?= icon('server', 13) ?> سرویس‌ها
                </a>
                <a href="invoice.php?q=<?= urlencode((string) $id) ?>" class="btn btn-ghost btn-sm" style="justify-content:center">
                    <?= icon('invoice', 13) ?> همه سفارشات
                </a>
            </div>
        </div>

    </div>

    <div class="u-main-col" style="display:flex;flex-direction:column;gap:16px">

        <div class="card fade-up um-manage-card">
            <div class="card-head">
                <div class="card-title">مرکز مدیریت کاربر</div>
                <button type="button" class="btn btn-primary btn-sm" onclick="umOpenWallet('add')">
                    <?= icon('plus', 13) ?> موجودی
                </button>
            </div>
            <div class="um-cat-bar">
                <button type="button" class="um-cat-btn is-active" data-cat="fin" onclick="umSwitchCategory('fin')">💰 مالی</button>
                <button type="button" class="um-cat-btn" data-cat="st" onclick="umSwitchCategory('st')">👤 وضعیت</button>
                <button type="button" class="um-cat-btn" data-cat="ord" onclick="umSwitchCategory('ord')">🛒 سفارش</button>
                <button type="button" class="um-cat-btn" data-cat="card" onclick="umSwitchCategory('card')">💳 کارت</button>
                <button type="button" class="um-cat-btn" data-cat="ag" onclick="umSwitchCategory('ag')">👥 نمایندگی</button>
                <button type="button" class="um-cat-btn" data-cat="msg" onclick="umSwitchCategory('msg')">✉️ پیام</button>
            </div>

            <div class="um-actions um-cat-pane" data-cat="fin">
                <button type="button" class="um-action" onclick="umOpenWallet('add')">
                    <strong>مدیریت موجودی</strong>
                    <span>افزایش، کسر، تنظیم یا صفر</span>
                </button>
                <button type="button" class="um-action" onclick="openModal('discountModal')">
                    <strong>درصد تخفیف</strong>
                    <span>فعلی: <?= $pricediscount ?>٪</span>
                </button>
                <button type="button" class="um-action" onclick="switchTab('pay')">
                    <strong>تراکنش‌ها</strong>
                    <span><?= count($payments) ?> رکورد</span>
                </button>
            </div>

            <div class="um-actions um-cat-pane" data-cat="st" style="display:none">
                <?php if ($isBlocked): ?>
                    <a href="<?= $actionBase ?>&action=unblock" class="um-action um-action-ok" data-confirm="رفع مسدودیت این کاربر؟">
                        <strong>رفع مسدودیت</strong><span>فعال‌سازی مجدد</span>
                    </a>
                <?php else: ?>
                    <a href="<?= $actionBase ?>&action=block" class="um-action um-action-danger" data-confirm="مسدود کردن کاربر؟">
                        <strong>مسدود کردن</strong><span>قطع دسترسی به ربات</span>
                    </a>
                <?php endif; ?>
                <?php if (!$isVerified): ?>
                    <a href="<?= $actionBase ?>&action=verify" class="um-action um-action-ok" data-confirm="احراز هویت کاربر؟">
                        <strong>احراز هویت</strong><span>تأیید دستی حساب</span>
                    </a>
                <?php else: ?>
                    <a href="<?= $actionBase ?>&action=unverify" class="um-action" data-confirm="لغو احراز کاربر؟">
                        <strong>لغو احراز</strong><span>برگشت به حالت عادی</span>
                    </a>
                <?php endif; ?>
                <a href="<?= $actionBase ?>&action=confirm_number" class="um-action" data-confirm="تأیید شماره موبایل؟">
                    <strong>تأیید شماره</strong><span>بدون OTP</span>
                </a>
                <?php if (!$channelOk): ?>
                    <a href="<?= $actionBase ?>&action=confirm_channel" class="um-action" data-confirm="تأیید عضویت کانال؟">
                        <strong>تأیید کانال</strong><span>معاف از جوین اجباری</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="um-actions um-cat-pane" data-cat="ord" style="display:none">
                <a href="service.php?q=<?= urlencode((string) $id) ?>" class="um-action">
                    <strong>سرویس‌ها</strong><span><?= $activeServices ?> فعال</span>
                </a>
                <a href="invoice.php?q=<?= urlencode((string) $id) ?>" class="um-action">
                    <strong>همه سفارشات</strong><span><?= count($invoices) ?> مورد</span>
                </a>
                <button type="button" class="um-action" onclick="openModal('testLimitModal')">
                    <strong>محدودیت تست</strong><span>فعلی: <?= htmlspecialchars($limitUsertest) ?></span>
                </button>
            </div>

            <div class="um-actions um-cat-pane" data-cat="card" style="display:none">
                <?php if (!$cardShown): ?>
                    <a href="<?= $actionBase ?>&action=show_card" class="um-action um-action-ok" data-confirm="فعال‌سازی پرداخت کارت؟">
                        <strong>فعال‌سازی کارت</strong><span>نمایش شماره کارت</span>
                    </a>
                <?php else: ?>
                    <a href="<?= $actionBase ?>&action=hide_card" class="um-action" data-confirm="غیرفعال کردن کارت؟">
                        <strong>غیرفعال‌سازی کارت</strong><span>مخفی کردن شماره کارت</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="um-actions um-cat-pane" data-cat="ag" style="display:none">
                <button type="button" class="um-action" onclick="openModal('roleModal')">
                    <strong>گروه کاربری</strong><span><?= user_role_label($agent) ?></span>
                </button>
                <?php if (!$isAgent): ?>
                    <button type="button" class="um-action" onclick="openModal('agentModal')">
                        <strong>افزودن نماینده</strong><span>نوع n یا n2</span>
                    </button>
                <?php else: ?>
                    <a href="<?= $actionBase ?>&action=remove_agent" class="um-action um-action-danger" data-confirm="حذف نمایندگی کاربر؟">
                        <strong>حذف نماینده</strong><span>بازگشت به کاربر عادی</span>
                    </a>
                    <?php if ($agent === 'n2'): ?>
                        <button type="button" class="um-action" onclick="openModal('maxbuyModal')">
                            <strong>سقف خرید نماینده</strong><span>فعلی: <?= htmlspecialchars((string) ($user['maxbuyagent'] ?? '0')) ?></span>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="<?= $actionBase ?>&action=remove_affiliate" class="um-action" data-confirm="خارج کردن از زیرمجموعه؟">
                    <strong>خروج از زیرمجموعه</strong><span>حذف معرف</span>
                </a>
                <a href="<?= $actionBase ?>&action=remove_affiliates" class="um-action um-action-danger" data-confirm="حذف همه زیرمجموعه‌های این کاربر؟">
                    <strong>حذف زیرمجموعه‌ها</strong><span><?= number_format((int) ($user['affiliatescount'] ?? 0)) ?> نفر</span>
                </a>
            </div>

            <div class="um-actions um-cat-pane" data-cat="msg" style="display:none">
                <button type="button" class="um-action" onclick="openModal('messageModal')">
                    <strong>ارسال پیام</strong><span>از طرف ادمین در تلگرام</span>
                </button>
            </div>
        </div>

        <div class="card fade-up">
            <div class="card-head">
                <div class="u-tab-bar" style="display:flex;gap:4px;background:var(--sf2);border-radius:7px;padding:3px">
                    <button class="btn btn-sm" id="tabOrders" onclick="switchTab('orders')"
                        style="background:var(--ac);color:#fff;border-radius:5px;font-size:.75rem">
                        سفارشات
                    </button>
                    <button class="btn btn-sm" id="tabPay" onclick="switchTab('pay')"
                        style="background:transparent;color:var(--mute);border-radius:5px;font-size:.75rem;border:none">
                        تراکنش‌ها
                    </button>
                    <?php if (count($referrals) > 0): ?>
                        <button class="btn btn-sm" id="tabRefs" onclick="switchTab('refs')"
                            style="background:transparent;color:var(--mute);border-radius:5px;font-size:.75rem;border:none">
                            زیرمجموعه
                            <span
                                style="background:var(--acs);color:var(--ac);padding:1px 6px;border-radius:99px;font-size:.65rem">
                                <?= count($referrals) ?>
                            </span>
                        </button>
                    <?php endif; ?>
                </div>
                <a href="invoice.php?q=<?= urlencode($id) ?>" class="btn-link" style="font-size:.75rem">همه ←</a>
            </div>

            <div id="paneOrders">
                <div class="tbl-wrap">
                    <table class="tbl-lg">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>قیمت</th>
                                <th>حجم</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty" style="padding:30px">
                                            <p>سفارشی ثبت نشده</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else:
                                $statusMap = [
                                    'active' => ['tag-ok', 'فعال'],
                                    'end_of_time' => ['tag-warn', 'نزدیک به پایان زمان'],
                                    'end_of_volume' => ['tag-no', 'نزدیک به پایان حجم'],
                                    'sendedwarn' => ['tag-warn', 'اعلان همگی ارسال شده'],
                                    'send_on_hold' => ['tag-plain', 'در انتظار'],
                                    'unpiad' => ['tag-plain', 'پرداخت نشده'],
                                ];
                                foreach ($invoices as $inv):
                                    [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                                    ?>
                                    <tr>
                                        <td class="cs"
                                            style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                            <?= htmlspecialchars($inv['name_product'] ?? '—') ?>
                                        </td>
                                        <td class="cn cs" style="white-space:nowrap">
                                            <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf">ت</span>
                                        </td>
                                        <td class="cn cf"><?= htmlspecialchars($inv['Volume'] ?? '—') ?></td>
                                        <td class="cf" style="white-space:nowrap">
                                            <?= safe_date($inv['time_sell'] ?? null, 'Y/m/d') ?>
                                        </td>
                                        <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="panePay" style="display:none">
                <div class="tbl-wrap">
                    <table class="tbl-md">
                        <thead>
                            <tr>
                                <th>مبلغ</th>
                                <th>روش</th>
                                <th>تاریخ</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty" style="padding:30px">
                                            <p>تراکنشی ثبت نشده</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else:
                                $methodLabels = [
                                    'cart to cart' => 'کارت→کارت',
                                    'add balance by admin' => 'افزایش ادمین',
                                    'low balance by admin' => 'کسر ادمین',
                                    'zarinpal' => 'زرین‌پال',
                                    'aqayepardakht' => 'آقای پرداخت',
                                    'plisio' => 'Plisio',
                                    'nowpayment' => 'NowPayment',
                                    'Star Telegram' => 'استار تلگرام',
                                    'Currency Rial 1' => 'ریالی ۱',
                                    'Currency Rial tow' => 'ریالی ۲',
                                    'Currency Rial 3' => 'ریالی ۳',
                                    'arze digital offline' => 'ارز دیجیتال',
                                ];
                                $payStatusMap = [
                                    'paid' => ['tag-ok', 'موفق'],
                                    'Unpaid' => ['tag-no', 'ناموفق'],
                                    'expire' => ['tag-plain', 'منقضی'],
                                    'reject' => ['tag-no', 'رد'],
                                    'waiting' => ['tag-warn', 'در انتظار'],
                                    'pending' => ['tag-warn', 'در انتظار'],
                                ];
                                foreach ($payments as $p):
                                    $payStatus = $p['payment_Status'] ?? '';
                                    [$tagClass, $label] = $payStatusMap[$payStatus] ?? ['tag-plain', $payStatus ?: '—'];
                                    $method = $methodLabels[$p['Payment_Method'] ?? ''] ?? ($p['Payment_Method'] ?? '—');
                                    ?>
                                    <tr>
                                        <td class="cn cs" style="white-space:nowrap">
                                            <?= number_format((int) ($p['price'] ?? 0)) ?> <span class="cf">ت</span>
                                        </td>
                                        <td style="font-size:.82rem"><?= htmlspecialchars($method) ?></td>
                                        <td class="cf" style="white-space:nowrap">
                                            <?= safe_date($p['time'] ?? null, 'Y/m/d H:i') ?>
                                        </td>
                                        <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (count($referrals) > 0): ?>
                <div id="paneRefs" style="display:none">
                    <div class="tbl-wrap">
                        <table class="tbl-md">
                            <thead>
                                <tr>
                                    <th>آیدی</th>
                                    <th>نام</th>
                                    <th>موجودی</th>
                                    <th>گروه</th>
                                    <th>ثبت‌نام</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referrals as $ref):
                                    $refName = $ref['namecustom'] ?? '';
                                    if ($refName === 'none')
                                        $refName = '';
                                    $refUname = $ref['username'] ?? '';
                                    if ($refUname === 'none')
                                        $refUname = '';
                                    $refAgent = $ref['agent'] ?? 'f';
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="user.php?id=<?= (int) $ref['id'] ?>" class="cm" style="color:var(--ac)">
                                                <?= htmlspecialchars($ref['id']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($refName): ?>
                                                <span class="cs"><?= htmlspecialchars(trunc($refName, 16)) ?></span>
                                            <?php elseif ($refUname): ?>
                                                <span class="cm"
                                                    style="color:var(--ac)">@<?= htmlspecialchars(trunc($refUname, 14)) ?></span>
                                            <?php else: ?>
                                                <span class="cf">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cn" style="white-space:nowrap">
                                            <?= number_format((int) ($ref['Balance'] ?? 0)) ?> <span class="cf">ت</span>
                                        </td>
                                        <td>
                                            <span class="tag <?= user_role_tag($refAgent) ?>">
                                                <?= user_role_label($refAgent) ?>
                                            </span>
                                        </td>
                                        <td class="cf"><?= safe_date($ref['register'] ?? null, 'm/d') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<div class="modal-veil" id="walletModal">
    <div class="modal">
        <div class="modal-head">
            <h3>مدیریت موجودی</h3>
            <button class="modal-x" onclick="closeModal('walletModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST" id="walletForm">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="wallet">
                <input type="hidden" name="wallet_mode" value="add">
                <div class="um-wallet-tabs">
                    <button type="button" class="um-wallet-tab is-active" data-mode="add" onclick="umSetWalletMode('add')">افزایش</button>
                    <button type="button" class="um-wallet-tab" data-mode="deduct" onclick="umSetWalletMode('deduct')">کسر</button>
                    <button type="button" class="um-wallet-tab" data-mode="set" onclick="umSetWalletMode('set')">تنظیم</button>
                    <button type="button" class="um-wallet-tab" data-mode="zero" onclick="umSetWalletMode('zero')">صفر</button>
                </div>
                <div id="walletAmountWrap" class="field">
                    <label>مبلغ (تومان)</label>
                    <input type="number" name="amount" class="input" placeholder="مثلاً ۵۰٬۰۰۰" min="1000" required>
                    <span class="field-hint">حداقل ۱٬۰۰۰ — حداکثر ۱۰۰٬۰۰۰٬۰۰۰ تومان</span>
                </div>
                <div id="walletZeroNote" class="field-hint" style="display:none;color:var(--warn)">
                    موجودی فعلی <?= number_format($balance) ?> تومان به صفر تنظیم می‌شود (بدون ثبت تراکنش).
                </div>
                <div class="um-wallet-preview" id="walletPreview"></div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary" id="walletSubmitBtn">افزودن به موجودی</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('walletModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="discountModal">
    <div class="modal">
        <div class="modal-head">
            <h3>درصد تخفیف</h3>
            <button class="modal-x" onclick="closeModal('discountModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_discount">
                <div class="field">
                    <label>درصد (۰ تا ۱۰۰)</label>
                    <input type="number" name="discount" class="input" value="<?= $pricediscount ?>" min="0" max="100" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary">ذخیره</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('discountModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="testLimitModal">
    <div class="modal">
        <div class="modal-head">
            <h3>محدودیت اکانت تست</h3>
            <button class="modal-x" onclick="closeModal('testLimitModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_test_limit">
                <div class="field">
                    <label>تعداد مجاز</label>
                    <input type="number" name="test_limit" class="input" value="<?= htmlspecialchars($limitUsertest !== '—' ? $limitUsertest : '1') ?>" min="0" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary">ذخیره</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('testLimitModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="agentModal">
    <div class="modal">
        <div class="modal-head">
            <h3>افزودن نماینده</h3>
            <button class="modal-x" onclick="closeModal('agentModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_agent">
                <div class="field">
                    <label>نوع نمایندگی</label>
                    <select name="agent_type" class="select" required>
                        <option value="n">نماینده (n)</option>
                        <option value="n2">نماینده پیشرفته (n2)</option>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary">تأیید</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('agentModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="maxbuyModal">
    <div class="modal">
        <div class="modal-head">
            <h3>سقف خرید نماینده</h3>
            <button class="modal-x" onclick="closeModal('maxbuyModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_maxbuyagent">
                <div class="field">
                    <label>حداکثر بدهی مجاز (تومان)</label>
                    <input type="number" name="maxbuyagent" class="input" value="<?= htmlspecialchars((string) ($user['maxbuyagent'] ?? '0')) ?>" min="0" required>
                    <span class="field-hint">۰ = نامحدود</span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary">ذخیره</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('maxbuyModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="messageModal">
    <div class="modal">
        <div class="modal-head">
            <h3>ارسال پیام به کاربر</h3>
            <button class="modal-x" onclick="closeModal('messageModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="send_message">
                <div class="field">
                    <label>متن پیام</label>
                    <textarea name="message" class="input" rows="5" required placeholder="متن پیام برای ارسال در تلگرام…"></textarea>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-size:.82rem;margin-top:8px">
                    <input type="checkbox" name="allow_reply" value="1">
                    کاربر بتواند پاسخ دهد
                </label>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary">ارسال</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('messageModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="roleModal">
    <div class="modal">
        <div class="modal-head">
            <h3>تغییر گروه کاربری</h3>
            <button class="modal-x" onclick="closeModal('roleModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_role">
                <div class="field">
                    <label>گروه</label>
                    <select name="new_role" class="select">
                        <option value="f" <?= $agent === 'f' ? 'selected' : '' ?>>کاربر عادی (f)</option>
                        <option value="n" <?= $agent === 'n' ? 'selected' : '' ?>>نماینده (n)</option>
                        <option value="n2" <?= $agent === 'n2' ? 'selected' : '' ?>>نماینده پیشرفته (n2)</option>
                    </select>
                    <span class="field-hint">
                        گروه فعلی: <strong><?= user_role_label($agent) ?></strong>
                        <span class="cm" style="color:var(--mute)">(<?= htmlspecialchars($agent) ?>)</span>
                    </span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ذخیره</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('roleModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<script src="js/profile.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>