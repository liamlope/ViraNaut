<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', __DIR__ . '/../error_log');

try {
    $textbotlang = languagechange(__DIR__ . '/../text.json');

    $settingRow = select('setting', 'keyboardmain', null, null, 'select');
    $keyboardJson = is_array($settingRow) ? (string) ($settingRow['keyboardmain'] ?? '') : '';
    $keyboardmain = json_decode($keyboardJson, true);
    if (!is_array($keyboardmain)) {
        $keyboardmain = [];
    }
    if (!isset($keyboardmain['keyboard']) || !is_array($keyboardmain['keyboard'])) {
        $keyboardmain['keyboard'] = [];
    }

    $list_keyboard = [
        'text_sell',
        'text_extend',
        'text_usertest',
        'text_wheel_luck',
        'text_Purchased_services',
        'accountwallet',
        'text_affiliates',
        'text_Tariff_list',
        'text_support',
        'text_help',
    ];

    $textbot = is_array($textbotlang['textbot'] ?? null) ? $textbotlang['textbot'] : [];
    $labelMap = [
        'text_sell' => $textbot['sell'] ?? $textbot['text_sell'] ?? '🔐 خرید اشتراک',
        'text_extend' => $textbot['extend'] ?? $textbot['text_extend'] ?? '♻️ تمدید سرویس',
        'text_usertest' => $textbot['userTest'] ?? $textbot['text_usertest'] ?? '🔑 اکانت تست',
        'text_wheel_luck' => $textbot['wheelLuck'] ?? $textbot['text_wheel_luck'] ?? '🎲 گردونه شانس',
        'text_Purchased_services' => $textbot['purchasedServices'] ?? $textbot['text_Purchased_services'] ?? '🛍 سرویس های من',
        'accountwallet' => $textbot['accountWallet'] ?? $textbot['accountwallet'] ?? '🏦 کیف پول + شارژ',
        'text_affiliates' => $textbot['affiliates'] ?? $textbot['text_affiliates'] ?? '👥 زیر مجموعه گیری',
        'text_Tariff_list' => $textbot['tariffList'] ?? $textbot['text_Tariff_list'] ?? '💵 تعرفه اشتراک ها',
        'text_support' => $textbot['support'] ?? $textbot['text_support'] ?? '☎️ پشتیبانی',
        'text_help' => $textbot['help'] ?? $textbot['text_help'] ?? '📚 آموزش',
    ];

    $normalizeCell = static function ($cell): ?array {
        if (is_string($cell)) {
            $key = trim($cell);
            return $key === '' ? null : ['text' => $key];
        }
        if (is_array($cell) && isset($cell['text'])) {
            $key = trim((string) $cell['text']);
            return $key === '' ? null : ['text' => $key];
        }
        return null;
    };

    $userlist = [];
    foreach ($keyboardmain['keyboard'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalizedRow = [];
        foreach ($row as $cell) {
            $normalized = $normalizeCell($cell);
            if ($normalized === null) {
                continue;
            }
            $normalizedRow[] = $normalized;
            $key = $normalized['text'];
            $index = array_search($key, $list_keyboard, true);
            if ($index !== false) {
                unset($list_keyboard[$index]);
            }
        }
        if ($normalizedRow !== []) {
            $userlist[] = $normalizedRow;
        }
    }

    if ($userlist === []) {
        $userlist = [
            [['text' => 'text_sell'], ['text' => 'text_extend']],
            [['text' => 'text_usertest'], ['text' => 'text_wheel_luck']],
            [['text' => 'text_Purchased_services'], ['text' => 'accountwallet']],
            [['text' => 'text_affiliates'], ['text' => 'text_Tariff_list']],
            [['text' => 'text_support'], ['text' => 'text_help']],
        ];
        $list_keyboard = [];
    }

    $list_keyboard = array_values($list_keyboard);
    $keylist = [];
    foreach ($list_keyboard as $key) {
        $keylist[] = [['text' => $key]];
    }

    echo json_encode([
        'keylist' => $keylist,
        'userlist' => $userlist,
        'text' => $labelMap,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/keyboard.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
