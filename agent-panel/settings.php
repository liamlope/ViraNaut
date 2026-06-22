<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$uid = (string) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    agent_csrf_verify();
    if (isset($_POST['theme'])) {
        agent_panel_set_theme($pdo, $uid, $_POST['theme']);
        agent_flash('ok', 'تم ذخیره شد');
    }
    if (!empty($_POST['rotate_token'])) {
        agent_panel_rotate_token($pdo, $uid);
        agent_flash('ok', 'توکن چرخید');
    }
    if (!empty($_POST['logout_all'])) {
        agent_invalidate_all_sessions($pdo, $uid);
        agent_flash('ok', 'همه نشست‌ها باطل شد');
    }
    if (isset($_POST['twofa'])) {
        agent_panel_ensure_token($pdo, $uid);
        db_query($pdo, 'UPDATE agent_panel_tokens SET twofa_enabled = ? WHERE id_user = ?', [(int) $_POST['twofa'], $uid]);
        agent_flash('ok', '2FA به‌روز شد');
    }
    if (isset($_POST['notify_telegram'])) {
        agent_panel_ensure_token($pdo, $uid);
        db_query($pdo, 'UPDATE agent_panel_tokens SET notify_telegram = ? WHERE id_user = ?', [(int) $_POST['notify_telegram'], $uid]);
        agent_flash('ok', 'اعلان تلگرام');
    }
    if (!empty($_POST['new_token_label'])) {
        agent_panel_add_token($pdo, $uid, trim($_POST['new_token_label']));
        agent_flash('ok', 'توکن جدید');
    }
    header('Location: settings.php');
    exit;
}

$token = agent_panel_ensure_token($pdo, $uid);
$theme = agent_panel_get_theme($pdo, $uid);
$row = db_fetch($pdo, 'SELECT twofa_enabled, notify_telegram FROM agent_panel_tokens WHERE id_user = ? LIMIT 1', [$uid]);
$multi = agent_panel_list_tokens($pdo, $uid);
$logins = db_fetchAll($pdo, 'SELECT ip, user_agent, created_at FROM agent_login_log WHERE id_user = ? ORDER BY created_at DESC LIMIT 20', [$uid]);
$pageTitle = 'تنظیمات';
$activeNav = 'settings';
require __DIR__ . '/inc/layout_head.php';
?>
<form method="post" class="card"><div class="card-body">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(agent_csrf_token()) ?>">
<h3>ظاهر</h3>
<select name="theme" class="input">
<?php foreach (['viranaut','navy','purple','emerald','sunset','slate','light','linen','mint','lavender'] as $t): ?>
<option value="<?= $t ?>" <?= $theme === $t ? 'selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
</select>
<h3 style="margin-top:16px">API Token اصلی</h3>
<div class="agent-token-row"><code><?= htmlspecialchars($token) ?></code></div>
<button class="btn btn-ghost btn-sm" name="rotate_token" value="1" type="submit">چرخش توکن</button>
<h3 style="margin-top:16px">توکن‌های اضافه</h3>
<?php foreach ($multi as $mt): ?>
<p><strong><?= htmlspecialchars($mt['label']) ?></strong>: <code><?= htmlspecialchars(substr($mt['api_token'],0,12)) ?>…</code> (<?= (int)$mt['rate_limit'] ?>/min)</p>
<?php endforeach; ?>
<input name="new_token_label" class="input" placeholder="نام توکن جدید">
<h3 style="margin-top:16px">امنیت</h3>
<label><input type="checkbox" name="twofa" value="1" <?= !empty($row['twofa_enabled'])?'checked':'' ?>> 2FA تلگرام</label><br>
<label><input type="checkbox" name="notify_telegram" value="1" <?= !empty($row['notify_telegram'])?'checked':'' ?>> اعلان تلگرام</label><br>
<button class="btn btn-no btn-sm" name="logout_all" value="1" type="submit">خروج از همه دستگاه‌ها</button>
<button class="btn btn-primary" type="submit" style="margin-top:12px">ذخیره</button>
</div></form>
<div class="card" style="margin-top:16px"><div class="card-head"><h3>IP ورود</h3></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>IP</th><th>زمان</th></tr></thead><tbody>
<?php foreach ($logins as $l): ?><tr><td><?= htmlspecialchars($l['ip']??'') ?></td><td><?= htmlspecialchars($l['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
