<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$stats = agent_panel_sales_stats($pdo, (string) $user['id']);
$token = agent_panel_ensure_token($pdo, (string) $user['id']);
$domain = $domainhosts ?? '';
$pageTitle = 'داشبورد نماینده';
$activeNav = 'dashboard';
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'assets/agent.js'];
require __DIR__ . '/inc/layout_head.php';
?>
<h1>داشبورد نمایندگی</h1>
<div class="agent-grid">
<div class="agent-card"><div>موجودی</div><div class="agent-stat"><?= number_format((int) $user['Balance']) ?></div><small>تومان</small></div>
<div class="agent-card"><div>تعداد فروش</div><div class="agent-stat"><?= number_format($stats['count']) ?></div></div>
<div class="agent-card"><div>جمع فروش</div><div class="agent-stat"><?= number_format($stats['sum']) ?></div><small>تومان</small></div>
</div>
<div class="agent-chart-wrap">
<h3>روند ۱۴ روز</h3>
<canvas id="agent-chart" height="100"></canvas>
</div>
<p style="margin-top:20px">API: <code>POST https://<?= htmlspecialchars($domain) ?>/api/agent.php</code></p>
<p>Token: <code><?= htmlspecialchars($token) ?></code></p>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
