<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$panels = agent_panel_list($pdo, $user);
$pageTitle = 'سرویس دلخواه';
$activeNav = 'buy_custom';
require __DIR__ . '/inc/layout_head.php';
?>
<form id="custom-form" class="card"><div class="card-body">
<label class="field">پنل</label>
<select name="panel" class="input" required id="custom-panel">
<?php foreach ($panels as $p):
  $cp = agent_custom_pricing($p, $user['agent'] ?? 'f');
  if (!$cp['enabled']) continue; ?>
<option value="<?= htmlspecialchars($p['name_panel']) ?>" data-minv="<?= $cp['min_volume'] ?>" data-maxv="<?= $cp['max_volume'] ?>" data-mint="<?= $cp['min_time'] ?>" data-maxt="<?= $cp['max_time'] ?>" data-pv="<?= $cp['price_volume'] ?>" data-pt="<?= $cp['price_time'] ?>"><?= htmlspecialchars($p['name_panel']) ?></option>
<?php endforeach; ?>
</select>
<label class="field">حجم (GB)</label>
<input type="number" name="volume" class="input" required id="custom-vol">
<label class="field">زمان (روز)</label>
<input type="number" name="days" class="input" required id="custom-days">
<p id="custom-price-hint" class="lede"></p>
<button type="submit" class="btn btn-primary">پیش‌فاکتور</button>
</div></form>
<script>
function updateCustomHint(){
  var o = document.getElementById('custom-panel').selectedOptions[0];
  if(!o) return;
  var v = parseInt(document.getElementById('custom-vol').value||0,10);
  var d = parseInt(document.getElementById('custom-days').value||0,10);
  var price = v*parseInt(o.dataset.pv,10) + d*parseInt(o.dataset.pt,10);
  document.getElementById('custom-price-hint').textContent = 'قیمت تقریبی: ' + price.toLocaleString('fa-IR') + ' تومان';
}
['custom-vol','custom-days','custom-panel'].forEach(function(id){ document.getElementById(id).addEventListener('input', updateCustomHint); document.getElementById(id).addEventListener('change', updateCustomHint); });
document.getElementById('custom-form').addEventListener('submit', function(e){
  e.preventDefault();
  var f = new FormData(e.target);
  agentCheckoutPreview('custom', { panel: f.get('panel'), volume: f.get('volume'), days: f.get('days') }, function(){
    agentBuySubmit('buy_custom', { panel: f.get('panel'), volume: f.get('volume'), days: f.get('days') }).then(function(j){ if(j.ok) location.href='services.php'; });
  });
});
updateCustomHint();
</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
