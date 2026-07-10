<?php

/**
 * بازیابی سرویس حذف‌شده از پنل VPN — فقط با فیلدهای موجود invoice.
 * بدون snapshot یا ستون اضافه؛ لینک در user_info همان زمان خرید ذخیره می‌شود.
 */

if (!function_exists('vira_ensure_invoice_panel_sync_at_column')) {
    function vira_ensure_invoice_panel_sync_at_column(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (function_exists('addFieldToTable')) {
            addFieldToTable('invoice', 'panel_sync_at', null, 'VARCHAR(100) NULL');
        }
    }
}

if (!function_exists('vira_invoice_repairable_statuses')) {
    function vira_invoice_repairable_statuses(): array
    {
        return ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabledn', 'disabled'];
    }
}

if (!function_exists('vira_extract_client_uuid_from_invoice')) {
    function vira_extract_client_uuid_from_invoice(array $invoice): ?string
    {
        $uuidRaw = trim((string) ($invoice['uuid'] ?? ''));
        if ($uuidRaw !== '' && $uuidRaw[0] === '{') {
            $proxies = json_decode($uuidRaw, true);
            if (is_array($proxies)) {
                foreach ($proxies as $p) {
                    if (is_string($p) && preg_match('/^[0-9a-f-]{36}$/i', $p)) {
                        return $p;
                    }
                }
            }
        } elseif (preg_match('/^[0-9a-f-]{36}$/i', $uuidRaw)) {
            return $uuidRaw;
        }
        return null;
    }
}

if (!function_exists('vira_invoice_stored_subscription_url')) {
    function vira_invoice_stored_subscription_url(array $invoice): string
    {
        $stored = trim((string) ($invoice['user_info'] ?? ''));
        if ($stored !== '' && preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        if ($stored !== '' && strpos($stored, '{') === 0) {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                foreach (['subscription_url', 'file', 'subUrl'] as $key) {
                    $u = trim((string) ($decoded[$key] ?? ''));
                    if ($u !== '' && preg_match('#^https?://#i', $u)) {
                        return $u;
                    }
                }
            }
        }
        return '';
    }
}

if (!function_exists('vira_invoice_subscription_url')) {
    function vira_invoice_subscription_url(array $invoice, ?array $panelData = null): string
    {
        if (is_array($panelData)) {
            $live = trim((string) ($panelData['subscription_url'] ?? ''));
            if ($live !== '') {
                return $live;
            }
        }
        return vira_invoice_stored_subscription_url($invoice);
    }
}

if (!function_exists('vira_invoice_is_subvip_proxy')) {
    function vira_invoice_is_subvip_proxy(array $invoice, ?array $panelRow = null): bool
    {
        if (is_array($panelRow) && ($panelRow['subvip'] ?? '') === 'onsubvip') {
            return true;
        }
        $idInvoice = trim((string) ($invoice['id_invoice'] ?? ''));
        $url = vira_invoice_stored_subscription_url($invoice);
        return $idInvoice !== '' && $url !== '' && stripos($url, '/sub/' . $idInvoice) !== false;
    }
}

if (!function_exists('vira_subscription_suffix_from_url')) {
    function vira_subscription_suffix_from_url(?string $subUrl, ?string $linksubx): string
    {
        $subUrl = trim((string) $subUrl);
        $linksubx = rtrim(trim((string) $linksubx), '/');
        if ($subUrl === '') {
            return '';
        }
        if ($linksubx !== '' && stripos($subUrl, $linksubx) === 0) {
            return ltrim(substr($subUrl, strlen($linksubx)), '/');
        }
        $subPath = (string) (parse_url($subUrl, PHP_URL_PATH) ?? '');
        $basePath = (string) (parse_url($linksubx, PHP_URL_PATH) ?? '');
        if ($basePath !== '' && $subPath !== '' && strncmp($subPath, $basePath, strlen($basePath)) === 0) {
            return ltrim(substr($subPath, strlen($basePath)), '/');
        }
        return '';
    }
}

if (!function_exists('vira_invoice_sub_id_from_stored_url')) {
    function vira_invoice_sub_id_from_stored_url(array $invoice, ?array $panelRow = null): string
    {
        $url = vira_invoice_stored_subscription_url($invoice);
        if ($url === '') {
            return '';
        }
        $linksubx = is_array($panelRow) ? trim((string) ($panelRow['linksubx'] ?? '')) : '';
        $suffix = vira_subscription_suffix_from_url($url, $linksubx);
        if ($suffix !== '' && preg_match('/^[A-Za-z0-9_\\/.-]{2,256}$/', $suffix)) {
            return $suffix;
        }
        if (preg_match('#/sub/([A-Za-z0-9_-]+)/?$#', $url, $m)) {
            return $m[1];
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }
        $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== ''));
        if ($parts === []) {
            return '';
        }
        $last = (string) end($parts);
        return preg_match('/^[A-Za-z0-9_-]{4,128}$/', $last) ? $last : '';
    }
}

