<?php

/**
 * عملیات بهینه‌سازی امن ربات — بدون حذف کاربران، تنظیمات، پرداخت‌های موفق یا سرویس‌های فعال.
 */

function mirza_optimize_day_options(): array
{
    return [7, 30, 90];
}

function mirza_optimize_sanitize_days($value, int $default = 30): int
{
    $days = (int) $value;
    return in_array($days, mirza_optimize_day_options(), true) ? $days : $default;
}

function mirza_optimize_cutoff(int $days): string
{
    return date('Y/m/d', strtotime('-' . $days . ' days')) . ' 00:00:00';
}

function mirza_optimize_invoice_remove_statuses(): array
{
    return [
        'end_of_time',
        'end_of_volume',
        'removeTime',
        'removevolume',
        'removebyadmin',
        'removedbyadmin',
        'removebyuser',
        'unpaid',
        'disabled',
    ];
}

function mirza_optimize_count_invoices(PDO $pdo, array $statuses, ?string $extraWhere = null): int
{
    if ($statuses === []) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $sql = "SELECT COUNT(*) FROM invoice WHERE Status IN ($placeholders)";
    if ($extraWhere !== null && $extraWhere !== '') {
        $sql .= ' AND ' . $extraWhere;
    }
    return db_count($pdo, $sql, $statuses);
}

function mirza_optimize_delete_invoices(PDO $pdo, array $statuses, ?string $extraWhere = null): int
{
    if ($statuses === []) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $sql = "DELETE FROM invoice WHERE Status IN ($placeholders)";
    if ($extraWhere !== null && $extraWhere !== '') {
        $sql .= ' AND ' . $extraWhere;
    }
    return db_query($pdo, $sql, $statuses)->rowCount();
}

function mirza_optimize_count_unpaid_orders(PDO $pdo): int
{
    return db_count(
        $pdo,
        "SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'"
    );
}

function mirza_optimize_delete_unpaid_orders(PDO $pdo): int
{
    return db_query(
        $pdo,
        "DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'"
    )->rowCount();
}

function mirza_optimize_preview(PDO $pdo, int $daysExpireReject = 90, int $daysUnpaid = 30): array
{
    $daysExpireReject = mirza_optimize_sanitize_days($daysExpireReject, 90);
    $daysUnpaid = mirza_optimize_sanitize_days($daysUnpaid, 30);

    $dead = mirza_optimize_invoice_remove_statuses();
    $expired = mirza_optimize_count_invoices($pdo, ['end_of_time', 'end_of_volume', 'removeTime', 'removevolume']);
    $junk = mirza_optimize_count_invoices($pdo, ['disabled', 'removebyadmin', 'removedbyadmin', 'removebyuser'])
        + mirza_optimize_count_unpaid_orders($pdo);
    $cutoffPay = mirza_optimize_cutoff($daysExpireReject);
    $oldPayments = db_count(
        $pdo,
        "SELECT COUNT(*) FROM Payment_report WHERE payment_Status IN ('expire','reject') AND time < ?",
        [$cutoffPay]
    );
    $cutoffUnpaidPay = mirza_optimize_cutoff($daysUnpaid);
    $oldUnpaidPay = db_count(
        $pdo,
        "SELECT COUNT(*) FROM Payment_report WHERE payment_Status = 'Unpaid' AND time < ?",
        [$cutoffUnpaidPay]
    );
    $oldCancel = 0;
    try {
        $oldCancel = db_count($pdo, "SELECT COUNT(*) FROM cancel_service WHERE status IN ('accept','reject')");
    } catch (Throwable $e) {
        $oldCancel = 0;
    }
    $totalInvoices = db_count($pdo, 'SELECT COUNT(*) FROM invoice');
    $activeInvoices = db_count(
        $pdo,
        "SELECT COUNT(*) FROM invoice WHERE Status IN ('active','sendedwarn','send_on_hold')"
    );

    return [
        'expired_services' => $expired,
        'junk_orders' => $junk,
        'dead_total' => mirza_optimize_count_invoices($pdo, $dead),
        'old_payments' => $oldPayments,
        'old_unpaid_payments' => $oldUnpaidPay,
        'old_cancel_requests' => $oldCancel,
        'total_invoices' => $totalInvoices,
        'active_invoices' => $activeInvoices,
        'days_expire_reject' => $daysExpireReject,
        'days_unpaid' => $daysUnpaid,
    ];
}

