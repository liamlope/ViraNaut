<?php

function vira_campaign_ensure_tables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        text_body MEDIUMTEXT NULL,
        target_type VARCHAR(40) NOT NULL DEFAULT 'all_active',
        reply_markup_json TEXT NULL,
        pin_after_send TINYINT(1) NOT NULL DEFAULT 0,
        sent_count INT NOT NULL DEFAULT 0,
        failed_count INT NOT NULL DEFAULT 0,
        total_recipients INT NOT NULL DEFAULT 0,
        offset_cursor INT NOT NULL DEFAULT 0,
        status VARCHAR(24) NOT NULL DEFAULT 'draft',
        paused TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function vira_campaign_target_defs(): array
{
    return [
        'all_active' => 'همه کاربران فعال',
        'all_users' => 'همه کاربران',
        'blocked' => 'مسدودها',
        'agents' => 'نمایندگان',
        'customers' => 'دارای خرید',
        'no_purchase' => 'بدون خرید',
    ];
}

function vira_campaign_recipient_sql(string $targetType): array
{
    switch ($targetType) {
        case 'all_users':
            return ['SELECT id FROM user ORDER BY id ASC', []];
        case 'blocked':
            return ["SELECT id FROM user WHERE User_Status = 'block' ORDER BY id ASC", []];
        case 'agents':
            return ["SELECT id FROM user WHERE agent IN ('n','n2') ORDER BY id ASC", []];
        case 'customers':
            return ['SELECT DISTINCT u.id FROM user u INNER JOIN invoice i ON i.id_user = u.id ORDER BY u.id ASC', []];
        case 'no_purchase':
            return ['SELECT u.id FROM user u LEFT JOIN invoice i ON i.id_user = u.id WHERE i.id IS NULL ORDER BY u.id ASC', []];
        case 'all_active':
        default:
            return ["SELECT id FROM user WHERE User_Status = 'Active' ORDER BY id ASC", []];
    }
}

function vira_campaign_count_recipients(PDO $pdo, string $targetType): int
{
    switch ($targetType) {
        case 'all_users':
            return db_count($pdo, 'SELECT COUNT(*) FROM user');
        case 'blocked':
            return db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status = 'block'");
        case 'agents':
            return db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent IN ('n','n2')");
        case 'customers':
            return db_count($pdo, 'SELECT COUNT(DISTINCT id_user) FROM invoice');
        case 'no_purchase':
            return db_count($pdo, 'SELECT COUNT(*) FROM user u LEFT JOIN invoice i ON i.id_user = u.id WHERE i.id IS NULL');
        case 'all_active':
        default:
            return db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status = 'Active'");
    }
}

function vira_campaign_fetch_recipients(PDO $pdo, string $targetType, int $offset, int $limit): array
{
    [$sql] = vira_campaign_recipient_sql($targetType);
    $limit = max(1, min(40, $limit));
    $offset = max(0, $offset);
    return db_fetchAll($pdo, $sql . " LIMIT {$limit} OFFSET {$offset}");
}

function vira_campaign_row_to_array(array $row): array
{
    $total = (int) ($row['total_recipients'] ?? 0);
    $sent = (int) ($row['sent_count'] ?? 0);
    $failed = (int) ($row['failed_count'] ?? 0);
    $done = (int) ($row['offset_cursor'] ?? 0);
    $progress = $total > 0 ? min(100, (int) round(($done / $total) * 100)) : 0;
    return [
        'id' => (int) $row['id'],
        'message_type' => (string) ($row['message_type'] ?? 'text'),
        'text_body' => (string) ($row['text_body'] ?? ''),
        'target_type' => (string) ($row['target_type'] ?? 'all_active'),
        'target_label' => vira_campaign_target_defs()[$row['target_type'] ?? 'all_active'] ?? $row['target_type'],
        'reply_markup_json' => (string) ($row['reply_markup_json'] ?? ''),
        'pin_after_send' => (int) ($row['pin_after_send'] ?? 0) === 1,
        'sent_count' => $sent,
        'failed_count' => $failed,
        'total_recipients' => $total,
        'offset_cursor' => $done,
        'progress' => $progress,
        'status' => (string) ($row['status'] ?? 'draft'),
        'paused' => (int) ($row['paused'] ?? 0) === 1,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'completed_at' => (string) ($row['completed_at'] ?? ''),
    ];
}

