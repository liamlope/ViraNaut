<?php

/**
 * محافظت کیف پول و خرید: جلوگیری از شارژ/کسر/ساخت سرویس تکراری + تعمیر یک‌باره پس از آپدیت.
 */

if (!function_exists('vira_buy_guard_ensure_schema')) {
    function vira_buy_guard_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        global $pdo;
        if (!isset($pdo)) {
            return;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_ledger (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_user VARCHAR(64) NOT NULL,
                ref_key VARCHAR(191) NOT NULL,
                direction ENUM('credit','debit') NOT NULL,
                amount INT NOT NULL DEFAULT 0,
                source VARCHAR(64) NOT NULL DEFAULT 'system',
                meta_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_wallet_ref (ref_key),
                KEY idx_wallet_user (id_user)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log('[buy-guard] schema: ' . $e->getMessage());
        }
    }
}

if (!function_exists('vira_payment_is_wallet_topup')) {
    function vira_payment_is_wallet_topup(array $payment): bool
    {
        if (($payment['payment_Status'] ?? '') !== 'paid') {
            return false;
        }
        $method = (string) ($payment['Payment_Method'] ?? '');
        if ($method === 'low balance by admin') {
            return false;
        }
        $payload = function_exists('vira_card_invoice_payment_payload')
            ? vira_card_invoice_payment_payload((string) ($payment['id_invoice'] ?? ''))
            : (string) ($payment['id_invoice'] ?? '');
        $payload = trim($payload);
        if ($payload === '' || $payload === 'none') {
            return true;
        }
        $step = explode('|', $payload)[0] ?? '';
        $direct = ['getconfigafterpay', 'getextenduser', 'getextravolumeuser', 'getextratimeuser', 'bulkbuy'];
        return !in_array($step, $direct, true);
    }
}

if (!function_exists('vira_payment_direct_service_username')) {
    function vira_payment_direct_service_username(array $payment): ?string
    {
        $payload = function_exists('vira_card_invoice_payment_payload')
            ? vira_card_invoice_payment_payload((string) ($payment['id_invoice'] ?? ''))
            : (string) ($payment['id_invoice'] ?? '');
        $parts = explode('|', $payload, 2);
        if (($parts[0] ?? '') !== 'getconfigafterpay') {
            return null;
        }
        $username = trim((string) ($parts[1] ?? ''));
        return $username !== '' ? $username : null;
    }
}

