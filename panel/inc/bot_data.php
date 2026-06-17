<?php

/** Shared helpers for bot management pages in web panel. */
require_once __DIR__ . '/bot_texts_defs.php';

function mirza_panel_keyboard_catalog(): array
{
    return [
        'text_sell' => 'خرید اشتراک',
        'text_extend' => 'تمدید سرویس',
        'text_usertest' => 'اکانت تست',
        'text_wheel_luck' => 'گردونه شانس',
        'text_Purchased_services' => 'سرویس‌های من',
        'accountwallet' => 'کیف پول',
        'text_affiliates' => 'زیرمجموعه',
        'text_Tariff_list' => 'لیست تعرفه',
        'text_support' => 'پشتیبانی',
        'text_help' => 'آموزش',
    ];
}

function mirza_panel_default_keyboard_json(): string
{
    return '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
}

function mirza_panel_load_keyboardmain(PDO $pdo): array
{
    $row = db_fetch($pdo, "SELECT keyboardmain FROM setting LIMIT 1");
    $raw = $row['keyboardmain'] ?? '';
    if ($raw === '' || $raw === null) {
        $raw = mirza_panel_default_keyboard_json();
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['keyboard']) || !is_array($data['keyboard'])) {
        $data = json_decode(mirza_panel_default_keyboard_json(), true);
    }
    return $data;
}

function mirza_panel_load_datatextbot(PDO $pdo): array
{
    $catalog = mirza_panel_keyboard_catalog();
    $out = array_fill_keys(array_keys($catalog), '');
    try {
        $rows = db_fetchAll($pdo, "SELECT id_text, text FROM textbot");
        foreach ($rows as $row) {
            if (isset($out[$row['id_text']])) {
                $out[$row['id_text']] = (string) $row['text'];
            }
        }
    } catch (Exception $e) {
        error_log('mirza_panel_load_datatextbot: ' . $e->getMessage());
    }
    return $out;
}

function mirza_panel_keyboard_label(string $keyId, array $datatextbot, array $catalog): string
{
    $raw = trim($datatextbot[$keyId] ?? '');
    if ($raw !== '') {
        if (function_exists('mirza_resolve_keyboard_button')) {
            $resolved = mirza_resolve_keyboard_button($raw);
            $label = $resolved['text'];
            if (!empty($resolved['icon_custom_emoji_id'])) {
                $label = '◆ ' . $label;
            }
            return $label !== '' ? $label : mirza_textbot_display($raw);
        }
        return $raw;
    }
    return $catalog[$keyId] ?? $keyId;
}

function mirza_panel_save_keyboardmain(PDO $pdo, array $keyboardRows): bool
{
    $payload = json_encode(['keyboard' => $keyboardRows], JSON_UNESCAPED_UNICODE);
    $exists = db_count($pdo, "SELECT COUNT(*) FROM setting");
    if ($exists > 0) {
        db_query($pdo, "UPDATE setting SET keyboardmain = ?", [$payload]);
    } else {
        db_query($pdo, "INSERT INTO setting (keyboardmain) VALUES (?)", [$payload]);
    }
    return true;
}