if (!function_exists('vira_invoice_sell_timestamp')) {
    function vira_invoice_sell_timestamp(array $invoice): int
    {
        $raw = $invoice['time_sell'] ?? '';
        if (is_numeric($raw) && (int) $raw > 100000) {
            return (int) $raw;
        }
        $ts = strtotime((string) $raw);
        return $ts > 0 ? $ts : 0;
    }
}

if (!function_exists('vira_invoice_apply_service_other')) {
    function vira_invoice_apply_service_other(string $username, int &$expire, int &$volumeBytes): void
    {
        global $pdo;
        $username = trim($username);
        if ($username === '' || !isset($pdo)) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT type, value FROM service_other WHERE username = ? AND (status = 'paid' OR status IS NULL)"
            );
            $stmt->execute([$username]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $type = (string) ($row['type'] ?? '');
                $val = $row['value'] ?? '';
                $json = is_string($val) ? json_decode($val, true) : null;
                if (!is_array($json)) {
                    continue;
                }
                if (in_array($type, ['extend_user', 'extend_user_by_admin', 'extends_not_user', 'extra_time_user', 'gift_time'], true)) {
                    $extraDays = (int) ($json['Service_time'] ?? $json['day'] ?? $json['days'] ?? 0);
                    if ($extraDays > 0) {
                        $expire = ($expire > 0 ? $expire : time()) + $extraDays * 86400;
                    }
                }
                if (in_array($type, ['extra_user', 'gift_volume'], true)) {
                    $extraGb = (int) ($json['Volume_constraint'] ?? $json['volume'] ?? $json['gb'] ?? 0);
                    if ($extraGb > 0) {
                        $volumeBytes += $extraGb * (int) pow(1024, 3);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('vira_invoice_apply_service_other: ' . $e->getMessage());
        }
    }
}

if (!function_exists('vira_invoice_sync_live_from_panel')) {
    /**
     * وقتی سرویس در پنل هست: حجم باقی‌مانده را در همان فیلد Volume ذخیره می‌کند.
     * بدون ستون جدید — فقط برای بازیابی درست بعد از حذف تصادفی از پنل.
     */
    function vira_invoice_sync_live_from_panel(string $idInvoice, array $userData): void
    {
        $idInvoice = trim($idInvoice);
        if ($idInvoice === '') {
            return;
        }
        if (function_exists('vira_ensure_invoice_panel_sync_at_column')) {
            vira_ensure_invoice_panel_sync_at_column();
        }
        $limit = (int) ($userData['data_limit'] ?? 0);
        $used = (int) ($userData['used_traffic'] ?? 0);
        if ($limit > 0) {
            $remainingBytes = max(0, $limit - $used);
            $remainingGb = max(1, (int) ceil($remainingBytes / (int) pow(1024, 3)));
            update('invoice', 'Volume', (string) $remainingGb, 'id_invoice', $idInvoice);
        }
        $panelExpire = (int) ($userData['expire'] ?? 0);
        if ($panelExpire > 0) {
            $inv = select('invoice', 'time_sell', 'id_invoice', $idInvoice, 'select');
            if (is_array($inv)) {
                $sellTs = vira_invoice_sell_timestamp($inv);
                if ($sellTs > 0 && $panelExpire > $sellTs) {
                    $totalDays = (int) ceil(($panelExpire - $sellTs) / 86400);
                    if ($totalDays > 0) {
                        update('invoice', 'Service_time', (string) $totalDays, 'id_invoice', $idInvoice);
                    }
                }
            }
        }
        update('invoice', 'panel_sync_at', (string) time(), 'id_invoice', $idInvoice);
    }
}

if (!function_exists('vira_invoice_refresh_one_from_panel')) {
    /**
     * فقط یک سفارش — برای مشاهده وضعیت در ربات یا پنل ادمین.
     * یک درخواست DataUser (+ در صورت نیاز repair همان سفارش). cron ساعتی را صدا نمی‌زند.
     *
     * @return array{ok:bool,data?:array,error?:string,repaired?:bool}
     */
    function vira_invoice_refresh_one_from_panel(array $invoice, ?ManagePanel $panel = null, bool $autoRepair = true): array
    {
        $location = trim((string) ($invoice['Service_location'] ?? ''));
        $username = trim((string) ($invoice['username'] ?? ''));
        $idInvoice = trim((string) ($invoice['id_invoice'] ?? ''));
        if ($location === '' || $username === '' || $idInvoice === '') {
            return ['ok' => false, 'error' => 'missing_username'];
        }
        if (!function_exists('vira_ensure_manage_panel')) {
            require_once dirname(__DIR__) . '/function.php';
        }
        $panel = $panel ?? vira_ensure_manage_panel();
        $repaired = false;

        $live = $panel->DataUser($location, $username);
        if ($autoRepair && vira_invoice_panel_user_missing($live)) {
            $repair = vira_repair_invoice_on_panel($invoice, $panel);
            if (!empty($repair['repaired'])) {
                $repaired = true;
                $live = $panel->DataUser($location, $username);
            } elseif ($repair['ok'] ?? false) {
                $live = $panel->DataUser($location, $username);
            }
        }

        if (vira_invoice_panel_user_missing($live)) {
            return ['ok' => false, 'error' => 'user_not_in_panel', 'data' => is_array($live) ? $live : [], 'repaired' => $repaired];
        }

        vira_invoice_sync_live_from_panel($idInvoice, $live);
        return ['ok' => true, 'data' => $live, 'repaired' => $repaired];
    }
}

if (!function_exists('vira_invoice_compute_repair_limits')) {
    /**
     * حجم و زمان از همان فیلدهای سفارش (Volume، Service_time، time_sell) + تمدیدها.
     *
     * @return array{expire:int,data_limit_bytes:int,sub_id:?string,client_uuid:?string}|null
     */
    function vira_invoice_compute_repair_limits(array $invoice, ?array $panelRow = null): ?array
    {
        $username = trim((string) ($invoice['username'] ?? ''));
        if ($username === '') {
            return null;
        }

        $sellTs = vira_invoice_sell_timestamp($invoice);
        $days = (int) ($invoice['Service_time'] ?? 0);
        $expire = ($days > 0 && $sellTs > 0) ? $sellTs + ($days * 86400) : 0;

        $volumeGb = (int) ($invoice['Volume'] ?? 0);
        $dataLimitBytes = $volumeGb > 0 ? $volumeGb * (int) pow(1024, 3) : 0;

        vira_invoice_apply_service_other($username, $expire, $dataLimitBytes);

        if ($expire > 0 && $expire < time()) {
            return null;
        }
        if ($dataLimitBytes <= 0 && $expire <= 0) {
            return null;
        }

        $subId = null;
        if (!vira_invoice_is_subvip_proxy($invoice, $panelRow)) {
            $parsed = vira_invoice_sub_id_from_stored_url($invoice, $panelRow);
            if ($parsed !== '') {
                $subId = $parsed;
            }
        }

        return [
            'expire' => $expire,
            'data_limit_bytes' => $dataLimitBytes,
            'sub_id' => $subId,
            'client_uuid' => vira_extract_client_uuid_from_invoice($invoice),
        ];
    }
}

if (!function_exists('vira_invoice_panel_user_missing')) {
    function vira_invoice_panel_user_missing(?array $live): bool
    {
        if (!is_array($live)) {
            return true;
        }
        if (($live['status'] ?? '') === 'Unsuccessful') {
            return true;
        }
        if (isset($live['msg']) && (string) $live['msg'] === 'User not found') {
            return true;
        }
        return false;
    }
}

if (!function_exists('vira_repair_invoice_on_panel')) {
    /**
     * @return array{ok:bool,msg:string,repaired:bool}
     */
    function vira_repair_invoice_on_panel(array $invoice, ?ManagePanel $panel = null): array
    {
        $username = trim((string) ($invoice['username'] ?? ''));
        $location = trim((string) ($invoice['Service_location'] ?? ''));
        $idInvoice = trim((string) ($invoice['id_invoice'] ?? ''));
        if ($username === '' || $location === '' || $idInvoice === '') {
            return ['ok' => false, 'msg' => 'اطلاعات سفارش ناقص است.', 'repaired' => false];
        }

        $status = (string) ($invoice['Status'] ?? '');
        if (!in_array($status, vira_invoice_repairable_statuses(), true)) {
            return ['ok' => false, 'msg' => 'وضعیت این سفارش برای بازیابی مناسب نیست.', 'repaired' => false];
        }

        if (!function_exists('vira_ensure_manage_panel')) {
            require_once dirname(__DIR__) . '/function.php';
        }
        $panel = $panel ?? vira_ensure_manage_panel();

        $live = $panel->DataUser($location, $username);
        if (!vira_invoice_panel_user_missing($live)) {
            return ['ok' => true, 'msg' => 'سرویس در پنل موجود است.', 'repaired' => false];
        }

        $panelRow = select('marzban_panel', '*', 'name_panel', $location, 'select');
        if (!is_array($panelRow)) {
            return ['ok' => false, 'msg' => 'پنل سرویس یافت نشد.', 'repaired' => false];
        }
        if (($panelRow['status'] ?? '') === 'disabled') {
            return ['ok' => false, 'msg' => 'پنل غیرفعال است.', 'repaired' => false];
        }

        $limits = vira_invoice_compute_repair_limits($invoice, $panelRow);
        if ($limits === null) {
            return ['ok' => false, 'msg' => 'زمان یا حجم سرویس برای بازیابی کافی نیست.', 'repaired' => false];
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
            'id_invoice' => $idInvoice,
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
            return ['ok' => false, 'msg' => 'بازیابی در پنل ناموفق: ' . $err, 'repaired' => false];
        }

        $subUrl = trim((string) ($created['subscription_url'] ?? ''));
        if ($subUrl === '') {
            $subUrl = vira_invoice_stored_subscription_url($invoice);
        }
        if ($subUrl !== '') {
            update('invoice', 'user_info', $subUrl, 'id_invoice', $idInvoice);
        }
        if (($invoice['Status'] ?? '') !== 'active') {
            update('invoice', 'Status', 'active', 'id_invoice', $idInvoice);
        }

        error_log('[panel-repair] restored invoice=' . $idInvoice . ' user=' . $username . ' panel=' . $location);
        return ['ok' => true, 'msg' => 'سرویس در پنل بازیابی شد.', 'repaired' => true];
    }
}

if (!function_exists('vira_repair_missing_panel_services')) {
    /**
     * @return array{checked:int,repaired:int,skipped:int,errors:int}
     */
    function vira_repair_missing_panel_services(?int $limit = 30): array
    {
        global $pdo;
        $stats = ['checked' => 0, 'repaired' => 0, 'skipped' => 0, 'errors' => 0];
        if (!isset($pdo)) {
            return $stats;
        }

        $statuses = vira_invoice_repairable_statuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $testName = 'سرویس تست';
        $lim = $limit !== null ? (int) max(1, min(100, $limit)) : 100;
        $sql = "SELECT * FROM invoice
                 WHERE Status IN ($placeholders)
                   AND name_product != ?
                   AND username IS NOT NULL AND username != ''
                   AND Service_location IS NOT NULL AND Service_location != ''
                 ORDER BY time_sell DESC LIMIT {$lim}";

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
            $live = $panel->DataUser((string) $invoice['Service_location'], (string) $invoice['username']);
            if (!vira_invoice_panel_user_missing($live)) {
                $stats['skipped']++;
                continue;
            }
            $result = vira_repair_invoice_on_panel($invoice, $panel);
            if (!empty($result['repaired'])) {
                $stats['repaired']++;
            } elseif ($result['ok']) {
                $stats['skipped']++;
            } else {
                $stats['errors']++;
            }
            usleep(150000);
        }

        return $stats;
    }
}

