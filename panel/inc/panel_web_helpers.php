<?php

require_once __DIR__ . '/panel_type_defs.php';

function panel_web_default_json_prices(): array
{
    return [
        'value' => json_encode(['f' => '4000', 'n' => '4000', 'n2' => '4000']),
        'main' => json_encode(['f' => '1', 'n' => '1', 'n2' => '1']),
        'max' => json_encode(['f' => '1000', 'n' => '1000', 'n2' => '1000']),
        'custom' => json_encode(['f' => '0', 'n' => '0', 'n2' => '0']),
    ];
}

/** limit_panel: 0 یعنی نامحدود. */
function panel_web_normalize_limit_value($raw): int
{
    $v = trim((string) $raw);
    if ($v === '' || $v === '-' || mb_strtolower($v) === 'unlimited' || $v === 'نامحدود' || $v === '∞') {
        return 0;
    }
    if (!preg_match('/^\d+$/', $v)) {
        return 0;
    }
    return max(0, (int) $v);
}

/** اینباند ذخیره‌شده در DB (ستون inbounds یا inboundid) */
function panel_web_stored_inbounds_raw(array $row): string
{
    $candidates = [
        trim((string) ($row['inbounds'] ?? '')),
        trim((string) ($row['inboundid'] ?? '')),
    ];
    foreach ($candidates as $raw) {
        if ($raw === '' || $raw === 'null' || $raw === '-') {
            continue;
        }
        if (function_exists('mirza_xui_parse_id_list')) {
            require_once __DIR__ . '/../../x-ui_single.php';
            if (mirza_xui_parse_id_list($raw) !== []) {
                return $raw;
            }
            continue;
        }
        return $raw;
    }
    return '';
}

function panel_web_validate_inbounds(string $type, string $raw): ?string
{
    if (!panel_web_type_needs_inbounds($type)) {
        return null;
    }
    $raw = trim($raw);
    if ($raw === '' || $raw === '-') {
        return 'حداقل یک اینباند باید انتخاب شود.';
    }
    if (function_exists('mirza_xui_parse_id_list')) {
        require_once __DIR__ . '/../../x-ui_single.php';
        $ids = mirza_xui_parse_id_list($raw);
        if ($ids === []) {
            return 'اینباند انتخاب‌شده معتبر نیست.';
        }
    }
    return null;
}

function panel_web_count_active_invoices(PDO $pdo, string $namePanel): int
{
    return (int) db_count(
        $pdo,
        "SELECT COUNT(*) FROM invoice WHERE Service_location = ? AND Status IN ('active','sendedwarn','send_on_hold')",
        [$namePanel]
    );
}

