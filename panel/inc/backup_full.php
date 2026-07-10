<?php

/** بکاپ/بازیابی کامل Vira — ZIP شامل DB، cronbot، text.json و راهنمای کرون */

if (!function_exists('vira_backup_normalize_host')) {
    function vira_backup_normalize_host($h): string
    {
        if (function_exists('vira_normalize_domainhosts_value')) {
            return vira_normalize_domainhosts_value($h);
        }
        $h = trim(str_replace("\r", '', (string) $h));
        $h = preg_replace('#^https?://#i', '', $h);
        return rtrim($h, '/');
    }
}

function vira_project_root(): string
{
    $root = realpath(__DIR__ . '/../..');
    return $root ?: dirname(__DIR__, 2);
}

function vira_backup_list_tables(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query('SHOW TABLES') as $row) {
        $out[] = (string) reset($row);
    }
    return $out;
}

function vira_backup_cron_job_lines(): array
{
    global $domainhosts;
    $host = isset($domainhosts) ? vira_backup_normalize_host($domainhosts) : 'YOUR_DOMAIN';
    return [
        "*/15 * * * * curl -fsS https://{$host}/cronbot/statusday.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/NoticationsService.php",
        "0 * * * * curl -fsS https://{$host}/cronbot/invoice_panel_sync.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/card_receipt_prompt.php",
        "*/5 * * * * curl -fsS https://{$host}/cronbot/payment_expire.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/sendmessage.php",
        "*/3 * * * * curl -fsS https://{$host}/cronbot/plisio.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/activeconfig.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/disableconfig.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/iranpay1.php",
        "0 */5 * * * curl -fsS https://{$host}/cronbot/backupbot.php",
        "*/2 * * * * curl -fsS https://{$host}/cronbot/gift.php",
        "*/30 * * * * curl -fsS https://{$host}/cronbot/expireagent.php",
        "*/15 * * * * curl -fsS https://{$host}/cronbot/on_hold.php",
        "*/2 * * * * curl -fsS https://{$host}/cronbot/configtest.php",
        "*/15 * * * * curl -fsS https://{$host}/cronbot/uptime_node.php",
        "*/15 * * * * curl -fsS https://{$host}/cronbot/uptime_panel.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/lottery.php",
    ];
}

function vira_backup_collect_files(): array
{
    $root = vira_project_root();
    $map = [];

    foreach (['text.json', 'version'] as $rel) {
        $p = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($p)) {
            $map[$rel] = $p;
        }
    }

    $cronDir = $root . DIRECTORY_SEPARATOR . 'cronbot';
    if (is_dir($cronDir)) {
        foreach (scandir($cronDir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $cronDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) {
                continue;
            }
            if (preg_match('/\.php$/i', $name)) {
                continue;
            }
            $map['cronbot/' . $name] = $path;
        }
    }

    return $map;
}

/** بکاپ SQL سازگار با بازیابی پنل (بدون mysqldump / DROP TABLE) */
function vira_backup_export_sql_php(PDO $pdo): string
{
    $tables = vira_backup_list_tables($pdo);
    $out = "-- Vira PHP panel backup " . date('c') . "\n";
    $out .= "-- sql_format: php_panel\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($tables as $t) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
            continue;
        }
        try {
            $rows = db_fetchAll($pdo, "SELECT * FROM `$t`");
            $out .= "\n-- Table `$t`\nDELETE FROM `$t`;\n";
            if ($rows === []) {
                continue;
            }
            foreach ($rows as $row) {
                $cols = array_map(static fn($c) => '`' . $c . '`', array_keys($row));
                $vals = array_map(static function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote((string) $v);
                }, array_values($row));
                $out .= 'INSERT INTO `' . $t . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
        } catch (Throwable $e) {
            $out .= "-- skip `$t`: " . $e->getMessage() . "\n";
        }
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

function vira_backup_export_sql(PDO $pdo, bool $panelSafe = true): string
{
    if ($panelSafe) {
        return vira_backup_export_sql_php($pdo);
    }

    global $dbhost, $dbname, $usernamedb, $passworddb;
    $host = $dbhost ?: 'localhost';
    $tmpSql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vira_dump_' . uniqid('', true) . '.sql';
    $cmd = 'mysqldump -h ' . escapeshellarg($host)
        . ' -u ' . escapeshellarg((string) $usernamedb)
        . ' -p' . escapeshellarg((string) $passworddb)
        . ' --add-drop-table --single-transaction --routines --no-tablespaces '
        . escapeshellarg((string) $dbname)
        . ' > ' . escapeshellarg($tmpSql) . ' 2>&1';

    $output = [];
    $code = 0;
    @exec($cmd, $output, $code);

    if ($code === 0 && is_file($tmpSql) && filesize($tmpSql) > 32) {
        $sql = file_get_contents($tmpSql);
        @unlink($tmpSql);
        if ($sql !== false && $sql !== '') {
            return $sql;
        }
    }
    @unlink($tmpSql);

    return vira_backup_export_sql_php($pdo);
}

