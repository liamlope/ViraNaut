<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
$pageTitle = 'ارسال پیام همگانی';
$activeNav = 'broadcast';
$extraJs = ['js/bot_tools.js', 'js/broadcast.js'];
include __DIR__ . '/inc/layout_head.php';
?>
<div class="fade-up" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="card">
        <div class="card-head"><div class="card-title">پیام به همه کاربران فعال</div></div>
        <div class="card-body">
            <p class="field-hint">ارسال دسته‌ای (۲۵ کاربر در هر مرحله) — برای جلوگیری از محدودیت تلگرام.</p>
            <textarea id="broadcastMsg" class="input" rows="6" placeholder="متن پیام (HTML)"></textarea>
            <button type="button" class="btn btn-primary" id="startBroadcast" style="margin-top:12px">شروع ارسال</button>
            <div id="broadcastProgress" class="cf" style="margin-top:12px"></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php';