function panel_web_insert_panel(PDO $pdo, string $type, array $data): array
{
    $type = panel_web_normalize_type($type);
    $defs = panel_web_type_defs();
    if (!isset($defs[$type])) {
        return ['ok' => false, 'msg' => 'نوع پنل پشتیبانی نمی‌شود.'];
    }
    $def = $defs[$type];
    $name = trim((string) ($data['name_panel'] ?? ''));
    $url = trim((string) ($data['url_panel'] ?? ''));
    if ($name === '' || $url === '') {
        return ['ok' => false, 'msg' => 'نام پنل و آدرس الزامی است.'];
    }
    if (db_count($pdo, 'SELECT COUNT(*) FROM marzban_panel WHERE name_panel = ?', [$name]) > 0) {
        return ['ok' => false, 'msg' => 'پنلی با این نام وجود دارد.'];
    }

    $ibErr = null;
    if (!empty($def['needs_inbounds'])) {
        $ibRaw = trim((string) ($data['panel_inbounds'] ?? ''));
        if ($ibRaw !== '' && $ibRaw !== '-') {
            $ibErr = panel_web_validate_inbounds($type, $ibRaw);
            if ($ibErr !== null) {
                return ['ok' => false, 'msg' => $ibErr];
            }
        }
    }

    if (!empty($def['xui']) && function_exists('mirza_normalize_xui_panel_url')) {
        require_once __DIR__ . '/../../x-ui_single.php';
        $url = mirza_normalize_xui_panel_url($url);
    }

    $linksubx = trim((string) ($data['linksubx'] ?? ''));
    if ($linksubx === '') {
        $linksubx = $url;
    }

    $prices = panel_web_default_json_prices();
    $codePanel = bin2hex(random_bytes(2));
    $dbType = (string) ($def['db_type'] ?? $type);
    $versionPanel = (string) ($def['version_panel'] ?? '0');
    $limit = panel_web_normalize_limit_value($data['limit_panel'] ?? '0');
    $agent = trim((string) ($data['agent'] ?? 'all'));
    $user = trim((string) ($data['username_panel'] ?? ''));
    $pass = (string) ($data['password_panel'] ?? '');
    $token = trim((string) ($data['xui_api_token'] ?? ''));
    $tokenDb = ($token === '' || $token === '-') ? null : $token;
    $secret = trim((string) ($data['secret_code'] ?? ''));

    if ($type === 'hiddify' && $secret === '') {
        return ['ok' => false, 'msg' => 'کلید API هیدیفای الزامی است.'];
    }
    if (in_array($type, ['marzban', 'pasarguard'], true) && ($user === '' || $pass === '')) {
        return ['ok' => false, 'msg' => 'نام کاربری و رمز Marzban الزامی است.'];
    }

    if ($type === 'hiddify') {
        $user = 'null';
        $pass = 'null';
    }

    try {
        db_query(
            $pdo,
            "INSERT INTO marzban_panel (
                code_panel, name_panel, sublink, config, MethodUsername, TestAccount, status, limit_panel,
                namecustom, Methodextend, type, conecton, inboundid, agent, inbound_deactive, inboundstatus,
                url_panel, username_panel, password_panel, time_usertest, val_usertest, linksubx,
                priceextravolume, priceextratime, pricecustomvolume, pricecustomtime,
                mainvolume, maxvolume, maintime, maxtime, status_extend, subvip, changeloc, customvolume,
                on_hold_test, version_panel, xui_api_token, secret_code
            ) VALUES (
                ?, ?, 'onsublink', 'offconfig', 'آیدی عددی + حروف و عدد رندوم', 'ONTestAccount', 'active', ?,
                'none', 'ریست حجم و زمان', ?, 'offconecton', '1', ?, '1', 'offinbounddisable',
                ?, ?, ?, '1', '100', ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'on_extend', 'offsubvip', 'offchangeloc', ?,
                '1', ?, ?, ?
            )",
            [
                $codePanel, $name, $limit, $dbType, $agent,
                $url, $user, $pass, $linksubx,
                $prices['value'], $prices['value'], $prices['value'], $prices['value'],
                $prices['main'], $prices['max'], $prices['main'], $prices['max'],
                $prices['custom'], $versionPanel, $tokenDb,
                $type === 'hiddify' ? $secret : null,
            ]
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => 'خطای ثبت: ' . $e->getMessage()];
    }

    if (!empty($def['needs_inbounds'])) {
        require_once __DIR__ . '/../../x-ui_single.php';
        $ids = mirza_xui_parse_id_list((string) ($data['panel_inbounds'] ?? ''));
        if ($ids !== []) {
            mirza_xui_save_panel_inbounds($name, $ids);
        }
    }

    return ['ok' => true, 'msg' => 'پنل «' . $name . '» اضافه شد.', 'code_panel' => $codePanel];
}

