<?php
require_once __DIR__ . '/inc/template_bootstrap.php';
$mirzaTpl = mirza_miniapp_resolve_template();
$tplClass = 'mirza-tpl-' . preg_replace('/[^a-z0-9_-]/', '', $mirzaTpl);
$mirzaDemo = mirza_miniapp_is_demo_mode();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"<?= $mirzaDemo ? ' data-demo-mode="1"' : '' ?>>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    <meta name="theme-color" content="#0f172a" id="tg-theme-color" />
    <meta name="color-scheme" content="dark light" />
    <meta name="mirza-template" content="<?= htmlspecialchars($mirzaTpl) ?>" />
    <title>فروشگاه VPN · ویرانات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <script src="./js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="./css/miniapp-polish.css" />
    <link rel="stylesheet" href="./css/templates/template-base.css" />
    <link rel="stylesheet" href="./css/templates/<?= htmlspecialchars($mirzaTpl) ?>.css" />
    <link rel="stylesheet" href="./css/miniapp-shell.css" />
  </head>
  <body class="mirza-miniapp mirza-shell-active <?= htmlspecialchars($tplClass) ?>" data-mirza-template="<?= htmlspecialchars($mirzaTpl) ?>">
    <div id="mirza-app-loader" class="mirza-app-loader" aria-live="polite">
      <div class="mirza-app-loader-inner">
        <div class="mirza-app-loader-ring"></div>
        <p>در حال بارگذاری فروشگاه…</p>
      </div>
    </div>
    <div id="mirza-shell" aria-live="polite"></div>
    <div id="root" hidden></div>
<?php if ($mirzaDemo): ?>
    <script src="./js/miniapp-demo-data.js"></script>
<?php endif; ?>
    <script src="./js/miniapp-polish.js" defer></script>
    <script src="./js/miniapp-theme.js" defer></script>
    <script src="./js/miniapp-checkout.js" defer></script>
    <script src="./js/miniapp-shell-features.js" defer></script>
<?php if (!$mirzaDemo): ?>
    <script src="./js/miniapp-api.js" defer></script>
<?php endif; ?>
    <script src="./js/miniapp-shell.js" defer></script>
  </body>
</html>
