<?php

/**
 * بهینه‌سازی قابل تنظیم — ادمین انتخاب می‌کند چه داده‌هایی حذف شوند.
 * قبل از اجرا: بکاپ SQL به تلگرام (قابل غیرفعال‌سازی).
 */

function vira_optimize_day_options(): array
{
    return [7, 30, 90, 180, 365];
}

function vira_optimize_sanitize_days($value, int $default = 30): int
{
    $days = (int) $value;
    return in_array($days, vira_optimize_day_options(), true) ? $days : $default;
}

function vira_optimize_task_catalog(): array
{
    return [
        'expired_invoices' => [
            'label' => 'سرویس تمام‌شده (پایان زمان/حجم، removeTime، removevolume)',
            'group' => 'invoice',
        ],
        'removed_invoices' => [
            'label' => 'سفارش حذف‌شده / غیرفعال / disabledn',
            'group' => 'invoice',
        ],
        'unpaid_invoices' => [
            'label' => 'سفارش پرداخت‌نشده (unpaid)',
            'group' => 'invoice',
        ],
        'unsuccessful_invoices' => [
            'label' => 'سفارش ناموفق (Unsuccessful)',
            'group' => 'invoice',
        ],
        'failed_payments' => [
            'label' => 'پرداخت منقضی / رد شده (expire, reject)',
            'group' => 'payment',
        ],
        'unpaid_payments' => [
            'label' => 'پرداخت Unpaid (پرداخت‌نشده)',
            'group' => 'payment',
        ],
        'cancel_requests' => [
            'label' => 'درخواست‌های لغو سرویس (تأیید/رد شده)',
            'group' => 'other',
        ],
        'orphan_service_other' => [
            'label' => 'رکوردهای service_other بدون سفارش مرتبط',
            'group' => 'other',
        ],
        'rotate_logs' => [
            'label' => 'کوتاه‌کردن error_log و log.txt',
            'group' => 'system',
        ],
        'optimize_tables' => [
            'label' => 'OPTIMIZE TABLE (invoice, Payment_report, user)',
            'group' => 'system',
        ],
    ];
}

function vira_optimize_default_tasks(): array
{
    $tasks = [];
    foreach (vira_optimize_task_catalog() as $key => $_meta) {
        $tasks[$key] = true;
    }
    $tasks['telegram_backup'] = true;
    return $tasks;
}

/** @param array<string,mixed> $input */
function vira_optimize_parse_options(array $input): array
{
    $catalog = vira_optimize_task_catalog();
    $tasks = [];
    $hasTaskInput = false;
    foreach ($catalog as $key => $_meta) {
        if (!empty($input['task_' . $key]) || (isset($input['tasks'][$key]) && $input['tasks'][$key])) {
            $hasTaskInput = true;
            break;
        }
    }
    if (isset($input['tasks']) && is_array($input['tasks'])) {
        $hasTaskInput = true;
    }

    foreach ($catalog as $key => $_meta) {
        if (!$hasTaskInput) {
            $tasks[$key] = true;
            continue;
        }
        if (isset($input['tasks']) && is_array($input['tasks'])) {
            $tasks[$key] = !empty($input['tasks'][$key]);
        } else {
            $tasks[$key] = !empty($input['task_' . $key]);
        }
    }

    return [
        'tasks' => $tasks,
        'days_expire' => vira_optimize_sanitize_days($input['days_expire'] ?? 90, 90),
        'days_unpaid' => vira_optimize_sanitize_days($input['days_unpaid'] ?? 30, 30),
        'telegram_backup' => !array_key_exists('telegram_backup', $input) || !empty($input['telegram_backup']),
    ];
}

function vira_optimize_cutoff(int $days): string
{
    return date('Y/m/d', strtotime('-' . $days . ' days')) . ' 00:00:00';
}

function vira_optimize_count_invoices(PDO $pdo, array $statuses, ?string $extraWhere = null): int
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

function vira_optimize_delete_invoices(PDO $pdo, array $statuses, ?string $extraWhere = null): int
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