function vira_backup_build_zip(): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('افزونه ZipArchive در PHP فعال نیست');
    }

    global $pdo;
    $root = vira_project_root();
    $version = is_file($root . '/version') ? trim((string) file_get_contents($root . '/version')) : 'unknown';
    $tables = vira_backup_list_tables($pdo);
    $sql = vira_backup_export_sql($pdo);
    $files = vira_backup_collect_files();
    $cronLines = vira_backup_cron_job_lines();

    $tmpZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vira_full_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('ساخت فایل ZIP ممکن نشد');
    }

    $manifest = [
        'format' => 'vira-full-backup',
        'version' => 1,
        'sql_format' => 'php_panel',
        'app_version' => $version,
        'created_at' => date('c'),
        'domain' => $GLOBALS['domainhosts'] ?? '',
        'tables' => $tables,
        'files' => array_keys($files),
    ];
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->addFromString('database.sql', $sql);
    $zip->addFromString('meta/cron_jobs.txt', implode("\n", $cronLines) . "\n");

    foreach ($files as $rel => $abs) {
        $zip->addFile($abs, str_replace('\\', '/', $rel));
    }

    $zip->close();
    return $tmpZip;
}

function vira_backup_send_zip_download(): void
{
    $path = vira_backup_build_zip();
    $name = 'vira-full-backup-' . date('Y-m-d_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    @unlink($path);
    exit;
}

/**
 * ارسال بکاپ SQL دیتابیس به کانال گزارش تلگرام (مثل backupbot.php)
 *
 * @return array{ok:bool,msg:string,bytes?:int}
 */
function vira_backup_send_database_telegram(PDO $pdo, string $caption = ''): array
{
    global $dbhost, $dbname, $usernamedb, $passworddb;

    if (!function_exists('select')) {
        require_once vira_project_root() . '/function.php';
    }
    if (!function_exists('telegram')) {
        require_once vira_project_root() . '/botapi.php';
    }

    $setting = select('setting', '*');
    $chatId = trim((string) ($setting['Channel_Report'] ?? ''));
    if ($chatId === '') {
        return ['ok' => false, 'msg' => 'Channel_Report در تنظیمات خالی است.'];
    }

    $threadRow = select('topicid', 'idreport', 'report', 'backupfile', 'select');
    $topicId = is_array($threadRow) ? trim((string) ($threadRow['idreport'] ?? '')) : '';

    $tmpSql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vira_opt_backup_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
    $written = false;

    $host = ($dbhost ?? '') !== '' ? $dbhost : 'localhost';
    $cmd = 'mysqldump -h ' . escapeshellarg((string) $host)
        . ' -u ' . escapeshellarg((string) $usernamedb)
        . ' -p' . escapeshellarg((string) $passworddb)
        . ' --no-tablespaces --ssl-mode=DISABLED '
        . escapeshellarg((string) $dbname)
        . ' > ' . escapeshellarg($tmpSql) . ' 2>&1';
    $output = [];
    $code = 0;
    @exec($cmd, $output, $code);
    if ($code === 0 && is_file($tmpSql) && filesize($tmpSql) > 32) {
        $written = true;
    } else {
        @unlink($tmpSql);
        $sql = vira_backup_export_sql_php($pdo);
        if ($sql !== '' && @file_put_contents($tmpSql, $sql) !== false) {
            $written = true;
        }
    }

    if (!$written || !is_file($tmpSql)) {
        @unlink($tmpSql);
        return ['ok' => false, 'msg' => 'ساخت فایل بکاپ SQL ناموفق بود.'];
    }

    $size = (int) filesize($tmpSql);
    if ($size > 48 * 1024 * 1024) {
        @unlink($tmpSql);
        return ['ok' => false, 'msg' => 'حجم بکاپ برای تلگرام زیاد است (' . round($size / 1048576, 1) . 'MB). ابتدا دستی بکاپ بگیرید.'];
    }

    $cap = trim($caption);
    if ($cap === '') {
        $cap = 'بکاپ دیتابیس';
    }
    $cap .= "\n" . date('Y/m/d H:i:s');

    $payload = [
        'chat_id' => $chatId,
        'document' => new CURLFile($tmpSql, 'application/sql', 'database_' . date('Y-m-d_His') . '.sql'),
        'caption' => $cap,
    ];
    if ($topicId !== '' && $topicId !== '0') {
        $payload['message_thread_id'] = $topicId;
    }

    $resp = telegram('sendDocument', $payload);
    @unlink($tmpSql);

    if (!is_array($resp) || empty($resp['ok'])) {
        $err = is_array($resp) ? (string) ($resp['description'] ?? 'خطای تلگرام') : 'خطای تلگرام';
        return ['ok' => false, 'msg' => $err];
    }

    return ['ok' => true, 'msg' => 'بکاپ به تلگرام ارسال شد.', 'bytes' => $size];
}

function vira_backup_extract_zip(string $zipPath): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive فعال نیست');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('فایل ZIP نامعتبر است');
    }
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vira_restore_' . uniqid('', true);
    if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('پوشه موقت ساخته نشد');
    }
    if (!$zip->extractTo($dir)) {
        $zip->close();
        throw new RuntimeException('استخراج ZIP ناموفق بود');
    }
    $zip->close();
    return $dir;
}

