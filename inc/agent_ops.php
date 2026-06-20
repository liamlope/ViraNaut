<?php
declare(strict_types=1);
/**
 * Shared agent business logic for agent-panel web + api/agent.php
 */
require_once __DIR__ . '/../panels.php';

if (!function_exists('db_query')) {
    function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    function db_fetch(PDO $pdo, string $sql, array $params = []): ?array
    {
        return db_query($pdo, $sql, $params)->fetch() ?: null;
    }
    function db_fetchAll(PDO $pdo, string $sql, array $params = []): array
    {
        return db_query($pdo, $sql, $params)->fetchAll();
    }
    function db_count(PDO $pdo, string $sql, array $params = []): int
    {
        return (int) db_query($pdo, $sql, $params)->fetchColumn();
    }
}

function agent_panel_sales_stats(PDO $pdo, string $userId): array
{
    $count = db_fetch($pdo, 'SELECT COUNT(*) AS c, COALESCE(SUM(price_product),0) AS s FROM invoice WHERE id_user = ?', [$userId]);
    return [
        'count' => (int) ($count['c'] ?? 0),
        'sum' => (int) ($count['s'] ?? 0),
    ];
}

function agent_ops_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $sqlFile = __DIR__ . '/../migrations/viranaut_migrate_3_2_0_agent_panel.sql';
    if (!is_readable($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || stripos($stmt, 'INSERT INTO') === 0) {
            try {
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            } catch (Throwable $e) {
                // column may exist
            }
            continue;
        }
        if (stripos($stmt, 'ADD COLUMN IF NOT EXISTS') !== false) {
            preg_match_all('/ADD COLUMN IF NOT EXISTS\s+(\w+)/i', $stmt, $cols);
            $table = '';
            if (preg_match('/ALTER TABLE\s+(\w+)/i', $stmt, $tm)) {
                $table = $tm[1];
            }
            foreach ($cols[1] ?? [] as $col) {
                try {
                    $chk = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($col));
                    if ($chk && $chk->rowCount() === 0) {
                        $one = preg_replace('/,\s*ADD COLUMN IF NOT EXISTS\s+' . preg_quote($col, '/') . '\s+[^,]+/i', '', $stmt);
                        $one = str_replace('IF NOT EXISTS ', '', $one);
                        $pdo->exec($one);
                    }
                } catch (Throwable $e) {
                    error_log('agent_ops_ensure_schema: ' . $e->getMessage());
                }
            }
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            error_log('agent_ops_ensure_schema: ' . $e->getMessage());
        }
    }
    $done = true;
}

function agent_invoice_panel(array $inv): string
{
    return (string) ($inv['Service_location'] ?? $inv['Location'] ?? '');
}

function agent_pay_setting(string $name): string
{
    $r = select('PaySetting', 'ValuePay', 'NamePay', $name, 'select');
    return (string) ($r['ValuePay'] ?? '');
}

function agent_action_log_list(PDO $pdo, string $agentId, int $limit = 50): array
{
    try {
        return db_fetchAll($pdo, 'SELECT * FROM agent_action_log WHERE id_user = ? ORDER BY created_at DESC LIMIT ' . (int) $limit, [$agentId]);
    } catch (Throwable $e) {
        return [];
    }
}

function agent_user_context(PDO $pdo, string $agentId): ?array
{
    $user = select('user', '*', 'id', $agentId, 'select');
    if (!$user || ($user['agent'] ?? 'f') === 'f') {
        return null;
    }
    $user['agent_label'] = ($user['agent'] ?? 'f') === 'n2' ? 'n2' : 'n';
    $user['debt_ceiling'] = (int) ($user['maxbuyagent'] ?? 0);
    $user['debt_used'] = max(0, -(int) $user['Balance']);
    return $user;
}

function agent_pay_value($raw, string $agent, $default = 0)
{
    return mirza_pay_agent_value($raw, $agent, $default);
}