if (!function_exists('vira_wallet_credit_user')) {
    /** شارژ کیف پول — فقط یک‌بار برای هر ref_key */
    function vira_wallet_credit_user(string $userId, string $refKey, int $amount, string $source = 'payment'): bool
    {
        global $pdo;
        vira_buy_guard_ensure_schema();
        $userId = trim($userId);
        $amount = (int) $amount;
        if ($userId === '' || $amount <= 0) {
            return true;
        }
        $fullRef = 'credit:' . preg_replace('/[^a-zA-Z0-9:_-]/', '', $refKey);
        try {
            $ins = $pdo->prepare(
                'INSERT INTO wallet_ledger (id_user, ref_key, direction, amount, source)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$userId, $fullRef, 'credit', $amount, $source]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }
        $stmt = $pdo->prepare('UPDATE user SET Balance = Balance + ? WHERE id = ?');
        $stmt->execute([$amount, $userId]);
        if (function_exists('clearSelectCache')) {
            clearSelectCache('user');
        }
        return true;
    }
}

if (!function_exists('vira_wallet_debit_user')) {
    /** کسر کیف پول — اتمیک؛ ref تکراری = قبلاً کسر شده */
    function vira_wallet_debit_user(string $userId, string $refKey, int $amount, string $source = 'buy'): bool
    {
        global $pdo;
        vira_buy_guard_ensure_schema();
        $userId = trim($userId);
        $amount = (int) $amount;
        if ($userId === '' || $amount <= 0) {
            return true;
        }
        $fullRef = 'debit:' . preg_replace('/[^a-zA-Z0-9:_-]/', '', $refKey);
        $chk = $pdo->prepare('SELECT 1 FROM wallet_ledger WHERE ref_key = ? LIMIT 1');
        $chk->execute([$fullRef]);
        if ($chk->fetchColumn()) {
            return true;
        }
        $stmt = $pdo->prepare('UPDATE user SET Balance = Balance - ? WHERE id = ? AND Balance >= ?');
        $stmt->execute([$amount, $userId, $amount]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        try {
            $ins = $pdo->prepare(
                'INSERT INTO wallet_ledger (id_user, ref_key, direction, amount, source)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$userId, $fullRef, 'debit', $amount, $source]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return true;
            }
            $refund = $pdo->prepare('UPDATE user SET Balance = Balance + ? WHERE id = ?');
            $refund->execute([$amount, $userId]);
            throw $e;
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('user');
        }
        return true;
    }
}

if (!function_exists('vira_wallet_refund_debit')) {
    function vira_wallet_refund_debit(string $userId, string $refKey, int $amount, string $source = 'refund'): bool
    {
        global $pdo;
        vira_buy_guard_ensure_schema();
        $userId = trim($userId);
        $amount = (int) $amount;
        if ($userId === '' || $amount <= 0) {
            return false;
        }
        $debitRef = 'debit:' . preg_replace('/[^a-zA-Z0-9:_-]/', '', $refKey);
        $chk = $pdo->prepare('SELECT 1 FROM wallet_ledger WHERE ref_key = ? LIMIT 1');
        $chk->execute([$debitRef]);
        if (!$chk->fetchColumn()) {
            return false;
        }
        $refundRef = 'refund:' . preg_replace('/[^a-zA-Z0-9:_-]/', '', $refKey);
        return vira_wallet_credit_user($userId, $refundRef, $amount, $source);
    }
}

if (!function_exists('vira_buy_guard_remove_orphan_invoice')) {
    function vira_buy_guard_remove_orphan_invoice(array $invoice): bool
    {
        global $ManagePanel;
        if (!function_exists('vira_ensure_manage_panel')) {
            return false;
        }
        vira_ensure_manage_panel();
        $panel = (string) ($invoice['Service_location'] ?? '');
        $username = (string) ($invoice['username'] ?? '');
        $idInvoice = (string) ($invoice['id_invoice'] ?? '');
        if ($panel === '' || $username === '' || $idInvoice === '') {
            return false;
        }
        try {
            $ManagePanel->RemoveUser($panel, $username);
        } catch (Throwable $e) {
            error_log('[buy-guard] RemoveUser failed invoice=' . $idInvoice . ' err=' . $e->getMessage());
        }
        update('invoice', 'Status', 'removed_duplicate_auto', 'id_invoice', $idInvoice);
        error_log('[buy-guard] removed duplicate invoice=' . $idInvoice . ' user=' . ($invoice['id_user'] ?? '') . ' cfg=' . $username);
        return true;
    }
}

if (!function_exists('vira_repair_wallet_duplicate_services')) {
    /**
     * شبیه‌سازی کیف پول هر کاربر: فقط سرویس‌هایی می‌مانند که واقعاً پوشش داده شده‌اند.
     */
    function vira_repair_wallet_duplicate_services(): array
    {
        global $pdo;
        vira_buy_guard_ensure_schema();
        $stats = ['users' => 0, 'removed' => 0, 'kept' => 0];

        $userRows = $pdo->query(
            "SELECT DISTINCT id_user FROM invoice
             WHERE Status = 'active' AND name_product != 'سرویس تست'"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($userRows)) {
            return $stats;
        }

        foreach ($userRows as $userId) {
            $userId = (string) $userId;
            if ($userId === '') {
                continue;
            }

            $payStmt = $pdo->prepare(
                "SELECT * FROM Payment_report
                 WHERE id_user = ? AND payment_Status = 'paid'
                 ORDER BY time ASC"
            );
            $payStmt->execute([$userId]);
            $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

            $invStmt = $pdo->prepare(
                "SELECT * FROM invoice
                 WHERE id_user = ? AND Status = 'active' AND name_product != 'سرویس تست'
                 ORDER BY time_sell ASC, id_invoice ASC"
            );
            $invStmt->execute([$userId]);
            $invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($invoices) < 2) {
                continue;
            }

            $events = [];
            foreach ($payments as $payment) {
                $ts = function_exists('vira_parse_payment_report_time')
                    ? vira_parse_payment_report_time($payment['time'] ?? null)
                    : strtotime((string) ($payment['time'] ?? ''));
                if ($ts === null) {
                    $ts = 0;
                }
                if (vira_payment_is_wallet_topup($payment)) {
                    $events[] = ['ts' => $ts, 'type' => 'credit', 'amount' => (int) ($payment['price'] ?? 0)];
                } else {
                    $directUser = vira_payment_direct_service_username($payment);
                    if ($directUser !== null) {
                        $events[] = ['ts' => $ts, 'type' => 'direct', 'username' => $directUser];
                    }
                }
            }
            foreach ($invoices as $invoice) {
                $ts = (int) ($invoice['time_sell'] ?? 0);
                $events[] = ['ts' => $ts, 'type' => 'invoice', 'invoice' => $invoice];
            }

            usort($events, static function ($a, $b) {
                return ($a['ts'] <=> $b['ts']) ?: 0;
            });

            $pool = 0;
            $directSlots = [];
            foreach ($payments as $payment) {
                $u = vira_payment_direct_service_username($payment);
                if ($u !== null) {
                    $directSlots[$u] = ($directSlots[$u] ?? 0) + 1;
                }
            }

            $userHadDup = false;
            foreach ($events as $event) {
                if ($event['type'] === 'credit') {
                    $pool += (int) $event['amount'];
                    continue;
                }
                if ($event['type'] !== 'invoice') {
                    continue;
                }
                $invoice = $event['invoice'];
                $cfg = (string) ($invoice['username'] ?? '');
                $price = (int) ($invoice['price_product'] ?? 0);

                if ($cfg !== '' && !empty($directSlots[$cfg])) {
                    $directSlots[$cfg]--;
                    $stats['kept']++;
                    continue;
                }

                if ($price <= 0 || $pool >= $price) {
                    if ($price > 0) {
                        $pool -= $price;
                    }
                    $stats['kept']++;
                    continue;
                }

                $userHadDup = true;
                if (vira_buy_guard_remove_orphan_invoice($invoice)) {
                    $stats['removed']++;
                }
            }

            if ($userHadDup) {
                $stats['users']++;
            }
        }

        return $stats;
    }
}

if (!function_exists('vira_ensure_buy_guard')) {
    function vira_ensure_buy_guard(): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        vira_buy_guard_ensure_schema();

        if (!function_exists('getPaySettingValue') || !function_exists('vira_set_pay_setting_value')) {
            return;
        }
        if (getPaySettingValue('wallet_dup_repair_v1_done', '0') === '1') {
            return;
        }

        $stats = vira_repair_wallet_duplicate_services();
        vira_set_pay_setting_value('wallet_dup_repair_v1_done', '1');
        error_log('[buy-guard] repair done users=' . ($stats['users'] ?? 0) . ' removed=' . ($stats['removed'] ?? 0) . ' kept=' . ($stats['kept'] ?? 0));
    }
}
