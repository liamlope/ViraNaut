<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$agentId = (string) $user['id'];
$pageTitle = 'گزارش‌ها';
$activeNav = 'reports';
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'];
$topProducts = agent_top_products($pdo, $agentId);
$topPanels = agent_top_panels($pdo, $agentId);
$logs = agent_action_log_list($pdo, $agentId);
require __DIR__ . '/inc/layout_head.php';
?>
<div class="card"><div class="card-head"><h3>نمودار ۹۰ روز</h3></div><div class="card-body"><canvas id="agent-chart" data-days="90" height="80"></canvas></div></div>
<div class="card" style="margin-top:12px"><div class="card-head"><h3>پرفروش‌ترین محصولات</h3>
<a href="api/export.php?type=products" class="btn btn-sm btn-ghost">CSV</a></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>محصول</th><th>تعداد</th><th>جمع</th></tr></thead><tbody>
<?php foreach ($topProducts as $r): ?><tr><td><?= htmlspecialchars($r['name_product']) ?></td><td><?= (int)$r['cnt'] ?></td><td><?= number_format((int)$r['total']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="card" style="margin-top:12px"><div class="card-head"><h3>پرفروش‌ترین پنل‌ها</h3></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>پنل</th><th>تعداد</th><th>جمع</th></tr></thead><tbody>
<?php foreach ($topPanels as $r): ?><tr><td><?= htmlspecialchars($r['Location']) ?></td><td><?= (int)$r['cnt'] ?></td><td><?= number_format((int)$r['total']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php if ($logs): ?>
<div class="card" style="margin-top:12px"><div class="card-head"><h3>لاگ عملیات</h3></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>عملیات</th><th>سرویس</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr><td><?= htmlspecialchars($l['action']) ?></td><td><?= htmlspecialchars($l['username']??'') ?></td><td><?= htmlspecialchars($l['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
