/**
 * قابلیت‌های مشترک مینی‌اپ — فیلتر، جستجو، سرویس، دعوت، pull-refresh
 */
(function (global) {
  function fmt(n) {
    return Number(n || 0).toLocaleString('fa-IR');
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg) {
    var el = document.getElementById('mirza-mini-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'mirza-mini-toast';
      el.className = 'mirza-mini-toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(function () { el.classList.remove('show'); }, 2800);
  }

  function tgUser() {
    var w = global.Telegram && global.Telegram.WebApp;
    return w && w.initDataUnsafe && w.initDataUnsafe.user ? w.initDataUnsafe.user : null;
  }

  function filterProducts(D, state) {
    var list = (D.products || []).slice();
    if (state.countryId && state.countryId !== 'all') {
      list = list.filter(function (p) { return String(p.country_id) === String(state.countryId); });
    }
    if (state.search) {
      var q = String(state.search).toLowerCase();
      list = list.filter(function (p) {
        return (p.name || '').toLowerCase().indexOf(q) >= 0 ||
          (p.cat || '').toLowerCase().indexOf(q) >= 0 ||
          (p.panel || '').toLowerCase().indexOf(q) >= 0;
      });
    }
    if (state.page === 'offers') {
      list = list.filter(function (p) { return p.badge && String(p.badge).length > 0; });
    }
    if (state.sort === 'price_asc') list.sort(function (a, b) { return (a.price || 0) - (b.price || 0); });
    if (state.sort === 'price_desc') list.sort(function (a, b) { return (b.price || 0) - (a.price || 0); });
    if (state.sort === 'gb_desc') list.sort(function (a, b) { return Number(b.gb || 0) - Number(a.gb || 0); });
    return list;
  }

  function filterServices(D, state) {
    var list = (D.services || []).slice();
    if (!state.serviceFilter || state.serviceFilter === 'all') return list;
    return list.filter(function (s) { return s.status_cls === state.serviceFilter; });
  }

  function balanceBarHtml(D, extra) {
    return '<div class="mirza-sh-balance-bar">' +
      '<span>موجودی</span><strong>' + fmt((D.user || {}).balance) + ' ت</strong>' +
      (extra || '') + '</div>';
  }

  function countryChipsHtml(D, state) {
    var countries = D.countries || [];
    if (!countries.length) return '';
    var html = '<div class="mirza-sh-country-chips">';
    html += '<button type="button" class="mirza-sh-chip' + (state.countryId === 'all' || !state.countryId ? ' on' : '') + '" data-country="all">همه</button>';
    countries.forEach(function (c) {
      var on = String(state.countryId) === String(c.id) ? ' on' : '';
      html += '<button type="button" class="mirza-sh-chip' + on + '" data-country="' + esc(c.id) + '">' +
        esc(c.flag || '🌐') + ' ' + esc(c.name) + '</button>';
    });
    html += '</div>';
    return html;
  }

  function searchHtml(state, placeholder) {
    var v = esc(state.search || '');
    return '<input type="search" class="mirza-sh-search mirza-sh-search-live" placeholder="' + esc(placeholder || 'جستجو…') + '" value="' + v + '" />';
  }

  function productBuyBtn(p, label) {
    return '<button type="button" class="mirza-sh-btn mirza-sh-buy" data-product-id="' + esc(p.id) + '" data-country-id="' + esc(p.country_id || '') + '">' + (label || 'خرید') + '</button>';
  }

  function productCardsHtml(products, layout) {
    var html = '';
    products.forEach(function (p) {
      if (layout === 'stack') {
        html += '<div class="mirza-sh-card mirza-sh-card-full" data-product-id="' + esc(p.id) + '" data-country-id="' + esc(p.country_id || '') + '">' +
          (p.badge ? '<span class="mirza-sh-badge">' + esc(p.badge) + '</span>' : '') +
          '<div class="mirza-sh-card-row"><div><h4>' + esc(p.name) + '</h4>' +
          '<p class="mirza-sh-meta">' + esc(p.days) + ' روز · ' + esc(p.gb) + ' GB · ' + esc(p.cat) + '</p></div>' +
          '<div class="mirza-sh-price-lg">' + (p.price ? fmt(p.price) + ' ت' : 'رایگان') + '</div></div>' +
          productBuyBtn(p, 'خرید پلن') + '</div>';
      } else if (layout === 'list') {
        html += '<div class="mirza-sh-list-row" data-product-id="' + esc(p.id) + '" data-country-id="' + esc(p.country_id || '') + '">' +
          '<span class="flag">📦</span>' +
          '<div class="meta"><h4>' + esc(p.name) + '</h4><p>' + esc(p.days) + ' روز · ' + esc(p.gb) + ' GB</p></div>' +
          '<div class="price-col">' + (p.price ? fmt(p.price) + ' ت' : 'رایگان') + '</div>' +
          productBuyBtn(p, 'خرید') + '</div>';
      } else if (layout === 'large') {
        var disc = p.discount_pct ? '<span class="mirza-sh-discount">' + esc(p.discount_pct) + '% تخفیف</span>' : '';
        html += '<div class="mirza-sh-plan-lg' + (p.featured ? ' featured' : '') + '" data-product-id="' + esc(p.id) + '" data-country-id="' + esc(p.country_id || '') + '">' +
          (p.badge ? '<span class="mirza-sh-badge">' + esc(p.badge) + '</span>' : '') + disc +
          '<h4>' + esc(p.name) + '</h4>' +
          '<p class="mirza-sh-meta">' + esc(p.days) + ' روز · ' + esc(p.gb) + ' گیگابایت</p>' +
          '<div class="price">' + (p.price ? fmt(p.price) + ' تومان' : 'رایگان') + '</div>' +
          productBuyBtn(p, 'خرید این پلن') + '</div>';
      } else {
        html += '<div class="mirza-sh-card" data-product-id="' + esc(p.id) + '" data-country-id="' + esc(p.country_id || '') + '">' +
          (p.badge ? '<span class="mirza-sh-badge">' + esc(p.badge) + '</span>' : '') +
          '<h4>' + esc(p.name) + '</h4>' +
          '<p class="mirza-sh-meta">' + esc(p.days) + ' روز · ' + esc(p.gb) + 'G</p>' +
          '<div class="price">' + (p.price ? fmt(p.price) + ' ت' : 'رایگان') + '</div>' +
          productBuyBtn(p, 'خرید') + '</div>';
      }
    });
    return html || '<div class="mirza-sh-empty">پلنی یافت نشد.</div>';
  }

  function servicesListHtml(D, state, withDetail) {
    var html = '';
    filterServices(D, state || {}).forEach(function (s) {
      html += '<div class="mirza-sh-list-row mirza-sh-service-row" data-service-user="' + esc(s.name) + '">' +
        '<span class="flag">' + (s.status_cls === 'ok' ? '🟢' : s.status_cls === 'warn' ? '🟡' : '⚪') + '</span>' +
        '<div class="meta"><h4>' + esc(s.name) + '</h4><p>' + esc(s.panel) + ' · انقضا ' + esc(s.expire) + '</p></div>' +
        (withDetail !== false ? '<button type="button" class="mirza-sh-btn mirza-sh-service-detail" data-username="' + esc(s.name) + '" style="width:auto;padding:8px 12px;font-size:.7rem">جزئیات</button>' : '') +
        '</div>';
    });
    return html || '<div class="mirza-sh-empty">سرویس فعالی نیست.</div>';
  }

  function servicesCardsHtml(D, state) {
    var html = '<div class="mirza-sh-service-cards">';
    filterServices(D, state || {}).forEach(function (s) {
      var pct = s.traffic_pct != null ? s.traffic_pct : 50;
      html += '<div class="mirza-sh-svc-card" data-service-user="' + esc(s.name) + '">' +
        '<div class="mirza-sh-svc-head"><strong>' + esc(s.name) + '</strong>' +
        '<span class="mirza-sh-pill ' + esc(s.status_cls) + '">' + esc(s.status) + '</span></div>' +
        '<div class="mirza-sh-progress"><span style="width:' + pct + '%"></span></div>' +
        '<p class="mirza-sh-meta">انقضا: ' + esc(s.expire) + '</p>' +
        '<button type="button" class="mirza-sh-btn mirza-sh-service-detail" data-username="' + esc(s.name) + '">جزئیات و کپی</button></div>';
    });
    html += '</div>';
    return html || '<div class="mirza-sh-empty">سرویس فعالی نیست.</div>';
  }

  function accountHtml(D, compact, withProfile) {
    var u = D.user || {};
    var tg = tgUser();
    var head = '';
    if (withProfile && tg) {
      var photo = tg.photo_url ? '<img src="' + esc(tg.photo_url) + '" alt="" class="mirza-sh-avatar" />' : '<span class="mirza-sh-avatar-ph">👤</span>';
      head = '<div class="mirza-sh-profile-head">' + photo +
        '<div><h3>' + esc(tg.first_name || 'کاربر') + '</h3><p class="mirza-sh-meta">@' + esc(tg.username || '—') + '</p></div></div>';
    }
    var card = '<div class="mirza-sh-account-card">' +
      '<div class="mirza-sh-kv"><span>موجودی</span><strong>' + fmt(u.balance) + ' ت</strong></div>' +
      '<div class="mirza-sh-kv"><span>سرویس فعال</span><span>' + esc(u.count_order) + '</span></div>' +
      '<div class="mirza-sh-kv"><span>پرداخت‌ها</span><span>' + esc(u.count_payment) + '</span></div>' +
      '<div class="mirza-sh-kv"><span>عضویت</span><span>' + esc(u.time_join) + '</span></div></div>';
    if (compact) return head + card;
    return head + '<div class="mirza-sh-stats">' +
      '<div class="mirza-sh-stat"><b>' + fmt(u.balance) + '</b><span>موجودی</span></div>' +
      '<div class="mirza-sh-stat"><b>' + esc(u.count_order) + '</b><span>سرویس</span></div>' +
      '<div class="mirza-sh-stat"><b>' + esc(u.count_payment) + '</b><span>پرداخت</span></div></div>' + card;
  }

  function inviteLink(D) {
    if (D.user && D.user.invite_link) return D.user.invite_link;
    if (D.invite_link) return D.invite_link;
    var code = (D.user && D.user.codeInvitation) || D.invite_code || '';
    var bot = (D.meta && D.meta.bot_username) || '';
    bot = String(bot).replace(/^@/, '').trim();
    if (!bot || !code) {
      if (isDemo() && code) return 'https://t.me/mirzabot_demo?start=' + code;
      return '';
    }
    return 'https://t.me/' + bot + '?start=' + code;
  }

  function inviteHtml(D) {
    var link = inviteLink(D);
    var code = (D.user && D.user.codeInvitation) || D.invite_code || '';
    if (!link && !code && isDemo()) {
      link = 'https://t.me/mirzabot_demo?start=MIRZA-DEMO';
      code = 'MIRZA-DEMO';
    }
    return '<div class="mirza-sh-card mirza-sh-invite">' +
      '<h4>دعوت دوستان</h4>' +
      '<p class="mirza-sh-meta">لینک اختصاصی ربات (همان لینک دعوت تلگرام):</p>' +
      '<code class="mirza-checkout-code mirza-sh-invite-link">' + esc(link || '—') + '</code>' +
      (code ? '<p class="mirza-sh-meta" style="margin-top:6px">کد: <strong>' + esc(code) + '</strong></p>' : '') +
      '<button type="button" class="mirza-sh-btn mirza-sh-invite-copy">کپی لینک دعوت</button>' +
      '<button type="button" class="mirza-sh-btn mirza-sh-invite-share" style="margin-top:8px;background:var(--mirza-surface-2);color:var(--mirza-text)">اشتراک‌گذاری</button></div>';
  }

  function isDemo() {
    return document.documentElement.getAttribute('data-demo-mode') === '1';
  }

  function navBottom(tabs, state) {
    return '<nav class="mirza-sh-nav-bottom">' + tabs.map(function (t) {
      return '<button type="button" data-page="' + t.k + '" class="' + (state.page === t.k ? 'on' : '') + '">' +
        '<span class="ico">' + t.ico + '</span><span>' + t.l + '</span></button>';
    }).join('') + '</nav>';
  }

  function openServiceDetail(username, isDemo) {
    if (!username) return;
    if (isDemo) {
      toast('پیش‌نمایش — جزئیات سرویس در حالت واقعی');
      return;
    }
    var api = global.MIRZA_MINIAPP_API;
    if (!api || !api.getService) {
      toast('در حال بارگذاری…');
      return;
    }
    if (!document.getElementById('mirza-checkout-overlay')) {
      var ov = document.createElement('div');
      ov.id = 'mirza-checkout-overlay';
      ov.className = 'mirza-checkout-overlay';
      ov.innerHTML =
        '<div class="mirza-checkout-sheet" role="dialog">' +
        '<button type="button" class="mirza-checkout-close" aria-label="بستن">×</button>' +
        '<div class="mirza-checkout-body"></div></div>';
      document.body.appendChild(ov);
      ov.addEventListener('click', function (e) {
        if (e.target === ov) ov.classList.remove('open');
      });
      ov.querySelector('.mirza-checkout-close').addEventListener('click', function () {
        ov.classList.remove('open');
      });
    }
    api.getService(username).then(function (obj) {
      var links = '';
      (obj.service_output || []).forEach(function (c, i) {
        if (c.type === 'link' && c.value) links += '<button type="button" class="mirza-sh-btn mirza-sh-copy-link" data-copy="' + esc(c.value) + '">کپی لینک اشتراک</button>';
        if (c.type === 'config' && c.value) {
          (Array.isArray(c.value) ? c.value : [c.value]).forEach(function (l, j) {
            links += '<button type="button" class="mirza-sh-btn mirza-sh-copy-link" data-copy="' + esc(l) + '">کپی کانفیگ ' + (j + 1) + '</button>';
          });
        }
      });
      var body = document.getElementById('mirza-checkout-overlay');
      if (!body) return;
      var sheet = body.querySelector('.mirza-checkout-body');
      if (!sheet) return;
      body.classList.add('open');
      sheet.innerHTML =
        '<h3 class="mirza-checkout-title">جزئیات سرویس</h3>' +
        '<p><strong>' + esc(obj.username) + '</strong></p>' +
        '<div class="mirza-sh-kv"><span>حجم کل</span><span>' + esc(obj.total_traffic_gb) + ' GB</span></div>' +
        '<div class="mirza-sh-kv"><span>مصرف</span><span>' + esc(obj.used_traffic_gb) + ' GB</span></div>' +
        '<div class="mirza-sh-kv"><span>باقی‌مانده</span><span>' + esc(obj.remaining_traffic_gb) + ' GB</span></div>' +
        '<div class="mirza-sh-kv"><span>انقضا</span><span>' + esc(obj.expiration_time) + '</span></div>' +
        '<div class="mirza-sh-kv"><span>وضعیت</span><span>' + esc(obj.status) + '</span></div>' +
        links +
        '<button type="button" class="mirza-sh-btn mirza-checkout-done" style="margin-top:12px">بستن</button>';
      sheet.querySelector('.mirza-checkout-done').addEventListener('click', function () {
        body.classList.remove('open');
      });
      sheet.querySelectorAll('.mirza-sh-copy-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var t = btn.getAttribute('data-copy');
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(function () { toast('کپی شد'); });
          } else {
            toast(t);
          }
        });
      });
    }).catch(function (e) {
      toast((e && e.message) || 'خطا در دریافت سرویس');
    });
  }

  function bindPullRefresh(shell, onRefresh) {
    var startY = 0;
    var inner = shell.querySelector('.mirza-shell-inner');
    if (!inner) return;
    inner.addEventListener('touchstart', function (e) {
      if (inner.scrollTop <= 0) startY = e.touches[0].clientY;
    }, { passive: true });
    inner.addEventListener('touchend', function (e) {
      if (startY && e.changedTouches[0].clientY - startY > 80 && inner.scrollTop <= 0) {
        onRefresh();
      }
      startY = 0;
    }, { passive: true });
  }

  function bindContainer(container, opts) {
    opts = opts || {};
    var D = opts.D || global.MIRZA_DEMO_DATA || {};
    var state = opts.state;
    var isDemo = opts.isDemo;
    var render = opts.render;

    container.querySelectorAll('[data-page]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.page = btn.getAttribute('data-page');
        if (render) render();
      });
    });

    container.querySelectorAll('[data-country]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.countryId = btn.getAttribute('data-country');
        if (render) render();
      });
    });

    container.querySelectorAll('[data-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var f = btn.getAttribute('data-filter');
        if (btn.hasAttribute('data-svc-filter')) state.serviceFilter = f;
        else state.filter = f;
        if (render) render();
      });
    });

    container.querySelectorAll('.mirza-sh-search-live').forEach(function (inp) {
      inp.addEventListener('input', function () {
        state.search = inp.value;
        if (render) render();
      });
    });

    container.querySelectorAll('[data-sort]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.sort = btn.getAttribute('data-sort');
        if (render) render();
      });
    });

    container.querySelectorAll('.mirza-sh-invite-copy').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var link = inviteLink(D);
        if (!link) {
          toast('لینک دعوت در دسترس نیست — نام ربات در تنظیمات سرور را بررسی کنید');
          return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(function () { toast('لینک دعوت کپی شد'); });
        } else {
          toast(link);
        }
      });
    });

    container.querySelectorAll('.mirza-sh-invite-share').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var link = inviteLink(D);
        if (!link) {
          toast('لینک دعوت در دسترس نیست');
          return;
        }
        var text = 'با این لینک وارد ربات شوید و از سرویس VPN استفاده کنید:\n' + link;
        if (navigator.share) {
          navigator.share({ title: 'دعوت به ربات', text: text, url: link }).catch(function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(link).then(function () { toast('لینک کپی شد'); });
            }
          });
        } else if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(function () { toast('لینک دعوت کپی شد'); });
        } else {
          toast(link);
        }
      });
    });

    container.querySelectorAll('.mirza-sh-support-link').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var sid = (D.meta && D.meta.support_id) || '';
        if (!sid) { toast('پشتیبانی تنظیم نشده'); return; }
        var url = 'https://t.me/' + String(sid).replace('@', '');
        var tg = global.Telegram && global.Telegram.WebApp;
        if (tg && tg.openLink) tg.openLink(url);
        else global.open(url, '_blank');
      });
    });

    container.querySelectorAll('.mirza-sh-service-detail').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        openServiceDetail(btn.getAttribute('data-username'), isDemo);
      });
    });

    container.querySelectorAll('.mirza-sh-wallet-open').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (global.MIRZA_CHECKOUT) {
          global.MIRZA_CHECKOUT.openWallet({
            isDemo: isDemo,
            ctx: global.MIRZA_MINIAPP_API && global.MIRZA_MINIAPP_API.getContext(),
            onSuccess: opts.afterPurchase,
          });
        }
      });
    });

    if (opts.afterPurchase) {
      function openBuyFromEl(el) {
        var id = el.getAttribute('data-product-id');
        var cid = el.getAttribute('data-country-id');
        if (!id || !global.MIRZA_CHECKOUT) return;
        global.MIRZA_CHECKOUT.openProduct(id, cid, {
          isDemo: isDemo,
          ctx: global.MIRZA_MINIAPP_API && global.MIRZA_MINIAPP_API.getContext(),
          onSuccess: opts.afterPurchase,
        });
      }
      container.querySelectorAll('.mirza-sh-buy').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          openBuyFromEl(btn);
        });
      });
      container.querySelectorAll('[data-product-id]').forEach(function (row) {
        if (row.classList.contains('mirza-sh-card') || row.classList.contains('mirza-sh-plan-lg') ||
            row.classList.contains('mirza-sh-list-row') || row.classList.contains('mirza-sh-card-full')) {
          row.addEventListener('click', function (e) {
            if (e.target.closest('.mirza-sh-buy')) return;
            openBuyFromEl(row);
          });
        }
      });
    }
  }

  global.MIRZA_SHELL_FEATURES = {
    fmt: fmt,
    esc: esc,
    toast: toast,
    filterProducts: filterProducts,
    filterServices: filterServices,
    balanceBarHtml: balanceBarHtml,
    countryChipsHtml: countryChipsHtml,
    searchHtml: searchHtml,
    productCardsHtml: productCardsHtml,
    productBuyBtn: productBuyBtn,
    servicesListHtml: servicesListHtml,
    servicesCardsHtml: servicesCardsHtml,
    accountHtml: accountHtml,
    inviteLink: inviteLink,
    inviteHtml: inviteHtml,
    navBottom: navBottom,
    bindContainer: bindContainer,
    bindPullRefresh: bindPullRefresh,
    openServiceDetail: openServiceDetail,
  };
})(window);
