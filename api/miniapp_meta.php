<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../panel/inc/miniapp_templates_defs.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$botUser = $usernamebot ?? '';
if (strpos((string) $botUser, '{') !== false) {
    $botUser = '';
}
$support = '';
try {
    $st = select('setting', '*');
    if ($st && !empty($st['id_support'])) {
        $support = trim((string) $st['id_support']);
    }
} catch (Throwable $e) {
}

$gateways = [];
foreach (['Cartstatus', 'nowpaymentstatus', 'statusnowpayment', 'zarinpalstatus'] as $k) {
    try {
        $row = select('PaySetting', 'ValuePay', 'NamePay', $k, 'select');
        $gateways[$k] = $row['ValuePay'] ?? '';
    } catch (Throwable $e) {
        $gateways[$k] = '';
    }
}

$cardNumber = '';
$cardHolder = '';
try {
    $cn = select('PaySetting', 'ValuePay', 'NamePay', 'CartNumber', 'select');
    $ch = select('PaySetting', 'ValuePay', 'NamePay', 'CartName', 'select');
    $cardNumber = trim((string) ($cn['ValuePay'] ?? ''));
    $cardHolder = trim((string) ($ch['ValuePay'] ?? ''));
} catch (Throwable $e) {
}

$payLimit = static function (string $name, int $default): int {
    try {
        $row = select('PaySetting', 'ValuePay', 'NamePay', $name, 'select');
        return (int) ($row['ValuePay'] ?? $default);
    } catch (Throwable $e) {
        return $default;
    }
};

$depositLimits = [
    'zarinpal' => [
        'min' => $payLimit('minbalancezarinpal', 5000),
        'max' => $payLimit('maxbalancezarinpal', 50000000),
    ],
    'card' => [
        'min' => $payLimit('minbalancecart', 5000),
        'max' => $payLimit('maxbalancecart', 50000000),
    ],
];

$template = vira_miniapp_get_template($pdo ?? null);
$templates = vira_miniapp_templates();
$tplMeta = $templates[$template] ?? $templates['midnight'];

echo json_encode([
    'ok' => true,
    'bot_username' => $botUser,
    'support_id' => $support,
    'domain' => $domainhosts ?? '',
    'gateways' => $gateways,
    'card_number' => $cardNumber,
    'card_holder' => $cardHolder,
    'deposit_limits' => $depositLimits,
    'template' => $template,
    'template_label' => $tplMeta['label'] ?? $template,
    'template_layout' => $tplMeta['layout'] ?? '',
    'template_features' => vira_miniapp_all_features_list(),
    'shell_ui' => true,
    'banners' => [],
    'features' => [
        'quick_buy' => true,
        'balance_bar' => true,
        'support_chat' => $support !== '',
        'dock_nav' => true,
        'pull_refresh' => true,
    ],
], JSON_UNESCAPED_UNICODE);