function vira_optimize_preview(PDO $pdo, $daysExpireRejectOrOptions = 90, ?int $daysUnpaid = null): array
{
    if (is_array($daysExpireRejectOrOptions)) {
        $options = vira_optimize_parse_options($daysExpireRejectOrOptions);
    } else {
        $options = vira_optimize_parse_options([
            'days_expire' => $daysExpireRejectOrOptions,
            'days_unpaid' => $daysUnpaid ?? 30,
        ]);
        foreach (array_keys(vira_optimize_task_catalog()) as $key) {
            $options['tasks'][$key] = true;
        }
    }

    $daysExpire = (int) $options['days_expire'];
    $daysUnpaid = (int) $options['days_unpaid'];
    $cutoffPay = vira_optimize_cutoff($daysExpire);
    $cutoffUnpaidPay = vira_optimize_cutoff($daysUnpaid);

    $counts = [
        'expired_invoices' => vira_optimize_count_invoices($pdo, ['end_of_time', 'end_of_volume', 'removeTime', 'removevolume']),
        'removed_invoices' => vira_optimize_count_invoices($pdo, ['disabled', 'removebyadmin', 'removedbyadmin', 'removebyuser', 'disabledn']),
        'unpaid_invoices' => db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'"),
        'unsuccessful_invoices' => vira_optimize_count_invoices($pdo, ['Unsuccessful']),
        'failed_payments' => db_count(
            $pdo,
            "SELECT COUNT(*) FROM Payment_report WHERE payment_Status IN ('expire','reject') AND time < ?",
            [$cutoffPay]
        ),
        'unpaid_payments' => db_count(
            $pdo,
            "SELECT COUNT(*) FROM Payment_report WHERE payment_Status = 'Unpaid' AND time < ?",
            [$cutoffUnpaidPay]
        ),
        'cancel_requests' => 0,
        'orphan_service_other' => 0,
    ];

    try {
        $counts['cancel_requests'] = db_count($pdo, "SELECT COUNT(*) FROM cancel_service WHERE status IN ('accept','reject')");
    } catch (Throwable $e) {
        $counts['cancel_requests'] = 0;
    }
    try {
        $counts['orphan_service_other'] = db_count(
            $pdo,
            "SELECT COUNT(*) FROM service_other so
             WHERE so.username IS NOT NULL AND so.username <> ''
               AND NOT EXISTS (SELECT 1 FROM invoice i WHERE i.username = so.username LIMIT 1)"
        );
    } catch (Throwable $e) {
        $counts['orphan_service_other'] = 0;
    }

    $selectedTotal = 0;
    foreach ($counts as $key => $n) {
        if (!empty($options['tasks'][$key])) {
            $selectedTotal += (int) $n;
        }
    }

    return [
        'counts' => $counts,
        'tasks' => $options['tasks'],
        'days_expire_reject' => $daysExpire,
        'days_unpaid' => $daysUnpaid,
        'selected_total' => $selectedTotal,
        'expired_services' => $counts['expired_invoices'],
        'junk_orders' => $counts['removed_invoices'] + $counts['unpaid_invoices'] + $counts['unsuccessful_invoices'],
        'old_payments' => $counts['failed_payments'],
        'old_unpaid_payments' => $counts['unpaid_payments'],
        'old_cancel_requests' => $counts['cancel_requests'],
        'total_invoices' => db_count($pdo, 'SELECT COUNT(*) FROM invoice'),
        'active_invoices' => db_count(
            $pdo,
            "SELECT COUNT(*) FROM invoice WHERE Status IN ('active','sendedwarn','send_on_hold')"
        ),
    ];
}

function vira_optimize_cleanup_payments(PDO $pdo, int $daysExpireReject, ?int $daysUnpaid = null, bool $failed = true, bool $unpaid = true): array
{
    $daysExpireReject = vira_optimize_sanitize_days($daysExpireReject, 90);
    $deletedExpire = 0;
    $deletedUnpaid = 0;

    if ($failed) {
        $deletedExpire = db_query(
            $pdo,
            "DELETE FROM Payment_report WHERE payment_Status IN ('expire','reject') AND time < ?",
            [vira_optimize_cutoff($daysExpireReject)]
        )->rowCount();
    }
    if ($unpaid && $daysUnpaid !== null) {
        $daysUnpaid = vira_optimize_sanitize_days($daysUnpaid, 30);
        $deletedUnpaid = db_query(
            $pdo,
            "DELETE FROM Payment_report WHERE payment_Status = 'Unpaid' AND time < ?",
            [vira_optimize_cutoff($daysUnpaid)]
        )->rowCount();
    }

    return [
        'payments_deleted' => $deletedExpire,
        'unpaid_payments_deleted' => $deletedUnpaid,
        'days_expire_reject' => $daysExpireReject,
        'days_unpaid' => $daysUnpaid,
    ];
}

function vira_optimize_rotate_log_file(string $path, int $maxBytes = 1048576, int $keepLines = 3000): array
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

