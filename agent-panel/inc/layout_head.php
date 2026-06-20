<?php
require_once __DIR__ . '/../../panel/inc/icons.php';
require_once __DIR__ . '/nav_registry.php';

if (!isset($user)) {
    $user = agent_panel_require_auth($pdo);
}
$theme = agent_panel_get_theme($pdo, (string) $user['id']);
$lang = agent_lang($pdo, (string) $user['id']);
$pageLede = $pageLede ?? '';
$activeNav = $activeNav ?? 'dashboard';
$extraCss = $extraCss ?? ['../panel/css/dashboard.css', 'assets/agent.css'];
$extraJs = $extraJs ?? [];
$botNavIds = agent_bot_nav_ids();
$botNavActive = in_array($activeNav, $botNavIds, true);
$showPageHead = $showPageHead ?? true;
$currentUser = (string) ($user['username'] ?? $user['id']);
$initials = mb_strtoupper(mb_substr($currentUser, 0, 1, 'UTF-8'), 'UTF-8');
$expiryWarn = agent_expiry_warning($user);
$flash = agent_get_flash();
$csrf = agent_csrf_token();
$agentBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/agent-panel'), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <meta name="theme-color" content="#0B1220" id="mtc">
  <meta name="csrf" content="<?= htmlspecialchars($csrf) ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <title><?= htmlspecialchars(($pageTitle ?? agent_t('dashboard', $lang)) . ' — ' . AGENT_PANEL_TITLE) ?></title>
  <link rel="stylesheet" href="<?= agent_panel_asset('../panel/css/style.css') ?>">
  <link rel="stylesheet" href="<?= agent_panel_asset('../panel/css/panel_progress.css') ?>">
  <?php foreach ($extraCss as $href): ?>
  <link rel="stylesheet" href="<?= agent_panel_asset($href) ?>">
  <?php endforeach; ?>
  <script>
    (function () {
      var t = localStorage.getItem('panel-theme') || '<?= htmlspecialchars($theme) ?>';
      var bg = { navy:'#0F172A', purple:'#180D2E', emerald:'#0A1F1C', sunset:'#1A0D0D', slate:'#080808', light:'#F1F5F9', linen:'#FAF7F2', mint:'#F0FDF4', lavender:'#FAF5FF', viranaut:'#0B1220' };
      var root = document.documentElement;
      root.style.backgroundColor = bg[t] || '#0B1220';
      root.setAttribute('data-theme', t);
      root.style.colorScheme = (t === 'light' || t === 'linen' || t === 'mint' || t === 'lavender') ? 'light' : 'dark';
    }());
  </script>
</head>
<body data-panel-base="<?= htmlspecialchars($agentBase) ?>" data-agent-lang="<?= htmlspecialchars($lang) ?>">

<div id="load-bar"></div>
<div id="toast-area"></div>

<div class="confirm-veil" id="confirm-veil">
  <div class="confirm-box">
    <div class="confirm-icon"><?= icon('block', 26) ?></div>
    <h4 id="confirm-title"><?= htmlspecialchars(agent_t('confirm', $lang)) ?></h4>
    <p id="confirm-msg">آیا اطمینان دارید؟</p>
    <div class="confirm-btns">
      <button class="btn btn-no" id="confirm-ok"><?= htmlspecialchars(agent_t('confirm', $lang)) ?></button>
      <button class="btn btn-ghost" onclick="closeConfirm()"><?= htmlspecialchars(agent_t('cancel', $lang)) ?></button>
    </div>
  </div>
</div>

<div class="app">
  <div class="sidebar-backdrop" id="backdrop" onclick="closeSidebar()"></div>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-mark"><?= AGENT_BRAND_MARK ?></div>
      <div class="brand-name"><?= AGENT_PANEL_SHORT ?></div>
    </div>
    <nav class="sidebar-nav">
      <?php agent_render_sidebar($activeNav); ?>
    </nav>
    <div class="sidebar-foot">
      <div class="user-pill">
        <div class="user-av"><?= htmlspecialchars($initials) ?></div>
        <div><strong><?= htmlspecialchars($currentUser) ?></strong><small><?= htmlspecialchars($user['agent'] ?? '') ?></small></div>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" onclick="toggleSidebar()" aria-label="menu"><?= icon('menu', 22) ?></button>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>
      <div class="topbar-actions">
        <a href="buy.php" class="btn btn-primary btn-sm"><?= icon('package', 16) ?> <?= htmlspecialchars(agent_t('buy_new', $lang)) ?></a>
        <a href="settings.php" class="icon-btn" title="settings"><?= icon('settings', 20) ?></a>
      </div>
    </header>
    <main class="content">
      <?php if ($expiryWarn): ?>
      <div class="notice notice-warn"><?= htmlspecialchars($expiryWarn) ?></div>
      <?php endif; ?>
      <?php if ($flash): ?>
      <div class="notice notice-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>
      <?php if ($showPageHead && !empty($pageTitle)): ?>
      <div class="page-head"><h1><?= htmlspecialchars($pageTitle) ?></h1><?php if ($pageLede): ?><p class="lede"><?= htmlspecialchars($pageLede) ?></p><?php endif; ?></div>
      <?php endif; ?>
