<?php
/**
 * بعد از update — نمونه کوچک repair (نه sync همه سفارش‌ها)
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../inc/panel_service_repair.php';

if (!function_exists('vira_invoice_run_post_update_integrity')) {
    fwrite(STDERR, "INVOICE_POST_UPDATE:missing_function\n");
    exit(1);
}

try {
    $stats = vira_invoice_run_post_update_integrity();
    printf(
        "INVOICE_POST_UPDATE:checked=%d repaired=%d skipped=%d errors=%d\n",
        (int) ($stats['checked'] ?? 0),
        (int) ($stats['repaired'] ?? 0),
        (int) ($stats['skipped'] ?? 0),
        (int) ($stats['errors'] ?? 0)
    );
    exit(0);
} catch (Throwable $e) {
    error_log('invoice_integrity_after_update: ' . $e->getMessage());
    fwrite(STDERR, 'INVOICE_POST_UPDATE:error ' . $e->getMessage() . "\n");
    exit(1);
}
