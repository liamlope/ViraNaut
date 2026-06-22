<?php
require_once __DIR__ . '/pay_settings_defs.php';

function vira_crypto_wallet_defs(): array
{
    $evm = '0xb60a111813bae216e3b178a5f9e31a95549c000e';

    return [
        ['key' => 'wallet_btc', 'label' => 'Bitcoin', 'network' => 'Bitcoin', 'symbol' => 'BTC', 'default' => 'bc1q5xw4nyqc5s993eukq9udrcpfh8ky6pc0mzlfsn'],
        ['key' => 'wallet_eth', 'label' => 'Ethereum', 'network' => 'Ethereum', 'symbol' => 'ETH · USDT · USDC', 'default' => $evm],
        ['key' => 'wallet_bnb', 'label' => 'BNB Smart Chain', 'network' => 'BSC', 'symbol' => 'BNB · USDT · USDC', 'default' => $evm],
        ['key' => 'wallet_polygon', 'label' => 'Polygon', 'network' => 'Polygon', 'symbol' => 'MATIC · USDT', 'default' => $evm],
        ['key' => 'wallet_solana', 'label' => 'Solana', 'network' => 'Solana', 'symbol' => 'SOL', 'default' => 'GfKRLRTrKx7SYJHd76Rc7tVE6WwJKTNoZutSQitfppR6'],
        ['key' => 'wallet_trx_tron', 'label' => 'Tron', 'network' => 'Tron', 'symbol' => 'TRX · USDT · USDC', 'default' => 'TQEW4TP8eGzmJNyzu6kdi4GJdZdNqmTFRL'],
        ['key' => 'wallet_doge', 'label' => 'Dogecoin', 'network' => 'Dogecoin', 'symbol' => 'DOGE', 'default' => 'DFAfCU1LHdc7sKFVs9dD7MySA7Wt4EJQtX'],
        ['key' => 'wallet_ton', 'label' => 'Toncoin', 'network' => 'TON', 'symbol' => 'TON (Gram)', 'default' => 'UQDpQupJJM8bcxk19XmEZtwe-oQ4XmIbxM8SB88z0MXmXYsu'],
    ];
}

function vira_wallet_get(PDO $pdo, string $key, string $default = ''): string
{
    $v = vira_pay_get_value($pdo, $key);
    return $v !== '' ? $v : $default;
}

function vira_wallet_set(PDO $pdo, string $key, string $value): void
{
    vira_pay_set_value($pdo, $key, $value);
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
