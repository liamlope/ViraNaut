/**
 * بارگذاری داده واقعی — همان API و فرمت درخواست اپ React
 */
(function (global) {
  var API = (global.location && global.location.origin)
    ? global.location.origin + '/api/'
    : '../api/';
  var STORAGE_TOKEN = 'vira_mini_token';
  var STORAGE_UID = 'vira_mini_user_id';

  function tg() {
    return global.Telegram && global.Telegram.WebApp;
  }

  function tgUserId() {
    var w = tg();
    if (!w || !w.initDataUnsafe || !w.initDataUnsafe.user) return 0;
    return Number(w.initDataUnsafe.user.id) || 0;
  }

  function getStored() {
    try {
      return {
        token: localStorage.getItem(STORAGE_TOKEN) || sessionStorage.getItem(STORAGE_TOKEN) || '',
        userId: Number(localStorage.getItem(STORAGE_UID) || sessionStorage.getItem(STORAGE_UID) || 0) || 0,
      };
    } catch (e) {
      return { token: '', userId: 0 };
    }
  }

  function saveSession(token, userId) {
    try {
      localStorage.setItem(STORAGE_TOKEN, token);
      localStorage.setItem(STORAGE_UID, String(userId));
      sessionStorage.setItem(STORAGE_TOKEN, token);
      sessionStorage.setItem(STORAGE_UID, String(userId));
    } catch (e) { /* ignore */ }
  }

  /** منتظر Telegram.WebApp.ready و پر شدن initData */
  function whenTelegramReady() {
    return new Promise(function (resolve) {
      var w = tg();
      if (!w) {
        resolve(null);
        return;
      }
      try {
        w.ready();
      } catch (e) { /* ignore */ }
      if (w.initData && String(w.initData).length > 0) {
        resolve(w);
        return;
      }
      var n = 0;
      var timer = setInterval(function () {
        n += 1;
        if ((w.initData && String(w.initData).length > 0) || n >= 50) {
          clearInterval(timer);
          resolve(w);
        }
      }, 80);
    });
  }

  function statusLabel(st) {
    var s = String(st || '').toLowerCase();
    if (s === 'active') return 'فعال';
    if (s.indexOf('end') >= 0) return 'منقضی / پایان';
    if (s.indexOf('hold') >= 0) return 'در انتظار';
    return st || '—';
  }

  function statusClass(st) {
    var s = String(st || '').toLowerCase();
    if (s === 'active') return 'ok';
    if (s.indexOf('end') >= 0) return 'off';
    return 'warn';
  }

  function miniGet(action, params, ctx) {
    var q = new URLSearchParams();
    q.set('actions', action);
    q.set('user_id', String(ctx.userId));
    if (params) {
      Object.keys(params).forEach(function (k) {
        if (params[k] !== undefined && params[k] !== null && params[k] !== '') {
          q.set(k, String(params[k]));
        }
      });
    }
    return fetch(API + 'miniapp?' + q.toString(), {
      headers: { Authorization: 'Bearer ' + ctx.token },
    }).then(function (r) {
      return r.json().then(function (d) {
        if (!d || d.status !== true) {
          var err = new Error((d && d.msg) || 'API error');
          err.payload = d;
          throw err;
        }
        return d;
      });
    });
  }

  /**
   * مثل React: POST /api/verify با body = JSON.stringify(initData) (رشته خام)
   */
  function verifyWithInit(init) {
    var headers = {
      'Content-Type': 'application/json',
      'X-Telegram-Init-Data': init,
    };
    return fetch(API + 'verify', {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(init),
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.status || !d.token) {
          var msg = (d && d.msg) || 'VERIFY_FAILED';
          if (msg.indexOf('User verification failed') >= 0) {
            msg = 'توکن ربات در config.php اشتباه است — با @BotFather چک کنید';
          }
          throw new Error(msg);
        }
        var uid = tgUserId();
        if (!uid) {
          throw new Error('User data is missing or malformed in init data');
        }
        saveSession(d.token, uid);
        return { token: d.token, userId: uid };
      });
  }

  function ensureContext() {
    return whenTelegramReady().then(function (w) {
      if (!w) {
        return Promise.reject(new Error('NO_TELEGRAM'));
      }
      var init = String(w.initData || '').trim();
      if (init.length > 0) {
        return verifyWithInit(init);
      }
      var unsafe = w.initDataUnsafe || {};
      if (!unsafe.user || !unsafe.user.id) {
        return Promise.reject(new Error('NO_TELEGRAM'));
      }
      var stored = getStored();
      if (stored.token && stored.userId) {
        return { token: stored.token, userId: stored.userId };
      }
      return Promise.reject(new Error('NO_TELEGRAM'));
    });
  }

  function buildInviteLink(botUsername, code) {
    var bot = String(botUsername || '').replace(/^@/, '').trim();
    var c = String(code || '').trim();
    if (!bot || !c) return '';
    return 'https://t.me/' + bot + '?start=' + c;
  }

  function mapUser(obj, tgUser, meta) {
    var code = obj.codeInvitation || '';
    var inviteLink = buildInviteLink(meta && meta.bot_username, code);
    return {
      balance: Number(obj.balance || 0),
      name: (tgUser && (tgUser.first_name || tgUser.username)) || 'کاربر',
      phone: obj.phone || '—',
      count_order: obj.count_order != null ? obj.count_order : 0,
      count_payment: obj.count_payment != null ? obj.count_payment : 0,
      group_type: obj.group_type || '—',
      time_join: obj.time_join || '—',
      codeInvitation: code,
      invite_link: inviteLink,
    };
  }

  function getService(username, ctx) {
    ctx = ctx || sessionCtx;
    return miniGet('service', { username: username }, ctx).then(function (res) {
      return res.obj || {};
    });
  }

  function getCategories(countryId, ctx) {
    ctx = ctx || sessionCtx;
    return miniGet('categories', { country_id: countryId }, ctx).then(function (res) {
      return res.obj || [];
    });
  }

  function mapProduct(p, panelName) {
    var days = Number(p.time_days || p.time_range_id || 0);
    var gb = p.traffic_gb != null ? p.traffic_gb : '—';
    return {
      id: p.id,
      name: p.name || 'پلن',
      price: Number(p.price || 0),
      days: days,
      gb: gb,
      cat: panelName || 'عمومی',
      badge: Number(p.price) === 0 ? 'رایگان' : '',
      panel: panelName,
      country_id: p.country_id,
    };
  }

  function mapService(inv) {
    return {
      id: inv.username,
      name: inv.username,
      status: statusLabel(inv.status),
      status_cls: statusClass(inv.status),
      expire: inv.expire || '—',
      traffic: inv.note || '—',
      panel: inv.note || '—',
    };
  }

  function loadProducts(ctx) {
    return miniGet('countries', null, ctx).then(function (res) {
      var panels = res.obj || [];
      if (!panels.length) {
        return { products: [], countries: [], categories: [] };
      }
      var jobs = panels.map(function (panel) {
        return miniGet('services', { country_id: panel.id, time_range_day: 0 }, ctx).then(function (pr) {
          return { panel: panel, list: pr.obj || [] };
        });
      });
      return Promise.all(jobs).then(function (bundles) {
        var products = [];
        var countries = [];
        bundles.forEach(function (b) {
          var panel = b.panel;
          countries.push({ id: panel.id, name: panel.name, flag: '🌐' });
          (b.list || []).forEach(function (p) {
            products.push(mapProduct(p, panel.name));
          });
        });
        var cats = {};
        products.forEach(function (p) {
          if (p.cat) cats[p.cat] = true;
        });
        var categories = Object.keys(cats).map(function (name, i) {
          return { id: i + 1, name: name };
        });
        return { products: products, countries: countries, categories: categories };
      });
    });
  }

  var sessionCtx = null;

  function miniPost(body, ctx) {
    var payload = {};
    Object.keys(body).forEach(function (k) { payload[k] = body[k]; });
    payload.user_id = ctx.userId;
    return fetch(API + 'miniapp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer ' + ctx.token,
      },
      body: JSON.stringify(payload),
    }).then(function (r) { return r.json(); });
  }

  function purchase(product, ctx) {
    ctx = ctx || sessionCtx;
    return miniPost({
      actions: 'purchase',
      country_id: product.country_id,
      service_id: String(product.id),
    }, ctx).then(function (d) {
      if (d && (d.success === true || d.status === true)) return d;
      throw new Error((d && (d.msg || d.message)) || 'خرید انجام نشد');
    });
  }

  function createDeposit(amount, gateway, ctx) {
    ctx = ctx || sessionCtx;
    return fetch(API + 'miniapp_deposit.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer ' + ctx.token,
      },
      body: JSON.stringify({ user_id: ctx.userId, amount: amount, gateway: gateway || 'zarinpal' }),
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.status) return d;
      throw new Error((d && d.msg) || 'خطا در ساخت پرداخت');
    });
  }

  function refreshUser(ctx) {
    ctx = ctx || sessionCtx;
    return miniGet('user_info', null, ctx).then(function (res) { return res.obj || {}; });
  }

  function reloadCatalog(ctx) {
    ctx = ctx || sessionCtx;
    return Promise.all([
      refreshUser(ctx),
      loadProducts(ctx),
      miniGet('invoices', { page: 1, limit: 10 }, ctx),
    ]).then(function (parts) {
      var rawUser = parts[0] || {};
      var meta = (global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.meta) || {};
      return {
        user: rawUser,
        invite_link: buildInviteLink(meta.bot_username, rawUser.codeInvitation),
        products: parts[1].products,
        countries: parts[1].countries,
        categories: parts[1].categories,
        services: (parts[2].obj || []).map(mapService),
      };
    });
  }

  function loadAll() {
    return ensureContext().then(function (ctx) {
      sessionCtx = ctx;
      var w = tg();
      var tgUser = w && w.initDataUnsafe && w.initDataUnsafe.user;
      return Promise.all([
        miniGet('user_info', null, ctx),
        loadProducts(ctx),
        miniGet('invoices', { page: 1, limit: 10 }, ctx),
        fetch(API + 'miniapp_meta.php').then(function (r) { return r.json(); }).catch(function () { return {}; }),
      ]).then(function (parts) {
        var userRes = parts[0];
        var catalog = parts[1];
        var invRes = parts[2];
        var meta = parts[3] || {};
        if (meta.ok !== false) {
          meta.gateways = meta.gateways || {};
        }
        return {
          user: mapUser(userRes.obj || {}, tgUser, meta),
          products: catalog.products,
          countries: catalog.countries,
          categories: catalog.categories,
          services: (invRes.obj || []).map(mapService),
          banners: meta.banners || [],
          meta: meta,
          invite_link: buildInviteLink(meta.bot_username, (userRes.obj || {}).codeInvitation),
        };
      });
    });
  }

  global.VIRA_MINIAPP_API = {
    load: loadAll,
    ensureContext: ensureContext,
    purchase: purchase,
    createDeposit: createDeposit,
    refreshUser: refreshUser,
    reloadCatalog: reloadCatalog,
    getService: getService,
    getCategories: getCategories,
    loadProducts: loadProducts,
    getContext: function () { return sessionCtx; },
  };
})(window);
