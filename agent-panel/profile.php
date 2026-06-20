<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$setting = select('setting', '*', null, null, 'select');
$usernamebot = $setting['usernamebot'] ?? '';
if (empty($user['codeInvitation'])) {
    $code = bin2hex(random_bytes(4));
    update('user', 'codeInvitation', $code, 'id', $user['id']);
    $user['codeInvitation'] = $code;
}
$refLink = $usernamebot ? "https://t.me/{$usernamebot}?start={$user['codeInvitation']}" : '';
$pageTitle = 'پروفایل نماینده';
$activeNav = 'profile';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="stats">
  <div class="stat"><span>نوع نمایندگی</span><strong><?= htmlspecialchars($user['agent'] ?? '') ?></strong></div>
  <div class="stat"><span>موجودی</span><strong><?= number_format((int) $user['Balance']) ?></strong></div>
  <div class="stat"><span>اکانت تست باقی</span><strong><?= (int) ($user['limit_usertest'] ?? 0) ?></strong></div>
  <?php if (!empty($user['expire'])): ?>
  <div class="stat warn"><span>انقضای نمایندگی</span><strong><?= date('Y/m/d H:i', (int) $user['expire']) ?></strong></div>
  <?php endif; ?>
  <?php if (($user['agent'] ?? '') === 'n2'): ?>
  <div class="stat"><span>سقف بدهی</span><strong><?= number_format((int) ($user['maxbuyagent'] ?? 0)) ?></strong></div>
  <?php endif; ?>
</div>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h3>دعوت و referral</h3></div>
  <div class="card-body">
    <p>کد دعوت: <code id="ref-code"><?= htmlspecialchars((string) $user['codeInvitation']) ?></code>
    <button class="btn btn-sm btn-ghost" type="button" onclick="navigator.clipboard.writeText(document.getElementById('ref-code').textContent);toast('کپی شد','ok')">کپی</button></p>
    <?php if ($refLink): ?>
    <p>لینk: <a href="<?= htmlspecialchars($refLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($refLink) ?></a>
    <button class="btn btn-sm btn-ghost" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($refLink, ENT_QUOTES) ?>');toast('کپی شد','ok')">کپی</button></p>
    <?php endif; ?>
    <p>زیرمجموعه: <?= (int) ($user['affiliatescount'] ?? 0) ?></p>
  </div>
</div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
