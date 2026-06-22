<?php

/** بارگذاری قالب مینی‌اپ — از shopSetting یا پیش‌نمایش ?tpl_preview= */

function vira_miniapp_resolve_template(): string
{
    $allowed = ['midnight', 'aurora', 'emerald', 'sunset', 'ocean'];
    $preview = trim((string) ($_GET['tpl_preview'] ?? ''));
    if ($preview !== '' && in_array($preview, $allowed, true)) {
        return $preview;
    }
    $default = 'midnight';
    $config = dirname(__DIR__, 2) . '/config.php';
    if (!is_file($config)) {
        return $default;
    }
    require_once $config;
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $row = $pdo->query("SELECT value FROM shopSetting WHERE Namevalue = 'miniapp_template' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $v = trim((string) ($row['value'] ?? ''));
            if (in_array($v, $allowed, true)) {
                return $v;
            }
        } elseif (function_exists('select')) {
            $row = @select('shopSetting', 'value', 'Namevalue', 'miniapp_template', 'select');
            $v = trim((string) ($row['value'] ?? ''));
            if (in_array($v, $allowed, true)) {
                return $v;
            }
        }
    } catch (Throwable $e) {
        return $default;
    }
    return $default;
}

/** فقط پیش‌نمایش پنل — نه آدرس اصلی /app/ برای کاربران */
function vira_miniapp_is_demo_mode(): bool
{
    if (isset($_GET['tpl_preview']) && trim((string) $_GET['tpl_preview']) !== '') {
        return true;
    }
    $demo = $_GET['demo'] ?? null;
    return $demo === '1' || $demo === 1 || $demo === 'true';
}