function vira_backup_mysql_cli_import(string $sqlPath): bool
{
    global $dbhost, $dbname, $usernamedb, $passworddb;
    $host = $dbhost ?: 'localhost';
    $mysql = trim((string) @shell_exec('which mysql 2>/dev/null'));
    if ($mysql === '') {
        return false;
    }

    $cmd = escapeshellarg($mysql)
        . ' -h ' . escapeshellarg($host)
        . ' -u ' . escapeshellarg((string) $usernamedb)
        . ' -p' . escapeshellarg((string) $passworddb)
        . ' ' . escapeshellarg((string) $dbname)
        . ' < ' . escapeshellarg($sqlPath)
        . ' 2>&1';

    $output = [];
    $code = 0;
    @exec($cmd, $output, $code);
    return $code === 0;
}

function vira_backup_import_sql_mysqli(string $sqlPath): bool
{
    global $dbhost, $dbname, $usernamedb, $passworddb;
    if (!function_exists('mysqli_connect')) {
        return false;
    }
    $host = $dbhost ?: 'localhost';
    $mysqli = @mysqli_connect($host, (string) $usernamedb, (string) $passworddb, (string) $dbname);
    if (!$mysqli) {
        return false;
    }
    mysqli_set_charset($mysqli, 'utf8mb4');
    $sql = file_get_contents($sqlPath);
    if ($sql === false || trim($sql) === '') {
        mysqli_close($mysqli);
        return false;
    }

    @mysqli_query($mysqli, 'SET FOREIGN_KEY_CHECKS=0');
    @mysqli_query($mysqli, "SET NAMES utf8mb4");

    if (!@mysqli_multi_query($mysqli, $sql)) {
        mysqli_close($mysqli);
        return false;
    }
    do {
        if ($result = mysqli_store_result($mysqli)) {
            mysqli_free_result($result);
        }
    } while (@mysqli_more_results($mysqli) && @mysqli_next_result($mysqli));

    $ok = mysqli_errno($mysqli) === 0;
    @mysqli_query($mysqli, 'SET FOREIGN_KEY_CHECKS=1');
    mysqli_close($mysqli);
    return $ok;
}

function vira_backup_split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $len = strlen($sql);
    $inString = false;
    $quote = '';

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inString) {
            $buffer .= $c;
            if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }
            continue;
        }
        if ($c === "'" || $c === '"') {
            $inString = true;
            $quote = $c;
            $buffer .= $c;
            continue;
        }
        if ($c === ';') {
            $stmt = trim($buffer);
            $buffer = '';
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            continue;
        }
        $buffer .= $c;
    }
    $stmt = trim($buffer);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
    return $statements;
}

function vira_backup_should_run_sql_stmt(string $stmt): bool
{
    $s = trim($stmt);
    if ($s === '') {
        return false;
    }
    if (preg_match('/^(--|#)/', $s)) {
        return false;
    }
    if (preg_match('/^(CREATE DATABASE|USE `)/i', $s)) {
        return false;
    }
    if (preg_match('/^\/\*!(\d+)?\s*(.+?)\s*\*\/\s*$/s', $s, $m)) {
        $s = trim($m[2]);
    }
    return (bool) preg_match('/\b(SET|INSERT|DELETE|DROP|CREATE|ALTER|LOCK|UNLOCK|TRUNCATE)\b/i', $s);
}

