<?php

/** Premium custom emoji library (Telegram Bot API icon_custom_emoji_id). */

if (!function_exists('mirza_ensure_bot_custom_emoji_table')) {
    function mirza_ensure_bot_custom_emoji_table(): void
    {
        global $pdo;
        if (!isset($pdo)) {
            return;
        }
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS bot_custom_emoji (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    emoji_name VARCHAR(120) NOT NULL,
                    custom_emoji_id VARCHAR(32) NOT NULL,
                    emoji_utf8 VARCHAR(16) DEFAULT NULL,
                    created_at INT UNSIGNED NOT NULL DEFAULT 0,
                    created_by VARCHAR(32) DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_custom_emoji_id (custom_emoji_id),
                    KEY idx_emoji_name (emoji_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('mirza_ensure_bot_custom_emoji_table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('mirza_utf16_strlen')) {
    function mirza_utf16_strlen(string $text): int
    {
        $len = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            return 0;
        }
        foreach ($chars as $ch) {
            $code = function_exists('mb_ord') ? mb_ord($ch, 'UTF-8') : unpack('N', mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8'))[1];
            $len += ($code > 0xFFFF) ? 2 : 1;
        }
        return $len;
    }
}

if (!function_exists('mirza_utf16_substring')) {
    function mirza_utf16_substring(string $text, int $offset, int $length): string
    {
        $pos = 0;
        $out = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            return '';
        }
        foreach ($chars as $ch) {
            $code = function_exists('mb_ord') ? mb_ord($ch, 'UTF-8') : unpack('N', mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8'))[1];
            $units = ($code > 0xFFFF) ? 2 : 1;
            if ($pos >= $offset + $length) {
                break;
            }
            if ($pos + $units > $offset) {
                $out .= $ch;
            }
            $pos += $units;
        }
        return $out;
    }
}

if (!function_exists('mirza_message_extract_custom_emoji')) {
    /** @return array{custom_emoji_id:string,emoji_utf8:string}|null */
    function mirza_message_extract_custom_emoji(array $message): ?array
    {
        $entities = $message['entities'] ?? [];
        $text = (string) ($message['text'] ?? $message['caption'] ?? '');
        if ($text === '' || !is_array($entities)) {
            return null;
        }
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') !== 'custom_emoji') {
                continue;
            }
            $id = trim((string) ($entity['custom_emoji_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $offset = (int) ($entity['offset'] ?? 0);
            $length = (int) ($entity['length'] ?? 0);
            $char = $length > 0 ? mirza_utf16_substring($text, $offset, $length) : '';
            return [
                'custom_emoji_id' => $id,
                'emoji_utf8' => $char,
            ];
        }
        return null;
    }
}

if (!function_exists('mirza_custom_emoji_list')) {
    function mirza_custom_emoji_list(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
        if (!$pdo instanceof PDO) {
            return [];
        }
        mirza_ensure_bot_custom_emoji_table();
        try {
            $stmt = $pdo->query('SELECT * FROM bot_custom_emoji ORDER BY emoji_name ASC, id ASC');
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log('mirza_custom_emoji_list: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('mirza_custom_emoji_by_name_map')) {
    function mirza_custom_emoji_by_name_map(?PDO $pdo = null): array
    {
        $map = [];
        foreach (mirza_custom_emoji_list($pdo) as $row) {
            $key = mb_strtolower(trim((string) $row['emoji_name']), 'UTF-8');
            if ($key !== '') {
                $map[$key] = $row;
            }
        }
        return $map;
    }
}

if (!function_exists('mirza_emoji_placeholder')) {
    function mirza_emoji_placeholder(array $emojiRow): string
    {
        $name = trim((string) ($emojiRow['emoji_name'] ?? ''));
        return $name !== '' ? '{emoji:' . $name . '}' : '';
    }
}

if (!function_exists('mirza_emoji_lookup_row')) {
    function mirza_emoji_lookup_row(string $ref, ?PDO $pdo = null): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        $byName = mirza_custom_emoji_by_name_map($pdo);
        $lower = mb_strtolower($ref, 'UTF-8');
        if (isset($byName[$lower])) {
            return $byName[$lower];
        }
        if (ctype_digit($ref)) {
            $all = mirza_custom_emoji_by_id_map($pdo);
            return $all[(int) $ref] ?? null;
        }
        return null;
    }
}

if (!function_exists('mirza_replace_emoji_placeholders_text_only')) {
    /** جایگزینی {emoji:نام} با کاراکتر — برای دکمه‌ها (بدون entity). */
    function mirza_replace_emoji_placeholders_text_only(string $text, ?PDO $pdo = null): string
    {
        if (strpos($text, '{emoji:') === false) {
            return $text;
        }
        return (string) preg_replace_callback('/\{emoji:([^}]+)\}/u', static function (array $m) use ($pdo) {
            $row = mirza_emoji_lookup_row($m[1], $pdo);
            if (!$row) {
                return $m[0];
            }
            $char = (string) ($row['emoji_utf8'] ?? '');
            return $char !== '' ? $char : $m[0];
        }, $text);
    }
}

if (!function_exists('mirza_render_text_custom_emojis')) {
    /**
     * {emoji:نام} را در هر نقطهٔ متن به ایموجی پرمیوم + entities تبدیل می‌کند.
     *
     * @return array{text:string,entities:string|null,has_placeholders:bool}
     */
    function mirza_render_text_custom_emojis(string $text, ?PDO $pdo = null): array
    {
        if (strpos($text, '{emoji:') === false) {
            return ['text' => $text, 'entities' => null, 'has_placeholders' => false];
        }
        $entities = [];
        $out = '';
        $offset = 0;
        $pattern = '/\{emoji:([^}]+)\}/u';
        while ($offset < strlen($text)) {
            if (!preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $out .= substr($text, $offset);
                break;
            }
            $matchStart = (int) $m[0][1];
            $matchLen = strlen($m[0][0]);
            $out .= substr($text, $offset, $matchStart - $offset);
            $row = mirza_emoji_lookup_row($m[1][0], $pdo);
            if ($row === null) {
                $out .= $m[0][0];
            } else {
                $char = (string) ($row['emoji_utf8'] ?? '');
                if ($char === '') {
                    $out .= $m[0][0];
                } else {
                    $entities[] = [
                        'type' => 'custom_emoji',
                        'offset' => mirza_utf16_strlen($out),
                        'length' => mirza_utf16_strlen($char),
                        'custom_emoji_id' => (string) $row['custom_emoji_id'],
                    ];
                    $out .= $char;
                }
            }
            $offset = $matchStart + $matchLen;
        }
        return [
            'text' => $out,
            'entities' => $entities !== [] ? json_encode($entities, JSON_UNESCAPED_UNICODE) : null,
            'has_placeholders' => true,
        ];
    }
}

if (!function_exists('mirza_prepare_outgoing_text')) {
    /** آماده‌سازی متن قبل از sendMessage (placeholder + HTML). */
    function mirza_prepare_outgoing_text(string $text, ?string $parse_mode = 'HTML'): array
    {
        $rendered = mirza_render_text_custom_emojis($text);
        if (!$rendered['has_placeholders']) {
            return ['text' => $text, 'parse_mode' => $parse_mode, 'entities' => null];
        }
        $hasHtml = strpos($rendered['text'], '<') !== false;
        if ($rendered['entities'] !== null && !$hasHtml) {
            return ['text' => $rendered['text'], 'parse_mode' => null, 'entities' => $rendered['entities']];
        }
        return [
            'text' => $rendered['text'],
            'parse_mode' => $parse_mode,
            'entities' => $rendered['entities'],
        ];
    }
}

if (!function_exists('mirza_custom_emoji_by_id_map')) {
    function mirza_custom_emoji_by_id_map(?PDO $pdo = null): array
    {
        $map = [];
        foreach (mirza_custom_emoji_list($pdo) as $row) {
            $map[(int) $row['id']] = $row;
        }
        return $map;
    }
}

if (!function_exists('mirza_custom_emoji_save')) {
    function mirza_custom_emoji_save(string $name, string $customEmojiId, string $emojiUtf8 = '', ?string $createdBy = null): array
    {
        global $pdo;
        mirza_ensure_bot_custom_emoji_table();
        $name = trim($name);
        $customEmojiId = trim($customEmojiId);
        if ($name === '' || $customEmojiId === '') {
            return ['ok' => false, 'error' => 'نام یا شناسه ایموجی خالی است.'];
        }
        if (mb_strlen($name, 'UTF-8') > 120) {
            $name = mb_substr($name, 0, 120, 'UTF-8');
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO bot_custom_emoji (emoji_name, custom_emoji_id, emoji_utf8, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE emoji_name = VALUES(emoji_name), emoji_utf8 = VALUES(emoji_utf8)'
            );
            $stmt->execute([$name, $customEmojiId, $emojiUtf8, time(), $createdBy]);
            $id = (int) $pdo->lastInsertId();
            if ($id === 0) {
                $find = $pdo->prepare('SELECT id FROM bot_custom_emoji WHERE custom_emoji_id = ? LIMIT 1');
                $find->execute([$customEmojiId]);
                $id = (int) ($find->fetchColumn() ?: 0);
            }
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            error_log('mirza_custom_emoji_save: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'ذخیره در پایگاه داده ناموفق بود.'];
        }
    }
}

if (!function_exists('mirza_custom_emoji_delete')) {
    function mirza_custom_emoji_delete(int $id): bool
    {
        global $pdo;
        if ($id <= 0) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM bot_custom_emoji WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('mirza_custom_emoji_delete: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('mirza_custom_emoji_rename')) {
    function mirza_custom_emoji_rename(int $id, string $name): bool
    {
        global $pdo;
        $name = trim($name);
        if ($id <= 0 || $name === '') {
            return false;
        }
        if (mb_strlen($name, 'UTF-8') > 120) {
            $name = mb_substr($name, 0, 120, 'UTF-8');
        }
        try {
            $stmt = $pdo->prepare('SELECT emoji_name FROM bot_custom_emoji WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $current = (string) ($stmt->fetchColumn() ?: '');
            if ($current === $name) {
                return true;
            }
            $stmt = $pdo->prepare('UPDATE bot_custom_emoji SET emoji_name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('mirza_custom_emoji_rename: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('mirza_textbot_emoji_map_get')) {
    function mirza_textbot_emoji_map_get(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
        if (!$pdo instanceof PDO) {
            return [];
        }
        try {
            $row = select('PaySetting', 'ValuePay', 'NamePay', 'bot_textbot_emoji_map', 'select');
            $raw = is_array($row) ? (string) ($row['ValuePay'] ?? '') : '';
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('mirza_textbot_emoji_map_set')) {
    function mirza_textbot_emoji_map_set(array $map, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
        if (!$pdo instanceof PDO) {
            return;
        }
        $clean = [];
        foreach ($map as $key => $val) {
            $key = trim((string) $key);
            $val = (int) $val;
            if ($key !== '' && $val > 0) {
                $clean[$key] = $val;
            }
        }
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $exists = select('PaySetting', 'NamePay', 'NamePay', 'bot_textbot_emoji_map', 'count');
        if ((int) $exists > 0) {
            update('PaySetting', 'ValuePay', $json, 'NamePay', 'bot_textbot_emoji_map');
        } else {
            $stmt = $pdo->prepare('INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)');
            $stmt->execute(['bot_textbot_emoji_map', $json]);
        }
    }
}

if (!function_exists('mirza_resolve_textbot_emoji')) {
    function mirza_resolve_textbot_emoji(string $idText, ?PDO $pdo = null): ?array
    {
        $map = mirza_textbot_emoji_map_get($pdo);
        $emojiId = (int) ($map[$idText] ?? 0);
        if ($emojiId <= 0) {
            return null;
        }
        $all = mirza_custom_emoji_by_id_map($pdo);
        return $all[$emojiId] ?? null;
    }
}

if (!function_exists('mirza_prepend_custom_emoji_to_message')) {
    /** @return array{text:string,entities:string|null} */
    function mirza_prepend_custom_emoji_to_message(string $text, ?array $emojiRow): array
    {
        if (!$emojiRow || empty($emojiRow['custom_emoji_id'])) {
            return ['text' => $text, 'entities' => null];
        }
        $char = (string) ($emojiRow['emoji_utf8'] ?? '');
        if ($char === '') {
            $char = '⭐';
        }
        $full = $char . $text;
        return [
            'text' => $full,
            'entities' => json_encode([[
                'type' => 'custom_emoji',
                'offset' => 0,
                'length' => mirza_utf16_strlen($char),
                'custom_emoji_id' => (string) $emojiRow['custom_emoji_id'],
            ]], JSON_UNESCAPED_UNICODE),
        ];
    }
}

if (!function_exists('mirza_keyboard_button_styles')) {
    function mirza_keyboard_button_styles(): array
    {
        return [
            '' => 'پیش‌فرض',
            'primary' => 'Primary',
            'success' => 'Success',
            'danger' => 'Danger',
        ];
    }
}

if (!function_exists('mirza_keyboard_apply_custom_emojis')) {
    /** @param array<int,array<int,array<string,mixed>>> $rows */
    function mirza_keyboard_apply_custom_emojis(array &$rows, ?PDO $pdo = null): void
    {
        $emojiMap = mirza_custom_emoji_by_id_map($pdo);
        foreach ($rows as $ri => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $bi => $btn) {
                if (!is_array($btn)) {
                    continue;
                }
                $emojiId = (int) ($btn['emoji_id'] ?? 0);
                if ($emojiId <= 0 || !isset($emojiMap[$emojiId])) {
                    continue;
                }
                $rows[$ri][$bi]['icon_custom_emoji_id'] = (string) $emojiMap[$emojiId]['custom_emoji_id'];
                $style = trim((string) ($btn['style'] ?? ''));
                if ($style !== '' && in_array($style, ['primary', 'success', 'danger'], true)) {
                    $rows[$ri][$bi]['style'] = $style;
                }
            }
        }
    }
}

if (!function_exists('mirza_keyboard_replace_text_keys')) {
    function mirza_keyboard_replace_text_keys(array $rows, array $replacements): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $newRow = [];
            foreach ($row as $btn) {
                if (!is_array($btn)) {
                    continue;
                }
                $key = (string) ($btn['text'] ?? '');
                $label = $replacements[$key] ?? $key;
                $label = mirza_replace_emoji_placeholders_text_only($label);
                $newBtn = $btn;
                $newBtn['text'] = $label;
                unset($newBtn['emoji_id']);
                $newRow[] = $newBtn;
            }
            if ($newRow !== []) {
                $out[] = $newRow;
            }
        }
        return $out;
    }
}

if (!function_exists('mirza_send_datatextbot_message')) {
    function mirza_send_datatextbot_message($chat_id, string $idText, string $text, $keyboard, string $parse_mode = 'HTML', $bot_token = null)
    {
        return sendmessage($chat_id, $text, $keyboard, $parse_mode, $bot_token);
    }
}
