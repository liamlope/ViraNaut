<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    agent_csrf_verify();
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        try {
            db_query($pdo, 'INSERT INTO site_admin_requests (id_user, message, status, source) VALUES (?,?,?,?)', [
                (string) $user['id'], $msg, 'pending', 'agent_panel',
            ]);
            agent_flash('ok', 'تیکت ثبت شد');
        } catch (Throwable $e) {
            db_query($pdo, 'INSERT INTO site_admin_requests (id_user, message, status) VALUES (?,?,?)', [
                (string) $user['id'], $msg, 'pending',
            ]);
            agent_flash('ok', 'تیکت ثبت شد');
        }
    }
    header('Location: support.php');
    exit;
}

$tickets = db_fetchAll($pdo, 'SELECT * FROM site_admin_requests WHERE id_user = ? ORDER BY created_at DESC LIMIT 20', [(string) $user['id']]);
$pageTitle = 'پشتیبانی';
$activeNav = 'support';
require __DIR__ . '/inc/layout_head.php';
?>
<form method="post" class="card"><div class="card-body">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(agent_csrf_token()) ?>">
<label>پیام به ادمین</label>
<textarea name="message" class="input" rows="4" required></textarea>
<button class="btn btn-primary" type="submit">ارسال</button>
</div></form>
<div class="card" style="margin-top:12px"><div class="card-head"><h3>تیکت‌های قبلی</h3></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>وضعیت</th><th>پیام</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($tickets as $t): ?>
<tr><td><?= htmlspecialchars($t['status']) ?></td><td><?= htmlspecialchars(mb_substr($t['message']??'',0,80)) ?></td><td><?= htmlspecialchars($t['created_at']??'') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
