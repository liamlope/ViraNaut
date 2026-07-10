<?php

/**
 * بازیابی سرویس‌های حذف‌شده از پنل VPN با حفظ لینک اشتراک و حجم/زمان باقی‌مانده.
 */

if (!function_exists('vira_ensure_invoice_panel_snapshot_columns')) {
    function vira_ensure_invoice_panel_snapshot_columns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        global $pdo;
        if (!isset($pdo)) {
            return;
        }
        $done = true;
        $columns = [
            'panel_expire' => 'VARCHAR(32) NULL',
            'panel_data_limit' => 'VARCHAR(32) NULL',
            'panel_used_traffic' => 'VARCHAR(32) NULL',
            'panel_sub_id' => 'VARCHAR(128) NULL',
            'panel_snap_time' => 'VARCHAR(32) NULL',
        ];
        foreach ($columns as $name => $ddl) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'invoice\' AND COLUMN_NAME = ?'
                );
                $stmt->execute([$name]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE invoice ADD {$name} {$ddl}");
                }
            } catch (Throwable $e) {
                error_log('vira_ensure_invoice_panel_snapshot_columns: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('vira_extract_sub_id_from_subscription_url')) {
    function vira_extract_sub_id_from_subscription_url(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#/sub/([A-Za-z0-9_-]+)/?$#', $url, $m)) {
            return $m[1];
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== ''));
        if ($parts === []) {
            return null;
        }
        $last = (string) end($parts);
        return preg_match('/^[A-Za-z0-9_-]{4,128}$/', $last) ? $last : null;
    }
}

if (!function_exists('vira_invoice_subscription_url')) {
    /** لینک اشتراک از پنل زنده یا user_info ذخیره‌شده */
    function vira_invoice_subscription_url(array $invoice, ?array $panelData = null): string
    {
        if (is_array($panelData)) {
            $live = trim((string) ($panelData['subscription_url'] ?? ''));
            if ($live !== '') {
                return $live;
            }
        }
        $stored = trim((string) ($invoice['user_info'] ?? ''));
        if ($stored !== '' && preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        if ($stored !== '' && strpos($stored, '{') === 0) {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                foreach (['subscription_url', 'file', 'subUrl'] as $key) {
                    $u = trim((string) ($decoded[$key] ?? ''));
                    if ($u !== '') {
                        return $u;
                    }
                }
            }
        }
        $subId = trim((string) ($invoice['panel_sub_id'] ?? ''));
        if ($subId !== '') {
            $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'] ?? '', 'select');
            if (is_array($panel) && trim((string) ($panel['linksubx'] ?? '')) !== '') {
                return rtrim((string) $panel['linksubx'], '/') . '/' . $subId;
            }
        }
        return '';
    }
}

if (!function_exists('vira_invoice_update_panel_snapshot')) {
    function vira_invoice_update_panel_snapshot(string $idInvoice, array $userData): void
    {
        global $pdo;
        vira_ensure_invoice_panel_snapshot_columns();
        if (!isset($pdo) || $idInvoice === '') {
            return;
        }
        $expire = isset($userData['expire']) ? (int) $userData['expire'] : 0;
        $limit = isset($userData['data_limit']) ? (string) (int) $userData['data_limit'] : '0';
        $used = isset($userData['used_traffic']) ? (string) (int) $userData['used_traffic'] : '0';
        $subUrl = trim((string) ($userData['subscription_url'] ?? ''));
        $subId = vira_extract_sub_id_from_subscription_url($subUrl) ?? '';
        try {
            $stmt = $pdo->prepare(
                'UPDATE invoice SET panel_expire = ?, panel_data_limit = ?, panel_used_traffic = ?,
                 panel_sub_id = CASE WHEN ? <> \'\' THEN ? ELSE panel_sub_id END,
                 panel_snap_time = ?
                 WHERE id_invoice = ?'
            );
            $stmt->execute([
                (string) $expire,
                $limit,
                $used,
                $subId,
                $subId,
                (string) time(),
                $idInvoice,
            ]);
            if ($subUrl !== '') {
                update('invoice', 'user_info', $subUrl, 'id_invoice', $idInvoice);
            }
        } catch (Throwable $e) {
            error_log('vira_invoice_update_panel_snapshot: ' . $e->getMessage());
        }
    }
}

if (!function_exists('vira_invoice_repairable_statuses')) {
    function vira_invoice_repairable_statuses(): array
    {
        return ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabledn', 'disabled'];
    }
}

