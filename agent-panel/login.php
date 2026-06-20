<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

function agent_complete_login(PDO $pdo, array $user, bool $remember): void
{
    $_SESSION['agent_user_id'] = $user['id'];
    $_SESSION['agent_login_at'] = time();
    $sv = db_fetch($pdo, 'SELECT session_version FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [(string) $user['id']]);
    $_SESSION['agent_session_version'] = (int) ($sv['session_version'] ?? 0);
    agent_panel_ensure_token($pdo, (string) $user['id']);
    agent_log_login($pdo, (string) $user['id']);
    if ($remember) {
        agent_set_remember_cookie((string) $user['id']);
    }
    unset($_SESSION['agent_pending_2fa']);
    header('Location: index.php');
    exit;
}

if (!empty($_SESSION['agent_user_id']) && agent_session_valid($pdo)) {
    header('Location: index.php');
    exit;
}

$loginError = '';
$needs2fa = false;
$errMap = [
    'not_agent' => 'این آیدی نماینده (n/n2) نیست.',
    'expired' => 'نمایندگی شما منقضی شده است.',
    'session' => 'نشست منقضی شد — دوباره وارد شوید.',
    '2fa' => 'کد دو مرحله‌ای نامعتبر است.',
];
if (!empty($_GET['err']) && isset($errMap[$_GET['err']])) {
    $loginError = $errMap[$_GET['err']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!agent_login_rate_ok()) {
        $loginError = 'تلاش زیاد — ۱۰ دقیقه بعد دوباره امتحان کنید.';
    } else {
        $id = trim($_POST['telegram_id'] ?? $_SESSION['agent_pending_2fa'] ?? '');
        $user = select('user', '*', 'id', $id, 'select');
        if (!$user || ($user['agent'] ?? 'f') === 'f') {
            $loginError = 'کاربر نماینده یافت نشد.';
        } elseif (!empty($user['expire']) && (int) $user['expire'] < time()) {
            $loginError = 'نمایندگی منقضی شده است.';
        } elseif (agent_is_2fa_enabled($pdo, $id)) {
            $code = trim($_POST['twofa_code'] ?? '');
            if ($code === '') {
                agent_send_2fa_code($pdo, $id);
                $_SESSION['agent_pending_2fa'] = $id;
                $needs2fa = true;
            } elseif (!agent_verify_2fa($pdo, $id, $code)) {
                $loginError = 'کد دو مرحله‌ای نامعتبر است.';
                $needs2fa = true;
            } else {
                agent_complete_login($pdo, $user, !empty($_POST['remember']));
            }
        } else {
            agent_complete_login($pdo, $user, !empty($_POST['remember']));
        }
    }
}

$lang = 'fa';
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars(agent_t('login_title', $lang)) ?></title>
<link rel="stylesheet" href="<?= agent_panel_asset('../panel/css/style.css') ?>">
<link rel="stylesheet" href="<?= agent_panel_asset('assets/agent.css') ?>">
</head>
<body class="agent-login-body">
<div class="agent-login-card card">
<h2><?= htmlspecialchars(agent_t('login_title', $lang)) ?></h2>
<?php if ($loginError): ?><div class="notice notice-no"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
<form method="post" class="agent-login-form">
<?php if ($needs2fa): ?>
<input type="hidden" name="telegram_id" value="<?= htmlspecialchars((string) ($_SESSION['agent_pending_2fa'] ?? '')) ?>">
<label>کد ارسال‌شده به تلگرام</label>
<input name="twofa_code" required dir="ltr" class="input" placeholder="123456" autofocus>
<?php else: ?>
<label>آیدی عددی تلگرام</label>
<p class="lede"><?= agent_t('login_hint', $lang) ?> <a href="https://t.me/IDFindeerBot" target="_blank" rel="noopener">@IDFindeerBot</a></p>
<input name="telegram_id" required dir="ltr" class="input" placeholder="123456789" value="<?= htmlspecialchars($_POST['telegram_id'] ?? '') ?>">
<label class="agent-check"><input type="checkbox" name="remember" value="1"> <?= agent_t('remember_me', $lang) ?></label>
<?php endif; ?>
<button class="btn btn-primary" type="submit" style="width:100%;margin-top:12px">ورود</button>
</form>
</div>
</body>
</html>