/** @param array<string,mixed> $options */
function vira_optimize_run(PDO $pdo, string $botRoot, $daysExpireRejectOrOptions = 90, ?int $daysUnpaid = null): array
{
    @ini_set('max_execution_time', '600');

    if (is_array($daysExpireRejectOrOptions)) {
        $options = vira_optimize_parse_options($daysExpireRejectOrOptions);
    } else {
        $options = vira_optimize_parse_options([
            'days_expire' => $daysExpireRejectOrOptions,
            'days_unpaid' => $daysUnpaid ?? 30,
        ]);
        foreach (array_keys(vira_optimize_task_catalog()) as $key) {
            $options['tasks'][$key] = true;
        }
    }

    $tasks = $options['tasks'];
    $result = [
        'expired_deleted' => 0,
        'removed_deleted' => 0,
        'unpaid_invoices_deleted' => 0,
        'unsuccessful_deleted' => 0,
        'junk_deleted' => 0,
        'payments_deleted' => 0,
        'unpaid_payments_deleted' => 0,
        'cancel_deleted' => 0,
        'orphan_service_other_deleted' => 0,
        'days_expire_reject' => $options['days_expire'],
        'days_unpaid' => $options['days_unpaid'],
        'logs' => [],
        'tables_optimized' => [],
        'telegram_backup' => null,
        'tasks_run' => array_keys(array_filter($tasks)),
    ];

    if (!empty($options['telegram_backup'])) {
        if (!function_exists('vira_backup_send_database_telegram')) {
            require_once __DIR__ . '/backup_full.php';
        }
        $backup = vira_backup_send_database_telegram($pdo, '🗄 بکاپ قبل از بهینه‌سازی پنل');
        $result['telegram_backup'] = $backup;
        if (empty($backup['ok'])) {
            throw new RuntimeException('ارسال بکاپ به تلگرام ناموفق بود: ' . ($backup['msg'] ?? ''));
        }
    }

    if (!empty($tasks['expired_invoices'])) {
        $result['expired_deleted'] = vira_optimize_delete_invoices(
            $pdo,
            ['end_of_time', 'end_of_volume', 'removeTime', 'removevolume']
        );
    }
    if (!empty($tasks['removed_invoices'])) {
        $result['removed_deleted'] = vira_optimize_delete_invoices(
            $pdo,
            ['disabled', 'removebyadmin', 'removedbyadmin', 'removebyuser', 'disabledn']
        );
    }
    if (!empty($tasks['unpaid_invoices'])) {
        $result['unpaid_invoices_deleted'] = db_query(
            $pdo,
            "DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست'"
        )->rowCount();
    }
    if (!empty($tasks['unsuccessful_invoices'])) {
        $result['unsuccessful_deleted'] = vira_optimize_delete_invoices($pdo, ['Unsuccessful']);
    }

    $result['junk_deleted'] = (int) $result['removed_deleted']
        + (int) $result['unpaid_invoices_deleted']
        + (int) $result['unsuccessful_deleted'];

    if (!empty($tasks['failed_payments']) || !empty($tasks['unpaid_payments'])) {
        $payCleanup = vira_optimize_cleanup_payments(
            $pdo,
            (int) $options['days_expire'],
            (int) $options['days_unpaid'],
            !empty($tasks['failed_payments']),
            !empty($tasks['unpaid_payments'])
        );
        $result['payments_deleted'] = $payCleanup['payments_deleted'];
        $result['unpaid_payments_deleted'] = $payCleanup['unpaid_payments_deleted'];
    }

    if (!empty($tasks['cancel_requests'])) {
        try {
            $result['cancel_deleted'] = db_query(
                $pdo,
                "DELETE FROM cancel_service WHERE status IN ('accept','reject')"
            )->rowCount();
        } catch (Throwable $e) {
            $result['cancel_deleted'] = 0;
        }
    }

    if (!empty($tasks['orphan_service_other'])) {
        try {
            $result['orphan_service_other_deleted'] = db_query(
                $pdo,
                "DELETE so FROM service_other so
                 WHERE so.username IS NOT NULL AND so.username <> ''
                   AND NOT EXISTS (SELECT 1 FROM invoice i WHERE i.username = so.username LIMIT 1)"
            )->rowCount();
        } catch (Throwable $e) {
            $result['orphan_service_other_deleted'] = 0;
        }
    }

    if (!empty($tasks['rotate_logs'])) {
        $root = rtrim($botRoot, '/\\');
        foreach (['error_log', 'log.txt'] as $logFile) {
            $path = $root . DIRECTORY_SEPARATOR . $logFile;
            $result['logs'][$logFile] = vira_optimize_rotate_log_file($path);
        }
    }

    if (!empty($tasks['optimize_tables'])) {
        foreach (['invoice', 'Payment_report', 'user'] as $table) {
            try {
                $pdo->exec("OPTIMIZE TABLE `$table`");
                $result['tables_optimized'][] = $table;
            } catch (Throwable $e) {
                // non-fatal
            }
        }
    }

    $time = time();
    $summary = sprintf(
        'optimize_panel_%d_exp%d_rm%d_pay%d_%d',
        $time,
        $result['expired_deleted'],
        $result['junk_deleted'],
        $result['payments_deleted'],
        $result['unpaid_payments_deleted']
    );
    @file_put_contents(rtrim($botRoot, '/\\') . DIRECTORY_SEPARATOR . 'log.txt', "\n" . $summary, FILE_APPEND);

    $result['total_removed'] = (int) $result['expired_deleted']
        + (int) $result['junk_deleted']
        + (int) $result['payments_deleted']
        + (int) $result['unpaid_payments_deleted']
        + (int) $result['cancel_deleted']
        + (int) $result['orphan_service_other_deleted'];

    return $result;
}

function vira_optimize_invoice_remove_statuses(): array
{
    return [
        'end_of_time', 'end_of_volume', 'removeTime', 'removevolume',
        'removebyadmin', 'removedbyadmin', 'removebyuser', 'unpaid', 'disabled', 'disabledn', 'Unsuccessful',
    ];
}
