<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!agent_login_rate_ok()) {
        $loginError = 'تلاش زیاد — ۱۰ دقیقه بعد دوباره امتحان کنید.';
    } else {
        $id = trim($_POST['telegram_id'] ?? '');
        $user = select('user', '*', 'id', $id, 'select');
        if ($user && ($user['agent'] ?? 'f') !== 'f') {
            $_SESSION['agent_user_id'] = $id;
            $_SESSION['agent_login_at'] = time();
            header('Location: index.php');
            exit;
        }
        $loginError = 'کاربر نماینده یافت نشد.';
    }
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ورود پنل نمایندگی</title>
<link rel="stylesheet" href="assets/agent.css">
</head>
<body class="agent-body" style="display:flex;align-items:center;justify-content:center;min-height:100vh">
<div class="agent-card" style="width:min(400px,92vw)">
<h2>پنل نمایندگی ViraNaut</h2>
<?php if (!empty($loginError)): ?><p style="color:#f87171"><?= htmlspecialchars($loginError) ?></p><?php endif; ?>
<form method="post">
<label>آیدی عددی تلگرام</label>
<input name="telegram_id" required dir="ltr" placeholder="123456789" style="width:100%;padding:10px;margin:10px 0;border-radius:8px;border:1px solid #334;background:#0f172a;color:#fff">
<button class="agent-btn" type="submit" style="width:100%">ورود</button>
</form>
</div>
</body>
</html>
