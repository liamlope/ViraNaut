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
                    emoji_slug VARCHAR(64) DEFAULT NULL,
                    custom_emoji_id VARCHAR(32) NOT NULL,
                    emoji_utf8 VARCHAR(16) DEFAULT NULL,
                    created_at INT UNSIGNED NOT NULL DEFAULT 0,
                    created_by VARCHAR(32) DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_custom_emoji_id (custom_emoji_id),
                    UNIQUE KEY uq_emoji_slug (emoji_slug),
                    KEY idx_emoji_name (emoji_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $col = $pdo->query("SHOW COLUMNS FROM bot_custom_emoji LIKE 'emoji_slug'");
            if ($col && $col->rowCount() === 0) {
                $pdo->exec('ALTER TABLE bot_custom_emoji ADD COLUMN emoji_slug VARCHAR(64) NULL AFTER emoji_name');
                $pdo->exec('ALTER TABLE bot_custom_emoji ADD UNIQUE KEY uq_emoji_slug (emoji_slug)');
            }
            $rows = $pdo->query('SELECT id, emoji_name, emoji_slug FROM bot_custom_emoji WHERE emoji_slug IS NULL OR emoji_slug = ""')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $slug = mirza_emoji_make_slug((string) $row['emoji_name'], (int) $row['id']);
                $upd = $pdo->prepare('UPDATE bot_custom_emoji SET emoji_slug = ? WHERE id = ?');
                $upd->execute([$slug, (int) $row['id']]);
            }
        } catch (Throwable $e) {
            error_log('mirza_ensure_bot_custom_emoji_table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('mirza_emoji_make_slug')) {
    function mirza_emoji_make_slug(string $name, ?int $excludeId = null): string
    {
        global $pdo;
        $slug = mb_strtolower(trim($name), 'UTF-8');
        $slug = preg_replace('/[\s]+/u', '_', $slug);
        $slug = preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string) $slug);
        if ($slug === '') {
            $slug = 'emoji';
        }
        $slug = mb_substr($slug, 0, 58, 'UTF-8');
        $base = $slug;
        $try = 0;
        while ($try < 100) {
            $candidate = $try === 0 ? $base : $base . '_' . $try;
            $candidate = mb_substr($candidate, 0, 64, 'UTF-8');
            $stmt = $pdo->prepare('SELECT id FROM bot_custom_emoji WHERE emoji_slug = ? LIMIT 1');
            $stmt->execute([$candidate]);
            $found = (int) ($stmt->fetchColumn() ?: 0);
            if ($found === 0 || ($excludeId !== null && $found === $excludeId)) {
                return $candidate;
            }
            $try++;
        }
        return $base . '_' . time();
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

if (!function_exists('mirza_custom_emoji_lookup_maps')) {
    function mirza_custom_emoji_lookup_maps(?PDO $pdo = null): array
    {
        $bySlug = [];
        $byName = [];
        $byId = [];
        foreach (mirza_custom_emoji_list($pdo) as $row) {
            $byId[(int) $row['id']] = $row;
            $slug = mb_strtolower(trim((string) ($row['emoji_slug'] ?? '')), 'UTF-8');
            if ($slug !== '') {
                $bySlug[$slug] = $row;
            }
            $name = mb_strtolower(trim((string) ($row['emoji_name'] ?? '')), 'UTF-8');
            if ($name !== '') {
                $byName[$name] = $row;
            }
        }
        return ['slug' => $bySlug, 'name' => $byName, 'id' => $byId];
    }
}

if (!function_exists('mirza_emoji_lookup_row')) {
    function mirza_emoji_lookup_row(string $ref, ?PDO $pdo = null): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        $maps = mirza_custom_emoji_lookup_maps($pdo);
        if (preg_match('/^#?(\d+)$/', $ref, $num)) {
            return $maps['id'][(int) $num[1]] ?? null;
        }
        $lower = mb_strtolower($ref, 'UTF-8');
        if (isset($maps['slug'][$lower])) {
            return $maps['slug'][$lower];
        }
        if (isset($maps['name'][$lower])) {
            return $maps['name'][$lower];
        }
        return null;
    }
}

if (!function_exists('mirza_emoji_placeholder')) {
    /** کد ثابت — با rename نام نمایشی عوض نمی‌شود. */
    function mirza_emoji_placeholder(array $emojiRow): string
    {
        $slug = trim((string) ($emojiRow['emoji_slug'] ?? ''));
        if ($slug === '') {
            $slug = (string) (int) ($emojiRow['id'] ?? 0);
        }
        return $slug !== '' && $slug !== '0' ? '{emoji:' . $slug . '}' : '';
    }
}

if (!function_exists('mirza_emoji_code_html')) {
    /** نمایش literal ‎{emoji:slug}‎ در HTML بدون resolve شدن توسط sendmessage. */
    function mirza_emoji_code_html(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }
        return '&#123;emoji:' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '&#125;';
    }
}

if (!function_exists('mirza_preserve_emoji_placeholders_html')) {
    /** متن را برای نمایش داخل &lt;code&gt; آماده می‌کند تا placeholderها resolve نشوند. */
    function mirza_preserve_emoji_placeholders_html(string $text): string
    {
        if (strpos($text, '{emoji:') === false) {
            return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
        }
        $parts = preg_split('/(\{emoji:[^}]+\})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
        }
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\{emoji:([^}]+)\}$/u', $part, $m)) {
                $out .= mirza_emoji_code_html($m[1]);
            } else {
                $out .= htmlspecialchars($part, ENT_NOQUOTES, 'UTF-8');
            }
        }
        return $out;
    }
}

