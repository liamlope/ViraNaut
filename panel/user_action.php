<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/user_manage_ops.php';
require_auth();
csrf_check_get();

$action = $_GET['action'] ?? '';
$id     = (int) ($_GET['id'] ?? 0);

$allowed_back = ['users.php', 'user.php'];
$rawBack = $_GET['back'] ?? '';
$back = 'users.php';
foreach ($allowed_back as $allowed) {
    if (strpos($rawBack, $allowed) === 0) {
        $base = explode('?', $rawBack)[0];
        $back = $base . ($id ? "?id=$id" : '');
        break;
    }
}
if ($rawBack === 'users.php') {
    $back = 'users.php';
}

if (!$id) {
    flash('error', $textbotlang['panel']['userActionInvalidUserId'] ?? 'شناسه کاربر نامعتبر است.');
    header('Location: users.php');
    exit;
}

$user = db_fetch($pdo, 'SELECT id FROM user WHERE id = ?', [$id]);
if (!$user) {
    flash('error', $textbotlang['panel']['userActionUserNotFound'] ?? 'کاربر یافت نشد.');
    header('Location: users.php');
    exit;
}

$adminUser = (string) ($_SESSION['admin_user'] ?? 'panel');
$result = um_handle_get_action($pdo, $id, $action, $adminUser);

if ($result['ok']) {
    flash('success', $result['msg']);
} else {
    flash($action === 'unblock' || $action === 'block' ? 'warning' : 'error', $result['msg']);
}

header("Location: $back");
exit;