if (!function_exists('vira_invoice_compute_repair_limits')) {
    /**
     * @return array{expire:int,data_limit_bytes:int,sub_id:?string,client_uuid:?string}|null
     */
    function vira_invoice_compute_repair_limits(array $invoice): ?array
    {
        global $pdo;
        $username = trim((string) ($invoice['username'] ?? ''));
        if ($username === '') {
            return null;
        }

        $expire = (int) ($invoice['panel_expire'] ?? 0);
        $totalLimit = (int) ($invoice['panel_data_limit'] ?? 0);
        $usedTraffic = (int) ($invoice['panel_used_traffic'] ?? 0);

        if ($expire <= 0) {
            $sellTs = is_numeric($invoice['time_sell'] ?? null)
                ? (int) $invoice['time_sell']
                : strtotime((string) ($invoice['time_sell'] ?? ''));
            $days = (int) ($invoice['Service_time'] ?? 0);
            if ($sellTs > 0 && $days > 0) {
                $expire = $sellTs + ($days * 86400);
            }
        }

        if ($totalLimit <= 0) {
            $gb = (int) ($invoice['Volume'] ?? 0);
            if ($gb > 0) {
                $totalLimit = $gb * (int) pow(1024, 3);
            }
        }

        if (isset($pdo) && $username !== '') {
            try {
                $rows = $pdo->prepare(
                    "SELECT type, value FROM service_other WHERE username = ? AND (status = 'paid' OR status IS NULL)"
                );
                $rows->execute([$username]);
                foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $type = (string) ($row['type'] ?? '');
                    $val = $row['value'] ?? '';
                    $json = is_string($val) ? json_decode($val, true) : null;
                    if (!is_array($json)) {
                        continue;
                    }
                    if (in_array($type, ['extend_user', 'extend_user_by_admin', 'extends_not_user', 'extra_time_user', 'gift_time'], true)) {
                        $extraDays = (int) ($json['Service_time'] ?? $json['day'] ?? $json['days'] ?? 0);
                        if ($extraDays > 0) {
                            $expire += $extraDays * 86400;
                        }
                    }
                    if (in_array($type, ['extra_user', 'gift_volume'], true)) {
                        $extraGb = (int) ($json['Volume_constraint'] ?? $json['volume'] ?? $json['gb'] ?? 0);
                        if ($extraGb > 0) {
                            $totalLimit += $extraGb * (int) pow(1024, 3);
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('vira_invoice_compute_repair_limits service_other: ' . $e->getMessage());
            }
        }

        if ($expire > 0 && $expire < time()) {
            return null;
        }

        $remaining = $totalLimit > 0 ? max(0, $totalLimit - max(0, $usedTraffic)) : 0;
        $dataLimitBytes = $remaining > 0 ? $remaining : $totalLimit;
        if ($dataLimitBytes <= 0 && $expire <= time()) {
            return null;
        }
        if ($dataLimitBytes <= 0 && $expire <= 0) {
            return null;
        }

        $subId = trim((string) ($invoice['panel_sub_id'] ?? ''));
        if ($subId === '') {
            $subId = vira_extract_sub_id_from_subscription_url(vira_invoice_subscription_url($invoice)) ?? '';
        }

        $clientUuid = null;
        $uuidRaw = trim((string) ($invoice['uuid'] ?? ''));
        if ($uuidRaw !== '' && $uuidRaw[0] === '{') {
            $proxies = json_decode($uuidRaw, true);
            if (is_array($proxies)) {
                foreach ($proxies as $p) {
                    if (is_string($p) && $p !== '') {
                        $clientUuid = $p;
                        break;
                    }
                }
            }
        } elseif (preg_match('/^[0-9a-f-]{36}$/i', $uuidRaw)) {
            $clientUuid = $uuidRaw;
        }

        return [
            'expire' => $expire > 0 ? $expire : (time() + 86400),
            'data_limit_bytes' => $dataLimitBytes > 0 ? $dataLimitBytes : (10 * (int) pow(1024, 3)),
            'sub_id' => $subId !== '' ? $subId : null,
            'client_uuid' => $clientUuid,
        ];
    }
}

if (!function_exists('vira_repair_invoice_on_panel')) {
  /**
   * @return array{ok:bool,msg:string}
   */
    function vira_repair_invoice_on_panel(array $invoice, ?ManagePanel $panel = null): array
    {
        $username = trim((string) ($invoice['username'] ?? ''));
        $location = trim((string) ($invoice['Service_location'] ?? ''));
        $idInvoice = trim((string) ($invoice['id_invoice'] ?? ''));
        if ($username === '' || $location === '' || $idInvoice === '') {
            return ['ok' => false, 'msg' => 'اطلاعات سفارش ناقص است.'];
        }

        $status = (string) ($invoice['Status'] ?? '');
        if (!in_array($status, vira_invoice_repairable_statuses(), true)) {
            return ['ok' => false, 'msg' => 'وضعیت این سفارش برای بازیابی مناسب نیست.'];
        }

        if (!function_exists('vira_ensure_manage_panel')) {
            require_once dirname(__DIR__) . '/function.php';
        }
        $panel = $panel ?? vira_ensure_manage_panel();

        $live = $panel->DataUser($location, $username);
        if (is_array($live) && ($live['status'] ?? '') !== 'Unsuccessful') {
            vira_invoice_update_panel_snapshot($idInvoice, $live);
            return ['ok' => true, 'msg' => 'سرویس در پنل موجود است؛ نیازی به بازیابی نبود.'];
        }

        $limits = vira_invoice_compute_repair_limits($invoice);
        if ($limits === null) {
            return ['ok' => false, 'msg' => 'زمان یا حجم سرویس برای بازیابی کافی نیست (احتمالاً منقضی شده).'];
        }

        $panelRow = select('marzban_panel', '*', 'name_panel', $location, 'select');
        if (!is_array($panelRow)) {
            return ['ok' => false, 'msg' => 'پنل سرویس یافت نشد.'];
        }
        if (($panelRow['status'] ?? '') === 'disabled') {
            return ['ok' => false, 'msg' => 'پنل غیرفعال است.'];
        }

        $codeProduct = trim((string) ($invoice['code_product'] ?? ''));
        if ($codeProduct === '') {
            $prod = select('product', 'code_product', 'name_product', $invoice['name_product'] ?? '', 'select');
            $codeProduct = is_array($prod) ? (string) ($prod['code_product'] ?? '') : '';
        }
        if ($codeProduct === '') {
            $codeProduct = 'customvolume';
        }

        $dataConfig = [
            'expire' => $limits['expire'],
            'data_limit' => $limits['data_limit_bytes'],
            'from_id' => (string) ($invoice['id_user'] ?? '0'),
            'username' => $username,
            'type' => 'repair',
            'repair' => true,
        ];
        if ($limits['sub_id'] !== null) {
            $dataConfig['sub_id'] = $limits['sub_id'];
        }
        if ($limits['client_uuid'] !== null) {
            $dataConfig['client_uuid'] = $limits['client_uuid'];
        }

        $created = $panel->createUser($location, $codeProduct, $username, $dataConfig);
        if (!is_array($created) || ($created['status'] ?? '') !== 'successful') {
            $err = (string) ($created['msg'] ?? 'خطای نامشخص پنل');
            return ['ok' => false, 'msg' => 'بازیابی در پنل ناموفق: ' . $err];
        }

        $subUrl = trim((string) ($created['subscription_url'] ?? ''));
        if ($subUrl !== '') {
            update('invoice', 'user_info', $subUrl, 'id_invoice', $idInvoice);
            $subId = vira_extract_sub_id_from_subscription_url($subUrl);
            if ($subId !== null && $subId !== '') {
                update('invoice', 'panel_sub_id', $subId, 'id_invoice', $idInvoice);
            }
        }
        update('invoice', 'Status', 'active', 'id_invoice', $idInvoice);

        $after = $panel->DataUser($location, $username);
        if (is_array($after) && ($after['status'] ?? '') !== 'Unsuccessful') {
            vira_invoice_update_panel_snapshot($idInvoice, $after);
        }

        error_log('[panel-repair] restored invoice=' . $idInvoice . ' user=' . $username . ' panel=' . $location);
        return ['ok' => true, 'msg' => 'سرویس در پنل با همان مشخصات قبلی بازیابی شد.'];
    }
}

if (!function_exists('vira_repair_missing_panel_services')) {
    /**
     * @return array{checked:int,repaired:int,skipped:int,errors:int}
     */
    function vira_repair_missing_panel_services(?int $limit = null): array
    {
        global $pdo;
        vira_ensure_invoice_panel_snapshot_columns();
        $stats = ['checked' => 0, 'repaired' => 0, 'skipped' => 0, 'errors' => 0];
        if (!isset($pdo)) {
            return $stats;
        }

        $statuses = vira_invoice_repairable_statuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $testName = 'سرویس تست';
        $sql = "SELECT * FROM invoice
                 WHERE Status IN ($placeholders)
                   AND name_product != ?
                   AND username IS NOT NULL AND username != ''
                   AND Service_location IS NOT NULL AND Service_location != ''
                 ORDER BY time_sell DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) max(1, min(50, $limit));
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($statuses, [$testName]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('vira_repair_missing_panel_services: ' . $e->getMessage());
            return $stats;
        }

        if (!function_exists('vira_ensure_manage_panel')) {
            require_once dirname(__DIR__) . '/function.php';
        }
        $panel = vira_ensure_manage_panel();

        foreach ($rows as $invoice) {
            $stats['checked']++;
            $username = (string) ($invoice['username'] ?? '');
            $location = (string) ($invoice['Service_location'] ?? '');
            $live = $panel->DataUser($location, $username);
            if (is_array($live) && ($live['status'] ?? '') !== 'Unsuccessful') {
                vira_invoice_update_panel_snapshot((string) $invoice['id_invoice'], $live);
                $stats['skipped']++;
                continue;
            }
            $result = vira_repair_invoice_on_panel($invoice, $panel);
            if ($result['ok']) {
                $stats['repaired']++;
            } else {
                $stats['errors']++;
            }
            usleep(200000);
        }

        return $stats;
    }
}

if (!function_exists('vira_repair_all_missing_panel_services')) {
    /** بازیابی دسته‌ای همه سرویس‌های گم‌شده — فقط از پنل ادمین */
    function vira_repair_all_missing_panel_services(): array
    {
        @ini_set('max_execution_time', '600');
        $stats = vira_repair_missing_panel_services(null);
        if (($stats['repaired'] ?? 0) > 0) {
            error_log('[panel-repair] admin batch ' . json_encode($stats, JSON_UNESCAPED_UNICODE));
        }
        return $stats;
    }
}
