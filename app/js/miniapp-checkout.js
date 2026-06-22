/**
 * خرید در مینی‌اپ — شارژ کیف پول از طریق هدایت مستقیم به ربات
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
    var el = document.getElementById('vira-mini-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'vira-mini-toast';
      el.className = 'vira-mini-toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(function () { el.classList.remove('show'); }, 3200);
  }

  function openLink(url) {
    var tg = global.Telegram && global.Telegram.WebApp;
    if (tg && tg.openLink) {
      try { tg.openLink(url); return; } catch (e) { /* fallthrough */ }
    }
    global.open(url, '_blank');
  }

  function overlayHtml() {
    var el = document.getElementById('vira-checkout-overlay');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'vira-checkout-overlay';
    el.className = 'vira-checkout-overlay';
    el.innerHTML =
      '<div class="vira-checkout-sheet" role="dialog" aria-modal="true">' +
      '<button type="button" class="vira-checkout-close" aria-label="بستن">×</button>' +
      '<div class="vira-checkout-body"></div></div>';
    document.body.appendChild(el);
    el.addEventListener('click', function (e) {
      if (e.target === el) close();
    });
    el.querySelector('.vira-checkout-close').addEventListener('click', close);
    return el;
  }

  function close() {
    var el = document.getElementById('vira-checkout-overlay');
    if (el) el.classList.remove('open');
  }

  function setBody(html) {
    var el = overlayHtml();
    el.querySelector('.vira-checkout-body').innerHTML = html;
    el.classList.add('open');
  }

  function gatewayOn(gw, key) {
    var v = String((gw && gw[key]) || '').toLowerCase().trim();
    if (!v) return false;
    return v.indexOf('on') === 0 || v === '1' || v === 'true' ||
      v === 'onzarinpal' || v === 'oncard' || v === 'oncardpv';
  }

  function ensureMetaDefaults() {
    var D = global.VIRA_DEMO_DATA || {};
    D.meta = D.meta || {};
    D.meta.gateways = D.meta.gateways || {};
    D.meta.deposit_limits = D.meta.deposit_limits || {};
    if (!D.meta.deposit_limits.zarinpal) {
      D.meta.deposit_limits.zarinpal = { min: 5000, max: 50000000 };
    }
    if (!D.meta.deposit_limits.card) {
      D.meta.deposit_limits.card = { min: 5000, max: 50000000 };
    }
    global.VIRA_DEMO_DATA = D;
    return D.meta;
  }

  function depositLimits(gateway) {
    ensureMetaDefaults();
    var meta = (global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.meta) || {};
    var limits = (meta.deposit_limits && meta.deposit_limits[gateway]) || {};
    var min = Number(limits.min) || 5000;
    var max = Number(limits.max) || 50000000;
    if (max < min) max = min;
    return { min: min, max: max };
  }

  function clampAmount(amount, gateway) {
    var lim = depositLimits(gateway);
    var n = parseInt(amount, 10) || 0;
    if (n < lim.min) return { ok: false, amount: n, msg: 'حداقل مبلغ ' + fmt(lim.min) + ' تومان است' };
    if (n > lim.max) return { ok: false, amount: n, msg: 'حداکثر مبلغ ' + fmt(lim.max) + ' تومان است' };
    return { ok: true, amount: n, limits: lim };
  }

  function limitsHintHtml(gateway) {
    var lim = depositLimits(gateway);
    return '<p class="vira-checkout-hint">حداقل ' + fmt(lim.min) + ' — حداکثر ' + fmt(lim.max) + ' تومان</p>';
  }

  function quickAmountsHtml(gateway, selected) {
    var lim = depositLimits(gateway);
    var presets = [lim.min, 50000, 100000, 200000, 500000].filter(function (v, i, a) {
      return v >= lim.min && v <= lim.max && a.indexOf(v) === i;
    }).slice(0, 4);
    return '<div class="vira-checkout-quick">' + presets.map(function (v) {
      var on = selected === v ? ' on' : '';
      return '<button type="button" class="vira-checkout-quick-btn' + on + '" data-amount="' + v + '">' + fmt(v) + '</button>';
    }).join('') + '</div>';
  }

  function bindQuickAmounts(sheet, inputId) {
    sheet.querySelectorAll('.vira-checkout-quick-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var inp = sheet.querySelector('#' + inputId);
        if (inp) inp.value = btn.getAttribute('data-amount');
        sheet.querySelectorAll('.vira-checkout-quick-btn').forEach(function (b) {
          b.classList.toggle('on', b === btn);
        });
      });
    });
  }

  function watchReturnAndRefresh(onSuccess) {
    if (!global.VIRA_MINIAPP_API || !global.VIRA_MINIAPP_API.reloadCatalog) return;
    var done = false;
    function handler() {
      if (document.visibilityState !== 'visible' || done) return;
      done = true;
      document.removeEventListener('visibilitychange', handler);
      global.VIRA_MINIAPP_API.reloadCatalog().then(function (part) {
        var D = global.VIRA_DEMO_DATA || {};
        if (part.user) {
          D.user = D.user || {};
          D.user.balance = Number(part.user.balance || 0);
        }
        global.VIRA_DEMO_DATA = D;
        toast('موجودی به‌روز شد');
        if (typeof onSuccess === 'function') onSuccess(part);
      }).catch(function () { /* ignore */ });
    }
    document.addEventListener('visibilitychange', handler);
    setTimeout(function () {
      document.removeEventListener('visibilitychange', handler);
    }, 600000);
  }

  function runDeposit(amount, gateway, ctx, onOpened) {
    var check = clampAmount(amount, gateway);
    if (!check.ok) {
      toast(check.msg);
      return Promise.reject(new Error(check.msg));
    }
    return global.VIRA_MINIAPP_API.createDeposit(check.amount, gateway, ctx).then(function (res) {
      if (res.payment_url) {
        openLink(res.payment_url);
        if (typeof onOpened === 'function') onOpened();
        toast('درگاه باز شد — پس از پرداخت به مینی‌اپ برگردید');
      }
      return res;
    });
  }

  function openPurchase(product, ctx, isDemo, onSuccess) {
    var balance = Number((global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.user && global.VIRA_DEMO_DATA.user.balance) || 0);
    var price = Number(product.price || 0);
    var needTopup = balance < price;
    var gw = (global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.meta && global.VIRA_DEMO_DATA.meta.gateways) || {};
    var zarinOn = gatewayOn(gw, 'zarinpalstatus');
    var lim = depositLimits('zarinpal');
    var defaultTopup = Math.max(lim.min, price - balance);

    setBody(
      '<h3 class="vira-checkout-title">تأیید خرید</h3>' +
      '<div class="vira-checkout-product">' +
      '<h4>' + esc(product.name) + '</h4>' +
      '<p>' + esc(product.days) + ' روز · ' + esc(product.gb) + ' GB · ' + esc(product.panel || product.cat) + '</p>' +
      '<div class="vira-checkout-price">' + (price ? fmt(price) + ' تومان' : 'رایگان') + '</div></div>' +
      '<div class="vira-checkout-balance">موجودی شما: <strong>' + fmt(balance) + ' ت</strong></div>' +
      (needTopup
        ? '<p class="vira-checkout-warn">موجودی کافی نیست — ابتدا کیف پول را شارژ کنید.</p>' +
          '<label class="vira-checkout-label">مبلغ شارژ (تومان)</label>' +
          limitsHintHtml('zarinpal') +
          quickAmountsHtml('zarinpal', defaultTopup) +
          '<input type="number" class="vira-checkout-input" id="viraTopupAmount" min="' + lim.min + '" max="' + lim.max + '" step="1000" value="' + defaultTopup + '" />' +
          (zarinOn ? '<button type="button" class="vira-sh-btn vira-checkout-topup">پرداخت با زرین‌پال</button>' : '<p class="vira-checkout-hint">درگاه آنلاین فعال نیست.</p>')
        : '') +
      '<button type="button" class="vira-sh-btn vira-checkout-confirm" ' + (needTopup ? 'disabled' : '') + '>پرداخت از موجودی و فعال‌سازی</button>'
    );

    var sheet = document.getElementById('vira-checkout-overlay');
    bindQuickAmounts(sheet, 'viraTopupAmount');
    var confirmBtn = sheet.querySelector('.vira-checkout-confirm');
    var topupBtn = sheet.querySelector('.vira-checkout-topup');

    if (topupBtn && !isDemo) {
      topupBtn.addEventListener('click', function () {
        var amount = parseInt(sheet.querySelector('#viraTopupAmount').value, 10) || 0;
        topupBtn.disabled = true;
        topupBtn.textContent = 'در حال ساخت لینک…';
        runDeposit(amount, 'zarinpal', ctx, function () {
          watchReturnAndRefresh(onSuccess);
        }).catch(function (err) {
          toast(err.message || 'خطا');
        }).finally(function () {
          topupBtn.disabled = false;
          topupBtn.textContent = 'پرداخت با زرین‌پال';
        });
      });
    } else if (topupBtn && isDemo) {
      topupBtn.addEventListener('click', function () {
        toast('پیش‌نمایش — شارژ واقعی در تلگرام');
      });
    }

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        if (isDemo) {
          toast('پیش‌نمایش — خرید واقعی فقط با داده واقعی');
          close();
          return;
        }
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'در حال پردازش…';
        global.VIRA_MINIAPP_API.purchase(product, ctx).then(function (res) {
          var svc = res.service || {};
          setBody(
            '<h3 class="vira-checkout-title">✅ خرید موفق</h3>' +
            '<p>نام کاربری سرویس:</p><code class="vira-checkout-code">' + esc(svc.username || '—') + '</code>' +
            '<p style="font-size:.8rem;color:var(--vira-muted);margin-top:12px">سرویس در بخش «سرویس‌ها» نمایش داده می‌شود.</p>' +
            '<button type="button" class="vira-sh-btn vira-checkout-done">بستن</button>'
          );
          sheet.querySelector('.vira-checkout-done').addEventListener('click', function () {
            close();
            if (typeof onSuccess === 'function') onSuccess(res);
          });
          toast('سرویس با موفقیت فعال شد');
        }).catch(function (err) {
          toast(err.message || 'خطا در خرید');
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'پرداخت از موجودی و فعال‌سازی';
        });
      });
    }
  }

  function walletChargeHtml(idSuffix) {
    ensureMetaDefaults();
    idSuffix = idSuffix || '';
    var amountId = 'viraWalletAmount' + idSuffix;
    var cardAmountId = 'viraCardAmount' + idSuffix;
    var balance = Number((global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.user && global.VIRA_DEMO_DATA.user.balance) || 0);
    var meta = (global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.meta) || {};
    var gw = meta.gateways || {};
    var zarinOn = gatewayOn(gw, 'zarinpalstatus');
    var cardOn = gatewayOn(gw, 'Cartstatus') || gatewayOn(gw, 'cartstatus');
    var cardNum = meta.card_number || '';
    var cardHolder = meta.card_holder || '';
    var limZ = depositLimits('zarinpal');
    var defaultAmt = Math.max(limZ.min, 50000);
    if (defaultAmt > limZ.max) defaultAmt = limZ.max;

    var cardBlock = '';
    if (cardOn && cardNum) {
      var limC = depositLimits('card');
      cardBlock =
        '<div class="vira-checkout-product vira-wallet-card-block" style="margin-top:12px">' +
        '<h4>کارت به کارت</h4>' +
        limitsHintHtml('card') +
        '<code class="vira-checkout-code">' + esc(cardNum) + '</code>' +
        (cardHolder ? '<p class="vira-checkout-hint">به نام: ' + esc(cardHolder) + '</p>' : '') +
        '<label class="vira-checkout-label">مبلغ واریز (تومان)</label>' +
        '<input type="tel" inputmode="numeric" pattern="[0-9]*" class="vira-checkout-input" id="' + cardAmountId + '" min="' + limC.min + '" max="' + limC.max + '" step="1000" value="' + Math.max(limC.min, defaultAmt) + '" placeholder="مثلاً ' + fmt(limC.min) + '" />' +
        '<button type="button" class="vira-sh-btn vira-wallet-copy-card">کپی شماره کارت</button>' +
        '<p class="vira-checkout-hint">مبلغ را دقیقاً واریز کنید. پس از تأیید ادمین موجودی شارژ می‌شود.</p></div>';
    }

    return '<div class="vira-wallet-charge-form" data-wallet-suffix="' + esc(idSuffix) + '">' +
      '<div class="vira-checkout-balance">موجودی فعلی: <strong>' + fmt(balance) + ' تومان</strong></div>' +
      '<h4 class="vira-wallet-charge-title">مبلغ شارژ را انتخاب یا وارد کنید</h4>' +
      '<label class="vira-checkout-label" for="' + amountId + '">مبلغ (تومان)</label>' +
      limitsHintHtml('zarinpal') +
      quickAmountsHtml('zarinpal', defaultAmt) +
      '<input type="tel" inputmode="numeric" pattern="[0-9]*" class="vira-checkout-input vira-wallet-amount-input" id="' + amountId + '" min="' + limZ.min + '" max="' + limZ.max + '" step="1000" value="' + defaultAmt + '" placeholder="مثلاً ' + fmt(defaultAmt) + '" autocomplete="off" />' +
      (zarinOn
        ? '<button type="button" class="vira-sh-btn vira-wallet-zarin">پرداخت آنلاین (زرین‌پال)</button>'
        : '<p class="vira-checkout-hint">درگاه زرین‌پال در پنل ادمین غیرفعال است — مبلغ را وارد کنید و از کارت به کارت استفاده کنید.</p>') +
      cardBlock +
      (!zarinOn && !cardOn ? '<p class="vira-checkout-warn">هیچ درگاه پرداختی فعال نیست. از پنل → تنظیمات پرداخت، زرین‌پال یا کارت را فعال کنید.</p>' : '') +
      '</div>';
  }

  function bindWalletCharge(root, ctx, isDemo, onSuccess) {
    if (!root) return;
    var form = root.classList && root.classList.contains('vira-wallet-charge-form')
      ? root
      : root.querySelector('.vira-wallet-charge-form');
    if (!form) form = root;
    var suffix = form.getAttribute('data-wallet-suffix') || '';
    var amountId = 'viraWalletAmount' + suffix;
    var amountInput = form.querySelector('#' + amountId) || form.querySelector('.vira-wallet-amount-input');
    bindQuickAmounts(form, amountId);

    var meta = (global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.meta) || {};
    var cardNum = meta.card_number || '';
    var copyCard = form.querySelector('.vira-wallet-copy-card');
    if (copyCard && cardNum) {
      copyCard.addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(String(cardNum).replace(/\s/g, '')).then(function () {
            toast('شماره کارت کپی شد');
          });
        } else {
          toast(cardNum);
        }
      });
    }

    var zBtn = form.querySelector('.vira-wallet-zarin');
    if (zBtn) {
      zBtn.addEventListener('click', function () {
        if (isDemo) {
          toast('پیش‌نمایش — شارژ واقعی فقط از داخل تلگرام');
          return;
        }
        var amount = parseInt(amountInput && amountInput.value, 10) || 0;
        zBtn.disabled = true;
        zBtn.textContent = 'در حال ساخت لینک…';
        runDeposit(amount, 'zarinpal', ctx, function () {
          watchReturnAndRefresh(onSuccess);
        }).catch(function (err) {
          toast(err.message || 'خطا');
        }).finally(function () {
          zBtn.disabled = false;
          zBtn.textContent = 'پرداخت آنلاین (زرین‌پال)';
        });
      });
    }
  }

  function botChargeUrl() {
    var D = global.VIRA_DEMO_DATA || {};
    var bot = D.meta && D.meta.bot_username ? String(D.meta.bot_username).replace(/^@/, '') : '';
    if (!bot) return '';
    return 'https://t.me/' + bot + '?start=charge';
  }

  function botChargeRedirectHtml() {
    var url = botChargeUrl();
    if (!url) {
      return '<div class="vira-sh-empty">برای شارژ کیف پول به ربات تلگرام بروید.</div>';
    }
    return '<div class="vira-sh-card vira-bot-charge-card" style="text-align:center;padding:20px">' +
      '<div style="font-size:2rem;margin-bottom:8px">🤖</div>' +
      '<h4 style="margin:0 0 8px">شارژ کیف پول در ربات</h4>' +
      '<p class="vira-checkout-hint">پرداخت و شارژ فقط از داخل ربات تلگرام انجام می‌شود.</p>' +
      '<button type="button" class="vira-sh-btn vira-bot-charge-btn" style="margin-top:12px">رفتن به ربات برای شارژ</button></div>';
  }

  function bindBotChargeBtn(root) {
    var btn = root.querySelector('.vira-bot-charge-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var url = botChargeUrl();
      if (!url) { toast('آدرس ربات یافت نشد'); return; }
      var tg = global.Telegram && global.Telegram.WebApp;
      if (tg && tg.openTelegramLink) {
        try { tg.openTelegramLink(url); return; } catch (e) { /* fallthrough */ }
      }
      openLink(url);
    });
  }

  function mountWalletInline(container, opts) {
    if (!container) return;
    container.innerHTML = botChargeRedirectHtml();
    bindBotChargeBtn(container);
  }

  function openWallet(ctx, isDemo, onSuccess) {
    setBody('<h3 class="vira-checkout-title">شارژ کیف پول</h3>' + botChargeRedirectHtml());
    var sheet = document.getElementById('vira-checkout-overlay');
    var body = sheet && sheet.querySelector('.vira-checkout-body');
    if (body) bindBotChargeBtn(body);
  }

  function findProduct(id, countryId) {
    var list = (global.VIRA_DEMO_DATA && global.VIRA_DEMO_DATA.products) || [];
    for (var i = 0; i < list.length; i++) {
      if (String(list[i].id) === String(id) && (!countryId || String(list[i].country_id) === String(countryId))) {
        return list[i];
      }
    }
    return null;
  }

  global.VIRA_CHECKOUT = {
    openProduct: function (productId, countryId, opts) {
      opts = opts || {};
      var p = findProduct(productId, countryId);
      if (!p) {
        toast('پلن یافت نشد');
        return;
      }
      openPurchase(p, opts.ctx || (global.VIRA_MINIAPP_API && global.VIRA_MINIAPP_API.getContext()), opts.isDemo, opts.onSuccess);
    },
    openWallet: function (opts) {
      opts = opts || {};
      openWallet(opts.ctx || (global.VIRA_MINIAPP_API && global.VIRA_MINIAPP_API.getContext()), opts.isDemo, opts.onSuccess);
    },
    mountWalletInline: mountWalletInline,
    walletChargeHtml: walletChargeHtml,
    close: close,
  };
})(window);
