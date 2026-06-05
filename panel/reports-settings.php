<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
$pageTitle = 'تنظیم گزارش‌ها';
$activeNav = 'reports-settings';
$extraJs = ['js/bot_tools.js', 'js/reports-settings.js'];
include __DIR__ . '/inc/layout_head.php';
?>
<div class="fade-up" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="card">
        <div class="card-head"><div class="card-title">کانال گزارش</div></div>
        <div class="card-body">
            <label class="field">
                <span class="field-label">آیدی گروه/کانال گزارش</span>
                <input type="text" id="channelReport" class="input" dir="ltr" placeholder="-100...">
            </label>
        </div>
    </div>
    <div class="card" style="margin-top:16px">
        <div class="card-head"><div class="card-title">Topic ID هر بخش</div></div>
        <div class="card-body" id="topicsForm"></div>
        <div class="card-foot">
            <button type="button" class="btn btn-primary" id="saveTopics">ذخیره</button>
        </div>
    </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php';
