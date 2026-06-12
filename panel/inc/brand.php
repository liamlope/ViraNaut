<?php
/**
 * ViraNaut (ویرانات) — برند مرکزی پنل و ربات
 */
define('VIRA_BRAND_NAME', 'ViraNaut');
define('VIRA_BRAND_NAME_FA', 'ویرانات');
define('VIRA_VERSION', '1.9');
define('VIRA_VERSION_LABEL', '1.9 ViraNaut');
define('VIRA_MINIAPP_VERSION', '2.0.1');
define('VIRA_PANEL_TITLE', 'پنل مدیریت ویرانات');
define('VIRA_PANEL_SHORT', 'ویرانات · پنل');
define('VIRA_BRAND_MARK', 'V');
define('VIRA_GITHUB_URL', 'https://github.com/liamlope/ViraNaut');
define('VIRA_SUPPORT_GROUP', 'https://t.me/satraNaut');

function vira_brand_version(): string
{
    $f = dirname(__DIR__, 2) . '/version';
    if (is_readable($f)) {
        $v = trim((string) file_get_contents($f));
        if ($v !== '') {
            return $v;
        }
    }
    return VIRA_VERSION_LABEL;
}

function vira_format_compact_number(int $n): string
{
    if ($n >= 1_000_000_000) {
        return rtrim(rtrim(number_format($n / 1_000_000_000, 1, '.', ''), '0'), '.') . 'B';
    }
    if ($n >= 1_000_000) {
        return rtrim(rtrim(number_format($n / 1_000_000, 1, '.', ''), '0'), '.') . 'M';
    }
    if ($n >= 10_000) {
        return rtrim(rtrim(number_format($n / 1_000, 1, '.', ''), '0'), '.') . 'K';
    }
    return number_format($n);
}
