<?php
/**
 * ViraNaut — نمونه تنظیمات (کپی به config.php و مقادیر را پر کنید)
 */
$request_exec_timeout = 120000;
$dbhost = '127.0.0.1';
$dbname = 'viranaut_bot';
$usernamedb = 'root';
$passworddb = 'your_password';
$connect = mysqli_connect($dbhost, $usernamedb, $passworddb, $dbname);
if ($connect->connect_error) { die('Database error: ' . $connect->connect_error); }
mysqli_set_charset($connect, 'utf8mb4');
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$dsn = "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4";
try { $pdo = new PDO($dsn, $usernamedb, $passworddb, $options); } catch (\PDOException $e) { error_log('Database connection failed: ' . $e->getMessage()); }
$APIKEY = 'YOUR_BOT_TOKEN';
$adminnumber = 'YOUR_TELEGRAM_ID';
$domainhosts = 'your-domain.com';
$usernamebot = 'YourBotUsername';

if (!function_exists('vira_normalize_domainhosts_value')) {
    function vira_normalize_domainhosts_value($h)
    {
        $h = trim(str_replace("\r", '', (string) $h));
        $h = preg_replace('#^https?://#i', '', $h);
        return rtrim($h, '/');
    }
}

if (isset($domainhosts) && $domainhosts !== '' && strpos((string) $domainhosts, '{') === false) {
    $domainhosts = vira_normalize_domainhosts_value($domainhosts);
}

if (!function_exists('select')) {
    require_once __DIR__ . '/function.php';
}

if (function_exists('vira_ensure_user_lang_column')) {
    vira_ensure_user_lang_column();
}
