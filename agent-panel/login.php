<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['telegram_id'] ?? '');
    $user = select('user', '*', 'id', $id, 'select');
    if ($user && ($user['agent'] ?? 'f') !== 'f') {
        $_SESSION['agent_user_id'] = $id;
        header('Location: index.php');
        exit;
    }
    $error = 'کاربر نماینده یافت نشد.';
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>ورود پنل نمایندگی — ViraNaut</title>
<style>
body{font-family:Tahoma,sans-serif;background:#0f1419;color:#e7ecf1;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.card{background:#1a2332;padding:28px;border-radius:12px;width:min(360px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.35)}
input{width:100%;padding:10px;margin:8px 0 16px;border-radius:8px;border:1px solid #334;background:#0f1419;color:#fff}
button{width:100%;padding:12px;border:0;border-radius:8px;background:#3390ec;color:#fff;font-weight:bold;cursor:pointer}
.err{color:#e17076;margin-bottom:8px}
</style>
</head>
<body>
<div class="card">
<h2>پنل نمایندگی</h2>
<?php if (!empty($error)): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>آیدی عددی تلگرام</label>
<input name="telegram_id" required dir="ltr" placeholder="123456789">
<button type="submit">ورود</button>
</form>
</div>
</body>
</html>
