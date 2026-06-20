<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$panels = agent_panel_list($pdo, $user, true);
$pageTitle = 'اکانت تست';
$activeNav = 'buy_test';
$pageLede = 'باقیمانده: ' . (int) ($user['limit_usertest'] ?? 0);
require __DIR__ . '/inc/layout_head.php';
?>
<form id="test-form" class="card"><div class="card-body">
<label class="field">پنل تست</label>
<select name="panel" class="input" required>
<?php foreach ($panels as $p): ?>
<option value="<?= htmlspecialchars($p['name_panel']) ?>"><?= htmlspecialchars($p['name_panel']) ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="btn btn-primary">ساخت اکانت تست</button>
</div></form>
<script>
document.getElementById('test-form').addEventListener('submit', function(e){
  e.preventDefault(); var f=new FormData(e.target);
  agentBuySubmit('buy_test', { panel: f.get('panel') }).then(function(j){ if(j.ok) location.href='services.php'; });
});
</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
