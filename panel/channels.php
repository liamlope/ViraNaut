<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
$pageTitle = 'کانال‌های جوین اجباری';
$pageLede = 'مدیریت کانال‌هایی که کاربر باید عضو شود.';
$activeNav = 'channels';
$extraJs = ['js/bot_tools.js', 'js/channels.js'];
include __DIR__ . '/inc/layout_head.php';
?>
<div class="fade-up" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="card">
        <div class="card-head"><div class="card-title">افزودن کانال</div></div>
        <div class="card-body">
            <form id="channelForm" class="form-grid" style="grid-template-columns:1fr 1fr 1fr auto">
                <input type="text" name="remark" class="input" placeholder="نام / توضیح" required>
                <input type="text" name="link" class="input" placeholder="@channel یا لینک" dir="ltr" required>
                <input type="text" name="linkjoin" class="input" placeholder="لینک جوین (اختیاری)" dir="ltr">
                <button type="submit" class="btn btn-primary btn-sm">افزودن</button>
            </form>
        </div>
    </div>
    <div class="card" style="margin-top:16px">
        <div class="card-head"><div class="card-title">لیست کانال‌ها</div></div>
        <div class="card-body" id="channelsList"><p class="cf">بارگذاری…</p></div>
    </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php';
