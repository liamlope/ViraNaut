<?php
/**
 * هر ۱۵ دقیقه — sync دسته‌ای سفارش‌های فعال از پنل (جدا از مشاهده وضعیت توسط کاربر)
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/_cron_guard.php';
if (!vira_cron_try_lock('invoice_panel_sync')) {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../inc/panel_service_repair.php';

@ini_set('max_execution_time', '300');

if (!function_exists('vira_invoice_sync_all_from_panel')) {
    fwrite(STDERR, "INVOICE_SYNC:missing_function\n");
    exit(1);
}

try {
    $stats = vira_invoice_sync_all_from_panel(60);
    printf(
        "INVOICE_SYNC:checked=%d synced=%d repaired=%d skipped=%d errors=%d\n",
        (int) ($stats['checked'] ?? 0),
        (int) ($stats['synced'] ?? 0),
        (int) ($stats['repaired'] ?? 0),
        (int) ($stats['skipped'] ?? 0),
        (int) ($stats['errors'] ?? 0)
    );
    exit(0);
} catch (Throwable $e) {
    error_log('invoice_panel_sync: ' . $e->getMessage());
    fwrite(STDERR, 'INVOICE_SYNC:error ' . $e->getMessage() . "\n");
    exit(1);
}
