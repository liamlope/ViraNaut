<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$theme = agent_panel_get_theme($pdo, (string) $user['id']);
$pageTitle = $pageTitle ?? 'پنل نمایندگی';
$activeNav = $activeNav ?? 'dashboard';
$extraCss = ['../panel/css/style.css', 'assets/agent.css'];
$extraJs = $extraJs ?? [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> — ViraNaut Agent</title>
<?php foreach ($extraCss as $href): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
<?php endforeach; ?>
<script>localStorage.setItem('panel-theme','<?= htmlspecialchars($theme) ?>');</script>
</head>
<body class="agent-body">
<header class="agent-topbar">
<nav class="agent-nav">
<a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">داشبورد</a>
<a href="services.php" class="<?= $activeNav === 'services' ? 'active' : '' ?>">سرویس‌ها</a>
<a href="settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">تنظیمات</a>
<a href="api.php" class="<?= $activeNav === 'api' ? 'active' : '' ?>">API</a>
<a href="logout.php">خروج</a>
</nav>
<span class="agent-badge">نماینده <?= htmlspecialchars((string) $user['id']) ?></span>
</header>
<main class="agent-main">
