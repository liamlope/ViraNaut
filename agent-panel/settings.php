<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$themes = ['viranaut', 'navy', 'purple', 'emerald', 'sunset', 'slate', 'light', 'mint', 'lavender'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    agent_csrf_check();
    if (($_POST['action'] ?? '') === 'theme') {
        agent_panel_set_theme($pdo, (string) $user['id'], (string) ($_POST['theme'] ?? 'viranaut'));
    }
    if (($_POST['action'] ?? '') === 'rotate_token') {
        agent_panel_rotate_token($pdo, (string) $user['id']);
    }
    header('Location: settings.php');
    exit;
}
$theme = agent_panel_get_theme($pdo, (string) $user['id']);
$token = agent_panel_ensure_token($pdo, (string) $user['id']);
$pageTitle = 'تنظیمات نماینده';
$activeNav = 'settings';
require __DIR__ . '/inc/layout_head.php';
?>
<h1>تنظیمات</h1>
<form method="post" class="agent-card" style="max-width:420px">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(agent_csrf_token()) ?>">
<input type="hidden" name="action" value="theme">
<label>تم رنگی</label>
<select name="theme" style="width:100%;padding:10px;margin:8px 0;border-radius:8px">
<?php foreach ($themes as $t): ?>
<option value="<?= $t ?>" <?= $theme === $t ? 'selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
</select>
<button class="agent-btn" type="submit">ذخیره تم</button>
</form>
<form method="post" class="agent-card" style="max-width:420px;margin-top:16px">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(agent_csrf_token()) ?>">
<input type="hidden" name="action" value="rotate_token">
<p>توکن API: <code style="word-break:break-all"><?= htmlspecialchars($token) ?></code></p>
<button class="agent-btn secondary" type="submit">چرخش توکن</button>
</form>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
