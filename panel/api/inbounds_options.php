<?php
require_once __DIR__ . '/../inc/config.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$panelName = trim((string) ($_GET['panel'] ?? ''));
if ($panelName === '') {
    echo json_encode(['ok' => false, 'msg' => 'panel required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = db_fetch($pdo, 'SELECT name_panel, type FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$panelName]);
if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'پنل در دیتابیس یافت نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

$typeRaw = strtolower(trim((string) ($row['type'] ?? '')));
$typeAliases = [
    '3x-ui' => 'x-ui_single',
    '3xui' => 'x-ui_single',
    'x_ui_single' => 'x-ui_single',
    'x_ui' => 'x-ui',
];
$typeNorm = $typeAliases[$typeRaw] ?? $typeRaw;
$xuiTypes = ['x-ui_single', 'x-ui', 'alireza_single'];

if (!in_array($typeNorm, $xuiTypes, true)) {
    echo json_encode([
        'ok' => true,
        'items' => [],
        'note' => 'not_xui',
        'msg' => 'نوع پنل «' . ($row['type'] ?? '') . '» است؛ اینباند فقط برای ۳x-ui / alireza_single نمایش داده می‌شود.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../../x-ui_single.php';
} catch (Throwable $e) {
    error_log('inbounds_options.php load x-ui_single: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'خطای بارگذاری ماژول پنل: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$list = mirza_xui_list_inbound_options($panelName);
$probeUrl = trim((string) ($_GET['probe_url'] ?? ''));
$probeToken = trim((string) ($_GET['probe_token'] ?? ''));

if ($probeUrl !== '' && $probeToken !== '' && function_exists('mirza_xui_verify_bearer')) {
    $probe = mirza_xui_verify_bearer($probeUrl, $probeToken, true);
    if (!empty($probe['success'])) {
        $base = mirza_xui_public_base($probeUrl);
        $url = $base . '/panel/api/inbounds/options';
        $req = new CurlRequest($url);
        $req->setTimeout(30000);
        $req->setConnectTimeout(15000);
        $req->setBearerToken($probeToken);
        $req->setHeaders(mirza_xui_spa_headers($probeUrl));
        $res = $req->get();
        if (empty($res['error']) && (int) ($res['status'] ?? 0) === 200) {
            $dec = json_decode((string) ($res['body'] ?? ''), true);
            if (is_array($dec) && !empty($dec['success']) && isset($dec['obj'])) {
                $list = mirza_xui_normalize_inbound_options_obj($dec['obj']);
            }
        }
    } else {
        $msg = trim((string) ($probe['msg'] ?? $probe['error'] ?? ''));
        if ($msg !== '') {
            echo json_encode(['ok' => true, 'items' => [], 'msg' => 'اتصال تستی با URL/Token جدید برقرار نشد: ' . $msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$fetchErr = function_exists('mirza_xui_last_inbound_fetch_error')
    ? trim(mirza_xui_last_inbound_fetch_error())
    : '';

$items = [];
foreach ($list as $ib) {
    if (!is_array($ib)) {
        continue;
    }
    $id = (int) ($ib['id'] ?? $ib['inboundId'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $items[] = [
        'id' => $id,
        'remark' => (string) ($ib['remark'] ?? $ib['tag'] ?? ''),
        'protocol' => (string) ($ib['protocol'] ?? ''),
        'port' => (int) ($ib['port'] ?? 0),
    ];
}

if ($items === []) {
    $msg = $fetchErr !== ''
        ? $fetchErr
        : 'اینباندی از پنل دریافت نشد — توکن API، آدرس پنل یا لاگین پنل را در تنظیمات پنل بررسی کنید.';
    echo json_encode(['ok' => true, 'items' => [], 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
