(function () {
  document.querySelectorAll('.be-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy') || '';
      if (!text) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          btn.textContent = 'کپی شد';
          setTimeout(function () { btn.textContent = 'کپی'; }, 1200);
        });
      } else {
        prompt('کپی کنید:', text);
      }
    });
  });

  document.querySelectorAll('.be-insert').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var code = btn.getAttribute('data-insert') || '';
      if (!code) return;
      try {
        sessionStorage.setItem('viranaut_pending_emoji', code);
      } catch (e) { /* ignore */ }
      window.location.href = 'bot-texts.php';
    });
  });
})();