if (!function_exists('vira_repair_all_missing_panel_services')) {
    function vira_repair_all_missing_panel_services(): array
    {
        @ini_set('max_execution_time', '1800');
        return vira_invoice_sync_all_from_panel(0);
    }
}

if (!function_exists('vira_invoice_sync_all_from_panel')) {
    /**
     * sync همه سفارش‌های فعال از پنل + بازیابی اگر حذف شده باشند.
     *
     * @return array{checked:int,synced:int,repaired:int,skipped:int,errors:int}
     */
    function vira_invoice_sync_all_from_panel(int $limit = 0): array
    {
        global $pdo;
        $stats = ['checked' => 0, 'synced' => 0, 'repaired' => 0, 'skipped' => 0, 'errors' => 0];
        if (!isset($pdo)) {
            return $stats;
        }
        if (function_exists('vira_ensure_invoice_panel_sync_at_column')) {
            vira_ensure_invoice_panel_sync_at_column();
        }

        $statuses = ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $testName = 'سرویس تست';
        $sql = "SELECT * FROM invoice
                 WHERE Status IN ($placeholders)
                   AND name_product != ?
                   AND username IS NOT NULL AND username != ''
                   AND Service_location IS NOT NULL AND Service_location != ''
                 ORDER BY (panel_sync_at IS NULL OR panel_sync_at = '' OR panel_sync_at = '0') DESC,
                          CAST(panel_sync_at AS UNSIGNED) ASC,
                          time_sell DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($statuses, [$testName]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('vira_invoice_sync_all_from_panel: ' . $e->getMessage());
            return $stats;
        }

        if (!function_exists('vira_ensure_manage_panel')) {
            require_once dirname(__DIR__) . '/function.php';
        }
        $panel = vira_ensure_manage_panel();

        foreach ($rows as $invoice) {
            $stats['checked']++;
            $idInvoice = (string) ($invoice['id_invoice'] ?? '');
            $location = (string) ($invoice['Service_location'] ?? '');
            $username = (string) ($invoice['username'] ?? '');
            $live = $panel->DataUser($location, $username);

            if (!vira_invoice_panel_user_missing($live)) {
                if ($idInvoice !== '' && function_exists('vira_invoice_sync_live_from_panel')) {
                    vira_invoice_sync_live_from_panel($idInvoice, $live);
                }
                $stats['synced']++;
                usleep(80000);
                continue;
            }

            $result = vira_repair_invoice_on_panel($invoice, $panel);
            if (!empty($result['repaired'])) {
                $stats['repaired']++;
            } elseif ($result['ok'] ?? false) {
                $stats['skipped']++;
            } else {
                $stats['errors']++;
            }
            usleep(120000);
        }

        return $stats;
    }
}