function panel_web_update_panel(PDO $pdo, string $name, array $data): array
{
    $row = db_fetch($pdo, 'SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$row) {
        return ['ok' => false, 'msg' => 'پنل یافت نشد.'];
    }
    $type = panel_web_normalize_type((string) ($row['type'] ?? ''));
    if ($type === 'marzban' && (string) ($row['version_panel'] ?? '0') === '1') {
        $type = 'pasarguard';
    }
    $defs = panel_web_type_defs();
    if (!isset($defs[$type])) {
        return ['ok' => false, 'msg' => 'ویرایش این نوع پنل از وب پشتیبانی نمی‌شود.'];
    }

    $url = trim((string) ($data['url_panel'] ?? $row['url_panel'] ?? ''));
    if ($url === '') {
        return ['ok' => false, 'msg' => 'آدرس الزامی است.'];
    }
    if (!empty($defs[$type]['xui']) && function_exists('mirza_normalize_xui_panel_url')) {
        require_once __DIR__ . '/../../x-ui_single.php';
        $url = mirza_normalize_xui_panel_url($url);
    }

    $inboundsRaw = trim((string) ($data['panel_inbounds'] ?? ''));
    if (!empty($defs[$type]['needs_inbounds'])) {
        if ($inboundsRaw === '' || $inboundsRaw === '-') {
            $inboundsRaw = panel_web_stored_inbounds_raw($row);
        }
        if ($inboundsRaw === '' || $inboundsRaw === '-') {
            return ['ok' => false, 'msg' => 'حداقل یک اینباند باید انتخاب شود.'];
        }
        $ibErr = panel_web_validate_inbounds($type, $inboundsRaw);
        if ($ibErr !== null) {
            return ['ok' => false, 'msg' => $ibErr];
        }
    }

    $linksubx = trim((string) ($data['linksubx'] ?? ''));
    if ($linksubx === '') {
        $linksubx = $url;
    }
    $limit = panel_web_normalize_limit_value($data['limit_panel'] ?? ($row['limit_panel'] ?? '0'));
    $agent = trim((string) ($data['agent'] ?? $row['agent'] ?? 'all'));
    $statusRaw = trim((string) ($data['status'] ?? $row['status'] ?? 'active'));
    $statusDb = function_exists('mirza_panel_is_active_status') && mirza_panel_is_active_status($statusRaw) ? 'active' : 'deactive';

    $params = [$url, $linksubx, $limit, $agent, $statusDb];
    $sql = 'UPDATE marzban_panel SET url_panel=?, linksubx=?, limit_panel=?, agent=?, status=?, datelogin=NULL';

    if (isset($data['username_panel'])) {
        $sql .= ', username_panel=?';
        $params[] = trim((string) $data['username_panel']);
    }
    $pass = (string) ($data['password_panel'] ?? '');
    if ($pass !== '' && $pass !== '********') {
        $sql .= ', password_panel=?';
        $params[] = $pass;
    }
    if (array_key_exists('xui_api_token', $data)) {
        $token = trim((string) $data['xui_api_token']);
        $sql .= ', xui_api_token=?';
        $params[] = ($token === '' || $token === '-') ? null : $token;
    }
    if (array_key_exists('secret_code', $data)) {
        $secret = trim((string) $data['secret_code']);
        if ($secret !== '' && $secret !== '********') {
            $sql .= ', secret_code=?';
            $params[] = $secret;
        }
    }
    $sql .= ' WHERE name_panel=?';
    $params[] = $name;
    db_query($pdo, $sql, $params);

    if (!empty($defs[$type]['needs_inbounds']) && $inboundsRaw !== '' && $inboundsRaw !== '-') {
        require_once __DIR__ . '/../../x-ui_single.php';
        $ids = mirza_xui_parse_id_list($inboundsRaw);
        if ($ids !== []) {
            mirza_xui_save_panel_inbounds($name, $ids);
        }
    }

    return ['ok' => true, 'msg' => 'پنل «' . $name . '» ذخیره شد.'];
}

