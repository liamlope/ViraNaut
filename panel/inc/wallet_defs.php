<?php
require_once __DIR__ . '/pay_settings_defs.php';

function vira_crypto_wallet_defs(): array
{
    return [
        ['key' => 'wallet_usdt_bsc', 'label' => 'USDT (BSC)', 'network' => 'BSC', 'symbol' => 'USDT', 'default' => '0x01f77c91107cbd28191a1e897073ad053fd2867c'],
        ['key' => 'wallet_usdt_polygon', 'label' => 'USDT (Polygon)', 'network' => 'Polygon', 'symbol' => 'USDT', 'default' => '0x01f77c91107cbd28191a1e897073ad053fd2867c'],
        ['key' => 'wallet_trx_tron', 'label' => 'TRX (Tron)', 'network' => 'Tron', 'symbol' => 'TRX', 'default' => 'TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL'],
        ['key' => 'wallet_btc', 'label' => 'Bitcoin', 'network' => 'BTC', 'symbol' => 'BTC', 'default' => 'bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn'],
        ['key' => 'wallet_solana', 'label' => 'Solana', 'network' => 'Solana', 'symbol' => 'SOL', 'default' => 'GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6'],
    ];
}

function vira_wallet_get(PDO $pdo, string $key, string $default = ''): string
{
    $v = mirza_pay_get_value($pdo, $key);
    return $v !== '' ? $v : $default;
}

function vira_wallet_set(PDO $pdo, string $key, string $value): void
{
    mirza_pay_set_value($pdo, $key, $value);
}

function vira_wallet_qr_data_uri(string $text, int $size = 200): string
{
    if (!class_exists(\Endroid\QrCode\QrCode::class)) {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_readable($autoload)) {
            require_once $autoload;
        }
    }
    if (!class_exists(\Endroid\QrCode\QrCode::class)) {
        return '';
    }
    try {
        $qr = \Endroid\QrCode\QrCode::create($text)->setSize($size)->setMargin(8);
        if (class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qr);
            return $result->getDataUri();
        }
    } catch (Throwable $e) {
        return '';
    }
    return '';
}

function vira_crypto_wallets_load(PDO $pdo): array
{
    $out = [];
    foreach (vira_crypto_wallet_defs() as $w) {
        $addr = vira_wallet_get($pdo, $w['key'], $w['default']);
        $out[] = array_merge($w, ['address' => $addr]);
    }
    return $out;
}

function vira_seed_default_wallets(PDO $pdo): void
{
    foreach (vira_crypto_wallet_defs() as $w) {
        if (vira_wallet_get($pdo, $w['key']) === '') {
            vira_wallet_set($pdo, $w['key'], $w['default']);
        }
    }
    if (vira_wallet_get($pdo, 'urlpaymenttron') === '') {
        vira_wallet_set($pdo, 'urlpaymenttron', 'TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL');
    }
}