function mirza_optimize_cleanup_payments(PDO $pdo, int $daysExpireReject, ?int $daysUnpaid = null): array
{
    $daysExpireReject = mirza_optimize_sanitize_days($daysExpireReject, 90);
    $deletedExpire = db_query(
        $pdo,
        "DELETE FROM Payment_report WHERE payment_Status IN ('expire','reject') AND time < ?",
        [mirza_optimize_cutoff($daysExpireReject)]
    )->rowCount();

    $deletedUnpaid = 0;
    if ($daysUnpaid !== null) {
        $daysUnpaid = mirza_optimize_sanitize_days($daysUnpaid, 30);
        $deletedUnpaid = db_query(
            $pdo,
            "DELETE FROM Payment_report WHERE payment_Status = 'Unpaid' AND time < ?",
            [mirza_optimize_cutoff($daysUnpaid)]
        )->rowCount();
    }

    return [
        'payments_deleted' => $deletedExpire,
        'unpaid_payments_deleted' => $deletedUnpaid,
        'days_expire_reject' => $daysExpireReject,
        'days_unpaid' => $daysUnpaid,
    ];
}

function mirza_optimize_rotate_log_file(string $path, int $maxBytes = 1048576, int $keepLines = 3000): array
{
    if (!is_file($path) || !is_readable($path)) {
        return ['rotated' => false, 'bytes_before' => 0, 'bytes_after' => 0];
    }
    $size = (int) filesize($path);
    if ($size <= $maxBytes) {
        return ['rotated' => false, 'bytes_before' => $size, 'bytes_after' => $size];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return ['rotated' => false, 'bytes_before' => $size, 'bytes_after' => $size];
    }
    $tail = array_slice($lines, -$keepLines);
    $newContent = implode("\n", $tail) . "\n";
    @file_put_contents($path, $newContent, LOCK_EX);
    $newSize = (int) (@filesize($path) ?: strlen($newContent));
    return ['rotated' => true, 'bytes_before' => $size, 'bytes_after' => $newSize];
}

function mirza_optimize_run(PDO $pdo, string $botRoot, int $daysExpireReject = 90, int $daysUnpaid = 30): array
{
    @ini_set('max_execution_time', '300');
    $daysExpireReject = mirza_optimize_sanitize_days($daysExpireReject, 90);
    $daysUnpaid = mirza_optimize_sanitize_days($daysUnpaid, 30);

    $result = [
        'expired_deleted' => 0,
        'junk_deleted' => 0,
        'payments_deleted' => 0,
        'unpaid_payments_deleted' => 0,
        'cancel_deleted' => 0,
        'days_expire_reject' => $daysExpireReject,
        'days_unpaid' => $daysUnpaid,
        'logs' => [],
        'tables_optimized' => [],
    ];

    $result['expired_deleted'] = mirza_optimize_delete_invoices(
        $pdo,
        ['end_of_time', 'end_of_volume', 'removeTime', 'removevolume']
    );
    $result['junk_deleted'] = mirza_optimize_delete_invoices(
        $pdo,
        ['disabled', 'removebyadmin', 'removedbyadmin', 'removebyuser']
    );
    $result['junk_deleted'] += mirza_optimize_delete_unpaid_orders($pdo);

    $payCleanup = mirza_optimize_cleanup_payments($pdo, $daysExpireReject, $daysUnpaid);
    $result['payments_deleted'] = $payCleanup['payments_deleted'];
    $result['unpaid_payments_deleted'] = $payCleanup['unpaid_payments_deleted'];

    try {
        $result['cancel_deleted'] = db_query(
            $pdo,
            "DELETE FROM cancel_service WHERE status IN ('accept','reject')"
        )->rowCount();
    } catch (Throwable $e) {
        $result['cancel_deleted'] = 0;
    }

    $root = rtrim($botRoot, '/\\');
    foreach (['error_log', 'log.txt'] as $logFile) {
        $path = $root . DIRECTORY_SEPARATOR . $logFile;
        $result['logs'][$logFile] = mirza_optimize_rotate_log_file($path);
    }

    foreach (['invoice', 'Payment_report', 'user'] as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE `$table`");
            $result['tables_optimized'][] = $table;
        } catch (Throwable $e) {
            // non-fatal
        }
    }

    $time = time();
    $summary = sprintf(
        'optimize_panel_%d_exp%d_junk%d_pay%d_%d',
        $time,
        $result['expired_deleted'],
        $result['junk_deleted'],
        $result['payments_deleted'],
        $result['unpaid_payments_deleted']
    );
    @file_put_contents($root . DIRECTORY_SEPARATOR . 'log.txt', "\n" . $summary, FILE_APPEND);

    $result['total_removed'] = $result['expired_deleted'] + $result['junk_deleted']
        + $result['payments_deleted'] + $result['unpaid_payments_deleted'] + $result['cancel_deleted'];

    return $result;
}
