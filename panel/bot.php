<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/bot_data.php';
require_auth();

$panelCount = 0;
$textCount = 0;
try {
    $panelCount = db_count($pdo, "SELECT COUNT(*) FROM marzban_panel");
    $textCount = db_count($pdo, "SELECT COUNT(*) FROM textbot");
} catch (Exception $e) {
}

$pageTitle = 'مدیریت ربات';
$pageLede = 'چیدمان منو، متن‌ها، پنل‌های VPN و تنظیمات کلیدی ربات از یکجا.';
$activeNav = 'bot';
include __DIR__ . '/inc/layout_head.php';

$cards = [
    [
        'href' => 'keyboard.php',
        'icon' => 'menu',
        'title' => 'چیدمان منوی استارت',
        'desc' => 'ترتیب و چیدمان دکمه‌های کیبورد اصلی ربات',
        'tag' => 'پیشنهادی',
    ],
    [
        'href' => 'bot-texts.php',
        'icon' => 'edit',
        'title' => 'متن‌های ربات',
        'desc' => 'ویرایش متن دکمه‌ها، استارت، پرداخت و نمایندگی',
        'tag' => $textCount . ' متن',
    ],
    [
        'href' => 'panels.php',
        'icon' => 'server',
        'title' => 'پنل‌های VPN',
        'desc' => 'لیست Marzban و 3x-ui متصل به ربات',
        'tag' => $panelCount . ' پنل',
    ],
    [
        'href' => 'bot-settings.php',
        'icon' => 'settings',
        'title' => 'تنظیمات ربات',
        'desc' => 'وضعیت ربات، کانال گزارش، اینلاین و محدودیت تست',
        'tag' => 'سیستمی',
    ],
];
?>

<div class="bot-hub-grid fade-up">
    <?php foreach ($cards as $i => $card): ?>
        <a href="<?= htmlspecialchars($card['href']) ?>" class="card bot-hub-card fade-up d<?= min($i + 1, 4) ?>">
            <div class="bot-hub-icon"><?= icon($card['icon'], 22) ?></div>
            <div class="bot-hub-body">
                <div class="card-title"><?= htmlspecialchars($card['title']) ?></div>
                <div class="card-subtitle"><?= htmlspecialchars($card['desc']) ?></div>
                <span class="tag tag-ok" style="margin-top:10px;font-size:.68rem"><?= htmlspecialchars($card['tag']) ?></span>
            </div>
            <span class="bot-hub-arrow">←</span>
        </a>
    <?php endforeach; ?>
</div>

<p class="field-hint fade-up d2" style="margin-top:18px">
    برای افزودن یا حذف پنل VPN و محصولات پیشرفته، از منوی تلگرام (پنل ادمین ربات) استفاده کنید.
    این بخش مدیریت روزمره و چیدمان منو را پوشش می‌دهد.
</p>

<style>
.bot-hub-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
}
.bot-hub-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px;
    text-decoration: none;
    color: inherit;
    transition: border-color .15s, transform .12s;
}
.bot-hub-card:hover {
    border-color: var(--ac);
    transform: translateY(-2px);
}
.bot-hub-icon {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--acg);
    color: var(--ac);
    border-radius: 10px;
}
.bot-hub-body { flex: 1; min-width: 0; }
.bot-hub-arrow {
    color: var(--mute);
    font-size: 1.1rem;
    align-self: center;
}
</style>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
