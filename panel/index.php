<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$todayTs = strtotime('today');
$totalUsers = 0;
$newToday = 0;
$totalRevenue = 0;
$revenueToday = 0;
$activeNow = 0;
$expiredServices = 0;
$pendingPay = 0;
$txToday = 0;
$paidToday = 0;
$productCount = 0;
$panelCount = 0;
$categoryCount = 0;
$blockedUsers = 0;
$agentUsers = 0;
$ordersToday = 0;
$lowBalanceUsers = 0;
$botStatusLabel = '—';
$dbOk = true;

try {
    $totalUsers = db_count($pdo, "SELECT COUNT(*) FROM user");
    $newToday = db_count($pdo, "SELECT COUNT(*) FROM user WHERE register > ?", [$todayTs]);
    $blockedUsers = db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status='block'");
    $agentUsers = db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent IN ('n','n2')");
    $lowBalanceUsers = db_count($pdo, "SELECT COUNT(*) FROM user WHERE Balance > 0 AND Balance < 10000 AND User_Status != 'block'");
} catch (Exception $e) {
    $dbOk = false;
}

try {
    $totalRevenue = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')")->fetchColumn();
    $revenueToday = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE time_sell > ?", [$todayTs])->fetchColumn();
    $activeNow = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE Status='active'");
    $expiredServices = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE Status IN ('end_of_time','end_of_volume')");
    $ordersToday = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE time_sell > ?", [$todayTs]);
} catch (Exception $e) {
}

try {
    $pendingPay = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status='waiting'");
    $txToday = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE time > ?", [$todayTs]);
    $paidToday = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE time > ? AND payment_Status IN ('paid','success')", [$todayTs]);
} catch (Exception $e) {
}

try {
    $productCount = db_count($pdo, "SELECT COUNT(*) FROM product");
    $panelCount = db_count($pdo, "SELECT COUNT(*) FROM marzban_panel");
    $categoryCount = db_count($pdo, "SELECT COUNT(*) FROM category");
} catch (Exception $e) {
}

try {
    $st = db_fetch($pdo, "SELECT bot_status FROM setting LIMIT 1");
    if ($st) {
        $botStatusLabel = (($st['bot_status'] ?? '') === 'offbot') ? 'خاموش' : 'روشن';
    }
} catch (Exception $e) {
}

$recentActivity = [];
try {
    $recentActivity = db_fetchAll(
        $pdo,
        "(SELECT time_sell AS ts, CONCAT('سفارش: ', COALESCE(name_product,'')) AS title, id_user AS uid, 'invoice' AS kind FROM invoice ORDER BY time_sell DESC LIMIT 6)
         UNION ALL
         (SELECT time AS ts, CONCAT('پرداخت: ', COALESCE(price,'')) AS title, id_user AS uid, 'pay' AS kind FROM Payment_report ORDER BY time DESC LIMIT 6)
         ORDER BY ts DESC LIMIT 10"
    );
} catch (Exception $e) {
}

$recentInvoices = [];
$recentUsers = [];
try {
    $recentInvoices = db_fetchAll($pdo, "SELECT * FROM invoice ORDER BY time_sell DESC LIMIT 8");
} catch (Exception $e) {
}
try {
    $recentUsers = db_fetchAll($pdo, "SELECT * FROM user ORDER BY register DESC LIMIT 8");
} catch (Exception $e) {
}

