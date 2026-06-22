(function () {
  var tg = window.Telegram && window.Telegram.WebApp;
  var meta = { bot_username: '', support_id: '', gateways: {} };
  var userBalance = null;
  var apiBase = '../api/';

  function toast(msg) {
    var el = document.getElementById('vira-mini-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'vira-mini-toast';
      el.className = 'vira-mini-toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(function () { el.classList.remove('show'); }, 2600);
  }

  function fmt(n) {
    return Number(n || 0).toLocaleString('fa-IR');
  }

  function haptic(type) {
    if (tg && tg.HapticFeedback) {
      try {
        if (type === 'success' && tg.HapticFeedback.notificationOccurred) {
          tg.HapticFeedback.notificationOccurred('success');
        } else if (tg.HapticFeedback.impactOccurred) {
          tg.HapticFeedback.impactOccurred('light');
        }
      } catch (e) { /* ignore */ }
    }
  }

  function getInitUserId() {
    if (!tg || !tg.initDataUnsafe || !tg.initDataUnsafe.user) return 0;
    return tg.initDataUnsafe.user.id || 0;
  }

  function fetchMeta() {
    return fetch(apiBase + 'miniapp_meta.php').then(function (r) { return r.json(); }).catch(function () { return {}; });
  }

  function fetchUserBalance(userId, token) {
    if (!userId || !token) return Promise.resolve(null);
    return fetch(apiBase + 'miniapp.php?actions=user_info&user_id=' + userId, {
      headers: { Authorization: 'Bearer ' + token },
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.status && d.obj) return d.obj.balance;
      return null;
    }).catch(function () { return null; });
  }

  function getStoredToken() {
    try {
      return localStorage.getItem('vira_mini_token') || sessionStorage.getItem('vira_mini_token') || '';
    } catch (e) {
      return '';
    }
  }

  function injectUI() {
    if (document.getElementById('vira-mini-topbar')) return;
    document.body.classList.add('vira-miniapp-enhanced');

    var top = document.createElement('div');
    top.id = 'vira-mini-topbar';
    top.className = 'vira-mini-topbar';
    var tplLabel = meta.template_label || window.VIRA_MINIAPP_TEMPLATE_LABEL || '';
    var brand = '🛍 فروشگاه VPN';
    if (tplLabel) brand += ' · ' + tplLabel;
    top.innerHTML =
      '<div class="vira-brand">' + brand + '</div>' +
      '<div class="vira-balance" id="viraBalancePill">موجودی: <strong>—</strong></div>';

    var dock = document.createElement('div');
    dock.className = 'vira-mini-dock';
    dock.innerHTML =
      '<button type="button" class="vira-dock-btn active" data-nav="buy"><span>🛒</span><span>خرید</span></button>' +
      '<button type="button" class="vira-dock-btn" data-nav="services"><span>📡</span><span>سرویس‌ها</span></button>' +
      '<button type="button" class="vira-dock-btn" data-nav="account"><span>👤</span><span>حساب</span></button>' +
      '<button type="button" class="vira-dock-btn" data-nav="support"><span>💬</span><span>پشتیبانی</span></button>';

    document.body.appendChild(top);
    document.body.appendChild(dock);

    dock.querySelectorAll('.vira-dock-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        haptic('light');
        dock.querySelectorAll('.vira-dock-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var nav = btn.getAttribute('data-nav');
        var path = { buy: '/buy', services: '/services', account: '/account', support: '' }[nav] || '/buy';
        if (nav === 'support') {
          if (meta.support_id) {
            if (tg && tg.openTelegramLink) {
              tg.openTelegramLink('https://t.me/' + String(meta.support_id).replace('@', ''));
            } else {
              window.open('https://t.me/' + String(meta.support_id).replace('@', ''), '_blank');
            }
          } else if (meta.bot_username) {
            toast('پشتیبانی: @' + meta.bot_username);
            if (tg && tg.openTelegramLink) tg.openTelegramLink('https://t.me/' + meta.bot_username);
          } else {
            toast('پشتیبانی در ربات تلگرام تنظیم نشده');
          }
          return;
        }
        if (window.location.pathname.indexOf(path) < 0) {
          window.location.href = '.' + path + window.location.search;
        }
      });
    });

    injectHintCard();
    observeBalance();
    syncDockRoute();
  }

  function injectHintCard() {
    var root = document.getElementById('root');
    if (!root || root.querySelector('.vira-mini-hint-card')) return;
    var plisioOn = (meta.gateways.nowpaymentstatus || '').indexOf('on') === 0;
    var card = document.createElement('div');
    card.className = 'vira-mini-hint-card';
    card.innerHTML =
      '<h4>راهنمای سریع</h4>' +
      '<p>• پلن را انتخاب کنید و از موجودی یا درگاه پرداخت کنید.<br>' +
      (plisioOn ? '• پرداخت ارزی (Plisio) فعال است.<br>' : '') +
      '• سرویس‌های فعال در بخش «سرویس‌ها» قابل تمدید هستند.<br>' +
      '• سوال دارید؟ دکمه پشتیبانی پایین صفحه.</p>';
    if (root.firstChild) {
      root.insertBefore(card, root.firstChild);
    } else {
      root.appendChild(card);
    }
  }

  function syncDockRoute() {
    var p = window.location.pathname || '';
    var dock = document.querySelector('.vira-mini-dock');
    if (!dock) return;
    var key = 'buy';
    if (p.indexOf('services') >= 0) key = 'services';
    else if (p.indexOf('account') >= 0) key = 'account';
    dock.querySelectorAll('.vira-dock-btn').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-nav') === key);
    });
  }

  function updateBalancePill() {
    var pill = document.getElementById('viraBalancePill');
    if (!pill) return;
    if (userBalance !== null) {
      pill.innerHTML = 'موجودی: <strong>' + fmt(userBalance) + ' ت</strong>';
    }
  }

  function observeBalance() {
    var tries = 0;
    var iv = setInterval(function () {
      tries++;
      var uid = getInitUserId();
      var token = getStoredToken();
      if (token && uid) {
        fetchUserBalance(uid, token).then(function (b) {
          if (b !== null) {
            userBalance = b;
            updateBalancePill();
          }
        });
      }
      if (tries > 20) clearInterval(iv);
    }, 1500);
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('button, a, [role="button"]');
    if (t && (t.textContent || '').indexOf('کپی') >= 0) {
      haptic('success');
      toast('در حال کپی…');
    }
  }, true);

  fetchMeta().then(function (m) {
    if (m && m.ok) meta = m;
    function ready() {
      injectUI();
    }
    if (document.body.classList.contains('vira-miniapp-ready')) {
      ready();
    } else {
      var obs = new MutationObserver(function () {
        if (document.body.classList.contains('vira-miniapp-ready')) {
          obs.disconnect();
          ready();
        }
      });
      obs.observe(document.body, { attributes: true, attributeFilter: ['class'] });
      setTimeout(ready, 5000);
    }
  });

  window.addEventListener('popstate', syncDockRoute);
}());
