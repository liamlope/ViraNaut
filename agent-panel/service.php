<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$username = trim($_GET['u'] ?? '');
if ($username === '') {
    header('Location: services.php');
    exit;
}
$detail = agent_service_detail($pdo, $user, $username);
if (!$detail['ok']) {
    agent_flash('no', 'سرویس یافت نشد');
    header('Location: services.php');
    exit;
}
$inv = $detail['invoice'];
$pu = $detail['panel_user'] ?? [];
$sub = $pu['subscription_url'] ?? $pu['link'] ?? '';
$pageTitle = 'سرویس: ' . $username;
$activeNav = 'services';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="stats">
<div class="stat"><span>محصول</span><strong><?= htmlspecialchars($inv['name_product']) ?></strong></div>
<div class="stat"><span>پنل</span><strong><?= htmlspecialchars($inv['Location']) ?></strong></div>
<div class="stat"><span>وضعیت</span><strong><?= htmlspecialchars($inv['Status']) ?></strong></div>
<div class="stat"><span>قیمت</span><strong><?= number_format((int) $inv['price_product']) ?></strong></div>
</div>
<?php if (!empty($pu)): ?>
<div class="card" style="margin-top:12px"><div class="card-body">
<p>حجم: <?= htmlspecialchars((string) ($pu['used_traffic'] ?? $pu['used'] ?? '?')) ?> / <?= htmlspecialchars((string) ($pu['data_limit'] ?? $pu['total'] ?? '?')) ?></p>
<p>انقضا: <?= htmlspecialchars((string) ($pu['expire'] ?? $pu['expiry'] ?? '—')) ?></p>
<?php if ($sub): ?>
<p>لینک: <code id="sub-link"><?= htmlspecialchars($sub) ?></code>
<button class="btn btn-sm btn-ghost" type="button" onclick="navigator.clipboard.writeText(document.getElementById('sub-link').textContent);toast('کپی شد','ok')">کپی</button></p>
<img class="agent-qr" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($sub) ?>" alt="QR">
<?php endif; ?>
</div></div>
<?php endif; ?>

<div class="um-actions agent-action-grid">
<button class="um-action btn btn-primary" data-agent-action="renew" data-username="<?= htmlspecialchars($username) ?>">تمدید</button>
<button class="um-action btn btn-ghost" data-agent-action="add_volume" data-username="<?= htmlspecialchars($username) ?>" data-gb="5">+۵GB</button>
<button class="um-action btn btn-ghost" data-agent-action="add_time" data-username="<?= htmlspecialchars($username) ?>" data-days="30">+۳۰روز</button>
<button class="um-action btn btn-ghost" data-agent-action="revoke" data-username="<?= htmlspecialchars($username) ?>">لینک جدید</button>
<button class="um-action btn btn-ghost" data-agent-action="send_telegram" data-username="<?= htmlspecialchars($username) ?>">ارسال به تلگرام</button>
</div>

<div class="card" style="margin-top:16px"><div class="card-body">
<label>حجم دلخواه (GB)</label>
<div style="display:flex;gap:8px;margin-top:8px">
<input type="number" id="vol-gb" class="input" value="10" min="1">
<button class="btn btn-primary" type="button" id="vol-btn">افزودن حجم</button>
</div>
<label style="margin-top:12px">زمان (روز)</label>
<div style="display:flex;gap:8px;margin-top:8px">
<input type="number" id="time-days" class="input" value="30" min="1">
<button class="btn btn-primary" type="button" id="time-btn">افزودن زمان</button>
</div>
</div></div>
<script>
document.getElementById('vol-btn').onclick=function(){
  var gb=document.getElementById('vol-gb').value;
  agentCheckoutPreview('volume',{username:'<?= htmlspecialchars($username, ENT_QUOTES) ?>',gb:gb},function(){
    var body=new URLSearchParams({action:'add_volume',username:'<?= htmlspecialchars($username, ENT_QUOTES) ?>',gb:gb,csrf:document.querySelector('meta[name=csrf]').content});
    agentFetch('api/service_action.php',{method:'POST',body:body}).then(function(j){toast(j.msg,j.ok?'ok':'no'); if(j.ok) location.reload();});
  });
};
document.getElementById('time-btn').onclick=function(){
  var days=document.getElementById('time-days').value;
  agentCheckoutPreview('time',{username:'<?= htmlspecialchars($username, ENT_QUOTES) ?>',days:days},function(){
    var body=new URLSearchParams({action:'add_time',username:'<?= htmlspecialchars($username, ENT_QUOTES) ?>',days:days,csrf:document.querySelector('meta[name=csrf]').content});
    agentFetch('api/service_action.php',{method:'POST',body:body}).then(function(j){toast(j.msg,j.ok?'ok':'no'); if(j.ok) location.reload();});
  });
};
</script>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
