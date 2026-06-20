<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$username = trim($_GET['u'] ?? '');
$inv = $username !== '' ? agent_invoice_owned($pdo, (string) $user['id'], $username) : null;
$pageTitle = 'جزئیات سرویس';
$activeNav = 'services';
require __DIR__ . '/inc/layout_head.php';
?>
<h1>سرویس <?= htmlspecialchars($username) ?></h1>
<?php if (!$inv): ?>
<p>سرویس یافت نشد.</p>
<?php else: ?>
<ul>
<li>محصول: <?= htmlspecialchars($inv['name_product']) ?></li>
<li>پنل: <?= htmlspecialchars($inv['Location']) ?></li>
<li>وضعیت: <?= htmlspecialchars($inv['Status']) ?></li>
<li>قیمت: <?= number_format((int) $inv['price_product']) ?> تومان</li>
</ul>
<p><a href="services.php">بازگشت</a></p>
<?php endif; ?>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