function agent_panel_list(PDO $pdo, array $user, bool $testOnly = false): array
{
    $agent = $user['agent'] ?? 'f';
    $uid = (string) $user['id'];
    $sql = "SELECT * FROM marzban_panel WHERE (agent = ? OR agent = 'all' OR agent = 'f')";
    if ($testOnly) {
        $sql .= " AND TestAccount = 'ONTestAccount'";
    }
    $sql .= " ORDER BY name_panel ASC";
    $rows = db_fetchAll($pdo, $sql, [$agent]);
    $out = [];
    foreach ($rows as $row) {
        $hide = json_decode($row['hide_user'] ?? '[]', true);
        if (is_array($hide) && in_array($uid, $hide, true)) {
            continue;
        }
        if ($testOnly && ($row['TestAccount'] ?? '') !== 'ONTestAccount') {
            continue;
        }
        $out[] = $row;
    }
    return $out;
}

function agent_product_list(PDO $pdo, array $user, string $location): array
{
    $agent = $user['agent'] ?? 'f';
    $uid = (string) $user['id'];
    $stmt = $pdo->prepare(
        "SELECT * FROM product WHERE (Location = :loc OR Location = '/all') AND (agent = :agent OR agent = 'all') ORDER BY price_product ASC"
    );
    $stmt->execute([':loc' => $location, ':agent' => $agent]);
    $products = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hide = json_decode($row['hide_panel'] ?? '[]', true);
        if (is_array($hide) && in_array($location, $hide, true)) {
            continue;
        }
        if (($row['one_buy_status'] ?? '0') === '1') {
            $cnt = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE id_user = ? AND Status != 'Unpaid'", [$uid]);
            if ($cnt > 0) {
                continue;
            }
        }
        $products[] = $row;
    }
    return $products;
}

function agent_custom_pricing(array $panelRow, string $agent): array
{
    $cv = json_decode($panelRow['customvolume'] ?? '{}', true);
    $enabled = is_array($cv) && !empty($cv[$agent]);
    $priceVol = agent_pay_value($panelRow['pricecustomvolume'] ?? 0, $agent, 0);
    $priceTime = agent_pay_value($panelRow['pricecustomtime'] ?? 0, $agent, 0);
    return [
        'enabled' => $enabled,
        'price_volume' => (int) $priceVol,
        'price_time' => (int) $priceTime,
        'min_volume' => (int) agent_pay_value($panelRow['mainvolume'] ?? 1, $agent, 1),
        'max_volume' => (int) agent_pay_value($panelRow['maxvolume'] ?? 500, $agent, 500),
        'min_time' => (int) agent_pay_value($panelRow['maintime'] ?? 1, $agent, 1),
        'max_time' => (int) agent_pay_value($panelRow['maxtime'] ?? 365, $agent, 365),
    ];
}

function agent_extra_volume_price(string $agent): int
{
    $row = select('PaySetting', '*', 'NamePay', 'priceextravolume', 'select');
    return (int) agent_pay_value($row['ValuePay'] ?? 0, $agent, 0);
}

function agent_extra_time_price(string $agent): int
{
    $row = select('PaySetting', '*', 'NamePay', 'priceextratime', 'select');
    return (int) agent_pay_value($row['ValuePay'] ?? 0, $agent, 0);
}

function agent_wallet_preflight(array $user, int $price): array
{
    $balance = (int) $user['Balance'];
    $agent = $user['agent'] ?? 'f';
    $after = $balance - $price;
    $needsGateway = false;
    $gatewayAmount = 0;
    $canProceed = true;
    $msg = '';

    if ($price <= 0) {
        return ['ok' => true, 'after' => $after, 'needs_gateway' => false, 'gateway_amount' => 0, 'msg' => ''];
    }

    if ($agent !== 'n2' && $after < 0) {
        $canProceed = false;
        $needsGateway = true;
        $gatewayAmount = abs($after);
        $msg = 'موجودی کافی نیست';
    }

    if ($agent === 'n2' && (int) ($user['maxbuyagent'] ?? 0) > 0) {
        $floor = -(int) $user['maxbuyagent'];
        if ($after < $floor) {
            $canProceed = false;
            $needsGateway = true;
            $gatewayAmount = abs($after - $floor);
            $msg = 'به سقف بدهی رسیدید';
        }
    }

    return [
        'ok' => $canProceed,
        'after' => $after,
        'needs_gateway' => $needsGateway,
        'gateway_amount' => $gatewayAmount,
        'msg' => $msg,
        'balance' => $balance,
        'price' => $price,
    ];
}

function agent_checkout_preview(array $user, int $price): array
{
    return agent_wallet_preflight($user, $price);
}

