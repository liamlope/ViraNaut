<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$rows = db_fetchAll($pdo, 'SELECT username, name_product, price_product, Status, time_sell, Location FROM invoice WHERE id_user = ? ORDER BY time_sell DESC LIMIT 100', [(string) $user['id']]);
$pageTitle = 'سرویس‌های نماینده';
$activeNav = 'services';
$extraJs = ['assets/agent.js'];
require __DIR__ . '/inc/layout_head.php';
?>
<meta name="csrf" content="<?= htmlspecialchars(agent_csrf_token()) ?>">
<h1>سرویس‌ها</h1>
<input type="search" class="agent-search" id="q" placeholder="جستجو...">
<table class="agent-table" id="tbl">
<thead><tr><th>کاربر</th><th>محصول</th><th>قیمت</th><th>وضعیت</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><a href="service.php?u=<?= urlencode($r['username']) ?>"><?= htmlspecialchars($r['username']) ?></a></td>
<td><?= htmlspecialchars($r['name_product']) ?></td>
<td><?= number_format((int) $r['price_product']) ?></td>
<td><?= htmlspecialchars($r['Status']) ?></td>
<td>
<button class="agent-btn secondary" data-agent-action="renew" data-username="<?= htmlspecialchars($r['username']) ?>">تمدید</button>
<button class="agent-btn" data-agent-action="add_volume" data-username="<?= htmlspecialchars($r['username']) ?>" data-gb="10">+۱۰GB</button>
<button class="agent-btn danger" data-agent-action="revoke" data-username="<?= htmlspecialchars($r['username']) ?>">لینک جدید</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<script>document.getElementById('q').addEventListener('input',function(){var q=this.value.toLowerCase();document.querySelectorAll('#tbl tbody tr').forEach(function(tr){tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none';});});</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
