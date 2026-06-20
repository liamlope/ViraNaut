<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$token = agent_panel_ensure_token($pdo, (string) $user['id']);
$domain = $domainhosts ?? '';
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><title>API نماینده</title>
<style>body{font-family:Tahoma,sans-serif;background:#0f1419;color:#e7ecf1;padding:24px}pre{background:#1a2332;padding:16px;border-radius:8px;overflow:auto}a{color:#3390ec}</style>
</head>
<body>
<nav><a href="index.php">داشبورد</a><a href="api.php">API</a></nav>
<h1>مستندات API نماینده</h1>
<p>Header: <code>Authorization: Bearer <?= htmlspecialchars($token) ?></code></p>
<pre>POST https://<?= htmlspecialchars($domain) ?>/api/users.php
{
  "actions": "get_user_data",
  "id_user": "<?= htmlspecialchars((string) $user['id']) ?>"
}</pre>
</body></html>