function agent_log_action(PDO $pdo, string $agentId, string $action, ?string $username = null, ?string $detail = null): void
{
    try {
        agent_ops_ensure_schema($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        db_query($pdo, 'INSERT INTO agent_action_log (id_user, username, action, detail, ip) VALUES (?,?,?,?,?)', [
            $agentId, $username, $action, $detail, $ip,
        ]);
    } catch (Throwable $e) {
        error_log('agent_log_action: ' . $e->getMessage());
    }
}

function agent_notify_webhook(PDO $pdo, string $agentId, string $event, array $payload = []): void
{
    try {
        agent_ops_ensure_schema($pdo);
        $rows = db_fetchAll($pdo, 'SELECT * FROM agent_webhooks WHERE id_user = ? AND active = 1', [$agentId]);
        foreach ($rows as $wh) {
            $events = json_decode($wh['events'] ?? '[]', true);
            if (is_array($events) && $events !== [] && !in_array($event, $events, true)) {
                continue;
            }
            $body = json_encode(['event' => $event, 'payload' => $payload, 'ts' => time()], JSON_UNESCAPED_UNICODE);
            $ch = curl_init($wh['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Agent-Secret: ' . ($wh['secret'] ?? '')],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (Throwable $e) {
        error_log('agent_notify_webhook: ' . $e->getMessage());
    }
}

function agent_push_notification(PDO $pdo, string $agentId, string $type, array $payload = []): void
{
    try {
        agent_ops_ensure_schema($pdo);
        db_query($pdo, 'INSERT INTO agent_notifications (id_user, type, payload) VALUES (?,?,?)', [
            $agentId, $type, json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('agent_push_notification: ' . $e->getMessage());
    }
}

function agent_deduct_balance(string $agentId, int $amount): void
{
    if ($amount <= 0) {
        return;
    }
    $u = select('user', '*', 'id', $agentId, 'select');
    if (!$u) {
        return;
    }
    update('user', 'Balance', (int) $u['Balance'] - $amount, 'id', $agentId);
}

function agent_generate_username(string $agentId, array $panelRow, ?string $customName = null): string
{
    $randomString = bin2hex(random_bytes(4));
    $user = select('user', '*', 'id', $agentId, 'select');
    $setting = select('setting', '*', null, null, 'select');
    $method = $panelRow['MethodUsername'] ?? 'آیدی عددی + حروف و عدد رندوم';
    $namecustom = $customName ?? ($user['namecustom'] ?? 'none');
    if ($namecustom === 'none') {
        $namecustom = $setting['namecustom'] ?? 'user';
    }
    return generateUsername($agentId, $method, $user['username'] ?? 'NOT_USERNAME', $randomString, $customName ?? '', $namecustom, $user['namecustom'] ?? 'none');
}

function agent_bump_username_counter(string $agentId, array $panelRow): void
{
    $method = $panelRow['MethodUsername'] ?? '';
    $user = select('user', '*', 'id', $agentId, 'select');
    if (!$user) {
        return;
    }
    if (in_array($method, ['نام کاربری + عدد به ترتیب', 'آیدی عددی+عدد ترتیبی', 'متن دلخواه نماینده + عدد ترتیبی'], true)) {
        update('user', 'number_username', (int) $user['number_username'] + 1, 'id', $agentId);
    }
    if (in_array($method, ['متن دلخواه + عدد ترتیبی', 'متن دلخواه نماینده + عدد ترتیبی'], true)) {
        $setting = select('setting', '*', null, null, 'select');
        update('setting', 'numbercount', (int) ($setting['numbercount'] ?? 0) + 1);
    }
}

function agent_create_invoice(string $agentId, string $username, array $panel, array $product, int $price, string $note = ''): string
{
    global $connect;
    $randomString = bin2hex(random_bytes(6));
    $date = date('Y-m-d H:i:s');
    $user = select('user', '*', 'id', $agentId, 'select');
    $notifctions = json_encode(['volume' => false, 'time' => false]);
    $stmt = $connect->prepare(
        'INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status, note, refral, notifctions)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $status = 'unpaid';
    $stmt->bind_param(
        'ssssssssssss',
        $agentId,
        $randomString,
        $username,
        $date,
        $panel['name_panel'],
        $product['name_product'],
        $price,
        $product['Volume_constraint'],
        $product['Service_time'],
        $status,
        $note,
        $user['affiliates'] ?? '0',
        $notifctions
    );
    $stmt->execute();
    $stmt->close();
    return $randomString;
}

function agent_finalize_service(PDO $pdo, ManagePanel $mp, string $agentId, array $user, array $panel, array $product, string $username, int $price): array
{
    $datetimestep = (int) $product['Service_time'] === 0 ? 0 : strtotime('+' . (int) $product['Service_time'] . ' days');
    if ($datetimestep > 0) {
        $datetimestep = strtotime(date('Y-m-d H:i:s', $datetimestep));
    }
    $datac = [
        'expire' => $datetimestep,
        'data_limit' => (int) $product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $agentId,
        'username' => $user['username'] ?? '',
        'type' => 'buy',
    ];
    try {
        $out = $mp->createUser($panel['name_panel'], $product['code_product'], $username, $datac);
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => $e->getMessage()];
    }
    if (empty($out['username'])) {
        return ['ok' => false, 'msg' => $out['msg'] ?? 'create failed'];
    }
    update('invoice', 'Status', 'active', 'username', $username);
    agent_deduct_balance($agentId, $price);
    agent_bump_username_counter($agentId, $panel);
    agent_log_action($pdo, $agentId, 'buy', $username, $product['name_product'] . ' @ ' . $panel['name_panel']);
    agent_notify_webhook($pdo, $agentId, 'purchase', ['username' => $username, 'product' => $product['name_product'], 'price' => $price]);
    return ['ok' => true, 'msg' => 'created', 'data' => $out];
}

function agent_buy_service(PDO $pdo, array $user, string $panelName, string $productCode, ?string $customUsername = null): array
{
    $agentId = (string) $user['id'];
    $panel = select('marzban_panel', '*', 'name_panel', $panelName, 'select');
    if (!$panel) {
        return ['ok' => false, 'msg' => 'panel not found'];
    }
    $product = db_fetch($pdo, 'SELECT * FROM product WHERE code_product = ? LIMIT 1', [$productCode]);
    if (!$product) {
        return ['ok' => false, 'msg' => 'product not found'];
    }
    $price = (int) $product['price_product'];
    $preflight = agent_wallet_preflight($user, $price);
    if (!$preflight['ok']) {
        return ['ok' => false, 'msg' => $preflight['msg'], 'needs_gateway' => $preflight['needs_gateway'], 'gateway_amount' => $preflight['gateway_amount']];
    }
    $username = $customUsername ?: agent_generate_username($agentId, $panel);
    agent_create_invoice($agentId, $username, $panel, $product, $price);
    $mp = new ManagePanel();
    return agent_finalize_service($pdo, $mp, $agentId, $user, $panel, $product, $username, $price);
}

function agent_buy_custom(PDO $pdo, array $user, string $panelName, int $volumeGb, int $days, ?string $customUsername = null): array
{
    $panel = select('marzban_panel', '*', 'name_panel', $panelName, 'select');
    if (!$panel) {
        return ['ok' => false, 'msg' => 'panel not found'];
    }
    $cp = agent_custom_pricing($panel, $user['agent'] ?? 'f');
    if (!$cp['enabled']) {
        return ['ok' => false, 'msg' => 'custom disabled'];
    }
    if ($volumeGb < $cp['min_volume'] || $volumeGb > $cp['max_volume'] || $days < $cp['min_time'] || $days > $cp['max_time']) {
        return ['ok' => false, 'msg' => 'out of range'];
    }
    $price = ($volumeGb * $cp['price_volume']) + ($days * $cp['price_time']);
    $preflight = agent_wallet_preflight($user, $price);
    if (!$preflight['ok']) {
        return ['ok' => false, 'msg' => $preflight['msg'], 'needs_gateway' => true, 'gateway_amount' => $preflight['gateway_amount']];
    }
    $product = [
        'name_product' => 'سرویس دلخواه',
        'code_product' => 'custom',
        'Volume_constraint' => $volumeGb,
        'Service_time' => $days,
        'price_product' => $price,
    ];
    $username = $customUsername ?: agent_generate_username($agentId = (string) $user['id'], $panel);
    agent_create_invoice($agentId, $username, $panel, $product, $price, 'custom');
    $mp = new ManagePanel();
    return agent_finalize_service($pdo, $mp, $agentId, $user, $panel, $product, $username, $price);
}

function agent_buy_bulk(PDO $pdo, array $user, string $panelName, string $productCode, int $count): array
{
    $count = max(1, min(15, $count));
    $results = [];
    $totalPrice = 0;
    $product = db_fetch($pdo, 'SELECT * FROM product WHERE code_product = ? LIMIT 1', [$productCode]);
    if (!$product) {
        return ['ok' => false, 'msg' => 'product not found'];
    }
    $totalPrice = (int) $product['price_product'] * $count;
    $preflight = agent_wallet_preflight($user, $totalPrice);
    if (!$preflight['ok']) {
        return ['ok' => false, 'msg' => $preflight['msg'], 'needs_gateway' => true, 'gateway_amount' => $preflight['gateway_amount']];
    }
    for ($i = 0; $i < $count; $i++) {
        $user = select('user', '*', 'id', $user['id'], 'select');
        $r = agent_buy_service($pdo, $user, $panelName, $productCode);
        $results[] = $r;
        if (!$r['ok']) {
            break;
        }
    }
    return ['ok' => true, 'msg' => 'bulk done', 'results' => $results];
}

function agent_buy_test(PDO $pdo, array $user, string $panelName): array
{
    $agentId = (string) $user['id'];
    if ((int) ($user['limit_usertest'] ?? 0) <= 0) {
        return ['ok' => false, 'msg' => 'limit_usertest exhausted'];
    }
    $panel = select('marzban_panel', '*', 'name_panel', $panelName, 'select');
    if (!$panel || ($panel['TestAccount'] ?? '') !== 'ONTestAccount') {
        return ['ok' => false, 'msg' => 'test panel not available'];
    }
    $product = [
        'name_product' => 'سرویس تست',
        'code_product' => 'test',
        'Volume_constraint' => (int) ($panel['VolumeTest'] ?? 1),
        'Service_time' => (int) ($panel['TimeTest'] ?? 1),
        'price_product' => 0,
    ];
    $username = agent_generate_username($agentId, $panel);
    agent_create_invoice($agentId, $username, $panel, $product, 0, 'test');
    $mp = new ManagePanel();
    $out = agent_finalize_service($pdo, $mp, $agentId, $user, $panel, $product, $username, 0);
    if ($out['ok']) {
        update('user', 'limit_usertest', (int) $user['limit_usertest'] - 1, 'id', $agentId);
    }
    return $out;
}

function agent_extend_method(): string
{
    $textbotlang = languagechange();
    return $textbotlang['keyboard']['resetVolumeTime'] ?? 'resetVolumeTime';
}

function agent_extend_service(PDO $pdo, array $user, string $username, ?array $product = null): array
{
    $agentId = (string) $user['id'];
    $inv = db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
    if (!$inv) {
        return ['ok' => false, 'msg' => 'not found'];
    }
    if (!$product) {
        $product = select('product', '*', 'name_product', $inv['name_product'], 'select');
    }
    if (!$product) {
        return ['ok' => false, 'msg' => 'product not found'];
    }
    $price = (int) $product['price_product'];
    $preflight = agent_wallet_preflight($user, $price);
    if (!$preflight['ok']) {
        return ['ok' => false, 'msg' => $preflight['msg'], 'needs_gateway' => true, 'gateway_amount' => $preflight['gateway_amount']];
    }
    $mp = new ManagePanel();
    $ext = $mp->extend(
        agent_extend_method(),
        (int) $product['Volume_constraint'],
        (int) $product['Service_time'],
        $username,
        $product['code_product'],
        agent_invoice_panel($inv)
    );
    if (empty($ext['status'])) {
        return ['ok' => false, 'msg' => $ext['msg'] ?? 'extend failed'];
    }
    agent_deduct_balance($agentId, $price);
    agent_log_action($pdo, $agentId, 'renew', $username);
    agent_notify_webhook($pdo, $agentId, 'renew', ['username' => $username, 'price' => $price]);
    return ['ok' => true, 'msg' => $ext['msg'] ?? 'renewed', 'data' => $ext];
}

function agent_add_volume(PDO $pdo, array $user, string $username, int $gb): array
{
    $agentId = (string) $user['id'];
    $inv = db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
    if (!$inv) {
        return ['ok' => false, 'msg' => 'not found'];
    }
    $panelName = agent_invoice_panel($inv);
    $price = agent_extra_volume_price($user['agent'] ?? 'f') * $gb;
    $preflight = agent_wallet_preflight($user, $price);
    if (!$preflight['ok']) {
        return ['ok' => false, 'msg' => $preflight['msg'], 'needs_gateway' => true, 'gateway_amount' => $preflight['gateway_amount']];
    }
    $mp = new ManagePanel();
    $ext = $mp->extra_volume($username, $panelName, $gb);
    if (empty($ext['status'])) {
        return ['ok' => false, 'msg' => $ext['msg'] ?? 'failed'];
    }
    agent_deduct_balance($agentId, $price);
    agent_log_action($pdo, $agentId, 'add_volume', $username, (string) $gb . 'GB');
    return ['ok' => true, 'msg' => $ext['msg'] ?? 'ok', 'data' => $ext, 'price' => $price];
}

function agent_add_time(PDO $pdo, array $user, string $username, int $days): array
{
    $agentId = (string) $user['id'];
    $inv = db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
    if (!$inv) {
        return ['ok' => false, 'msg' => 'not found'];
    }
    $price = agent_extra_time_price($user['agent'] ?? 'f') * $days;
    $preflight = agent_wallet_preflight($user, $price);
    if (!$preflight['ok']) {
        return ['ok' => false, 'msg' => $preflight['msg'], 'needs_gateway' => true, 'gateway_amount' => $preflight['gateway_amount']];
    }
    $mp = new ManagePanel();
    $ext = $mp->extra_time($username, agent_invoice_panel($inv), $days);
    if (empty($ext['status'])) {
        return ['ok' => false, 'msg' => $ext['msg'] ?? 'failed'];
    }
    agent_deduct_balance($agentId, $price);
    agent_log_action($pdo, $agentId, 'add_time', $username, (string) $days . 'd');
    return ['ok' => true, 'msg' => $ext['msg'] ?? 'ok', 'data' => $ext, 'price' => $price];
}

function agent_revoke_service(PDO $pdo, array $user, string $username): array
{
    $agentId = (string) $user['id'];
    $inv = db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
    if (!$inv) {
        return ['ok' => false, 'msg' => 'not found'];
    }
    $mp = new ManagePanel();
    $r = $mp->Revoke_sub(agent_invoice_panel($inv), $username);
    $ok = ($r['status'] ?? '') === 'successful';
    if ($ok) {
        agent_log_action($pdo, $agentId, 'revoke', $username);
    }
    return ['ok' => $ok, 'msg' => $r['msg'] ?? 'done', 'data' => $r];
}

function agent_service_detail(PDO $pdo, array $user, string $username): array
{
    $agentId = (string) $user['id'];
    $inv = db_fetch($pdo, 'SELECT * FROM invoice WHERE id_user = ? AND username = ? LIMIT 1', [$agentId, $username]);
    if (!$inv) {
        return ['ok' => false, 'msg' => 'not found'];
    }
    $panel = select('marzban_panel', '*', 'name_panel', agent_invoice_panel($inv), 'select');
    $mp = new ManagePanel();
    $du = $panel ? $mp->DataUser($panel['name_panel'], $username) : [];
    return ['ok' => true, 'invoice' => $inv, 'panel_user' => $du, 'panel' => $panel];
}

function agent_dashboard_metrics(PDO $pdo, array $user): array
{
    $agentId = (string) $user['id'];
    $stats = agent_panel_sales_stats($pdo, $agentId);
    $today = date('Y-m-d');
    $month = date('Y-m');
    $todayRow = db_fetch($pdo, "SELECT COUNT(*) AS c, COALESCE(SUM(price_product),0) AS s FROM invoice WHERE id_user = ? AND time_sell LIKE ?", [$agentId, $today . '%']);
    $monthRow = db_fetch($pdo, "SELECT COUNT(*) AS c, COALESCE(SUM(price_product),0) AS s FROM invoice WHERE id_user = ? AND time_sell LIKE ?", [$agentId, $month . '%']);
    $active = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE id_user = ? AND Status = 'active'", [$agentId]);
    $expired = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE id_user = ? AND Status = 'expired'", [$agentId]);
    $balance = (int) $user['Balance'];
    $debtCeiling = (int) ($user['maxbuyagent'] ?? 0);
    $debtUsed = max(0, -$balance);
    return [
        'balance' => $balance,
        'sales_count' => $stats['count'],
        'sales_sum' => $stats['sum'],
        'sales_today_count' => (int) ($todayRow['c'] ?? 0),
        'sales_today_sum' => (int) ($todayRow['s'] ?? 0),
        'sales_month_count' => (int) ($monthRow['c'] ?? 0),
        'sales_month_sum' => (int) ($monthRow['s'] ?? 0),
        'active_services' => $active,
        'expired_services' => $expired,
        'debt_ceiling' => $debtCeiling,
        'debt_used' => $debtUsed,
        'agent_type' => $user['agent'] ?? 'f',
        'expire_at' => $user['expire'] ?? null,
    ];
}

function agent_affiliate_stats(PDO $pdo, string $agentId): array
{
    $user = select('user', '*', 'id', $agentId, 'select');
    $count = (int) ($user['affiliatescount'] ?? 0);
    $refs = db_fetchAll($pdo, 'SELECT id, username, Balance FROM user WHERE affiliates = ? ORDER BY id DESC LIMIT 200', [$agentId]);
    $purchaseSum = 0;
    foreach ($refs as $ref) {
        $purchaseSum += (int) db_fetch($pdo, 'SELECT COALESCE(SUM(price_product),0) AS s FROM invoice WHERE id_user = ? AND Status != ?', [$ref['id'], 'Unpaid'])['s'];
    }
    return [
        'count' => $count,
        'referrals' => $refs,
        'purchase_sum' => $purchaseSum,
        'code' => $user['codeInvitation'] ?? '',
    ];
}

function agent_tariff_table(PDO $pdo, array $user): array
{
    $agent = $user['agent'] ?? 'f';
    $products = db_fetchAll($pdo, "SELECT name_product, Location, price_product, Volume_constraint, Service_time FROM product WHERE agent = ? OR agent = 'all' ORDER BY Location, price_product", [$agent]);
    $panels = agent_panel_list($pdo, $user);
    $custom = [];
    foreach ($panels as $p) {
        $custom[$p['name_panel']] = agent_custom_pricing($p, $agent);
    }
    return [
        'products' => $products,
        'custom' => $custom,
        'extra_volume' => agent_extra_volume_price($agent),
        'extra_time' => agent_extra_time_price($agent),
    ];
}

function agent_payment_gateways(): array
{
    $gates = [];
    if (agent_pay_setting('zarinpalstatus') === 'onzarinpal') {
        $gates[] = ['id' => 'zarinpal', 'label' => 'زرین‌پال'];
    }
    if (agent_pay_setting('statusaqayepardakht') === 'onaqayepardakht') {
        $gates[] = ['id' => 'aqayepardakht', 'label' => 'آقای پرداخت'];
    }
    return $gates;
}

function agent_create_topup_payment(PDO $pdo, array $user, int $amount, string $gatewayId): array
{
    global $connect;
    $agentId = (string) $user['id'];
    if ($amount < 5000) {
        return ['ok' => false, 'msg' => 'حداقل مبلغ ۵۰۰۰ تومان'];
    }
    update('user', 'Processing_value', $amount, 'id', $agentId);
    update('user', 'Processing_value_tow', '0', 'id', $agentId);
    update('user', 'Processing_value_one', '0', 'id', $agentId);
    $randomString = bin2hex(random_bytes(5));
    $dateacc = date('Y/m/d H:i:s');
    $invoiceMeta = '0|0';

    if ($gatewayId === 'zarinpal') {
        $min = (int) agent_pay_setting('minbalancezarinpal');
        $max = (int) agent_pay_setting('maxbalancezarinpal');
        if ($min > 0 && ($amount < $min || $amount > $max)) {
            return ['ok' => false, 'msg' => "مبلغ باید بین {$min} و {$max} تومان باشد"];
        }
        $pay = createPayZarinpal($amount, $randomString);
        if (($pay['data']['code'] ?? 0) != 100) {
            return ['ok' => false, 'msg' => 'خطا در ساخت لینک زرین‌پال'];
        }
        $authority = $pay['data']['authority'];
        $stmt = $connect->prepare('INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,dec_not_confirmed) VALUES (?,?,?,?,?,?,?,?)');
        $status = 'Unpaid';
        $method = 'zarinpal';
        $stmt->bind_param('ssssssss', $agentId, $randomString, $dateacc, $amount, $status, $method, $invoiceMeta, $authority);
        $stmt->execute();
        $stmt->close();
        return ['ok' => true, 'url' => 'https://www.zarinpal.com/pg/StartPay/' . $authority, 'order_id' => $randomString];
    }

    if ($gatewayId === 'aqayepardakht') {
        $min = (int) agent_pay_setting('minbalanceaqayepardakht');
        $max = (int) agent_pay_setting('maxbalanceaqayepardakht');
        if ($min > 0 && ($amount < $min || $amount > $max)) {
            return ['ok' => false, 'msg' => "مبلغ باید بین {$min} و {$max} تومان باشد"];
        }
        $pay = createPayaqayepardakht($amount, $randomString);
        if (($pay['status'] ?? '') !== 'success') {
            return ['ok' => false, 'msg' => 'خطا در ساخت لینک آقای پرداخت'];
        }
        $stmt = $connect->prepare('INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)');
        $status = 'Unpaid';
        $method = 'aqayepardakht';
        $stmt->bind_param('sssssss', $agentId, $randomString, $dateacc, $amount, $status, $method, $invoiceMeta);
        $stmt->execute();
        $stmt->close();
        return ['ok' => true, 'url' => 'https://panel.aqayepardakht.ir/startpay/' . ($pay['transid'] ?? ''), 'order_id' => $randomString];
    }

    return ['ok' => false, 'msg' => 'درگاه نامعتبر یا غیرفعال'];
}

function agent_services_query(PDO $pdo, string $agentId, array $filters = [], int $limit = 50, int $offset = 0): array
{
    $where = ['id_user = ?'];
    $params = [$agentId];
    if (!empty($filters['status'])) {
        $where[] = 'Status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['location'])) {
        $where[] = 'Service_location = ?';
        $params[] = $filters['location'];
    }
    if (!empty($filters['q'])) {
        $where[] = 'username LIKE ?';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['date_from'])) {
        $where[] = 'time_sell >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[] = 'time_sell <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    $sql = 'SELECT username, name_product, price_product, Status, time_sell, Service_location AS Location FROM invoice WHERE ' . implode(' AND ', $where) . ' ORDER BY time_sell DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    return db_fetchAll($pdo, $sql, $params);
}

function agent_chart_data(PDO $pdo, string $agentId, int $days = 14): array
{
    $labels = [];
    $orders = [];
    $revenue = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = $d;
        $row = db_fetch($pdo, 'SELECT COUNT(*) AS c, COALESCE(SUM(price_product),0) AS s FROM invoice WHERE id_user = ? AND time_sell LIKE ?', [$agentId, $d . '%']);
        $orders[] = (int) ($row['c'] ?? 0);
        $revenue[] = (int) ($row['s'] ?? 0);
    }
    return ['labels' => $labels, 'orders' => $orders, 'revenue' => $revenue];
}

function agent_top_products(PDO $pdo, string $agentId, int $limit = 10): array
{
    return db_fetchAll(
        $pdo,
        'SELECT name_product, COUNT(*) AS cnt, SUM(price_product) AS total FROM invoice WHERE id_user = ? GROUP BY name_product ORDER BY cnt DESC LIMIT ' . (int) $limit,
        [$agentId]
    );
}

function agent_top_panels(PDO $pdo, string $agentId, int $limit = 10): array
{
    return db_fetchAll(
        $pdo,
        'SELECT Service_location AS Location, COUNT(*) AS cnt, SUM(price_product) AS total FROM invoice WHERE id_user = ? GROUP BY Service_location ORDER BY cnt DESC LIMIT ' . (int) $limit,
        [$agentId]
    );
}

function agent_store_gateway_intent(PDO $pdo, string $agentId, int $amount, string $context): string
{
    update('user', 'Processing_value', $amount, 'id', $agentId);
    update('user', 'Processing_value_tow', $context, 'id', $agentId);
    return (string) $amount;
}
