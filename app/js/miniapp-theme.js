(function () {
  var tpl = document.body.getAttribute('data-vira-template') || 'midnight';
  var labels = {
    midnight: 'نیمه‌شب',
    aurora: 'شفق قطبی',
    emerald: 'زمرد',
    sunset: 'غروب',
    ocean: 'اقیانوس',
  };
  window.VIRA_MINIAPP_TEMPLATE = tpl;
  window.VIRA_MINIAPP_TEMPLATE_LABEL = labels[tpl] || tpl;

  var preview = /[?&]tpl_preview=([^&]+)/.exec(window.location.search);
  if (preview && preview[1]) {
    document.documentElement.setAttribute('data-preview-mode', '1');
  }
}());
