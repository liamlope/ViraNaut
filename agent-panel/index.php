<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$metrics = agent_dashboard_metrics($pdo, $user);
$token = agent_panel_ensure_token($pdo, (string) $user['id']);
$domain = $domainhosts ?? '';
$lang = agent_lang($pdo, (string) $user['id']);
$pageTitle = agent_t('dashboard', $lang);
$activeNav = 'dashboard';
$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'];
$onboard = db_fetch($pdo, 'SELECT onboarded FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [(string) $user['id']]);
$showOnboard = empty($onboard['onboarded']);
require __DIR__ . '/inc/layout_head.php';
?>
<div class="stats dashboard-stats">
  <div class="stat"><span><?= agent_t('balance', $lang) ?></span><strong><?= number_format($metrics['balance']) ?></strong><small>تومان</small></div>
  <div class="stat ok"><span><?= agent_t('active_services', $lang) ?></span><strong><?= number_format($metrics['active_services']) ?></strong></div>
  <div class="stat warn"><span><?= agent_t('expired_services', $lang) ?></span><strong><?= number_format($metrics['expired_services']) ?></strong></div>
  <div class="stat"><span><?= agent_t('sales_today', $lang) ?></span><strong><?= number_format($metrics['sales_today_sum']) ?></strong><small><?= $metrics['sales_today_count'] ?> فروش</small></div>
  <div class="stat"><span><?= agent_t('sales_month', $lang) ?></span><strong><?= number_format($metrics['sales_month_sum']) ?></strong><small><?= $metrics['sales_month_count'] ?> فروش</small></div>
  <?php if (($user['agent'] ?? '') === 'n2' && $metrics['debt_ceiling'] > 0): ?>
  <div class="stat no"><span><?= agent_t('debt_used', $lang) ?></span><strong><?= number_format($metrics['debt_used']) ?></strong><small>/ <?= number_format($metrics['debt_ceiling']) ?></small></div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head"><h3>روند فروش</h3>
    <div class="toolbar">
      <button class="btn btn-sm btn-ghost agent-chart-days" data-days="7">۷ روز</button>
      <button class="btn btn-sm btn-primary agent-chart-days active" data-days="30">۳۰ روز</button>
      <button class="btn btn-sm btn-ghost agent-chart-days" data-days="90">۹۰ روز</button>
    </div>
  </div>
  <div class="card-body agent-chart-wrap"><canvas id="agent-chart" height="100"></canvas></div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-body">
    <p>API: <code>POST https://<?= htmlspecialchars($domain) ?>/api/agent.php</code></p>
    <p>Token: <code id="dash-token"><?= htmlspecialchars($token) ?></code> <button class="btn btn-sm btn-ghost" type="button" onclick="navigator.clipboard.writeText(document.getElementById('dash-token').textContent);toast('کپی شد','ok')">کپی</button></p>
  </div>
</div>
<?php
$footerInlineJs = $showOnboard ? "document.addEventListener('DOMContentLoaded',function(){openModal('onboard-modal');document.getElementById('onboard-done').onclick=function(){closeModal('onboard-modal');fetch('api/onboard.php',{method:'POST',headers:{'X-CSRF-Token':document.querySelector('meta[name=csrf]').content}});};});" : '';
require __DIR__ . '/inc/layout_foot.php';
