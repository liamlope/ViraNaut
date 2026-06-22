<?php

require_once __DIR__ . '/reply_markup.php';

function vira_campaign_media_dir(): string
{
    $dir = __DIR__ . '/../uploads/campaigns';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function vira_campaign_ensure_tables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        text_body MEDIUMTEXT NULL,
        target_type VARCHAR(40) NOT NULL DEFAULT 'all',
        target_user_ids TEXT NULL,
        target_language_codes TEXT NULL,
        reply_markup_json TEXT NULL,
        media_path VARCHAR(500) NULL,
        parse_mode VARCHAR(20) NULL DEFAULT 'HTML',
        disable_web_page_preview TINYINT(1) NOT NULL DEFAULT 0,
        pin_after_send TINYINT(1) NOT NULL DEFAULT 0,
        auto_send_new_users TINYINT(1) NOT NULL DEFAULT 0,
        auto_send_delay_minutes INT NOT NULL DEFAULT 5,
        sent_count INT NOT NULL DEFAULT 0,
        failed_count INT NOT NULL DEFAULT 0,
        deleted_count INT NOT NULL DEFAULT 0,
        total_recipients INT NOT NULL DEFAULT 0,
        offset_cursor INT NOT NULL DEFAULT 0,
        status VARCHAR(24) NOT NULL DEFAULT 'draft',
        paused TINYINT(1) NOT NULL DEFAULT 0,
        is_pinned TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        broadcast_completed_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cols = [
        'target_user_ids' => 'TEXT NULL',
        'target_language_codes' => 'TEXT NULL',
        'media_path' => 'VARCHAR(500) NULL',
        'parse_mode' => "VARCHAR(20) NULL DEFAULT 'HTML'",
        'disable_web_page_preview' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'auto_send_new_users' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'auto_send_delay_minutes' => 'INT NOT NULL DEFAULT 5',
        'deleted_count' => 'INT NOT NULL DEFAULT 0',
        'is_pinned' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'broadcast_completed_at' => 'DATETIME NULL',
    ];
    foreach ($cols as $name => $def) {
        try {
            $chk = db_fetch($pdo, 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', ['message_campaigns', $name]);
            if (!$chk) {
                $pdo->exec("ALTER TABLE message_campaigns ADD COLUMN {$name} {$def}");
            }
        } catch (Throwable $e) {
            // ignore migration race
        }
    }
}

function vira_campaign_target_defs(): array
{
    return [
        'all' => 'همه کاربران فعال',
        'agent_f' => 'کاربران عادی (f)',
        'agent_n' => 'نمایندگان (n)',
        'agent_n2' => 'نمایندگان پیشرفته (n2)',
        'customers' => 'دارای خرید',
        'no_purchase' => 'بدون خرید',
        'blocked' => 'مسدودها',
        'users' => 'فقط کاربران انتخاب‌شده',
    ];
}

function vira_campaign_targets_with_counts(PDO $pdo): array
{
    $out = [];
    foreach (vira_campaign_target_defs() as $id => $label) {
        $count = $id === 'users' ? 0 : vira_campaign_count_recipients($pdo, $id);
        $out[] = ['id' => $id, 'label' => $label, 'count' => $count];
    }
    return $out;
}

function vira_campaign_parse_user_ids(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $dec = json_decode($raw, true);
    if (!is_array($dec)) {
        return [];
    }
    $ids = [];
    foreach ($dec as $id) {
        $id = trim((string) $id);
        if ($id !== '') {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function vira_campaign_search_users(PDO $pdo, string $q, int $limit = 25): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $qClean = ltrim($q, '@');
    $limit = max(1, min(50, $limit));
    $like = '%' . $qClean . '%';
    $conds = ['id LIKE ?', 'username LIKE ?', 'namecustom LIKE ?'];
    $params = [$like, $like, $like];
    if (ctype_digit($qClean)) {
        $conds[] = 'id = ?';
        $params[] = $qClean;
    }
    $sql = 'SELECT id, username, namecustom, lang, User_Status FROM user WHERE (' . implode(' OR ', $conds) . ') ORDER BY register DESC LIMIT ' . $limit;
    $rows = db_fetchAll($pdo, $sql, $params);
    return array_map(static function (array $row): array {
        $name = trim((string) ($row['namecustom'] ?? ''));
        $username = trim((string) ($row['username'] ?? ''));
        if ($name === '' || $name === 'none') {
            $name = ($username !== '' && $username !== 'none') ? $username : 'کاربر';
        }
        if ($username === 'none') {
            $username = '';
        }
        return [
            'id' => (string) ($row['id'] ?? ''),
            'telegram_id' => (string) ($row['id'] ?? ''),
            'username' => $username,
            'first_name' => $name,
            'language_code' => (string) ($row['lang'] ?? ''),
            'is_blocked' => (string) ($row['User_Status'] ?? '') === 'block',
        ];
    }, $rows);
}

function vira_campaign_recipient_where(string $targetType, array $userIds = []): array
{
    $legacyMap = [
        'all_active' => 'all',
        'all_users' => 'all',
        'agents' => 'agent_n',
        'language' => 'all',
    ];
    if (isset($legacyMap[$targetType])) {
        $targetType = $legacyMap[$targetType];
    }

    if ($targetType === 'blocked') {
        return ["User_Status = 'block'", []];
    }
    if ($targetType === 'agent_f') {
        return ["User_Status = 'Active' AND agent = 'f'", []];
    }
    if ($targetType === 'agent_n') {
        return ["User_Status = 'Active' AND agent = 'n'", []];
    }
    if ($targetType === 'agent_n2') {
        return ["User_Status = 'Active' AND agent = 'n2'", []];
    }
    if ($targetType === 'customers') {
        return ["User_Status = 'Active' AND id IN (SELECT DISTINCT id_user FROM invoice WHERE id_user IS NOT NULL)", []];
    }
    if ($targetType === 'no_purchase') {
        return ["User_Status = 'Active' AND id NOT IN (SELECT DISTINCT id_user FROM invoice WHERE id_user IS NOT NULL)", []];
    }
    if ($targetType === 'all') {
        return ["User_Status = 'Active'", []];
    }

    if ($targetType === 'users') {
        if ($userIds === []) {
            return ['1=0', []];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        return ["id IN ({$placeholders})", $userIds];
    }

    if (!array_key_exists($targetType, vira_campaign_target_defs())) {
        return ["User_Status = 'Active'", []];
    }

    return ['1=0', []];
}

function vira_campaign_count_recipients(PDO $pdo, string $targetType, array $userIds = []): int
{
    [$where, $params] = vira_campaign_recipient_where($targetType, $userIds);
    return db_count($pdo, "SELECT COUNT(*) FROM user WHERE {$where}", $params);
}

function vira_campaign_fetch_recipients(PDO $pdo, string $targetType, int $offset, int $limit, array $userIds = []): array
{
    [$where, $params] = vira_campaign_recipient_where($targetType, $userIds);
    $limit = max(1, min(40, $limit));
    $offset = max(0, $offset);
    $sql = "SELECT id FROM user WHERE {$where} ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}";
    return db_fetchAll($pdo, $sql, $params);
}

function vira_campaign_row_to_array(array $row): array
{
    $total = (int) ($row['total_recipients'] ?? 0);
    $sent = (int) ($row['sent_count'] ?? 0);
    $failed = (int) ($row['failed_count'] ?? 0);
    $done = (int) ($row['offset_cursor'] ?? 0);
    $processed = $sent + $failed;
    $progress = $total > 0 ? min(100, (int) round(($done / $total) * 100)) : 0;
    $targetType = (string) ($row['target_type'] ?? 'all');
    $targetLabel = vira_campaign_target_defs()[$targetType] ?? $targetType;

    return [
        'id' => (int) $row['id'],
        'message_type' => (string) ($row['message_type'] ?? 'text'),
        'text' => (string) ($row['text_body'] ?? ''),
        'text_body' => (string) ($row['text_body'] ?? ''),
        'target_type' => $targetType,
        'target_label' => $targetLabel,
        'target_user_ids' => (string) ($row['target_user_ids'] ?? ''),
        'target_language_codes' => (string) ($row['target_language_codes'] ?? ''),
        'reply_markup_json' => (string) ($row['reply_markup_json'] ?? ''),
        'media_path' => (string) ($row['media_path'] ?? ''),
        'media_url' => !empty($row['media_path']) ? vira_campaign_media_url((string) $row['media_path']) : '',
        'parse_mode' => (string) ($row['parse_mode'] ?? 'HTML'),
        'disable_web_page_preview' => (int) ($row['disable_web_page_preview'] ?? 0) === 1,
        'pin_after_send' => (int) ($row['pin_after_send'] ?? 0) === 1,
        'auto_send_new_users' => (int) ($row['auto_send_new_users'] ?? 0) === 1,
        'auto_send_delay_minutes' => (int) ($row['auto_send_delay_minutes'] ?? 5),
        'sent_count' => $sent,
        'failed_count' => $failed,
        'deleted_count' => (int) ($row['deleted_count'] ?? 0),
        'total_recipients' => $total,
        'offset_cursor' => $done,
        'processed' => $processed,
        'progress' => $progress,
        'percent' => $progress,
        'status' => (string) ($row['status'] ?? 'draft'),
        'paused' => (int) ($row['paused'] ?? 0) === 1,
        'is_pinned' => (int) ($row['is_pinned'] ?? 0) === 1,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'completed_at' => (string) ($row['completed_at'] ?? ''),
        'broadcast_completed_at' => (string) ($row['broadcast_completed_at'] ?? ''),
    ];
}

function vira_campaign_media_url(string $filename): string
{
    $base = function_exists('panel_asset') ? panel_asset('uploads/campaigns/' . rawurlencode(basename($filename))) : ('uploads/campaigns/' . rawurlencode(basename($filename)));
    return $base;
}

function vira_campaign_list(PDO $pdo, int $limit = 30, string $filter = ''): array
{
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT * FROM message_campaigns';
    $params = [];
    if ($filter === 'active') {
        $sql .= " WHERE status IN ('sending','paused','completed','active')";
    } elseif ($filter === 'deleted') {
        $sql .= " WHERE status = 'deleted'";
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
    $rows = db_fetchAll($pdo, $sql, $params);
    return array_map('vira_campaign_row_to_array', $rows);
}

function vira_campaign_get(PDO $pdo, int $id): ?array
{
    $row = db_fetch($pdo, 'SELECT * FROM message_campaigns WHERE id = ?', [$id]);
    return $row ? vira_campaign_row_to_array($row) : null;
}

function vira_campaign_save_uploaded_media(array $file, string $messageType): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('آپلود فایل ناموفق بود');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new InvalidArgumentException('فایل رسانه یافت نشد');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 50 * 1024 * 1024) {
        throw new InvalidArgumentException('حجم فایل باید بین ۱ بایت و ۵۰ مگابایت باشد');
    }

    $orig = (string) ($file['name'] ?? 'media');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowedPhoto = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedVideo = ['mp4', 'mov', 'webm', 'mkv'];
    if ($messageType === 'photo' && !in_array($ext, $allowedPhoto, true)) {
        throw new InvalidArgumentException('فرمت عکس پشتیبانی نمی‌شود');
    }
    if ($messageType === 'video' && !in_array($ext, $allowedVideo, true)) {
        throw new InvalidArgumentException('فرمت ویدیو پشتیبانی نمی‌شود');
    }

    $name = 'camp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
    $dest = vira_campaign_media_dir() . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('ذخیره فایل رسانه ناموفق بود');
    }
    return $name;
}

function vira_campaign_create(PDO $pdo, array $data, ?array $uploadedFile = null): array
{
    $target = (string) ($data['target_type'] ?? 'all');
    if (!array_key_exists($target, vira_campaign_target_defs())) {
        $target = 'all';
    }

    $messageType = (string) ($data['message_type'] ?? 'text');
    if (!in_array($messageType, ['text', 'photo', 'video'], true)) {
        $messageType = 'text';
    }

    $text = trim((string) ($data['text_body'] ?? $data['text'] ?? ''));
    if ($messageType === 'text' && $text === '') {
        throw new InvalidArgumentException('متن پیام خالی است');
    }
    if ($messageType !== 'text' && $uploadedFile === null && empty($data['media_path'])) {
        throw new InvalidArgumentException('فایل رسانه الزامی است');
    }

    $userIds = [];
    if ($target === 'users') {
        $rawIds = $data['user_ids'] ?? $data['target_user_ids'] ?? '';
        if (is_array($rawIds)) {
            $userIds = array_values(array_filter(array_map('strval', $rawIds)));
        } else {
            $userIds = array_values(array_filter(array_map('trim', explode(',', (string) $rawIds))));
        }
        if ($userIds === []) {
            throw new InvalidArgumentException('حداقل یک کاربر انتخاب کنید');
        }
    }

    $buttonsRaw = trim((string) ($data['buttons_json'] ?? $data['reply_markup_json'] ?? ''));
    $markup = null;
    if ($buttonsRaw !== '') {
        $rows = vira_reply_markup_parse_rows($buttonsRaw);
        $markup = vira_reply_markup_serialize_rows($rows);
    }

    $parseMode = vira_reply_markup_normalize_parse_mode($data['parse_mode'] ?? 'HTML');
    $disablePreview = !empty($data['disable_web_page_preview']);

    $mediaPath = null;
    if ($uploadedFile !== null) {
        $mediaPath = vira_campaign_save_uploaded_media($uploadedFile, $messageType);
    } elseif (!empty($data['media_path'])) {
        $mediaPath = basename((string) $data['media_path']);
    }

    $total = vira_campaign_count_recipients($pdo, $target, $userIds);
    if ($total <= 0) {
        throw new InvalidArgumentException('مخاطبی برای ارسال یافت نشد');
    }

    db_query($pdo, 'INSERT INTO message_campaigns (
        message_type, text_body, target_type, target_user_ids, target_language_codes,
        reply_markup_json, media_path, parse_mode, disable_web_page_preview, pin_after_send,
        auto_send_new_users, auto_send_delay_minutes, total_recipients, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $messageType,
        $text,
        $target,
        $userIds !== [] ? json_encode($userIds, JSON_UNESCAPED_UNICODE) : null,
        null,
        $markup,
        $mediaPath,
        $parseMode,
        $disablePreview ? 1 : 0,
        !empty($data['pin_after_send']) ? 1 : 0,
        !empty($data['auto_send_new_users']) ? 1 : 0,
        max(0, min(1440, (int) ($data['auto_send_delay_minutes'] ?? 5))),
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
    $row = db_fetch($pdo, 'SELECT media_path FROM message_campaigns WHERE id = ?', [$id]);
    db_query($pdo, 'DELETE FROM message_campaigns WHERE id = ?', [$id]);
    if ($row && !empty($row['media_path'])) {
        $path = vira_campaign_media_dir() . '/' . basename((string) $row['media_path']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function vira_campaign_send_one(array $row, int $chatId, ?string $keyboardJson, ?string $parseMode, bool $disablePreview): array
{
    if (!function_exists('telegram')) {
        require_once __DIR__ . '/../../botapi.php';
    }

    $messageType = (string) ($row['message_type'] ?? 'text');
    $text = (string) ($row['text_body'] ?? '');
    $parseMode = $parseMode !== '' ? $parseMode : null;

    if ($messageType === 'photo' || $messageType === 'video') {
        $mediaFile = vira_campaign_media_dir() . '/' . basename((string) ($row['media_path'] ?? ''));
        if (!is_file($mediaFile)) {
            return ['ok' => false, 'description' => 'media missing'];
        }
        $method = $messageType === 'photo' ? 'sendPhoto' : 'sendVideo';
        $field = $messageType === 'photo' ? 'photo' : 'video';
        $data = [
            'chat_id' => $chatId,
            $field => new CURLFile($mediaFile),
            'caption' => $text,
        ];
        if ($keyboardJson !== null && $keyboardJson !== '') {
            $data['reply_markup'] = $keyboardJson;
        }
        if ($parseMode !== null) {
            $data['parse_mode'] = $parseMode;
        }
        if ($disablePreview) {
            $data['disable_web_page_preview'] = true;
        }
        return telegram($method, $data);
    }

    if (!function_exists('sendmessage')) {
        require_once __DIR__ . '/../../botapi.php';
    }

    if ($disablePreview) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($keyboardJson !== null && $keyboardJson !== '') {
            $data['reply_markup'] = $keyboardJson;
        }
        if ($parseMode !== null) {
            $data['parse_mode'] = $parseMode;
        }
        return telegram('sendMessage', $data);
    }

    return sendmessage($chatId, $text, $keyboardJson, $parseMode ?? '');
}

function vira_campaign_send_batch(PDO $pdo, int $campaignId, int $batchSize = 25): array
{
    $row = db_fetch($pdo, 'SELECT * FROM message_campaigns WHERE id = ?', [$campaignId]);
    if (!$row) {
        throw new InvalidArgumentException('کمپین یافت نشد');
    }
    if ((int) ($row['paused'] ?? 0) === 1) {
        $out = vira_campaign_row_to_array($row);
        $out['done'] = false;
        $out['message'] = 'متوقف شده';
        return $out;
    }
    if (in_array(($row['status'] ?? ''), ['completed', 'deleted'], true)) {
        $out = vira_campaign_row_to_array($row);
        $out['done'] = true;
        $out['message'] = 'قبلاً تکمیل شده';
        return $out;
    }

    $target = (string) ($row['target_type'] ?? 'all');
    $userIds = vira_campaign_parse_user_ids($row['target_user_ids'] ?? null);

    $keyboard = vira_reply_markup_telegram_json($row['reply_markup_json'] ?? null);
    $parseMode = vira_reply_markup_normalize_parse_mode($row['parse_mode'] ?? 'HTML');
    $disablePreview = (int) ($row['disable_web_page_preview'] ?? 0) === 1;

    $offset = (int) ($row['offset_cursor'] ?? 0);
    $batchSize = max(5, min(40, $batchSize));
    $users = vira_campaign_fetch_recipients($pdo, $target, $offset, $batchSize, $userIds);

    $sent = 0;
    $failed = 0;
    foreach ($users as $u) {
        $chatId = (int) ($u['id'] ?? 0);
        if ($chatId <= 0) {
            continue;
        }
        $res = vira_campaign_send_one($row, $chatId, $keyboard, $parseMode, $disablePreview);
        if (!empty($res['ok'])) {
            $sent++;
            if ((int) ($row['pin_after_send'] ?? 0) === 1 && !empty($res['result']['message_id'])) {
                if (!function_exists('telegram')) {
                    require_once __DIR__ . '/../../botapi.php';
                }
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

    db_query($pdo, 'UPDATE message_campaigns SET sent_count = sent_count + ?, failed_count = failed_count + ?, offset_cursor = ?, status = ?, completed_at = IF(?, NOW(), completed_at), broadcast_completed_at = IF(?, NOW(), broadcast_completed_at) WHERE id = ?', [
        $sent,
        $failed,
        $newOffset,
        $status,
        $done ? 1 : 0,
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

function vira_campaign_estimate_minutes(int $total): int
{
    if ($total <= 0) {
        return 0;
    }
    return max(1, (int) ceil(($total * 0.04) / 60));
}
