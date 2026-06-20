<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$panels = agent_panel_list($pdo, $user);
$pageTitle = 'خرید انبوه';
$activeNav = 'buy_bulk';
require __DIR__ . '/inc/layout_head.php';
?>
<form id="bulk-form" class="card"><div class="card-body">
<label class="field">تعداد (۱–۱۵)</label>
<input type="number" name="count" class="input" min="1" max="15" value="5" required>
<label class="field">پنل</label>
<select name="panel" class="input" required id="bulk-panel"><option value="">—</option>
<?php foreach ($panels as $p): ?><option value="<?= htmlspecialchars($p['name_panel']) ?>"><?= htmlspecialchars($p['name_panel']) ?></option><?php endforeach; ?>
</select>
<label class="field">محصول</label>
<select name="product_code" class="input" required id="bulk-product"></select>
<button type="submit" class="btn btn-primary">پیش‌فاکتور</button>
</div></form>
<script>
document.getElementById('bulk-panel').addEventListener('change', function(){
  fetch('api/products.php?panel='+encodeURIComponent(this.value)).then(r=>r.json()).then(function(j){
    var sel = document.getElementById('bulk-product'); sel.innerHTML='';
    (j.items||[]).forEach(function(p){ var o=document.createElement('option'); o.value=p.code_product; o.textContent=p.name_product; sel.appendChild(o); });
  });
});
document.getElementById('bulk-form').addEventListener('submit', function(e){
  e.preventDefault(); var f=new FormData(e.target);
  agentCheckoutPreview('bulk', { panel:f.get('panel'), product_code:f.get('product_code'), count:f.get('count') }, function(){
    agentBuySubmit('buy_bulk', { panel:f.get('panel'), product_code:f.get('product_code'), count:f.get('count') }).then(function(j){ if(j.ok) location.href='services.php'; });
  });
});
</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
