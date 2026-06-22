<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/invoice_manage_ops.php';
require_auth();
csrf_check_get();

$action = (string) ($_GET['action'] ?? '');
$id = trim((string) ($_GET['id'] ?? ''));

$back = 'invoice_view.php?id=' . urlencode($id);
$rawBack = (string) ($_GET['back'] ?? '');
if (strpos($rawBack, 'invoice') === 0) {
    $back = explode('?', $rawBack)[0] === 'invoice.php' ? 'invoice.php' : $back;
}

if ($id === '') {
    flash('error', 'شناسه سفارش نامعتبر است.');
    header('Location: invoice.php');
    exit;
}

$adminUser = (string) ($_SESSION['admin_user'] ?? 'panel');
$result = im_handle_action($pdo, $id, $action, $adminUser);

if ($result['ok']) {
    flash('success', $result['msg']);
} else {
    flash('error', $result['msg']);
}

$redirect = (string) ($result['redirect'] ?? $back);
header('Location: ' . $redirect);
exit;
