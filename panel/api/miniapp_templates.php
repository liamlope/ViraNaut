<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/miniapp_templates_defs.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

function mat_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

global $domainhosts;
$domain = '';
if (!empty($domainhosts) && strpos((string) $domainhosts, '{') === false) {
    $host = function_exists('vira_normalize_domainhosts_value')
        ? vira_normalize_domainhosts_value($domainhosts)
        : preg_replace('#^https?://#i', '', trim((string) $domainhosts));
    $domain = $host;
}

if ($action === 'list') {
    $current = vira_miniapp_get_template($pdo);
    $items = [];
    foreach (vira_miniapp_templates() as $t) {
        $items[] = array_merge($t, [
            'active' => $t['id'] === $current,
            'preview_url' => $domain ? vira_miniapp_preview_url($t['id'], $domain) : '',
        ]);
    }
    mat_json(true, '', [
        'current' => $current,
        'items' => $items,
        'app_url' => $domain ? 'https://' . $domain . '/app/' : '',
    ]);
}

if ($action === 'apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = trim((string) ($_POST['template_id'] ?? ''));
    try {
        vira_miniapp_set_template($pdo, $id);
        mat_json(true, 'قالب «' . (vira_miniapp_templates()[$id]['label'] ?? $id) . '» برای مینی‌اپ فعال شد.', [
            'current' => $id,
        ]);
    } catch (Throwable $e) {
        mat_json(false, $e->getMessage());
    }
}

http_response_code(400);
mat_json(false, 'عملیات نامعتبر');
