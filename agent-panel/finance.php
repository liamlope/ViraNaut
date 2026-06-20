<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$metrics = agent_dashboard_metrics($pdo, $user);
$gates = agent_payment_gateways();
$pageTitle = 'مرکز مالی';
$activeNav = 'finance';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="stats">
<div class="stat"><span>موجودی</span><strong><?= number_format($metrics['balance']) ?></strong></div>
<?php if (($user['agent'] ?? '') === 'n2' && $metrics['debt_ceiling'] > 0): ?>
<div class="stat warn"><span>بدهی / سقف</span><strong><?= number_format($metrics['debt_used']) ?></strong><small>/ <?= number_format($metrics['debt_ceiling']) ?></small></div>
<?php endif; ?>
<div class="stat ok"><span>جمع فروش</span><strong><?= number_format($metrics['sales_sum']) ?></strong></div>
</div>
<div class="card" style="margin-top:16px"><div class="card-head"><h3>افزایش موجودی</h3></div><div class="card-body">
<?php if (!$gates): ?>
<p class="notice notice-warn">درگاه پرداخت فعالی یافت نشد. از پنل ادمین درگاه را فعال کنید.</p>
<?php else: ?>
<form id="topup-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
<input type="number" name="amount" class="input" min="5000" step="1000" placeholder="مبلغ (تومان)" required style="max-width:200px" value="10000">
<select name="gateway" class="input">
<?php foreach ($gates as $g): ?><option value="<?= htmlspecialchars($g['id']) ?>"><?= htmlspecialchars($g['label']) ?></option><?php endforeach; ?>
</select>
<button class="btn btn-primary" type="submit">پرداخت</button>
</form>
<?php endif; ?>
</div></div>
<script>
document.getElementById('topup-form')?.addEventListener('submit', function(e){
  e.preventDefault();
  var f=new FormData(e.target);
  agentFetch('api/topup.php',{method:'POST',body:new URLSearchParams({amount:f.get('amount'),gateway:f.get('gateway'),csrf:document.querySelector('meta[name=csrf]').content})}).then(function(j){
    if(j.ok && j.url){ window.location.href=j.url; return; }
    toast(j.msg || 'خطا در پرداخت','no');
  });
});
</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
