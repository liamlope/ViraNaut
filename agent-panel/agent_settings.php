<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
if (($user['agent'] ?? '') !== 'n2') {
    agent_flash('warn', 'فقط n2');
    header('Location: index.php');
    exit;
}
$botsaz = select('botsaz', '*', 'id_user', $user['id'], 'select');
$botSetting = $botsaz ? json_decode($botsaz['setting'] ?? '{}', true) : [];
$pageTitle = 'تنظیمات n2';
$activeNav = 'agent_settings';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="card"><div class="card-body">
<?php if ($botsaz): ?>
<p>ربات فروش: @<?= htmlspecialchars($botsaz['username'] ?? '') ?></p>
<p>قیمت پایه حجم: <?= number_format((int)($botSetting['minpricevolume'] ?? 0)) ?> — قیمت فعلی: <?= number_format((int)($botSetting['pricevolume'] ?? 0)) ?></p>
<p>قیمت پایه زمان: <?= number_format((int)($botSetting['minpricetime'] ?? 0)) ?> — قیمت فعلی: <?= number_format((int)($botSetting['pricetime'] ?? 0)) ?></p>
<p class="lede">تغییر قیمت از پنل ادمین یا ربات فروش اختصاصی شما.</p>
<?php else: ?>
<p>ربات فروش (vpnbot) برای شما فعال نیست. از ادمین درخواست «فعالسازی ربات فروش» کنید.</p>
<?php endif; ?>
<?php if (!empty($user['expire'])): ?>
<p>انقضای نمایندگی: <?= date('Y/m/d', (int)$user['expire']) ?></p>
<?php endif; ?>
</div></div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