if (!function_exists('vira_invoice_run_post_update_integrity')) {
    /** بعد از update: فقط نمونه کوچک repair — sync دسته‌ای هر ۱۵ دقیقه */
    function vira_invoice_run_post_update_integrity(): array
    {
        @ini_set('max_execution_time', '300');
        return vira_repair_missing_panel_services(30);
    }
}

// --- سازگاری با کد قدیمی (no-op) ---

if (!function_exists('vira_ensure_invoice_panel_snapshot_columns')) {
    function vira_ensure_invoice_panel_snapshot_columns(): void
    {
    }
}

if (!function_exists('vira_invoice_update_panel_snapshot')) {
    function vira_invoice_update_panel_snapshot(string $idInvoice, array $userData): void
    {
        vira_invoice_sync_live_from_panel($idInvoice, $userData);
    }
}

if (!function_exists('vira_invoice_after_purchase_success')) {
    function vira_invoice_after_purchase_success(
        string $idInvoice,
        array $dataOutput,
        int $expire,
        int $dataLimitBytes,
        ?string $codeProduct = null
    ): void {
        $subUrl = trim((string) ($dataOutput['subscription_url'] ?? ''));
        if ($subUrl !== '') {
            $inv = select('invoice', 'user_info', 'id_invoice', $idInvoice, 'select');
            if (is_array($inv) && trim((string) ($inv['user_info'] ?? '')) === '') {
                update('invoice', 'user_info', $subUrl, 'id_invoice', $idInvoice);
            }
        }
        if ($codeProduct !== null && $codeProduct !== '') {
            update('invoice', 'code_product', $codeProduct, 'id_invoice', $idInvoice);
        }
    }
}

