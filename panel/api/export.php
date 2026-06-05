<?php
require_once __DIR__ . '/../inc/config.php';
require_auth_api();

$type = trim((string) ($_GET['type'] ?? 'users'));
$format = trim((string) ($_GET['format'] ?? 'csv'));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="viranaut_' . $type . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

try {
    if ($type === 'users') {
        fputcsv($out, ['id', 'name', 'username', 'balance', 'status', 'register']);
        foreach (db_fetchAll($pdo, 'SELECT id, namecustom, username, Balance, User_Status, register FROM user ORDER BY register DESC LIMIT 50000') as $u) {
            fputcsv($out, [
                $u['id'],
                $u['namecustom'] ?? '',
                $u['username'] ?? '',
                $u['Balance'] ?? 0,
                $u['User_Status'] ?? '',
                $u['register'] ?? '',
            ]);
        }
    } elseif ($type === 'invoices') {
        fputcsv($out, ['id_invoice', 'id_user', 'name_product', 'price_product', 'Status', 'time_sell']);
        foreach (db_fetchAll($pdo, 'SELECT id_invoice, id_user, name_product, price_product, Status, time_sell FROM invoice ORDER BY time_sell DESC LIMIT 50000') as $inv) {
            fputcsv($out, [
                $inv['id_invoice'],
                $inv['id_user'],
                $inv['name_product'] ?? '',
                $inv['price_product'] ?? 0,
                $inv['Status'] ?? '',
                $inv['time_sell'] ?? '',
            ]);
        }
    }
} catch (Exception $e) {
    fputcsv($out, ['error', $e->getMessage()]);
}

fclose($out);
