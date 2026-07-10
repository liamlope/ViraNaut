<?php
require_once __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');
    if (site_admin_is_admin($pin)) {
        $_SESSION['site_admin_id'] = $pin;
        header('Location: index.php');
        exit;
    }
    $loginError = 'دسترسی مجاز نیست.';
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><title>ورود سایت ادمین</title>
<style>body{font-family:Tahoma,sans-serif;background:#111;color:#eee;display:flex;min-height:100vh;align-items:center;justify-content:center}form{background:#222;padding:24px;border-radius:10px;width:320px}input{width:100%;padding:10px;margin:10px 0}button{width:100%;padding:10px;background:#3390ec;border:0;color:#fff;border-radius:6px}</style>
</head>
<body>
<form method="post">
<h2>سایت ادمین</h2>
<?php if (!empty($loginError)): ?><p style="color:#e17076"><?= htmlspecialchars($loginError) ?></p><?php endif; ?>
<label>آیدی ادمین</label>
<input name="pin" required dir="ltr">
<button type="submit">ورود</button>
</form>
</body></html>
