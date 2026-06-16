<?php

/**
 * ساختار ناوبری پنل وب — منبع واحد برای سایدبار و bottom-nav
 */
function vira_panel_nav_sections(): array
{
    return [
        [
            'id' => 'home',
            'heading' => 'پیشخوان',
            'items' => [
                ['href' => 'index.php', 'icon' => 'dashboard', 'label' => 'داشبورد', 'nav' => 'dashboard', 'title' => 'داشبورد'],
            ],
        ],
        [
            'id' => 'customers',
            'heading' => 'مشتریان و سفارش',
            'items' => [
                ['href' => 'users.php', 'icon' => 'users', 'label' => 'کاربران', 'nav' => 'users', 'title' => 'کاربران'],
                ['href' => 'invoice.php', 'icon' => 'invoice', 'label' => 'سفارشات', 'nav' => 'invoice', 'title' => 'سفارشات'],
                ['href' => 'service.php', 'icon' => 'server', 'label' => 'سرویس‌های دستی', 'nav' => 'service', 'title' => 'سرویس‌ها'],
            ],
        ],
        [
            'id' => 'shop',
            'heading' => 'فروشگاه',
            'items' => [
                ['href' => 'product.php', 'icon' => 'package', 'label' => 'محصولات', 'nav' => 'product', 'title' => 'محصولات'],
                ['href' => 'shop-settings.php', 'icon' => 'settings', 'label' => 'تنظیمات فروشگاه', 'nav' => 'shop-settings', 'title' => 'فروشگاه'],
                ['href' => 'miniapp-templates.php', 'icon' => 'edit', 'label' => 'قالب مینی‌اپ', 'nav' => 'miniapp-templates', 'title' => 'قالب مینی‌اپ'],
            ],
        ],
        [
            'id' => 'finance',
            'heading' => 'مالی',
            'items' => [
                ['href' => 'finance.php', 'icon' => 'wallet', 'label' => 'مرکز مالی', 'nav' => 'finance', 'title' => 'مرکز مالی', 'nav_match' => ['finance', 'payment']],
            ],
        ],
        [
            'id' => 'bot',
            'heading' => 'ربات تلگرام',
            'items' => [
                ['href' => 'bot.php', 'icon' => 'bot', 'label' => 'مرکز ربات', 'nav' => 'bot', 'title' => 'مرکز ربات'],
                ['href' => 'keyboard.php', 'icon' => 'menu', 'label' => 'چیدمان منو', 'nav' => 'keyboard', 'title' => 'چیدمان منو'],
                ['href' => 'bot-emojis.php', 'icon' => 'edit', 'label' => 'ایموجی پرمیوم', 'nav' => 'bot-emojis', 'title' => 'کتابخانه ایموجی'],
                ['href' => 'bot-texts.php', 'icon' => 'edit', 'label' => 'متن‌های ربات', 'nav' => 'bot-texts', 'title' => 'متن‌های ربات'],
                ['href' => 'panels.php', 'icon' => 'server', 'label' => 'پنل‌های VPN', 'nav' => 'panels', 'title' => 'پنل VPN'],
                ['href' => 'test-settings.php', 'icon' => 'server', 'label' => 'اکانت تست', 'nav' => 'test-settings', 'title' => 'اکانت تست'],
            ],
        ],
        [
            'id' => 'bot-config',
            'heading' => 'تنظیمات ربات',
            'items' => [
                ['href' => 'bot-settings.php', 'icon' => 'settings', 'label' => 'تنظیمات عمومی', 'nav' => 'bot-settings', 'title' => 'تنظیمات ربات'],
                ['href' => 'channels.php', 'icon' => 'users', 'label' => 'جوین اجباری', 'nav' => 'channels', 'title' => 'جوین اجباری'],
                ['href' => 'reports-settings.php', 'icon' => 'chart', 'label' => 'گزارش و کانال', 'nav' => 'reports-settings', 'title' => 'گزارش‌ها'],
                ['href' => 'admins.php', 'icon' => 'users', 'label' => 'ادمین‌های ربات', 'nav' => 'admins', 'title' => 'ادمین‌ها'],
                ['href' => 'broadcast.php', 'icon' => 'edit', 'label' => 'ارسال همگانی', 'nav' => 'broadcast', 'title' => 'همگانی'],
            ],
        ],
        [
            'id' => 'system',
            'heading' => 'نگهداری',
            'items' => [
                ['href' => 'backup.php', 'icon' => 'package', 'label' => 'بکاپ', 'nav' => 'backup', 'title' => 'بکاپ'],
                ['href' => 'optimize.php', 'icon' => 'chart', 'label' => 'بهینه‌سازی', 'nav' => 'optimize', 'title' => 'بهینه‌سازی'],
                ['href' => 'migration.php', 'icon' => 'package', 'label' => 'مهاجرت DB', 'nav' => 'migration', 'title' => 'مهاجرت'],
                ['href' => 'about.php', 'icon' => 'chart', 'label' => 'درباره', 'nav' => 'about', 'title' => 'درباره'],
            ],
        ],
        [
            'id' => 'panel',
            'heading' => 'پنل وب',
            'items' => [
                ['href' => 'settings.php', 'icon' => 'settings', 'label' => 'ظاهر و امنیت', 'nav' => 'settings', 'title' => 'تنظیمات پنل'],
                ['href' => 'logout.php', 'icon' => 'logout', 'label' => 'خروج', 'nav' => '', 'title' => 'خروج'],
            ],
        ],
    ];
}

function vira_panel_nav_is_active(array $item, string $activeNav): bool
{
    if (!empty($item['nav_match']) && is_array($item['nav_match'])) {
        return in_array($activeNav, $item['nav_match'], true);
    }
    return ($item['nav'] ?? '') === $activeNav;
}

function vira_panel_bot_nav_ids(): array
{
    return [
        'bot', 'keyboard', 'bot-emojis', 'bot-texts', 'panels', 'bot-settings',
        'test-settings', 'channels', 'reports-settings', 'admins', 'broadcast',
    ];
}

function vira_panel_render_sidebar(string $activeNav): void
{
    foreach (vira_panel_nav_sections() as $section) {
        echo '<div class="nav-section">';
        echo '<div class="nav-heading">' . htmlspecialchars($section['heading']) . '</div>';
        foreach ($section['items'] as $item) {
            $active = vira_panel_nav_is_active($item, $activeNav) ? ' active' : '';
            $href = htmlspecialchars($item['href']);
            $title = htmlspecialchars($item['title'] ?? $item['label']);
            $label = htmlspecialchars($item['label']);
            echo '<a href="' . $href . '" class="nav-item' . $active . '" title="' . $title . '">';
            echo '<span class="nav-icon">' . icon($item['icon']) . '</span>';
            echo '<span class="nav-label">' . $label . '</span></a>';
        }
        echo '</div>';
    }
}
