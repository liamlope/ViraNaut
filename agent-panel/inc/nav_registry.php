<?php

function agent_nav_sections(): array
{
    return [
        [
            'id' => 'main',
            'heading' => 'پیشخوان',
            'items' => [
                ['href' => 'index.php', 'icon' => 'dashboard', 'label' => 'داشبورد', 'nav' => 'dashboard'],
                ['href' => 'profile.php', 'icon' => 'users', 'label' => 'پروفایل', 'nav' => 'profile'],
            ],
        ],
        [
            'id' => 'buy',
            'heading' => 'خرید',
            'items' => [
                ['href' => 'buy.php', 'icon' => 'package', 'label' => 'خرید سرویس', 'nav' => 'buy'],
                ['href' => 'buy_custom.php', 'icon' => 'edit', 'label' => 'سرویس دلخواه', 'nav' => 'buy_custom'],
                ['href' => 'buy_bulk.php', 'icon' => 'server', 'label' => 'خرید انبوه', 'nav' => 'buy_bulk'],
                ['href' => 'buy_test.php', 'icon' => 'server', 'label' => 'اکانت تست', 'nav' => 'buy_test'],
            ],
        ],
        [
            'id' => 'services',
            'heading' => 'سرویس‌ها',
            'items' => [
                ['href' => 'services.php', 'icon' => 'server', 'label' => 'لیست سرویس‌ها', 'nav' => 'services'],
            ],
        ],
        [
            'id' => 'finance',
            'heading' => 'مالی',
            'items' => [
                ['href' => 'finance.php', 'icon' => 'wallet', 'label' => 'مرکز مالی', 'nav' => 'finance', 'nav_match' => ['finance', 'transactions', 'tariff']],
                ['href' => 'transactions.php', 'icon' => 'invoice', 'label' => 'تراکنش‌ها', 'nav' => 'transactions'],
                ['href' => 'tariff.php', 'icon' => 'card', 'label' => 'تعرفه', 'nav' => 'tariff'],
            ],
        ],
        [
            'id' => 'agent',
            'heading' => 'نمایندگی',
            'items' => [
                ['href' => 'affiliates.php', 'icon' => 'users', 'label' => 'زیرمجموعه', 'nav' => 'affiliates'],
                ['href' => 'agent_settings.php', 'icon' => 'settings', 'label' => 'تنظیمات n2', 'nav' => 'agent_settings'],
                ['href' => 'reports.php', 'icon' => 'chart', 'label' => 'گزارش‌ها', 'nav' => 'reports'],
            ],
        ],
        [
            'id' => 'tools',
            'heading' => 'ابزار',
            'items' => [
                ['href' => 'api.php', 'icon' => 'bot', 'label' => 'API', 'nav' => 'api'],
                ['href' => 'support.php', 'icon' => 'edit', 'label' => 'پشتیبانی', 'nav' => 'support'],
                ['href' => 'settings.php', 'icon' => 'settings', 'label' => 'تنظیمات', 'nav' => 'settings'],
                ['href' => 'logout.php', 'icon' => 'logout', 'label' => 'خروج', 'nav' => 'logout'],
            ],
        ],
    ];
}

function agent_render_sidebar(string $activeNav = ''): void
{
    foreach (agent_nav_sections() as $section) {
        echo '<div class="nav-section"><div class="nav-heading">' . htmlspecialchars($section['heading']) . '</div>';
        foreach ($section['items'] as $item) {
            $match = $item['nav_match'] ?? [$item['nav']];
            $isActive = in_array($activeNav, $match, true);
            $cls = 'nav-item' . ($isActive ? ' active' : '');
            echo '<a href="' . htmlspecialchars($item['href']) . '" class="' . $cls . '" title="' . htmlspecialchars($item['label']) . '">';
            echo icon($item['icon'], 20);
            echo '<span>' . htmlspecialchars($item['label']) . '</span></a>';
        }
        echo '</div>';
    }
}

function agent_bot_nav_ids(): array
{
    return ['buy', 'services', 'finance', 'dashboard'];
}
