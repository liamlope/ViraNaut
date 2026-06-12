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

$pageTitle = 'مرکز ربات';
$pageLede = 'محتوا، پنل VPN، تنظیمات و ابزارهای ربات — دسته‌بندی شده.';
$activeNav = 'bot';
include __DIR__ . '/inc/layout_head.php';

$hubSections = [
    [
        'title' => 'ظاهر و محتوا',
        'items' => [
            ['href' => 'keyboard.php', 'icon' => 'menu', 'title' => 'چیدمان منوی استارت', 'desc' => 'ترتیب دکمه‌های کیبورد اصلی', 'tag' => 'پیشنهادی'],
            ['href' => 'bot-texts.php', 'icon' => 'edit', 'title' => 'متن‌های ربات', 'desc' => 'استارت، پرداخت، فروش و نمایندگی', 'tag' => $textCount . ' متن'],
        ],
    ],
    [
        'title' => 'زیرساخت VPN',
        'items' => [
            ['href' => 'panels.php', 'icon' => 'server', 'title' => 'پنل‌های VPN', 'desc' => 'Marzban و 3x-ui متصل', 'tag' => $panelCount . ' پنل'],
            ['href' => 'test-settings.php', 'icon' => 'server', 'title' => 'اکانت تست', 'desc' => 'محدودیت و پنل تست', 'tag' => 'تست'],
        ],
    ],
    [
        'title' => 'تنظیمات و دسترسی',
        'items' => [
            ['href' => 'bot-settings.php', 'icon' => 'settings', 'title' => 'تنظیمات عمومی', 'desc' => 'وضعیت ربات، فروش، cron', 'tag' => 'سیستمی'],
            ['href' => 'channels.php', 'icon' => 'users', 'title' => 'جوین اجباری', 'desc' => 'کانال‌های اجباری', 'tag' => 'عضویت'],
            ['href' => 'reports-settings.php', 'icon' => 'chart', 'title' => 'گزارش و کانال', 'desc' => 'کانال گزارش و Topicها', 'tag' => 'گزارش'],
            ['href' => 'admins.php', 'icon' => 'users', 'title' => 'ادمین‌های ربات', 'desc' => 'مدیریت دسترسی تلگرام', 'tag' => 'دسترسی'],
            ['href' => 'broadcast.php', 'icon' => 'edit', 'title' => 'ارسال همگانی', 'desc' => 'پیام به همه کاربران', 'tag' => 'مارکتینگ'],
        ],
    ],
];
?>

<?php foreach ($hubSections as $si => $section): ?>
<section class="hub-section fade-up d<?= min($si + 1, 4) ?>">
    <h2 class="hub-section-title"><?= htmlspecialchars($section['title']) ?></h2>
    <div class="bot-hub-grid">
        <?php foreach ($section['items'] as $i => $card): ?>
            <a href="<?= htmlspecialchars($card['href']) ?>" class="card bot-hub-card">
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
</section>
<?php endforeach; ?>

<p class="field-hint fade-up" style="margin-top:18px">
    تنظیمات مالی و SMS خودکار: <a href="finance.php?tab=gateways">مرکز مالی → درگاه‌ها</a>.
    بکاپ و بهینه‌سازی در بخش «نگهداری» منو.
</p>

<style>
.hub-section { margin-bottom: 22px; }
.hub-section-title {
    font-size: .78rem;
    font-weight: 700;
    color: var(--mute);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin: 0 0 10px 2px;
}
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

<?php include __DIR__ . '/inc/layout_foot.php';