$pageTitle = 'داشبورد';
$activeNav = 'dashboard';
$showPageHead = false;
$extraCss = ['css/dashboard.css'];
$extraJs = ['js/dashboard.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div class="stats dashboard-stats fade-up">
    <div class="stat">
        <div class="stat-label">کاربران</div>
        <div class="stat-num" title="<?= number_format($totalUsers) ?>"><?= vira_format_compact_number($totalUsers) ?></div>
        <div class="stat-meta"><?= $newToday > 0 ? '+' . $newToday . ' امروز' : '—' ?></div>
    </div>
    <div class="stat ok">
        <div class="stat-label">درآمد کل</div>
        <div class="stat-num" title="<?= number_format($totalRevenue) ?> ت"><?= vira_format_compact_number($totalRevenue) ?><small>ت</small></div>
        <div class="stat-meta">امروز <?= vira_format_compact_number($revenueToday) ?> ت</div>
    </div>
    <div class="stat warn">
        <div class="stat-label">سرویس فعال</div>
        <div class="stat-num"><?= number_format($activeNow) ?></div>
        <div class="stat-meta"><?= number_format($expiredServices) ?> منقضی/تمام حجم</div>
    </div>
    <div class="stat">
        <div class="stat-label">سفارش امروز</div>
        <div class="stat-num"><?= number_format($ordersToday) ?></div>
        <div class="stat-meta">فاکتور جدید</div>
    </div>
    <div class="stat <?= $pendingPay > 0 ? 'no' : '' ?>">
        <div class="stat-label">پرداخت معلق</div>
        <div class="stat-num"><?= number_format($pendingPay) ?></div>
        <div class="stat-meta"><?= $pendingPay > 0 ? '<a href="finance.php">بررسی</a>' : 'خالی' ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">تراکنش امروز</div>
        <div class="stat-num"><?= number_format($txToday) ?></div>
        <div class="stat-meta"><?= $paidToday ?> موفق</div>
    </div>
    <div class="stat">
        <div class="stat-label">محصولات</div>
        <div class="stat-num"><?= number_format($productCount) ?></div>
        <div class="stat-meta"><?= $categoryCount ?> دسته</div>
    </div>
    <div class="stat">
        <div class="stat-label">پنل VPN</div>
        <div class="stat-num"><?= number_format($panelCount) ?></div>
        <div class="stat-meta"><a href="panels.php">مدیریت</a></div>
    </div>
    <div class="stat">
        <div class="stat-label">نمایندگان</div>
        <div class="stat-num"><?= number_format($agentUsers) ?></div>
        <div class="stat-meta"><?= $blockedUsers ?> مسدود</div>
    </div>
    <div class="stat <?= $lowBalanceUsers > 0 ? 'warn' : '' ?>">
        <div class="stat-label">موجودی کم</div>
        <div class="stat-num"><?= number_format($lowBalanceUsers) ?></div>
        <div class="stat-meta">&lt; ۱۰٬۰۰۰ ت</div>
    </div>
</div>

<div class="card fade-up d1" style="margin-bottom:16px">
    <div class="card-head">
        <div class="card-title">سلامت و دسترسی سریع</div>
    </div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;padding-top:0">
        <span class="tag <?= $dbOk ? 'tag-ok' : 'tag-no' ?>">پایگاه داده: <?= $dbOk ? 'متصل' : 'خطا' ?></span>
        <span class="tag tag-info">ربات: <?= htmlspecialchars($botStatusLabel) ?></span>
        <a href="product.php" class="btn btn-ghost btn-sm">محصولات</a>
        <a href="users.php" class="btn btn-ghost btn-sm">کاربران</a>
        <a href="finance.php" class="btn btn-ghost btn-sm">مرکز مالی</a>
        <a href="panels.php" class="btn btn-ghost btn-sm">پنل VPN</a>
        <?php if ($pendingPay > 0): ?>
            <a href="finance.php" class="btn btn-no btn-sm"><?= $pendingPay ?> پرداخت معلق</a>
        <?php endif; ?>
        <?php if ($lowBalanceUsers > 0): ?>
            <a href="users.php" class="btn btn-warn btn-sm">موجودی کم (<?= $lowBalanceUsers ?>)</a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding-top:0;border-top:1px solid var(--bd);margin-top:4px">
        <p class="field-hint" style="margin:0">نمودار فروش ۱۴ روز اخیر</p>
        <div class="dashboard-chart-wrap"><canvas id="dashboardChart"></canvas></div>
        <div class="health-grid" id="healthGrid"></div>
    </div>
</div>

<?php if (!empty($recentActivity)): ?>
<div class="card fade-up d1" style="margin-bottom:16px">
    <div class="card-head">
        <div class="card-title">فعالیت اخیر</div>
        <div class="card-subtitle">سفارش و پرداخت</div>
    </div>
    <div class="tbl-wrap">
        <table class="tbl-sm">
            <thead><tr><th>زمان</th><th>رویداد</th><th>کاربر</th></tr></thead>
            <tbody>
                <?php foreach ($recentActivity as $act): ?>
                    <tr>
                        <td class="cf"><?= safe_date($act['ts'] ?? null, 'm/d H:i') ?></td>
                        <td class="cs"><?= htmlspecialchars(trunc($act['title'] ?? '—', 36)) ?></td>
                        <td class="cm"><a href="user.php?id=<?= (int) ($act['uid'] ?? 0) ?>"><?= htmlspecialchars($act['uid'] ?? '—') ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card fade-up d1" style="margin-bottom:16px">
    <div class="card-head">
        <div>
            <div class="card-title">مدیریت ربات تلگرام</div>
            <div class="card-subtitle">منوی استارت، متن‌ها، پنل VPN و تنظیمات سیستمی</div>
        </div>
        <a href="bot.php" class="btn btn-sm">مرکز ربات ←</a>
    </div>
    <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;padding-top:0">
        <a href="keyboard.php" class="btn btn-ghost btn-sm">چیدمان منو</a>
        <a href="bot-texts.php" class="btn btn-ghost btn-sm">متن‌های ربات</a>
        <a href="panels.php" class="btn btn-ghost btn-sm">پنل‌های VPN</a>
        <a href="bot-settings.php" class="btn btn-ghost btn-sm">تنظیمات ربات</a>
    </div>
</div>

<div class="two-col">
    <div class="card fade-up d1">
        <div class="card-head">
            <div>
                <div class="card-title">آخرین سفارشات</div>
                <div class="card-subtitle"><?= count($recentInvoices) ?> مورد اخیر</div>
            </div>
            <a href="invoice.php" class="btn-link" style="font-size:.78rem">همه ←</a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th>کاربر</th>
                        <th>محصول</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty" style="padding:24px">
                                    <p>سفارشی ثبت نشده</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $statusMap = [
                            'active' => ['tag-ok', 'فعال'],
                            'end_of_time' => ['tag-warn', 'منقضی'],
                            'end_of_volume' => ['tag-no', 'اتمام حجم'],
                            'sendedwarn' => ['tag-warn', 'اخطار'],
                            'send_on_hold' => ['tag-plain', 'در انتظار'],
                        ];
                        foreach ($recentInvoices as $inv):
                            [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                            ?>
                            <tr>
                                <td class="cm cf"><?= htmlspecialchars($inv['id_user'] ?? '—') ?></td>
                                <td class="cs"
                                    style="max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <?= htmlspecialchars(trunc($inv['name_product'] ?? '—', 20)) ?>
                                </td>
                                <td class="cn" style="white-space:nowrap">
                                    <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf">ت</span>
                                </td>
                                <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card fade-up d2">
        <div class="card-head">
            <div>
                <div class="card-title">آخرین کاربران</div>
                <div class="card-subtitle"><?= count($recentUsers) ?> مورد اخیر</div>
            </div>
            <a href="users.php" class="btn-link" style="font-size:.78rem">همه ←</a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th>آیدی</th>
                        <th>نام</th>
                        <th>موجودی</th>
                        <th>گروه</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty" style="padding:24px">
                                    <p>کاربری ثبت نشده</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        foreach ($recentUsers as $u):
                            $agent = $u['agent'] ?? 'f';
                            $isBlocked = ($u['User_Status'] ?? '') === 'block';
                            $name = $u['namecustom'] ?? '';
                            if ($name === 'none')
                                $name = '';
                            $uname = $u['username'] ?? '';
                            if ($uname === 'none')
                                $uname = '';
                            ?>
                            <tr>
                                <td class="cm cf"><?= htmlspecialchars($u['id']) ?></td>
                                <td>
                                    <?php if ($name): ?>
                                        <span class="cs"><?= htmlspecialchars(trunc($name, 14)) ?></span>
                                    <?php elseif ($uname): ?>
                                        <span class="cm" style="color:var(--ac)">@<?= htmlspecialchars(trunc($uname, 12)) ?></span>
                                    <?php else: ?>
                                        <span class="cf">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cn" style="white-space:nowrap">
                                    <?= number_format((int) ($u['Balance'] ?? 0)) ?> <span class="cf">ت</span>
                                </td>
                                <td>
                                    <?php if ($isBlocked): ?>
                                        <span class="tag tag-no" style="font-size:.65rem">مسدود</span>
                                    <?php else: ?>
                                        <span class="tag <?= user_role_tag($agent) ?>" style="font-size:.65rem">
                                            <?= user_role_label($agent) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>