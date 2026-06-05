(function () {
  var tpl = document.body.getAttribute('data-mirza-template') || 'midnight';
  var labels = {
    midnight: 'نیمه‌شب',
    aurora: 'شفق قطبی',
    emerald: 'زمرد',
    sunset: 'غروب',
    ocean: 'اقیانوس',
  };
  window.MIRZA_MINIAPP_TEMPLATE = tpl;
  window.MIRZA_MINIAPP_TEMPLATE_LABEL = labels[tpl] || tpl;

  var preview = /[?&]tpl_preview=([^&]+)/.exec(window.location.search);
  if (preview && preview[1]) {
    document.documentElement.setAttribute('data-preview-mode', '1');
  }
}());
