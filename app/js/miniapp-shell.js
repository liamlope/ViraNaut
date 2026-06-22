(function () {
  var tpl = document.body.getAttribute('data-vira-template') || 'midnight';
  var isDemo = document.documentElement.getAttribute('data-demo-mode') === '1';
  var D = window.VIRA_DEMO_DATA || {};
  var F = window.VIRA_SHELL_FEATURES;
  var state = {
    page: 'buy',
    filter: 'all',
    countryId: 'all',
    search: '',
    sort: '',
    serviceFilter: 'all',
    drawerOpen: false,
  };

  function afterPurchase() {
    var api = window.VIRA_MINIAPP_API;
    if (!api || !api.reloadCatalog || isDemo) {
      render();
      return;
    }
    api.reloadCatalog().then(function (part) {
      if (part.user) {
        D.user = D.user || {};
        D.user.balance = Number(part.user.balance || 0);
        D.user.codeInvitation = part.user.codeInvitation || part.user.code_invitation || D.user.codeInvitation;
        if (part.invite_link) D.user.invite_link = part.invite_link;
        else if (D.meta && D.meta.bot_username && D.user.codeInvitation) {
          var bot = String(D.meta.bot_username).replace(/^@/, '');
          D.user.invite_link = 'https://t.me/' + bot + '?start=' + D.user.codeInvitation;
        }
        if (part.user.count_order != null) D.user.count_order = part.user.count_order;
        if (part.user.count_payment != null) D.user.count_payment = part.user.count_payment;
      }
      if (part.products) {
        D.products = part.products;
        if (part.countries) D.countries = part.countries;
        if (part.categories) D.categories = part.categories;
      }
      if (part.services) D.services = part.services;
      window.VIRA_DEMO_DATA = D;
      render();
    }).catch(function () { render(); });
  }

  function bindShell(container) {
    if (!F) return;
    F.bindContainer(container, {
      D: D,
      state: state,
      isDemo: isDemo,
      render: render,
      afterPurchase: afterPurchase,
    });
    F.bindPullRefresh(container, function () {
      if (F.toast) F.toast('در حال بروزرسانی…');
      afterPurchase();
    });
    container.querySelectorAll('.vira-sh-drawer-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.drawerOpen = !state.drawerOpen;
        render();
      });
    });
    container.querySelectorAll('[data-page="shop"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.page = 'shop';
        render();
      });
    });
    container.querySelectorAll('.vira-sh-sticky-cta .primary').forEach(function (btn) {
      if (btn.getAttribute('data-page')) return;
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var feat = (D.products || []).find(function (p) { return p.featured || p.badge; }) || (D.products || [])[0];
        if (!feat || !window.VIRA_CHECKOUT) return;
        window.VIRA_CHECKOUT.openProduct(feat.id, feat.country_id, {
          isDemo: isDemo,
          ctx: window.VIRA_MINIAPP_API && window.VIRA_MINIAPP_API.getContext(),
          onSuccess: afterPurchase,
        });
      });
    });
    var walletInline = container.querySelector('#vira-wallet-inline');
    if (walletInline && window.VIRA_CHECKOUT && window.VIRA_CHECKOUT.mountWalletInline) {
      window.VIRA_CHECKOUT.mountWalletInline(walletInline, {
        isDemo: isDemo,
        ctx: window.VIRA_MINIAPP_API && window.VIRA_MINIAPP_API.getContext(),
        onSuccess: afterPurchase,
      });
    }
  }

  var layouts = {
    midnight: function () {
      if (!F) return '';
      var prods = F.filterProducts(D, state);
      var buyBody = prods.length
        ? '<div class="vira-sh-grid vira-sh-grid-responsive">' + F.productCardsHtml(prods, 'grid') + '</div>'
        : '<div class="vira-sh-empty">پلنی برای نمایش نیست.</div>';
      var pages = {
        buy: F.balanceBarHtml(D) +
          '<div class="vira-sh-header"><h2 class="vira-sh-title">خرید سرویس</h2></div>' +
          F.countryChipsHtml(D, state) + buyBody,
        services: '<div class="vira-sh-header"><h2 class="vira-sh-title">سرویس‌های من</h2></div>' + F.servicesListHtml(D, state),
        account: '<div class="vira-sh-header"><h2 class="vira-sh-title">حساب کاربری</h2></div>' + F.accountHtml(D, true) + F.inviteHtml(D),
        wallet: '<div class="vira-sh-header"><h2 class="vira-sh-title">شارژ در ربات</h2></div>' +
          '<div class="vira-sh-card vira-sh-wallet-inline-wrap" id="vira-wallet-inline"></div>',
      };
      var nav = '<nav class="vira-sh-nav-dock">' +
        [{ k: 'buy', ico: '🛒', l: 'خرید' }, { k: 'services', ico: '📡', l: 'سرویس' }, { k: 'account', ico: '👤', l: 'حساب' }, { k: 'wallet', ico: '🤖', l: 'شارژ' }].map(function (x) {
          return '<button type="button" data-page="' + x.k + '" class="' + (state.page === x.k ? 'on' : '') + '"><span class="ico">' + x.ico + '</span>' + x.l + '</button>';
        }).join('') + '</nav>';
      return '<div class="vira-shell-inner vira-scroll-y">' + (pages[state.page] || pages.buy) + '</div>' + nav;
    },

    aurora: function () {
      if (!F) return '';
      var b = (D.banners || [])[0] || {};
      var content = '';
      if (state.page === 'buy' || state.page === 'offers') {
        content = '<div class="vira-sh-hero vira-sh-hero-compact">' +
          '<div class="vira-sh-hero-row"><h3>' + F.esc(b.title || 'فروشگاه VPN') + '</h3>' +
          F.balanceBarHtml(D) + '</div><p>' + F.esc(b.text || 'پلن‌های عمودی — مناسب موبایل') + '</p></div>' +
          F.searchHtml(state, 'جستجوی پلن…') +
          F.countryChipsHtml(D, state) +
          '<div class="vira-sh-vfeed">' + F.productCardsHtml(F.filterProducts(D, state), 'stack') + '</div>';
      } else if (state.page === 'services') {
        content = '<h2 class="vira-sh-title">سرویس‌های فعال</h2>' + F.servicesListHtml(D, state);
      } else {
        content = F.accountHtml(D, true, true) + F.inviteHtml(D) +
          '<button type="button" class="vira-sh-btn vira-sh-wallet-open" style="margin-top:12px">شارژ در ربات</button>';
      }
      var nav = F.navBottom([
        { k: 'buy', ico: '🛍', l: 'فروشگاه' },
        { k: 'offers', ico: '✨', l: 'پیشنهاد' },
        { k: 'services', ico: '📡', l: 'سرویس' },
        { k: 'account', ico: '👤', l: 'پروفایل' },
      ], state);
      return '<div class="vira-shell-inner vira-scroll-y vira-tpl-aurora-body">' + content + '</div>' + nav;
    },

    emerald: function () {
      if (!F) return '';
      var filterBar = '<div class="vira-sh-filters">' +
        [{ k: 'all', l: 'همه' }, { k: 'ok', l: 'فعال' }, { k: 'warn', l: 'هشدار' }, { k: 'off', l: 'منقضی' }].map(function (x) {
          return '<button type="button" data-filter="' + x.k + '" data-svc-filter="1" class="' + (state.serviceFilter === x.k ? 'on' : '') + '">' + x.l + '</button>';
        }).join('') + '</div>';
      var pages = {
        dashboard: '<div class="vira-sh-header"><button type="button" class="vira-sh-drawer-btn">☰</button><h2 class="vira-sh-title">داشبورد</h2></div>' +
          F.balanceBarHtml(D) + F.accountHtml(D, false) +
          '<button type="button" class="vira-sh-btn vira-sh-wallet-open" style="margin:8px 0">شارژ در ربات</button>' +
          '<h3 class="vira-sh-subtitle">پلن‌های پیشنهادی</h3>' +
          '<div class="vira-sh-grid vira-sh-grid-responsive">' + F.productCardsHtml(F.filterProducts(D, state).slice(0, 4), 'grid') + '</div>',
        services: '<h2 class="vira-sh-title">سرویس‌ها</h2>' + filterBar +
          '<div class="vira-sh-table-wrap">' + servicesTableHtml() + '</div>' +
          '<div class="vira-sh-mobile-only">' + F.servicesCardsHtml(D, state) + '</div>',
        shop: '<h2 class="vira-sh-title">فروشگاه</h2>' + F.countryChipsHtml(D, state) +
          '<div class="vira-sh-grid vira-sh-grid-responsive">' + F.productCardsHtml(F.filterProducts(D, state), 'grid') + '</div>',
      };
      function servicesTableHtml() {
        var rows = '';
        F.filterServices(D, state).forEach(function (s) {
          rows += '<tr class="vira-sh-service-row"><td>' + F.esc(s.name) + '</td><td><span class="vira-sh-pill ' + F.esc(s.status_cls) + '">' + F.esc(s.status) + '</span></td><td>' + F.esc(s.traffic) + '</td><td><button type="button" class="vira-sh-btn vira-sh-service-detail" data-username="' + F.esc(s.name) + '">جزئیات</button></td></tr>';
        });
        return '<table class="vira-sh-table"><thead><tr><th>سرویس</th><th>وضعیت</th><th>حجم</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
      }
      var p = state.page;
      if (p === 'buy') p = 'shop';
      if (!pages[p]) p = 'dashboard';
      state.page = p;
      var drawer = state.drawerOpen ? '<div class="vira-sh-drawer"><button type="button" data-page="dashboard">داشبورد</button><button type="button" data-page="shop">فروشگاه</button><button type="button" data-page="services">سرویس‌ها</button><button type="button" class="vira-sh-wallet-open">شارژ</button></div>' : '';
      var nav = '<nav class="vira-sh-side-nav">' +
        [{ k: 'dashboard', l: 'داشبورد' }, { k: 'shop', l: 'فروشگاه' }, { k: 'services', l: 'سرویس‌ها' }].map(function (x) {
          return '<button type="button" data-page="' + x.k + '" class="' + (p === x.k ? 'on' : '') + '">' + x.l + '</button>';
        }).join('') + '</nav>';
      return drawer + '<div class="vira-shell-inner vira-scroll-y">' + pages[p] + '</div>' + nav;
    },

    sunset: function () {
      if (!F) return '';
      if (state.page === 'services') {
        return '<div class="vira-shell-inner vira-scroll-y"><h2 class="vira-sh-title">سرویس‌های من</h2>' + F.servicesListHtml(D, state) +
          '<div class="vira-sh-sticky-cta vira-sh-sticky-safe"><button type="button" class="ghost" data-page="buy">بازگشت</button></div></div>';
      }
      if (state.page === 'account') {
        return '<div class="vira-shell-inner vira-scroll-y"><h2 class="vira-sh-title">حساب من</h2>' + F.accountHtml(D, true) + F.inviteHtml(D) +
          '<div class="vira-sh-sticky-cta vira-sh-sticky-safe"><button type="button" class="primary" data-page="buy">فروشگاه</button></div></div>';
      }
      var prods = F.filterProducts(D, state);
      var featured = prods.find(function (p) { return p.featured || p.badge; }) || prods[0];
      if (featured) featured.featured = true;
      var timeline = '<div class="vira-sh-timeline"><div class="on"><span>۱</span>انتخاب پلن</div><div><span>۲</span>پرداخت</div><div><span>۳</span>فعال‌سازی</div></div>';
      var testi = '<div class="vira-sh-testimonials"><div class="vira-sh-testi">«سرعت عالیه»</div><div class="vira-sh-testi">«پشتیبانی سریع»</div></div>';
      return '<div class="vira-shell-inner vira-scroll-y vira-sunset-scroll">' +
        '<div class="vira-sh-story-hero vira-sh-gradient-head">' + F.balanceBarHtml(D) + '<h1>VPN پرسرعت</h1><p>قالب غروب — خرید درون اپ</p></div>' +
        timeline + F.productCardsHtml(prods, 'large') + testi +
        '</div><div class="vira-sh-sticky-cta vira-sh-sticky-safe">' +
        '<button type="button" class="primary">خرید پلن پیشنهادی</button>' +
        '<button type="button" class="ghost" data-page="services">سرویس‌ها</button>' +
        '<button type="button" class="ghost" data-page="account">حساب</button></div>';
    },

    ocean: function () {
      if (!F) return '';
      var cats = D.categories || [];
      var filters = ['all'].concat(cats.map(function (c) { return c.name; }));
      if (filters.length < 2) filters = ['all', 'اقتصادی', 'پرفروش', 'نامحدود'];
      var prods = F.filterProducts(D, state);
      if (state.filter !== 'all') {
        prods = prods.filter(function (p) { return p.cat === state.filter; });
      }
      var listHtml = prods.length ? F.productCardsHtml(prods, 'list') : '<div class="vira-sh-empty">پلنی یافت نشد. <button type="button" class="vira-sh-link-btn" data-page="buy" data-filter-reset="1">مشاهده همه</button></div>';
      var sortBar = '<div class="vira-sh-sort-bar">' +
        '<button type="button" data-sort="">پیش‌فرض</button>' +
        '<button type="button" data-sort="price_asc">ارزان‌ترین</button>' +
        '<button type="button" data-sort="price_desc">گران‌ترین</button>' +
        '<button type="button" data-sort="gb_desc">بیشترین حجم</button></div>';
      var pages = {
        buy: F.balanceBarHtml(D) + F.searchHtml(state, 'جستجوی پلن یا کشور…') +
          F.countryChipsHtml(D, state) + sortBar +
          '<div class="vira-sh-filters">' + filters.map(function (f) {
            var label = f === 'all' ? 'همه' : f;
            return '<button type="button" data-filter="' + F.esc(f) + '" class="' + (state.filter === f ? 'on' : '') + '">' + label + '</button>';
          }).join('') + '</div>' + listHtml,
        services: '<h2 class="vira-sh-title">سرویس‌ها</h2>' + F.servicesListHtml(D, state),
        account: '<h2 class="vira-sh-title">حساب</h2>' + F.accountHtml(D, true) + F.inviteHtml(D),
        more: '<h2 class="vira-sh-title">بیشتر</h2>' +
          '<div class="vira-sh-card"><button type="button" class="vira-sh-btn vira-sh-support-link">💬 پشتیبانی</button></div>' +
          '<div class="vira-sh-card">' + F.inviteHtml(D) + '</div>' +
          '<p class="vira-sh-meta" style="padding:8px">استفاده از VPN مطابق قوانین کشور شما.</p>',
      };
      var nav = '<nav class="vira-sh-nav-min">' +
        [{ k: 'buy', l: 'پلن‌ها' }, { k: 'services', l: 'سرویس' }, { k: 'account', l: 'حساب' }].map(function (x) {
          return '<button type="button" data-page="' + x.k + '" class="' + (state.page === x.k ? 'on' : '') + '">' + x.l + '</button>';
        }).join('') +
        '<button type="button" class="more" data-page="more">⋯</button></nav>';
      return '<div class="vira-shell-inner vira-scroll-y">' + (pages[state.page] || pages.buy) + '</div>' + nav;
    },
  };

  function render() {
    var shell = document.getElementById('vira-shell');
    if (!shell || !F) return;
    var fn = layouts[tpl] || layouts.midnight;
    var badge = isDemo ? '<div class="vira-shell-demo-badge">پیش‌نمایش · داده نمونه</div>' : '';
    shell.innerHTML = badge + fn();
    bindShell(shell);
    document.body.classList.add('vira-shell-ready');
    shell.querySelectorAll('[data-filter-reset]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.filter = 'all';
        state.search = '';
        render();
      });
    });
  }

  function showBootError(msg, hint) {
    var shell = document.getElementById('vira-shell');
    if (!shell) return;
    var esc = F ? F.esc : function (s) { return s; };
    shell.innerHTML =
      '<div class="vira-shell-inner vira-sh-empty" style="padding:24px 16px">' +
      '<h2 class="vira-sh-title" style="margin-bottom:12px">⚠️ ' + esc(msg) + '</h2>' +
      '<p style="font-size:.85rem;line-height:1.7;color:var(--vira-muted)">' + esc(hint || '') + '</p></div>';
    document.body.classList.add('vira-shell-ready');
  }

  function normalizeDemo(data) {
    if (!data) return;
    D = data;
    if (data.user) {
      D.user = data.user;
      D.user.codeInvitation = data.user.codeInvitation || data.invite_code || '';
      D.user.invite_link = data.user.invite_link || data.invite_link || '';
    }
    if (data.meta) D.meta = data.meta;
    if (D.meta && !D.meta.deposit_limits) {
      D.meta.deposit_limits = {
        zarinpal: { min: 5000, max: 50000000 },
        card: { min: 5000, max: 50000000 },
      };
    }
    if (!D.user.invite_link && D.meta && D.meta.bot_username && D.user.codeInvitation) {
      var b = String(D.meta.bot_username).replace(/^@/, '');
      D.user.invite_link = 'https://t.me/' + b + '?start=' + D.user.codeInvitation;
    }
    (D.products || []).forEach(function (p, i) {
      if (!p.country_id) p.country_id = ['de', 'nl', 'tr', 'fi'][i % 4];
    });
    window.VIRA_DEMO_DATA = D;
  }

  function loadDemoData(cb) {
    if (D.products && D.products.length) {
      normalizeDemo(D);
      cb();
      return;
    }
    fetch('../api/miniapp_demo.php').then(function (r) { return r.json(); }).then(function (data) {
      normalizeDemo(data);
      cb();
    }).catch(function () { cb(); });
  }

  function loadLiveData(cb) {
    var api = window.VIRA_MINIAPP_API;
    if (!api || !api.load) {
      showBootError('خطای بارگذاری', 'فایل miniapp-api.js روی سرور نیست.');
      return;
    }
    api.load().then(function (data) {
      normalizeDemo(data);
      cb();
    }).catch(function (err) {
      var code = err && err.message;
      if (code === 'NO_TELEGRAM') {
        showBootError('فقط از داخل تلگرام', 'مینی‌اپ را از دکمه فروشگاه در ربات باز کنید.');
      } else if (code && code.indexOf('Bot token not configured') >= 0) {
        showBootError('تنظیمات سرور', 'API_KEY در config.php را بررسی کنید.');
      } else if (code && code.indexOf('User verification failed') >= 0) {
        showBootError('احراز هویت ناموفق', 'توکن ربات با ربات فعلی یکی نیست.');
      } else {
        showBootError('خطا در ورود', (err && err.message) || 'دوباره از ربات باز کنید.');
      }
    });
  }

  function skeletonHtml() {
    var sk = '';
    for (var i = 0; i < 4; i++) sk += '<div class="vira-sh-skeleton"></div>';
    return '<div class="vira-shell-inner vira-scroll-y">' + sk + '</div>';
  }

  function init() {
    var root = document.getElementById('root');
    if (root) root.style.display = 'none';
    var shell = document.getElementById('vira-shell');
    if (!shell) {
      shell = document.createElement('div');
      shell.id = 'vira-shell';
      document.body.appendChild(shell);
    }
    document.body.classList.add('vira-shell-active');
    if (!window.VIRA_SHELL_FEATURES) {
      showBootError('خطای بارگذاری', 'فایل miniapp-shell-features.js یافت نشد.');
      return;
    }
    shell.innerHTML = skeletonHtml();
    (isDemo ? loadDemoData : loadLiveData)(function () { render(); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
