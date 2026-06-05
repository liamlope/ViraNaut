<?php
require_once __DIR__ . '/../inc/config.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

if (function_exists('mirza_ensure_marzban_panel_columns')) {
    mirza_ensure_marzban_panel_columns();
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

function panel_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function panel_is_xui_type(string $type): bool
{
    $t = strtolower(trim($type));
    $aliases = ['3x-ui' => 'x-ui_single', '3xui' => 'x-ui_single', 'x_ui_single' => 'x-ui_single'];
    $t = $aliases[$t] ?? $t;
    return in_array($t, ['x-ui_single', 'x-ui', 'alireza_single'], true);
}

if ($action === 'get') {
    $name = trim((string) ($_GET['name'] ?? ''));
    if ($name === '') {
        panel_json(false, 'نام پنل الزامی است');
    }
    $row = db_fetch($pdo, 'SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$row) {
        panel_json(false, 'پنل یافت نشد');
    }
    unset($row['password_panel']);
    panel_json(true, '', ['panel' => $row]);
}

if ($action === 'test') {
    $name = trim((string) ($_GET['name'] ?? ''));
    if ($name === '') {
        panel_json(false, 'نام پنل الزامی است');
    }
    $row = db_fetch($pdo, 'SELECT name_panel, type, code_panel FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$row) {
        panel_json(false, 'پنل یافت نشد');
    }
    if (!panel_is_xui_type((string) ($row['type'] ?? ''))) {
        panel_json(true, 'تست اتصال فقط برای پنل‌های ۳x-ui در وب‌پنل پشتیبانی می‌شود.', [
            'online' => null,
            'skipped' => true,
        ]);
    }
    try {
        require_once __DIR__ . '/../../x-ui_single.php';
    } catch (Throwable $e) {
        panel_json(false, 'خطای بارگذاری ماژول: ' . $e->getMessage());
    }
    $login = login($row['code_panel'], false, true);
    $online = !empty($login['success']);
    $msg = $online ? 'اتصال برقرار است' : strip_tags((string) ($login['msg'] ?? 'اتصال ناموفق'));
    panel_json(true, $msg, ['online' => $online]);
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $name = trim((string) ($_POST['name_panel'] ?? ''));
    if ($name === '') {
        panel_json(false, 'نام پنل الزامی است');
    }
    $exists = db_fetch($pdo, 'SELECT code_panel, type FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$exists) {
        panel_json(false, 'پنل یافت نشد');
    }
    if (!panel_is_xui_type((string) ($exists['type'] ?? ''))) {
        panel_json(false, 'ویرایش از وب فعلاً فقط برای پنل‌های ۳x-ui است');
    }

    $urlPanel = trim((string) ($_POST['url_panel'] ?? ''));
    $linksubx = trim((string) ($_POST['linksubx'] ?? ''));
    $userPanel = trim((string) ($_POST['username_panel'] ?? ''));
    $passPanel = (string) ($_POST['password_panel'] ?? '');
    $token = trim((string) ($_POST['xui_api_token'] ?? ''));
    $limitPanel = trim((string) ($_POST['limit_panel'] ?? '0'));
    $agent = trim((string) ($_POST['agent'] ?? 'all'));
    $status = trim((string) ($_POST['status'] ?? 'active'));
    $inboundsRaw = trim((string) ($_POST['panel_inbounds'] ?? ''));

    if ($urlPanel === '') {
        panel_json(false, 'آدرس API الزامی است');
    }
    if (function_exists('mirza_normalize_xui_panel_url')) {
        $urlPanel = mirza_normalize_xui_panel_url($urlPanel);
    }
    if ($linksubx === '') {
        $linksubx = $urlPanel;
    }
    $statusDb = mirza_panel_is_active_status($status) ? 'active' : 'deactive';
    $tokenDb = ($token === '' || $token === '-') ? null : $token;

    $params = [$urlPanel, $userPanel, $linksubx, $limitPanel, $agent, $statusDb, $tokenDb];
    $sql = 'UPDATE marzban_panel SET url_panel=?, username_panel=?, linksubx=?, limit_panel=?, agent=?, status=?, xui_api_token=?, datelogin=NULL';
    if ($passPanel !== '' && $passPanel !== '********') {
        $sql .= ', password_panel=?';
        $params[] = $passPanel;
    }
    $sql .= ' WHERE name_panel=?';
    $params[] = $name;
    db_query($pdo, $sql, $params);

    if ($inboundsRaw !== '' && $inboundsRaw !== '-') {
        require_once __DIR__ . '/../../x-ui_single.php';
        $ids = mirza_xui_parse_id_list($inboundsRaw);
        if ($ids !== []) {
            mirza_xui_save_panel_inbounds($name, $ids);
        }
    }

    panel_json(true, 'پنل «' . $name . '» ذخیره شد');
}

http_response_code(400);
panel_json(false, 'عملیات نامعتبر');
