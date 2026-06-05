<?php
require_once __DIR__ . '/icons.php';
$pageLede = $pageLede ?? '';
$activeNav = $activeNav ?? '';
$extraCss = $extraCss ?? [];
$extraJs = $extraJs ?? [];
$botNavIds = ['bot', 'keyboard', 'bot-texts', 'panels', 'bot-settings'];
$botNavActive = in_array($activeNav, $botNavIds, true);
$showPageHead = $showPageHead ?? true;
$currentUser = $_SESSION['admin_user'] ?? 'ادمین';
$initials = mb_strtoupper(mb_substr($currentUser, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <meta name="theme-color" content="#0F172A" id="mtc">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= htmlspecialchars(($pageTitle ?? 'داشبورد') . ' — ' . VIRA_PANEL_TITLE) ?></title>
  <link rel="stylesheet" href="<?= panel_asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= panel_asset('css/panel_progress.css') ?>">
  <?php foreach ($extraCss as $href): ?>
  <link rel="stylesheet" href="<?= panel_asset($href) ?>">
  <?php endforeach; ?>
  <script>
    (function () {
      var t = localStorage.getItem('panel-theme') || 'navy';
      var bg = {
        navy: '#0F172A', purple: '#180D2E', emerald: '#0A1F1C',
        sunset: '#1A0D0D', slate: '#080808', light: '#F1F5F9',
        linen: '#FAF7F2', mint: '#F0FDF4', lavender: '#FAF5FF',
        viranaut: '#0B1220'
      };

      var root = document.documentElement;
      root.style.backgroundColor = bg[t] || '#0F172A';
      root.setAttribute('data-theme', t);

      root.style.colorScheme = (t === 'light' || t === 'linen' || t === 'mint' || t === 'lavender') ? 'light' : 'dark';
      var mtc = document.getElementById('mtc');
      if (mtc && bg[t]) mtc.content = bg[t];
      if (localStorage.getItem('panel-sb-collapsed') === '1' && window.innerWidth > 768)
        root.classList.add('sb-pre-collapsed');
    }());
  </script>
</head>

<?php
$panelBasePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/panel');
$panelBasePath = rtrim(str_replace('\\', '/', $panelBasePath), '/') . '/';
?>
<body data-panel-base="<?= htmlspecialchars($panelBasePath) ?>">

  <div id="load-bar"></div>
  <div id="toast-area"></div>

  <div class="confirm-veil" id="confirm-veil">
    <div class="confirm-box">
      <div class="confirm-icon"><?= icon('block', 26) ?></div>
      <h4 id="confirm-title">تأیید عملیات</h4>
      <p id="confirm-msg">آیا اطمینان دارید؟ این عملیات قابل بازگشت نیست.</p>
      <div class="confirm-btns">
        <button class="btn btn-no" id="confirm-ok">بله، ادامه</button>
        <button class="btn btn-ghost" onclick="closeConfirm()">انصراف</button>
      </div>
    </div>
  </div>

  <div class="app">
    <div class="sidebar-backdrop" id="backdrop" onclick="closeSidebar()"></div>

    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark"><?= VIRA_BRAND_MARK ?></div>
        <div class="brand-name"><?= VIRA_BRAND_NAME_FA ?><span> · پنل</span></div>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section">
          <div class="nav-heading">عمومی</div>
          <a href="index.php" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>" title="داشبورد">
            <span class="nav-icon"><?= icon('dashboard') ?></span><span class="nav-label">داشبورد</span>
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-heading">مدیریت</div>
          <a href="users.php" class="nav-item <?= $activeNav === 'users' ? 'active' : '' ?>" title="کاربران">
            <span class="nav-icon"><?= icon('users') ?></span><span class="nav-label">کاربران</span>
          </a>
          <a href="invoice.php" class="nav-item <?= $activeNav === 'invoice' ? 'active' : '' ?>" title="سفارشات">
            <span class="nav-icon"><?= icon('invoice') ?></span><span class="nav-label">سفارشات</span>
          </a>
          <a href="service.php" class="nav-item <?= $activeNav === 'service' ? 'active' : '' ?>" title="سرویس‌ها">
            <span class="nav-icon"><?= icon('server') ?></span><span class="nav-label">سرویس‌ها</span>
          </a>
          <a href="product.php" class="nav-item <?= $activeNav === 'product' ? 'active' : '' ?>" title="محصولات">
            <span class="nav-icon"><?= icon('package') ?></span><span class="nav-label">محصولات</span>
          </a>
          <a href="shop-settings.php" class="nav-item <?= $activeNav === 'shop-settings' ? 'active' : '' ?>" title="تنظیمات فروشگاه">
            <span class="nav-icon"><?= icon('package') ?></span><span class="nav-label">فروشگاه</span>
          </a>
          <a href="miniapp-templates.php" class="nav-item <?= $activeNav === 'miniapp-templates' ? 'active' : '' ?>" title="قالب مینی‌اپ">
            <span class="nav-icon"><?= icon('edit') ?></span><span class="nav-label">قالب مینی‌اپ</span>
          </a>
          <a href="finance.php" class="nav-item <?= in_array($activeNav, ['finance', 'payment'], true) ? 'active' : '' ?>" title="مرکز مالی">
            <span class="nav-icon"><?= icon('wallet') ?></span><span class="nav-label">مالی</span>
          </a>
          <a href="about.php" class="nav-item <?= $activeNav === 'about' ? 'active' : '' ?>" title="درباره">
            <span class="nav-icon"><?= icon('chart') ?></span><span class="nav-label">درباره</span>
          </a>
          <a href="migration.php" class="nav-item <?= $activeNav === 'migration' ? 'active' : '' ?>" title="مهاجرت">
            <span class="nav-icon"><?= icon('package') ?></span><span class="nav-label">مهاجرت</span>
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-heading">ربات تلگرام</div>
          <a href="bot.php" class="nav-item <?= $activeNav === 'bot' ? 'active' : '' ?>" title="مرکز ربات">
            <span class="nav-icon"><?= icon('bot') ?></span><span class="nav-label">مرکز ربات</span>
          </a>
          <a href="keyboard.php" class="nav-item <?= $activeNav === 'keyboard' ? 'active' : '' ?>" title="چیدمان منوی استارت">
            <span class="nav-icon"><?= icon('menu') ?></span><span class="nav-label">چیدمان منو</span>
          </a>
          <a href="bot-texts.php" class="nav-item <?= $activeNav === 'bot-texts' ? 'active' : '' ?>" title="متن‌های ربات">
            <span class="nav-icon"><?= icon('edit') ?></span><span class="nav-label">متن‌های ربات</span>
          </a>
          <a href="panels.php" class="nav-item <?= $activeNav === 'panels' ? 'active' : '' ?>" title="پنل‌های VPN">
            <span class="nav-icon"><?= icon('server') ?></span><span class="nav-label">پنل‌های VPN</span>
          </a>
          <a href="bot-settings.php" class="nav-item <?= $activeNav === 'bot-settings' ? 'active' : '' ?>" title="تنظیمات ربات">
            <span class="nav-icon"><?= icon('settings') ?></span><span class="nav-label">تنظیمات ربات</span>
          </a>
          <a href="test-settings.php" class="nav-item <?= $activeNav === 'test-settings' ? 'active' : '' ?>" title="اکانت تست">
            <span class="nav-icon"><?= icon('server') ?></span><span class="nav-label">اکانت تست</span>
          </a>
          <a href="channels.php" class="nav-item <?= $activeNav === 'channels' ? 'active' : '' ?>" title="جوین اجباری">
            <span class="nav-icon"><?= icon('users') ?></span><span class="nav-label">جوین اجباری</span>
          </a>
          <a href="reports-settings.php" class="nav-item <?= $activeNav === 'reports-settings' ? 'active' : '' ?>" title="گزارش‌ها">
            <span class="nav-icon"><?= icon('chart') ?></span><span class="nav-label">گزارش‌ها</span>
          </a>
          <a href="admins.php" class="nav-item <?= $activeNav === 'admins' ? 'active' : '' ?>" title="ادمین‌ها">
            <span class="nav-icon"><?= icon('users') ?></span><span class="nav-label">ادمین‌ها</span>
          </a>
          <a href="broadcast.php" class="nav-item <?= $activeNav === 'broadcast' ? 'active' : '' ?>" title="ارسال همگانی">
            <span class="nav-icon"><?= icon('edit') ?></span><span class="nav-label">همگانی</span>
          </a>
          <a href="optimize.php" class="nav-item <?= $activeNav === 'optimize' ? 'active' : '' ?>" title="بهینه‌سازی">
            <span class="nav-icon"><?= icon('chart') ?></span><span class="nav-label">بهینه‌سازی</span>
          </a>
          <a href="backup.php" class="nav-item <?= $activeNav === 'backup' ? 'active' : '' ?>" title="بکاپ">
            <span class="nav-icon"><?= icon('package') ?></span><span class="nav-label">بکاپ</span>
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-heading">پنل</div>
          <a href="settings.php" class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>" title="تنظیمات">
            <span class="nav-icon"><?= icon('settings') ?></span><span class="nav-label">تنظیمات</span>
          </a>
          <a href="logout.php" class="nav-item" title="خروج">
            <span class="nav-icon"><?= icon('logout') ?></span><span class="nav-label">خروج</span>
          </a>
        </div>
      </nav>
      <div class="sidebar-foot">
        <div class="user-pill">
          <div class="user-mono"><?= htmlspecialchars($initials) ?></div>
          <div class="user-info">
            <div class="uname"><?= htmlspecialchars($currentUser) ?></div>
            <div class="urole">مدیر پنل</div>
          </div>
        </div>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-btn menu-toggle" onclick="openSidebar()"><?= icon('menu', 18) ?></button>
          <button class="icon-btn sb-toggle" onclick="toggleSidebar()"><?= icon('menu', 17) ?></button>
          <div>
            <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
            <div class="crumb"><span><?= VIRA_BRAND_NAME_FA ?></span><span
                style="opacity:.4;margin:0 3px">/</span><span><?= htmlspecialchars($pageTitle) ?></span></div>
          </div>
        </div>
        <div class="topbar-tools">
          <div class="global-search-wrap">
            <div class="search-box" style="min-width:180px">
              <?= icon('search', 14) ?>
              <input type="search" id="globalSearch" placeholder="جستجو…" autocomplete="off">
            </div>
            <div class="global-search-results" id="globalSearchResults"></div>
          </div>
          <a href="bot.php" class="icon-btn" title="مدیریت ربات"><?= icon('bot', 16) ?></a>
          <a href="settings.php" class="icon-btn" title="تنظیمات"><?= icon('settings', 16) ?></a>
          <a href="logout.php" class="icon-btn" title="خروج"><?= icon('logout', 16) ?></a>
        </div>
      </header>
      <main class="content">
        <?php
        $s = get_flash('success');
        $e = get_flash('error');
        $w = get_flash('warning');
        if ($s): ?>
          <div class="notice notice-ok"><?= htmlspecialchars($s) ?></div><?php endif;
        if ($e): ?>
          <div class="notice notice-no"><?= htmlspecialchars($e) ?></div><?php endif;
        if ($w): ?>
          <div class="notice notice-warn"><?= htmlspecialchars($w) ?></div><?php endif;
        if ($showPageHead): ?>
          <div class="page-head fade-up">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if ($pageLede): ?>
              <p><?= htmlspecialchars($pageLede) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>