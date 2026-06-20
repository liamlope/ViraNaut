<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$tariff = agent_tariff_table($pdo, $user);
$pageTitle = 'جدول تعرفه';
$activeNav = 'tariff';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="card"><div class="card-body">
<p>قیمت حجم اضافه (هر GB): <?= number_format($tariff['extra_volume']) ?> تومان</p>
<p>قیمت زمان اضافه (هر روز): <?= number_format($tariff['extra_time']) ?> تومان</p>
</div></div>
<div class="tbl-wrap card" style="margin-top:12px"><table class="tbl-lg"><thead><tr><th>محصول</th><th>پنل</th><th>قیمت</th><th>حجم</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($tariff['products'] as $p): ?>
<tr><td><?= htmlspecialchars($p['name_product']) ?></td><td><?= htmlspecialchars($p['Location']) ?></td><td><?= number_format((int)$p['price_product']) ?></td><td><?= (int)$p['Volume_constraint'] ?></td><td><?= (int)$p['Service_time'] ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
