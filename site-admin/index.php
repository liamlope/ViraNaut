<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
site_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_manual') {
    $id = (int) ($_POST['invoice_id'] ?? 0);
    if ($id > 0) {
        db_query($pdo, 'DELETE FROM invoice WHERE id = ? AND name_product LIKE ?', [$id, '%دستی%']);
    }
    header('Location: index.php');
    exit;
}

$requests = db_fetchAll($pdo, 'SELECT * FROM site_admin_requests ORDER BY created_at DESC LIMIT 50');
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><title>سایت ادمین</title>
<style>body{font-family:Tahoma,sans-serif;background:#0f1419;color:#eee;padding:24px}.card{background:#1a2332;padding:16px;margin:12px 0;border-radius:10px}a{color:#3390ec}img{max-width:240px;border-radius:8px}</style>
</head>
<body>
<h1>درخواست‌های سایت ادمین</h1>
<p><a href="logout.php">خروج</a></p>
<?php if (!$requests): ?>
<p>درخواستی ثبت نشده.</p>
<?php endif; ?>
<?php foreach ($requests as $r): ?>
<div class="card">
<strong>#<?= (int) $r['id'] ?></strong> — کاربر <?= htmlspecialchars($r['id_user']) ?> — <?= htmlspecialchars($r['status']) ?>
<p><?= nl2br(htmlspecialchars((string) $r['message'])) ?></p>
<?php if (!empty($r['photo_file_id'])): ?><p>📷 فایل: <?= htmlspecialchars($r['photo_file_id']) ?></p><?php endif; ?>
<small><?= htmlspecialchars((string) $r['created_at']) ?></small>
</div>
<?php endforeach; ?>
</body></html>
