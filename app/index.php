<?php
require_once __DIR__ . '/inc/template_bootstrap.php';
$viraTpl = vira_miniapp_resolve_template();
$tplClass = 'vira-tpl-' . preg_replace('/[^a-z0-9_-]/', '', $viraTpl);
$viraDemo = vira_miniapp_is_demo_mode();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"<?= $viraDemo ? ' data-demo-mode="1"' : '' ?>>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    <meta name="theme-color" content="#0f172a" id="tg-theme-color" />
    <meta name="color-scheme" content="dark light" />
    <meta name="vira-template" content="<?= htmlspecialchars($viraTpl) ?>" />
    <title>فروشگاه VPN · ویرا</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <script src="./js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="./css/miniapp-polish.css" />
    <link rel="stylesheet" href="./css/templates/template-base.css" />
    <link rel="stylesheet" href="./css/templates/<?= htmlspecialchars($viraTpl) ?>.css" />
    <link rel="stylesheet" href="./css/miniapp-shell.css" />
  </head>
  <body class="vira-miniapp vira-shell-active <?= htmlspecialchars($tplClass) ?>" data-vira-template="<?= htmlspecialchars($viraTpl) ?>">
    <div id="vira-app-loader" class="vira-app-loader" aria-live="polite">
      <div class="vira-app-loader-inner">
        <div class="vira-app-loader-ring"></div>
        <p>در حال بارگذاری فروشگاه…</p>
      </div>
    </div>
    <div id="vira-shell" aria-live="polite"></div>
    <div id="root" hidden></div>
<?php if ($viraDemo): ?>
    <script src="./js/miniapp-demo-data.js"></script>
<?php endif; ?>
    <script src="./js/miniapp-polish.js" defer></script>
    <script src="./js/miniapp-theme.js" defer></script>
    <script src="./js/miniapp-checkout.js" defer></script>
    <script src="./js/miniapp-shell-features.js" defer></script>
<?php if (!$viraDemo): ?>
    <script src="./js/miniapp-api.js" defer></script>
<?php endif; ?>
    <script src="./js/miniapp-shell.js" defer></script>
  </body>
</html>
