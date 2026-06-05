<?php
require_once __DIR__ . '/../inc/config.php';

header('Content-Type: application/json; charset=utf-8');
require_auth_api();

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

function cat_json(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list') {
    try {
        $rows = db_fetchAll($pdo, 'SELECT id, remark FROM category ORDER BY id ASC');
        cat_json(true, '', ['items' => $rows]);
    } catch (Exception $e) {
        cat_json(false, 'جدول category در دسترس نیست: ' . $e->getMessage());
    }
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $remark = trim((string) ($_POST['remark'] ?? ''));
    if ($remark === '') {
        cat_json(false, 'نام دسته الزامی است');
    }
    if (db_count($pdo, 'SELECT COUNT(*) FROM category WHERE remark = ?', [$remark]) > 0) {
        cat_json(false, 'این دسته قبلاً ثبت شده');
    }
    db_query($pdo, 'INSERT INTO category (remark) VALUES (?)', [$remark]);
    cat_json(true, 'دسته اضافه شد');
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['id'] ?? 0);
    $remark = trim((string) ($_POST['remark'] ?? ''));
    if (!$id || $remark === '') {
        cat_json(false, 'شناسه و نام دسته الزامی است');
    }
    $old = db_fetch($pdo, 'SELECT remark FROM category WHERE id = ?', [$id]);
    if (!$old) {
        cat_json(false, 'دسته یافت نشد');
    }
    if (db_count($pdo, 'SELECT COUNT(*) FROM category WHERE remark = ? AND id != ?', [$remark, $id]) > 0) {
        cat_json(false, 'نام دسته تکراری است');
    }
    db_query($pdo, 'UPDATE category SET remark = ? WHERE id = ?', [$remark, $id]);
    db_query($pdo, 'UPDATE product SET category = ? WHERE category = ?', [$remark, $old['remark']]);
    cat_json(true, 'دسته ویرایش شد');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        cat_json(false, 'شناسه نامعتبر');
    }
    $row = db_fetch($pdo, 'SELECT remark FROM category WHERE id = ?', [$id]);
    if (!$row) {
        cat_json(false, 'دسته یافت نشد');
    }
    $used = db_count($pdo, 'SELECT COUNT(*) FROM product WHERE category = ?', [$row['remark']]);
    if ($used > 0) {
        cat_json(false, 'این دسته در ' . $used . ' محصول استفاده شده — ابتدا محصولات را تغییر دهید');
    }
    db_query($pdo, 'DELETE FROM category WHERE id = ?', [$id]);
    cat_json(true, 'دسته حذف شد');
}

http_response_code(400);
cat_json(false, 'عملیات نامعتبر');
