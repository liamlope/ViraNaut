<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
$pageTitle = 'بکاپ و بازیابی';
$pageLede = 'بکاپ کامل ZIP (دیتابیس + cronbot + متن‌ها) — بازیابی و ری‌استارت وب‌هوک ربات.';
$activeNav = 'backup';
$extraJs = ['js/bot_tools.js', 'js/backup.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div class="fade-up backup-page" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="card">
        <div class="card-head">
            <div class="card-title">بکاپ کامل (ZIP)</div>
            <div class="card-subtitle">پیشنهادی — همه جداول دیتابیس + فایل‌های cronbot + text.json + راهنمای کرون</div>
        </div>
        <div class="card-body">
            <ul class="field-hint" style="margin:0 0 12px 1.2em;line-height:1.7">
                <li><code>database.sql</code> — کل دیتابیس (ترجیحاً mysqldump)</li>
                <li><code>cronbot/</code> — users.json، info، gift و سایر داده‌های صف</li>
                <li><code>meta/cron_jobs.txt</code> — خطوط crontab برای نصب مجدد</li>
                <li><code>text.json</code> و <code>version</code></li>
            </ul>
            <div class="backup-actions">
                <button type="button" class="btn btn-primary" id="dlFullBackup"><?= icon('package', 14) ?> دانلود ZIP کامل</button>
                <button type="button" class="btn btn-ghost" id="restartBot"><?= icon('bot', 14) ?> ری‌استارت ربات</button>
            </div>
            <p id="backupFullStatus" class="cf" style="margin-top:12px"></p>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-head">
            <div class="card-title">بازیابی از ZIP</div>
            <div class="card-subtitle">جایگزین کامل دیتابیس و فایل‌های بکاپ — با احتیاط</div>
        </div>
        <div class="card-body">
            <p class="field-hint warn-text">تمام داده‌های فعلی دیتابیس با محتوای بکاپ جایگزین می‌شود. قبل از بازیابی یک ZIP تازه بگیرید.</p>
            <p class="field-hint">بکاپ‌های جدید از پنل بدون نیاز به mysql در ترمینال قابل بازیابی‌اند. بکاپ قدیمی mysqldump هم با mysqli/PDO تلاش می‌شود.</p>
            <form id="restoreForm" class="backup-restore-form">
                <label class="field">
                    <span class="field-label">فایل بکاپ (.zip)</span>
                    <input type="file" id="restoreZip" name="backup_zip" class="input" accept=".zip,application/zip" required>
                </label>
                <label class="field backup-check">
                    <input type="checkbox" id="restoreRestart" value="1">
                    <span>بعد از بازیابی، ری‌استارت ربات (تنظیم مجدد وب‌هوک)</span>
                </label>
                <button type="submit" class="btn btn-no">بازیابی از ZIP</button>
            </form>
            <p id="restoreStatus" class="cf" style="margin-top:12px"></p>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-head"><div class="card-title">بکاپ سریع SQL</div></div>
        <div class="card-body">
            <p class="field-hint">فقط جداول اصلی — بدون فایل cronbot.</p>
            <button type="button" class="btn btn-ghost btn-sm" id="dlBackup">دانلود .sql</button>
            <p id="backupStatus" class="cf" style="margin-top:12px"></p>
        </div>
    </div>
</div>

<style>
.backup-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.backup-restore-form { display: grid; gap: 12px; max-width: 480px; }
.backup-check { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.warn-text { color: var(--warn, #c90); }
</style>

<?php include __DIR__ . '/inc/layout_foot.php';
