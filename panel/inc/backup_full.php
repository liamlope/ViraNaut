<?php

/** بکاپ/بازیابی کامل Mirza — ZIP شامل DB، cronbot، text.json و راهنمای کرون */

if (!function_exists('mirza_backup_normalize_host')) {
    function mirza_backup_normalize_host($h): string
    {
        if (function_exists('mirza_normalize_domainhosts_value')) {
            return mirza_normalize_domainhosts_value($h);
        }
        $h = trim(str_replace("\r", '', (string) $h));
        $h = preg_replace('#^https?://#i', '', $h);
        return rtrim($h, '/');
    }
}

function mirza_project_root(): string
{
    $root = realpath(__DIR__ . '/../..');
    return $root ?: dirname(__DIR__, 2);
}

function mirza_backup_list_tables(PDO $pdo): array
{
    $out = [];
    foreach ($pdo->query('SHOW TABLES') as $row) {
        $out[] = (string) reset($row);
    }
    return $out;
}

function mirza_backup_cron_job_lines(): array
{
    global $domainhosts;
    $host = isset($domainhosts) ? mirza_backup_normalize_host($domainhosts) : 'YOUR_DOMAIN';
    return [
        "*/15 * * * * curl -fsS https://{$host}/cronbot/statusday.php",
        "*/1 * * * * curl -fsS https://{$host}/cronbot/NoticationsService.php",
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

function mirza_backup_collect_files(): array
{
    $root = mirza_project_root();
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
function mirza_backup_export_sql_php(PDO $pdo): string
{
    $tables = mirza_backup_list_tables($pdo);
    $out = "-- Mirza PHP panel backup " . date('c') . "\n";
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

function mirza_backup_export_sql(PDO $pdo, bool $panelSafe = true): string
{
    if ($panelSafe) {
        return mirza_backup_export_sql_php($pdo);
    }

    global $dbhost, $dbname, $usernamedb, $passworddb;
    $host = $dbhost ?: 'localhost';
    $tmpSql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mirza_dump_' . uniqid('', true) . '.sql';
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

    return mirza_backup_export_sql_php($pdo);
}

function mirza_backup_build_zip(): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('افزونه ZipArchive در PHP فعال نیست');
    }

    global $pdo;
    $root = mirza_project_root();
    $version = is_file($root . '/version') ? trim((string) file_get_contents($root . '/version')) : 'unknown';
    $tables = mirza_backup_list_tables($pdo);
    $sql = mirza_backup_export_sql($pdo);
    $files = mirza_backup_collect_files();
    $cronLines = mirza_backup_cron_job_lines();

    $tmpZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mirza_full_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('ساخت فایل ZIP ممکن نشد');
    }

    $manifest = [
        'format' => 'mirza-full-backup',
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

function mirza_backup_send_zip_download(): void
{
    $path = mirza_backup_build_zip();
    $name = 'mirza-full-backup-' . date('Y-m-d_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    @unlink($path);
    exit;
}

function mirza_backup_extract_zip(string $zipPath): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive فعال نیست');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('فایل ZIP نامعتبر است');
    }
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mirza_restore_' . uniqid('', true);
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

function mirza_backup_mysql_cli_import(string $sqlPath): bool
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

function mirza_backup_import_sql_mysqli(string $sqlPath): bool
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

function mirza_backup_split_sql_statements(string $sql): array
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

function mirza_backup_should_run_sql_stmt(string $stmt): bool
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

function mirza_backup_import_sql_pdo(PDO $pdo, string $sqlPath): void
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
        $tables = mirza_backup_list_tables($pdo);
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
    foreach (mirza_backup_split_sql_statements($sql) as $stmt) {
        if (!mirza_backup_should_run_sql_stmt($stmt)) {
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

function mirza_backup_import_sql(PDO $pdo, string $sqlPath): void
{
    if (mirza_backup_import_sql_mysqli($sqlPath)) {
        return;
    }
    if (mirza_backup_mysql_cli_import($sqlPath)) {
        return;
    }
    mirza_backup_import_sql_pdo($pdo, $sqlPath);
}

function mirza_backup_restore_files(string $extractDir): int
{
    $root = mirza_project_root();
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

function mirza_backup_restore_zip(string $uploadedPath): array
{
    global $pdo;

    $extractDir = mirza_backup_extract_zip($uploadedPath);
    try {
        $manifestPath = $extractDir . '/manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('manifest.json در ZIP یافت نشد — بکاپ معتبر Mirza نیست');
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (($manifest['format'] ?? '') !== 'mirza-full-backup') {
            throw new RuntimeException('فرمت بکاپ ناشناخته است');
        }

        $sqlPath = $extractDir . '/database.sql';
        if (!is_file($sqlPath)) {
            throw new RuntimeException('database.sql در ZIP نیست');
        }

        mirza_backup_import_sql($pdo, $sqlPath);
        $imported = true;

        $fileCount = mirza_backup_restore_files($extractDir);

        return [
            'ok' => true,
            'msg' => 'بازیابی انجام شد',
            'db' => $imported,
            'files_restored' => $fileCount,
            'backup_date' => $manifest['created_at'] ?? '',
        ];
    } finally {
        mirza_backup_rmdir_recursive($extractDir);
    }
}

function mirza_backup_rmdir_recursive(string $dir): void
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
            mirza_backup_rmdir_recursive($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function mirza_bot_restart(): array
{
    global $domainhosts, $APIKEY;

    if (!function_exists('telegram')) {
        require_once mirza_project_root() . '/botapi.php';
    }

    $host = isset($domainhosts) ? mirza_backup_normalize_host($domainhosts) : '';
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

    $verFile = mirza_project_root() . '/version';
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
