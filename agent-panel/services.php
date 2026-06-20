<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$rows = db_fetchAll($pdo, 'SELECT username, name_product, price_product, status, time_sell FROM invoice WHERE id_user = ? ORDER BY time_sell DESC LIMIT 100', [(string) $user['id']]);
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><title>سرویس‌های نماینده</title>
<style>body{font-family:Tahoma,sans-serif;background:#0f1419;color:#e7ecf1;padding:24px}table{width:100%;border-collapse:collapse;margin-top:16px}td,th{border-bottom:1px solid #334;padding:8px;text-align:right}a{color:#3390ec}</style>
</head>
<body>
<nav><a href="index.php">داشبورد</a><a href="services.php">سرویس‌ها</a><a href="api.php">API</a></nav>
<h1>سرویس‌ها</h1>
<input type="search" id="q" placeholder="جستجو..." style="width:100%;max-width:320px;padding:8px;margin:8px 0">
<table id="tbl"><thead><tr><th>کاربر</th><th>محصول</th><th>قیمت</th><th>وضعیت</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><?= htmlspecialchars($r['username']) ?></td><td><?= htmlspecialchars($r['name_product']) ?></td><td><?= number_format((int)$r['price_product']) ?></td><td><?= htmlspecialchars($r['status']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<script>document.getElementById('q').addEventListener('input',function(){var q=this.value.toLowerCase();document.querySelectorAll('#tbl tbody tr').forEach(function(tr){tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none';});});</script>
</body></html>
