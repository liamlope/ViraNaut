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
<form id="topup-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
<input type="number" name="amount" class="input" min="10000" step="1000" placeholder="مبلغ (تومان)" required style="max-width:200px">
<select name="gateway" class="input">
<?php foreach ($gates as $g): ?><option value="<?= htmlspecialchars($g['url']) ?>"><?= htmlspecialchars($g['label']) ?></option><?php endforeach; ?>
<?php if (!$gates): ?><option disabled>درگاه فعالی نیست</option><?php endif; ?>
</select>
<button class="btn btn-primary" type="submit">پرداخت</button>
</form>
</div></div>
<script>
document.getElementById('topup-form').addEventListener('submit', function(e){
  e.preventDefault();
  var f=new FormData(e.target);
  var amount=f.get('amount');
  agentFetch('api/buy.php',{method:'POST',body:new URLSearchParams({action:'gateway_intent',amount:amount,csrf:document.querySelector('meta[name=csrf]').content})}).then(function(j){
    if(j.ok && j.gateways && j.gateways[0]) window.open(j.gateways[0].url+'?from_id=<?= urlencode((string)$user['id']) ?>&amount='+amount,'_blank');
  });
});
</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
