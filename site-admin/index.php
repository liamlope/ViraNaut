<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
site_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_manual') {
        $id = (int) ($_POST['invoice_id'] ?? 0);
        if ($id > 0) {
            db_query($pdo, 'DELETE FROM invoice WHERE id = ? AND name_product LIKE ?', [$id, '%دستی%']);
        }
        header('Location: index.php');
        exit;
    }
    if ($action === 'update_status') {
        $id = (int) ($_POST['request_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $reply = trim((string) ($_POST['reply_text'] ?? ''));
        $allowed = ['pending', 'answered', 'closed'];
        if ($id > 0 && in_array($status, $allowed, true)) {
            db_query($pdo, 'UPDATE site_admin_requests SET status = ?, admin_reply = ?, updated_at = NOW() WHERE id = ?', [$status, $reply, $id]);
            $req = db_fetch($pdo, 'SELECT * FROM site_admin_requests WHERE id = ?', [$id]);
            if ($req && $reply !== '' && function_exists('sendmessage')) {
                $uid = $req['id_user'];
                sendmessage($uid, "📬 پاسخ پشتیبانی:\n\n" . $reply, null, 'HTML');
            }
        }
        header('Location: index.php');
        exit;
    }
}

$requests = db_fetchAll($pdo, 'SELECT * FROM site_admin_requests ORDER BY created_at DESC LIMIT 50');
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>سایت ادمین</title>
<style>
body{font-family:Tahoma,sans-serif;background:#0f1419;color:#eee;padding:24px;max-width:960px;margin:0 auto}
.card{background:#1a2332;padding:16px;margin:12px 0;border-radius:10px;border:1px solid #2a3544}
a{color:#3390ec}img{max-width:320px;border-radius:8px;margin-top:8px}
.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;background:#334155}
.badge-pending{background:#854d0e;color:#fef3c7}
.badge-answered{background:#166534;color:#dcfce7}
.badge-closed{background:#475569;color:#e2e8f0}
textarea,input,select{width:100%;padding:8px;margin:6px 0;border-radius:6px;border:1px solid #334155;background:#0f1419;color:#eee}
button{padding:8px 14px;border-radius:6px;border:none;background:#3390ec;color:#fff;cursor:pointer}
</style>
</head>
<body>
<h1>درخواست‌های سایت ادمین</h1>
<p><a href="logout.php">خروج</a></p>
<?php if (!$requests): ?>
<p>درخواستی ثبت نشده.</p>
<?php endif; ?>
<?php foreach ($requests as $r):
    $st = (string) ($r['status'] ?? 'pending');
    $photoUrl = !empty($r['photo_file_id']) ? site_admin_telegram_file_url((string) $r['photo_file_id']) : null;
?>
<div class="card">
<strong>#<?= (int) $r['id'] ?></strong>
<span class="badge badge-<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></span>
— کاربر <?= htmlspecialchars((string) $r['id_user']) ?>
<p><?= nl2br(htmlspecialchars((string) $r['message'])) ?></p>
<?php if ($photoUrl): ?>
<p><img src="<?= htmlspecialchars($photoUrl) ?>" alt="پیوست"></p>
<?php elseif (!empty($r['photo_file_id'])): ?>
<p>📷 file_id: <?= htmlspecialchars((string) $r['photo_file_id']) ?></p>
<?php endif; ?>
<?php if (!empty($r['admin_reply'])): ?>
<p><strong>پاسخ:</strong> <?= nl2br(htmlspecialchars((string) $r['admin_reply'])) ?></p>
<?php endif; ?>
<small><?= htmlspecialchars((string) $r['created_at']) ?></small>
<form method="post" style="margin-top:12px">
<input type="hidden" name="action" value="update_status">
<input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
<label>وضعیت</label>
<select name="status">
<option value="pending"<?= $st === 'pending' ? ' selected' : '' ?>>pending</option>
<option value="answered"<?= $st === 'answered' ? ' selected' : '' ?>>answered</option>
<option value="closed"<?= $st === 'closed' ? ' selected' : '' ?>>closed</option>
</select>
<label>پاسخ به کاربر (تلگرام)</label>
<textarea name="reply_text" rows="3" placeholder="متن پاسخ…"><?= htmlspecialchars((string) ($r['admin_reply'] ?? '')) ?></textarea>
<button type="submit">ذخیره و ارسال پاسخ</button>
</form>
</div>
<?php endforeach; ?>
</body></html>
