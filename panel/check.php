<?php
/**
 * تشخیص سریع پنل — بعد از deploy حذف یا محدود به IP کنید.
 */
header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$required = [
    'inc/config.php',
    'inc/layout_head.php',
    'inc/bot_data.php',
    'inc/bot_settings_defs.php',
    'inc/shop_settings_defs.php',
    'bot-texts.php',
    'bot-settings.php',
    'index.php',
    'login.php',
    'finance.php',
    'shop-settings.php',
    'api/finance.php',
    'api/shop-settings.php',
];

echo "Mirza panel check — " . date('c') . "\n\n";

foreach ($required as $rel) {
    $path = $root . '/' . $rel;
    echo $rel . ': ';
    if (!is_readable($path)) {
        echo "MISSING\n";
        continue;
    }
    $src = @file_get_contents($path);
    if ($rel === 'bot-texts.php' && $src !== false && preg_match('/foreach\s*\(\s*\$groups\s+as\s+\$gi\s*=>\s*\$groupName\s*=>/', $src)) {
        echo "BROKEN (invalid foreach on server — upload fixed bot-texts.php)\n";
        continue;
    }
    echo "ok (" . filesize($path) . " bytes)\n";
}

echo "\nPHP " . PHP_VERSION . "\n";

try {
    require_once $root . '/inc/config.php';
    echo "config.php: loaded, DB ok\n";
} catch (Throwable $e) {
    echo "config.php: FAIL — " . $e->getMessage() . "\n";
}