function vira_backup_import_sql_pdo(PDO $pdo, string $sqlPath): void
{
    $sql = file_get_contents($sqlPath);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('database.sql خالی است');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('SET NAMES utf8mb4');

    $isPhpPanel = stripos($sql, 'sql_format: php_panel') !== false
        || (stripos($sql, 'DROP TABLE') === false && stripos($sql, 'DELETE FROM') !== false);

    if ($isPhpPanel && stripos($sql, 'DROP TABLE') === false) {
        $tables = vira_backup_list_tables($pdo);
        foreach ($tables as $t) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
                continue;
            }
            try {
                $pdo->exec("DELETE FROM `$t`");
            } catch (Throwable $e) {
            }
        }
    }

    $done = 0;
    $errors = [];
    foreach (vira_backup_split_sql_statements($sql) as $stmt) {
        if (!vira_backup_should_run_sql_stmt($stmt)) {
            continue;
        }
        try {
            $pdo->exec($stmt);
            $done++;
        } catch (Throwable $e) {
            $errors[] = substr($e->getMessage(), 0, 120);
            if (count($errors) > 8) {
                break;
            }
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    if ($done === 0) {
        $hint = $errors !== [] ? implode(' | ', array_slice($errors, 0, 3)) : 'هیچ دستور SQL اجرا نشد';
        throw new RuntimeException('بازیابی دیتابیس ناموفق: ' . $hint);
    }
}

function vira_backup_import_sql(PDO $pdo, string $sqlPath): void
{
    if (vira_backup_import_sql_mysqli($sqlPath)) {
        return;
    }
    if (vira_backup_mysql_cli_import($sqlPath)) {
        return;
    }
    vira_backup_import_sql_pdo($pdo, $sqlPath);
}

function vira_backup_restore_files(string $extractDir): int
{
    $root = vira_project_root();
    $count = 0;

    $manifestPath = $extractDir . '/manifest.json';
    $allowed = [];
    if (is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($manifest['files'] ?? null)) {
            $allowed = $manifest['files'];
        }
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $full = $file->getPathname();
        $rel = str_replace('\\', '/', substr($full, strlen($extractDir) + 1));
        if (in_array($rel, ['manifest.json', 'database.sql', 'meta/cron_jobs.txt'], true)) {
            continue;
        }
        if (str_starts_with($rel, 'meta/')) {
            continue;
        }
        if ($allowed !== [] && !in_array($rel, $allowed, true)) {
            continue;
        }
        if (!preg_match('#^(cronbot/|text\.json|version)#', $rel)) {
            continue;
        }
        if (str_contains($rel, '..')) {
            continue;
        }

        $dest = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $destDir = dirname($dest);
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
            throw new RuntimeException('ساخت پوشه ممکن نشد: ' . $rel);
        }
        if (!@copy($full, $dest)) {
            throw new RuntimeException('کپی فایل ناموفق: ' . $rel);
        }
        $count++;
    }

    return $count;
}

function vira_backup_restore_zip(string $uploadedPath): array
{
    global $pdo;

    $extractDir = vira_backup_extract_zip($uploadedPath);
    try {
        $manifestPath = $extractDir . '/manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('manifest.json در ZIP یافت نشد — بکاپ معتبر Vira نیست');
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (($manifest['format'] ?? '') !== 'vira-full-backup') {
            throw new RuntimeException('فرمت بکاپ ناشناخته است');
        }

        $sqlPath = $extractDir . '/database.sql';
        if (!is_file($sqlPath)) {
            throw new RuntimeException('database.sql در ZIP نیست');
        }

        vira_backup_import_sql($pdo, $sqlPath);
        $imported = true;

        $fileCount = vira_backup_restore_files($extractDir);

        return [
            'ok' => true,
            'msg' => 'بازیابی انجام شد',
            'db' => $imported,
            'files_restored' => $fileCount,
            'backup_date' => $manifest['created_at'] ?? '',
        ];
    } finally {
        vira_backup_rmdir_recursive($extractDir);
    }
}

function vira_backup_rmdir_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            vira_backup_rmdir_recursive($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function vira_bot_restart(): array
{
    global $domainhosts, $APIKEY;

    if (!function_exists('telegram')) {
        require_once vira_project_root() . '/botapi.php';
    }

    $host = isset($domainhosts) ? vira_backup_normalize_host($domainhosts) : '';
    if ($host === '' || strpos($host, '{') !== false) {
        return ['ok' => false, 'msg' => 'دامنه در config.php تنظیم نشده است'];
    }

    $url = 'https://' . $host . '/index.php';
    $resp = telegram('setwebhook', [
        'url' => $url,
        'drop_pending_updates' => true,
    ]);

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    $verFile = vira_project_root() . '/version';
    if (is_file($verFile)) {
        @touch($verFile);
    }

    $ok = is_array($resp) && !empty($resp['ok']);
    return [
        'ok' => $ok,
        'msg' => $ok ? 'وب‌هوک ربات تنظیم مجدد شد (ری‌استارت)' : ('خطای تلگرام: ' . ($resp['description'] ?? 'نامشخص')),
        'webhook_url' => $url,
    ];
}