if (!function_exists('vira_invoice_persist_subscription_data')) {
    function vira_invoice_persist_subscription_data(string $idInvoice, string $subUrl, ?array $panelUserData = null): void
    {
        $subUrl = trim($subUrl);
        if ($idInvoice === '' || $subUrl === '') {
            return;
        }
        update('invoice', 'user_info', $subUrl, 'id_invoice', $idInvoice);
    }
}

if (!function_exists('vira_invoice_has_repair_snapshot')) {
    function vira_invoice_has_repair_snapshot(array $invoice): bool
    {
        return (int) ($invoice['Volume'] ?? 0) > 0 || (int) ($invoice['Service_time'] ?? 0) > 0;
    }
}

if (!function_exists('vira_invoice_rebuild_snapshots_batch')) {
    function vira_invoice_rebuild_snapshots_batch(?int $limit = 50, bool $onlyMissingSnapshot = false): array
    {
        return ['ok' => 0, 'skip' => 0, 'err' => 0];
    }
}

if (!function_exists('vira_invoice_clear_shared_user_info')) {
    function vira_invoice_clear_shared_user_info(): int
    {
        return 0;
    }
}

if (!function_exists('vira_invoice_scan_shared_urls')) {
    function vira_invoice_scan_shared_urls(): array
    {
        return [];
    }
}

if (!function_exists('vira_invoice_url_taken_by_other')) {
    function vira_invoice_url_taken_by_other(array $invoice, string $url): bool
    {
        return false;
    }
}
