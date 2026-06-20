<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$stats = agent_panel_sales_stats($pdo, (string) $user['id']);
$token = agent_panel_ensure_token($pdo, (string) $user['id']);
$domain = $domainhosts ?? '';
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>داشبورد نماینده — ViraNaut</title>
<style>
body{font-family:Tahoma,sans-serif;background:#0f1419;color:#e7ecf1;margin:0;padding:24px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:20px}
.card{background:#1a2332;padding:20px;border-radius:12px}
.stat{font-size:1.6rem;font-weight:bold;color:#4fae4e}
a{color:#3390ec}
nav a{margin-left:12px}
code{background:#0f1419;padding:4px 8px;border-radius:6px;word-break:break-all}
</style>
</head>
<body>
<nav><a href="index.php">داشبورد</a><a href="services.php">سرویس‌ها</a><a href="api.php">API</a><a href="logout.php">خروج</a></nav>
<h1>سلام، نماینده <?= htmlspecialchars((string) $user['id']) ?></h1>
<div class="grid">
<div class="card"><div>موجودی</div><div class="stat"><?= number_format((int) $user['Balance']) ?> تومان</div></div>
<div class="card"><div>تعداد فروش</div><div class="stat"><?= number_format($stats['count']) ?></div></div>
<div class="card"><div>جمع فروش</div><div class="stat"><?= number_format($stats['sum']) ?> تومان</div></div>
</div>
<p style="margin-top:24px">توکن API: <code><?= htmlspecialchars($token) ?></code></p>
<p>Endpoint: <code>https://<?= htmlspecialchars($domain) ?>/api/users.php</code></p>
</body>
</html>