if (!function_exists('mirza_resolve_keyboard_button')) {
    /**
     * اولین {emoji:…} → icon_custom_emoji_id (پرمیوم متحرک چپ دکمه)
     * بقیه → کاراکتر ثابت در متن (محدودیت API تلگرام)
     *
     * @return array{text:string,icon_custom_emoji_id:?string}
     */
    function mirza_resolve_keyboard_button(string $raw, ?PDO $pdo = null): array
    {
        if (strpos($raw, '{emoji:') === false) {
            return ['text' => $raw, 'icon_custom_emoji_id' => null];
        }
        $iconId = null;
        $usedIcon = false;
        $text = (string) preg_replace_callback('/\{emoji:([^}]+)\}/u', static function (array $m) use ($pdo, &$iconId, &$usedIcon) {
            $row = mirza_emoji_lookup_row($m[1], $pdo);
            if (!$row) {
                return '';
            }
            if (!$usedIcon && !empty($row['custom_emoji_id'])) {
                $usedIcon = true;
                $iconId = (string) $row['custom_emoji_id'];
                return '';
            }
            $char = (string) ($row['emoji_utf8'] ?? '');
            return $char;
        }, $raw);
        $text = trim(preg_replace('/\s{2,}/u', ' ', $text));
        return ['text' => $text, 'icon_custom_emoji_id' => $iconId];
    }
}

if (!function_exists('mirza_render_text_custom_emojis')) {
    /** همه placeholderها با entity پرمیوم (برای پیام متنی، نه دکمه). */
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
                if ($char === '' || empty($row['custom_emoji_id'])) {
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
    function mirza_prepare_outgoing_text(string $text, ?string $parse_mode = 'HTML'): array
    {
        $rendered = mirza_render_text_custom_emojis($text);
        if (!$rendered['has_placeholders']) {
            return ['text' => $text, 'parse_mode' => $parse_mode, 'entities' => null];
        }
        if ($rendered['entities'] !== null) {
            return ['text' => $rendered['text'], 'parse_mode' => null, 'entities' => $rendered['entities']];
        }
        return ['text' => $rendered['text'], 'parse_mode' => $parse_mode, 'entities' => null];
    }
}

if (!function_exists('mirza_keyboard_raw_label')) {
    function mirza_keyboard_raw_label(array $datatextbot, string $key, string $fallback = ''): string
    {
        $raw = trim((string) ($datatextbot[$key] ?? ''));
        return $raw !== '' ? $raw : trim($fallback);
    }
}

if (!function_exists('mirza_textbot_display')) {
    function mirza_textbot_display(string $raw): string
    {
        return mirza_resolve_keyboard_button($raw)['text'];
    }
}

if (!function_exists('mirza_textbot_matches')) {
    function mirza_textbot_matches(?string $incoming, ?string $raw): bool
    {
        if ($incoming === null || $raw === null) {
            return false;
        }
        if ($incoming === $raw) {
            return true;
        }
        $resolved = mirza_resolve_keyboard_button($raw);
        if ($incoming === $resolved['text']) {
            return true;
        }
        return $incoming === mirza_render_text_custom_emojis($raw)['text'];
    }
}

if (!function_exists('mirza_custom_emoji_by_id_map')) {
    function mirza_custom_emoji_by_id_map(?PDO $pdo = null): array
    {
        return mirza_custom_emoji_lookup_maps($pdo)['id'];
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
            $find = $pdo->prepare('SELECT id, emoji_slug FROM bot_custom_emoji WHERE custom_emoji_id = ? LIMIT 1');
            $find->execute([$customEmojiId]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $slug = trim((string) ($existing['emoji_slug'] ?? ''));
                if ($slug === '') {
                    $slug = mirza_emoji_make_slug($name, (int) $existing['id']);
                }
                $stmt = $pdo->prepare(
                    'UPDATE bot_custom_emoji SET emoji_name = ?, emoji_slug = ?, emoji_utf8 = ? WHERE id = ?'
                );
                $stmt->execute([$name, $slug, $emojiUtf8, (int) $existing['id']]);
                return ['ok' => true, 'id' => (int) $existing['id'], 'slug' => $slug];
            }
            $slug = mirza_emoji_make_slug($name);
            $stmt = $pdo->prepare(
                'INSERT INTO bot_custom_emoji (emoji_name, emoji_slug, custom_emoji_id, emoji_utf8, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $slug, $customEmojiId, $emojiUtf8, time(), $createdBy]);
            return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'slug' => $slug];
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
    /** فقط نام نمایشی — slug و {emoji:slug} ثابت می‌ماند. */
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
            $stmt = $pdo->prepare('UPDATE bot_custom_emoji SET emoji_name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            return true;
        } catch (Throwable $e) {
            error_log('mirza_custom_emoji_rename: ' . $e->getMessage());
            return false;
        }
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
    function mirza_keyboard_apply_custom_emojis(array &$rows, ?PDO $pdo = null): void
    {
        unset($pdo);
        foreach ($rows as $ri => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $bi => $btn) {
                if (!is_array($btn)) {
                    continue;
                }
                unset($rows[$ri][$bi]['emoji_id']);
                $style = trim((string) ($btn['style'] ?? ''));
                if ($style !== '' && in_array($style, ['primary', 'success', 'danger'], true)) {
                    $rows[$ri][$bi]['style'] = $style;
                } else {
                    unset($rows[$ri][$bi]['style']);
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
                $raw = $replacements[$key] ?? $key;
                $resolved = mirza_resolve_keyboard_button($raw);
                $newBtn = $btn;
                $newBtn['text'] = $resolved['text'] !== '' ? $resolved['text'] : $raw;
                if (!empty($resolved['icon_custom_emoji_id'])) {
                    $newBtn['icon_custom_emoji_id'] = $resolved['icon_custom_emoji_id'];
                } else {
                    unset($newBtn['icon_custom_emoji_id']);
                }
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
