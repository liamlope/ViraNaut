<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$aff = agent_affiliate_stats($pdo, (string) $user['id']);
$setting = select('setting', '*', null, null, 'select');
$refLink = !empty($setting['usernamebot']) ? "https://t.me/{$setting['usernamebot']}?start={$aff['code']}" : '';
$pageTitle = 'زیرمجموعه';
$activeNav = 'affiliates';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="stats">
<div class="stat"><span>تعداد</span><strong><?= number_format($aff['count']) ?></strong></div>
<div class="stat ok"><span>مجموع خرید</span><strong><?= number_format($aff['purchase_sum']) ?></strong></div>
</div>
<div class="card" style="margin-top:12px"><div class="card-body">
<p>کد: <code id="aff-code"><?= htmlspecialchars($aff['code']) ?></code> <button class="btn btn-sm btn-ghost" onclick="navigator.clipboard.writeText(document.getElementById('aff-code').textContent);toast('کopi','ok')">کپی</button></p>
<?php if ($refLink): ?><p><a href="<?= htmlspecialchars($refLink) ?>"><?= htmlspecialchars($refLink) ?></a></p><?php endif; ?>
</div></div>
<div class="tbl-wrap card" style="margin-top:12px"><table class="tbl-lg"><thead><tr><th>آیدی</th><th>یوزرنیم</th><th>موجودی</th></tr></thead><tbody>
<?php foreach ($aff['referrals'] as $r): ?>
<tr><td><?= htmlspecialchars($r['id']) ?></td><td><?= htmlspecialchars($r['username'] ?? '') ?></td><td><?= number_format((int)$r['Balance']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