function panel_web_delete_panel(PDO $pdo, string $name, string $confirmName): array
{
    if ($name === '' || $confirmName !== $name) {
        return ['ok' => false, 'msg' => 'نام پنل برای حذف باید دقیقاً تأیید شود.'];
    }
    $row = db_fetch($pdo, 'SELECT name_panel FROM marzban_panel WHERE name_panel = ? LIMIT 1', [$name]);
    if (!$row) {
        return ['ok' => false, 'msg' => 'پنل یافت نشد.'];
    }
    $active = panel_web_count_active_invoices($pdo, $name);
    if ($active > 0) {
        return ['ok' => false, 'msg' => 'این پنل ' . $active . ' سرویس فعال دارد — ابتدا سرویس‌ها را منتقل یا حذف کنید.'];
    }
    db_query($pdo, 'DELETE FROM marzban_panel WHERE name_panel = ?', [$name]);
    return ['ok' => true, 'msg' => 'پنل «' . $name . '» حذف شد.'];
}

function panel_web_test_connection(array $row): array
{
    $type = panel_web_normalize_type((string) ($row['type'] ?? ''));
    $code = (string) ($row['code_panel'] ?? '');
    $name = (string) ($row['name_panel'] ?? '');

    if (panel_web_is_xui_type($type)) {
        require_once __DIR__ . '/../../x-ui_single.php';
        $login = function_exists('mirza_xui_test_connection')
            ? mirza_xui_test_connection($code)
            : login($code, true, true, true);
        $online = !empty($login['success']);
        $msg = $online ? 'اتصال برقرار است' : strip_tags((string) ($login['msg'] ?? 'اتصال ناموفق'));
        return ['online' => $online, 'msg' => $msg, 'skipped' => false];
    }

    if (in_array($type, ['marzban', 'pasarguard'], true)) {
        require_once __DIR__ . '/../../Marzban.php';
        $tok = token_panel($code, false);
        $online = is_array($tok) && !empty($tok['access_token']);
        $msg = $online ? 'اتصال Marzban برقرار است' : strip_tags((string) ($tok['error'] ?? $tok['detail'] ?? 'اتصال ناموفق'));
        return ['online' => $online, 'msg' => $msg, 'skipped' => false];
    }

    if ($type === 'hiddify') {
        require_once __DIR__ . '/../../hiddify.php';
        $resp = serverstatus($name);
        $online = is_array($resp) && empty($resp['error']) && (int) ($resp['status'] ?? 0) === 200;
        if (!$online && is_array($resp) && isset($resp['body'])) {
            $body = json_decode((string) $resp['body'], true);
            $online = is_array($body);
        }
        $msg = $online ? 'اتصال Hiddify برقرار است' : 'اتصال Hiddify ناموفق';
        return ['online' => $online, 'msg' => $msg, 'skipped' => false];
    }

    return ['online' => null, 'msg' => 'تست اتصال برای این نوع در وب پشتیبانی نمی‌شود.', 'skipped' => true];
}

function panel_web_dashboard_data(array $row): array
{
    $type = panel_web_normalize_type((string) ($row['type'] ?? ''));
    if (!panel_web_is_xui_type($type)) {
        return ['ok' => false, 'msg' => 'داشبورد فقط برای پنل‌های ۳x-ui است.'];
    }
    require_once __DIR__ . '/../../x-ui_single.php';
    $login = mirza_xui_test_connection((string) $row['code_panel']);
    $online = !empty($login['success']);
    $stats = $online ? mirza_xui_fetch_server_status((string) $row['name_panel']) : null;
    $inbounds = mirza_xui_resolve_inbounds($row, null);
    unset($row['password_panel']);
    return [
        'ok' => true,
        'online' => $online,
        'msg' => $online ? 'اتصال برقرار است' : strip_tags((string) ($login['msg'] ?? 'قطع')),
        'panel' => [
            'name_panel' => $row['name_panel'] ?? '',
            'url_panel' => $row['url_panel'] ?? '',
            'linksubx' => $row['linksubx'] ?? '',
            'type' => $row['type'] ?? '',
            'has_token' => !empty(trim((string) ($row['xui_api_token'] ?? ''))),
        ],
        'stats' => is_array($stats) ? $stats : null,
        'inbounds' => $inbounds,
        'auth_mode' => !empty(trim((string) ($row['xui_api_token'] ?? ''))) ? 'token' : 'password',
    ];
}