function vira_campaign_list(PDO $pdo, int $limit = 30): array
{
    $rows = db_fetchAll($pdo, 'SELECT * FROM message_campaigns ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)));
    return array_map('vira_campaign_row_to_array', $rows);
}

function vira_campaign_get(PDO $pdo, int $id): ?array
{
    $row = db_fetch($pdo, 'SELECT * FROM message_campaigns WHERE id = ?', [$id]);
    return $row ? vira_campaign_row_to_array($row) : null;
}

function vira_campaign_create(PDO $pdo, array $data): array
{
    $target = (string) ($data['target_type'] ?? 'all_active');
    if (!array_key_exists($target, vira_campaign_target_defs())) {
        $target = 'all_active';
    }
    $text = trim((string) ($data['text_body'] ?? ''));
    if ($text === '') {
        throw new InvalidArgumentException('متن پیام خالی است');
    }
    $markup = trim((string) ($data['reply_markup_json'] ?? ''));
    if ($markup !== '') {
        $dec = json_decode($markup, true);
        if (!is_array($dec)) {
            throw new InvalidArgumentException('JSON دکمه‌های اینلاین نامعتبر است');
        }
        $markup = json_encode($dec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $total = vira_campaign_count_recipients($pdo, $target);
    db_query($pdo, 'INSERT INTO message_campaigns (message_type, text_body, target_type, reply_markup_json, pin_after_send, total_recipients, status) VALUES (?, ?, ?, ?, ?, ?, ?)', [
        'text',
        $text,
        $target,
        $markup !== '' ? $markup : null,
        !empty($data['pin_after_send']) ? 1 : 0,
        $total,
        'sending',
    ]);
    $id = (int) $pdo->lastInsertId();
    $campaign = vira_campaign_get($pdo, $id);
    if (!$campaign) {
        throw new RuntimeException('ایجاد کمپین ناموفق بود');
    }
    return $campaign;
}

function vira_campaign_set_paused(PDO $pdo, int $id, bool $paused): ?array
{
    db_query($pdo, 'UPDATE message_campaigns SET paused = ?, status = ? WHERE id = ?', [
        $paused ? 1 : 0,
        $paused ? 'paused' : 'sending',
        $id,
    ]);
    return vira_campaign_get($pdo, $id);
}

function vira_campaign_delete(PDO $pdo, int $id): void
{
    db_query($pdo, 'DELETE FROM message_campaigns WHERE id = ?', [$id]);
}

function vira_campaign_send_batch(PDO $pdo, int $campaignId, int $batchSize = 25): array
{
    $row = db_fetch($pdo, 'SELECT * FROM message_campaigns WHERE id = ?', [$campaignId]);
    if (!$row) {
        throw new InvalidArgumentException('کمپین یافت نشد');
    }
    if ((int) ($row['paused'] ?? 0) === 1) {
        return array_merge(vira_campaign_row_to_array($row), ['done' => false, 'message' => 'متوقف شده']);
    }
    if (($row['status'] ?? '') === 'completed') {
        return array_merge(vira_campaign_row_to_array($row), ['done' => true, 'message' => 'قبلاً تکمیل شده']);
    }

    if (!function_exists('sendmessage')) {
        require_once __DIR__ . '/../../botapi.php';
        require_once __DIR__ . '/../../panels.php';
        require_once __DIR__ . '/../../keyboard.php';
        require_once __DIR__ . '/../../jdf.php';
    }

    $text = (string) ($row['text_body'] ?? '');
    $keyboard = null;
    $markup = trim((string) ($row['reply_markup_json'] ?? ''));
    if ($markup !== '') {
        $keyboard = $markup;
    }

    $offset = (int) ($row['offset_cursor'] ?? 0);
    $batchSize = max(5, min(40, $batchSize));
    $users = vira_campaign_fetch_recipients($pdo, (string) $row['target_type'], $offset, $batchSize);

    $sent = 0;
    $failed = 0;
    foreach ($users as $u) {
        $chatId = (int) ($u['id'] ?? 0);
        if ($chatId <= 0) {
            continue;
        }
        $res = sendmessage($chatId, $text, $keyboard, 'HTML');
        if (!empty($res['ok'])) {
            $sent++;
            if ((int) ($row['pin_after_send'] ?? 0) === 1 && !empty($res['result']['message_id'])) {
                telegram('pinChatMessage', [
                    'chat_id' => $chatId,
                    'message_id' => $res['result']['message_id'],
                    'disable_notification' => true,
                ]);
            }
        } else {
            $failed++;
        }
        usleep(35000);
    }

    $newOffset = $offset + count($users);
    $total = (int) ($row['total_recipients'] ?? 0);
    $done = $newOffset >= $total || count($users) === 0;
    $status = $done ? 'completed' : 'sending';

    db_query($pdo, 'UPDATE message_campaigns SET sent_count = sent_count + ?, failed_count = failed_count + ?, offset_cursor = ?, status = ?, completed_at = IF(?, NOW(), completed_at) WHERE id = ?', [
        $sent,
        $failed,
        $newOffset,
        $status,
        $done ? 1 : 0,
        $campaignId,
    ]);

    $out = vira_campaign_get($pdo, $campaignId);
    $out['done'] = $done;
    $out['batch_sent'] = $sent;
    $out['batch_failed'] = $failed;
    return $out;
}

function vira_user_stats_summary(PDO $pdo): array
{
    $todayTs = strtotime('today');
    $weekTs = strtotime('-7 days', $todayTs);
    $monthTs = strtotime('-30 days', $todayTs);

    return [
        'total_users' => db_count($pdo, 'SELECT COUNT(*) FROM user'),
        'users_today' => db_count($pdo, 'SELECT COUNT(*) FROM user WHERE register > ? AND register != ? AND register != ?', [$todayTs, 'none', '']),
        'users_7d' => db_count($pdo, 'SELECT COUNT(*) FROM user WHERE register > ? AND register != ?', [$weekTs, 'none']),
        'users_30d' => db_count($pdo, 'SELECT COUNT(*) FROM user WHERE register > ? AND register != ?', [$monthTs, 'none']),
        'active_users' => db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status = 'Active'"),
        'blocked_users' => db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status = 'block'"),
        'agent_users' => db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent IN ('n','n2')"),
    ];
}

function vira_user_growth_series(PDO $pdo, int $days = 30): array
{
    $days = max(7, min(365, $days));
    $labels = [];
    $counts = [];
    $cumulative = [];
    $running = 0;

    $beforeTs = strtotime('-' . ($days - 1) . ' days', strtotime('today'));
    try {
        $running = db_count($pdo, 'SELECT COUNT(*) FROM user WHERE register != ? AND register != ? AND CAST(register AS UNSIGNED) < ?', ['none', '', $beforeTs]);
    } catch (Exception $e) {
        $running = 0;
    }

    for ($i = $days - 1; $i >= 0; $i--) {
        $ts = strtotime("-{$i} days", strtotime('today'));
        $next = $ts + 86400;
        $labels[] = date('m/d', $ts);
        try {
            $cnt = db_count($pdo, 'SELECT COUNT(*) FROM user WHERE register != ? AND register != ? AND CAST(register AS UNSIGNED) >= ? AND CAST(register AS UNSIGNED) < ?', ['none', '', $ts, $next]);
        } catch (Exception $e) {
            $cnt = 0;
        }
        $counts[] = $cnt;
        $running += $cnt;
        $cumulative[] = $running;
    }

    return [
        'labels' => $labels,
        'counts' => $counts,
        'cumulative' => $cumulative,
        'days' => $days,
    ];
}
