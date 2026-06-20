<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$payments = db_fetchAll($pdo, 'SELECT * FROM Payment_report WHERE id_user = ? ORDER BY time DESC LIMIT 200', [(string) $user['id']]);
$actions = db_fetchAll($pdo, 'SELECT * FROM agent_action_log WHERE id_user = ? ORDER BY created_at DESC LIMIT 100', [(string) $user['id']]);
$pageTitle = 'تراکنش‌ها';
$activeNav = 'transactions';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="card"><div class="card-head"><h3>شارژ / پرداخت</h3></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($payments as $p): ?>
<tr><td><?= number_format((int) $p['price']) ?></td><td><?= htmlspecialchars($p['Payment_Method'] ?? '') ?></td><td><?= htmlspecialchars($p['payment_Status'] ?? '') ?></td><td><?= htmlspecialchars($p['time'] ?? '') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<div class="card" style="margin-top:16px"><div class="card-head"><h3>کسرها (خرید/تمدید)</h3></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>عملیات</th><th>سرویس</th><th>جزئیات</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($actions as $a): ?>
<tr><td><?= htmlspecialchars($a['action']) ?></td><td><?= htmlspecialchars($a['username'] ?? '') ?></td><td><?= htmlspecialchars($a['detail'] ?? '') ?></td><td><?= htmlspecialchars($a['created_at'] ?? '') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
