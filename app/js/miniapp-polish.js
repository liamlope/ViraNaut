(function () {
  var tg = window.Telegram && window.Telegram.WebApp;
  if (tg) {
    try {
      tg.ready();
      tg.expand();
      if (tg.enableClosingConfirmation) tg.enableClosingConfirmation();
      var scheme = tg.colorScheme || 'dark';
      document.documentElement.setAttribute('data-tg-scheme', scheme);
      if (tg.themeParams && tg.themeParams.bg_color) {
        var meta = document.getElementById('tg-theme-color');
        if (meta) meta.content = tg.themeParams.bg_color;
      }
      if (tg.setHeaderColor && tg.themeParams) {
        tg.setHeaderColor(tg.themeParams.secondary_bg_color || tg.themeParams.bg_color || '#0f172a');
      }
      if (tg.setBackgroundColor && tg.themeParams) {
        tg.setBackgroundColor(tg.themeParams.bg_color || '#0f172a');
      }
    } catch (e) { /* ignore */ }
  }

  function markReady() {
    document.body.classList.add('vira-miniapp-ready');
  }

  if (document.readyState === 'complete') {
    setTimeout(markReady, 400);
  } else {
    window.addEventListener('load', function () { setTimeout(markReady, 300); });
  }
  setTimeout(markReady, 8000);

  window.addEventListener('error', function () {
    var loader = document.querySelector('.vira-app-loader p');
    if (loader) loader.textContent = 'خطا در بارگذاری — اتصال را بررسی کنید';
  });
}());
