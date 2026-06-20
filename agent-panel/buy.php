<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$panels = agent_panel_list($pdo, $user);
$pageTitle = 'خرید سرویس';
$activeNav = 'buy';
require __DIR__ . '/inc/layout_head.php';
?>
<form id="buy-form" class="card">
<div class="card-body">
<label class="field">پنل</label>
<select name="panel" class="input" required id="buy-panel">
<option value="">— انتخاب —</option>
<?php foreach ($panels as $p): ?>
<option value="<?= htmlspecialchars($p['name_panel']) ?>"><?= htmlspecialchars($p['name_panel']) ?></option>
<?php endforeach; ?>
</select>
<label class="field">محصول</label>
<select name="product_code" class="input" required id="buy-product"><option value="">ابتدا پنل را انتخاب کنید</option></select>
<label class="field">نام کاربری (اختیاری)</label>
<input name="custom_username" class="input" dir="ltr" placeholder="auto">
<button type="submit" class="btn btn-primary" style="margin-top:12px">پیش‌فاکتور و خرید</button>
</div>
</form>
<script>
document.getElementById('buy-panel').addEventListener('change', function(){
  var panel = this.value;
  var sel = document.getElementById('buy-product');
  sel.innerHTML = '<option>…</option>';
  if(!panel) return;
  fetch('api/products.php?panel='+encodeURIComponent(panel)).then(r=>r.json()).then(function(j){
    sel.innerHTML = '';
    (j.items||[]).forEach(function(p){
      var o = document.createElement('option');
      o.value = p.code_product;
      o.textContent = p.name_product + ' — ' + Number(p.price_product).toLocaleString('fa-IR') + ' تومان';
      sel.appendChild(o);
    });
  });
});
document.getElementById('buy-form').addEventListener('submit', function(e){
  e.preventDefault();
  var f = new FormData(e.target);
  agentCheckoutPreview('buy', { panel: f.get('panel'), product_code: f.get('product_code') }, function(){
    agentBuySubmit('buy', { panel: f.get('panel'), product_code: f.get('product_code'), custom_username: f.get('custom_username')||'' }).then(function(j){ if(j.ok) location.href='services.php'; });
  });
});
</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
