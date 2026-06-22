(function () {
  var root = document.querySelector('.bc-hub');
  if (!root) return;

  var TYPE_LABEL = { text: 'متن', photo: 'عکس', video: 'ویدیو' };
  var STYLE_LABEL = { '': 'پیش‌فرض', primary: 'آبی', success: 'سبز', danger: 'قرمز' };
  var TYPE_BTN_LABEL = { url: 'لینک (URL)', web_app: 'Mini App', callback: 'Callback' };
  var STATUS_LABEL = {
    sending: 'در حال ارسال',
    paused: 'متوقف',
    completed: 'تکمیل',
    active: 'فعال',
    deleted: 'حذف شده',
    failed: 'ناموفق',
  };

  var state = {
    messageType: 'text',
    target: 'all',
    targets: [],
    buttonRows: [],
    selectedUsers: [],
    mediaFile: null,
    mediaUrl: null,
    filter: '',
    activeId: 0,
    sending: false,
  };

  function base() {
    var b = document.body && document.body.getAttribute('data-panel-base');
    if (b) return b;
    return (window.location.pathname || '').replace(/[^/]*$/, '');
  }

  function csrf() {
    return root.getAttribute('data-csrf') || '';
  }

  function fa(n) {
    try { return Number(n).toLocaleString('fa-IR'); } catch (e) { return String(n); }
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function api(action, data, method, isForm) {
    method = method || 'GET';
    var url = base() + 'api/campaigns.php?action=' + encodeURIComponent(action);
    var opts = { method: method, credentials: 'same-origin' };
    if (method === 'POST') {
      var fd;
      if (isForm && data instanceof FormData) {
        fd = data;
      } else {
        fd = new FormData();
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
      }
      fd.append('_csrf', csrf());
      fd.append('action', action);
      opts.body = fd;
    } else if (data) {
      var qs = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
      url += '&' + qs;
    }
    return fetch(url, opts).then(function (r) { return r.json(); });
  }

  function emptyButton() {
    return { text: '', type: 'url', url: '', callback_data: '', style: '' };
  }

  function serializeButtonRows(rows) {
    if (!rows.length) return '';
    var payload = {
      rows: rows.map(function (row) {
        return row.filter(function (btn) { return btn.text.trim(); }).map(function (btn) {
          var out = { text: btn.text.trim(), type: btn.type };
          if (btn.type === 'url' || btn.type === 'web_app') out.url = (btn.url || '').trim();
          if (btn.type === 'callback') out.callback_data = (btn.callback_data || '').trim();
          if (btn.style) out.style = btn.style;
          return out;
        });
      }).filter(function (row) { return row.length; }),
    };
    return payload.rows.length ? JSON.stringify(payload) : '';
  }

  function parseButtonRows(raw) {
    if (!raw || !String(raw).trim()) return [];
    try {
      var data = JSON.parse(raw);
      if (!data.rows || !Array.isArray(data.rows)) return [];
      return data.rows.map(function (row) {
        return row.map(function (btn) {
          return {
            text: btn.text || '',
            type: btn.type || 'url',
            url: btn.url || '',
            callback_data: btn.callback_data || '',
            style: btn.style || '',
          };
        });
      });
    } catch (e) { return []; }
  }

  function btnStyleClass(style) {
    if (style === 'primary') return 'style-primary';
    if (style === 'success') return 'style-success';
    if (style === 'danger') return 'style-danger';
    return 'style-default';
  }

  function estimateMinutes(total) {
    return Math.max(1, Math.ceil((total * 0.04) / 60));
  }

  function setResult(msg, ok) {
    var el = document.getElementById('bcResult');
    el.textContent = msg;
    el.classList.remove('hidden', 'ok', 'err');
    el.classList.add(ok ? 'ok' : 'err');
  }

  function clearResult() {
    var el = document.getElementById('bcResult');
    el.classList.add('hidden');
    el.textContent = '';
  }

  function updateSendDisabled() {
    var text = document.getElementById('bcText').value.trim();
    var ok = !state.sending;
    if (state.messageType === 'text') ok = ok && !!text;
    else ok = ok && !!state.mediaFile;
    var target = state.selectedUsers.length > 0 ? 'users' : state.target;
    if (target === 'users') ok = ok && state.selectedUsers.length > 0;
    document.getElementById('bcSend').disabled = !ok;
  }

  function renderButtonEditor() {
    var wrap = document.getElementById('bcButtonRows');
    if (!state.buttonRows.length) {
      wrap.innerHTML = '<p class="bc-empty-hint">دکمه‌ای اضافه نشده — برای لینک یا Mini App «ردیف دکمه» را بزنید</p>';
      updatePreview();
      return;
    }

    wrap.innerHTML = state.buttonRows.map(function (row, ri) {
      var chips = row.map(function (btn) {
        return '<span class="bc-btn-chip ' + btnStyleClass(btn.style) + '">' + esc(btn.text.trim() || 'متن دکمه') + '</span>';
      }).join('');

      var editors = row.map(function (btn, bi) {
        var urlField = (btn.type === 'url' || btn.type === 'web_app')
          ? '<label class="bc-field bc-field-full"><span class="field-hint">' + (btn.type === 'web_app' ? 'آدرس Mini App (https)' : 'لینک (URL)') + '</span><input class="input" dir="ltr" data-row="' + ri + '" data-btn="' + bi + '" data-field="url" value="' + esc(btn.url) + '" placeholder="https://..."></label>'
          : '<label class="bc-field bc-field-full"><span class="field-hint">callback_data</span><input class="input" dir="ltr" data-row="' + ri + '" data-btn="' + bi + '" data-field="callback_data" value="' + esc(btn.callback_data) + '" placeholder="action:buy"></label>';

        var typeOpts = ['url', 'web_app', 'callback'].map(function (t) {
          return '<option value="' + t + '"' + (btn.type === t ? ' selected' : '') + '>' + TYPE_BTN_LABEL[t] + '</option>';
        }).join('');
        var styleOpts = ['', 'primary', 'success', 'danger'].map(function (s) {
          return '<option value="' + s + '"' + (btn.style === s ? ' selected' : '') + '>' + STYLE_LABEL[s] + '</option>';
        }).join('');

        return '<div class="bc-btn-edit">' +
          '<label class="bc-field bc-field-full"><span class="field-hint">متن دکمه</span><input class="input" maxlength="64" data-row="' + ri + '" data-btn="' + bi + '" data-field="text" value="' + esc(btn.text) + '"></label>' +
          '<label class="bc-field"><span class="field-hint">نوع</span><select class="input" data-row="' + ri + '" data-btn="' + bi + '" data-field="type">' + typeOpts + '</select></label>' +
          '<label class="bc-field"><span class="field-hint">رنگ</span><select class="input" data-row="' + ri + '" data-btn="' + bi + '" data-field="style">' + styleOpts + '</select></label>' +
          urlField +
          '<div class="bc-btn-edit-actions">' +
            '<button type="button" class="btn btn-ghost btn-sm" data-action="add-btn" data-row="' + ri + '"' + (row.length >= 8 ? ' disabled' : '') + '>+ دکمه کنار هم</button>' +
            (row.length > 1 ? '<button type="button" class="btn btn-ghost btn-sm" data-action="del-btn" data-row="' + ri + '" data-btn="' + bi + '">حذف دکمه</button>' : '') +
          '</div></div>';
      }).join('');

      return '<div class="bc-btn-row" data-row="' + ri + '">' +
        '<div class="bc-btn-row-head"><span class="field-hint">ردیف ' + fa(ri + 1) + '</span><button type="button" class="btn btn-ghost btn-sm" data-action="del-row" data-row="' + ri + '">حذف ردیف</button></div>' +
        '<div class="bc-btn-chips">' + chips + '</div>' + editors + '</div>';
    }).join('');

    wrap.querySelectorAll('input, select').forEach(function (el) {
      el.addEventListener('input', onButtonFieldChange);
      el.addEventListener('change', onButtonFieldChange);
    });
    wrap.querySelectorAll('[data-action]').forEach(function (btn) {
      btn.onclick = onButtonAction;
    });
    updatePreview();
  }

  function onButtonFieldChange(e) {
    var el = e.target;
    var ri = parseInt(el.getAttribute('data-row'), 10);
    var bi = parseInt(el.getAttribute('data-btn'), 10);
    var field = el.getAttribute('data-field');
    if (isNaN(ri) || isNaN(bi) || !field) return;
    state.buttonRows[ri][bi][field] = el.value;
    if (field === 'type') renderButtonEditor();
    else {
      var chips = root.querySelectorAll('.bc-btn-row[data-row="' + ri + '"] .bc-btn-chips');
      if (chips[0]) {
        chips[0].innerHTML = state.buttonRows[ri].map(function (btn) {
          return '<span class="bc-btn-chip ' + btnStyleClass(btn.style) + '">' + esc(btn.text.trim() || 'متن دکمه') + '</span>';
        }).join('');
      }
      updatePreview();
    }
  }

  function onButtonAction(e) {
    var btn = e.currentTarget;
    var action = btn.getAttribute('data-action');
    var ri = parseInt(btn.getAttribute('data-row'), 10);
    var bi = parseInt(btn.getAttribute('data-btn'), 10);
    if (action === 'add-btn') {
      state.buttonRows[ri].push(emptyButton());
    } else if (action === 'del-btn') {
      state.buttonRows[ri].splice(bi, 1);
    } else if (action === 'del-row') {
      state.buttonRows.splice(ri, 1);
    }
    renderButtonEditor();
  }

  function updatePreview() {
    var text = document.getElementById('bcText').value.trim();
    var parseMode = document.getElementById('bcParseMode').value;
    var textEl = document.getElementById('bcPreviewText');
    var mediaEl = document.getElementById('bcPreviewMedia');
    var btnEl = document.getElementById('bcPreviewButtons');
    var pinEl = document.getElementById('bcPreviewPin');

    mediaEl.innerHTML = '';
    mediaEl.classList.add('hidden');
    if (state.messageType === 'photo') {
      mediaEl.classList.remove('hidden');
      if (state.mediaUrl) mediaEl.innerHTML = '<img src="' + state.mediaUrl + '" alt="">';
      else mediaEl.innerHTML = '<div class="bc-media-placeholder">عکس انتخاب نشده</div>';
    } else if (state.messageType === 'video') {
      mediaEl.classList.remove('hidden');
      if (state.mediaUrl) mediaEl.innerHTML = '<video src="' + state.mediaUrl + '" controls muted></video>';
      else mediaEl.innerHTML = '<div class="bc-media-placeholder">ویدیو انتخاب نشده</div>';
    }

    if (!text) {
      textEl.innerHTML = '<span class="bc-placeholder">متن پیام اینجا نمایش داده می‌شود…</span>';
    } else if (parseMode === 'HTML') {
      textEl.innerHTML = text;
    } else {
      textEl.textContent = text;
    }

    var visible = state.buttonRows.map(function (row) {
      return row.filter(function (b) { return b.text.trim(); });
    }).filter(function (row) { return row.length; });

    if (!visible.length) {
      btnEl.innerHTML = '';
    } else {
      btnEl.innerHTML = visible.map(function (row) {
        return '<div class="bc-preview-btn-row">' + row.map(function (btn) {
          return '<span class="bc-preview-btn ' + btnStyleClass(btn.style) + '">' + esc(btn.text.trim()) + '</span>';
        }).join('') + '</div>';
      }).join('');
    }

    pinEl.classList.toggle('hidden', !document.getElementById('bcPin').checked);
    updateAudiencePreview();
    document.getElementById('bcCharCount').textContent = fa(text.length) + '/۴۰۹۶';
  }

  function currentTargetMeta() {
    return state.targets.find(function (t) { return t.id === state.target; }) || { id: state.target, label: state.target, count: 0 };
  }

  function userLabel(u) {
    var un = u.username && u.username !== 'none' ? '@' + u.username : '';
    return (u.first_name || 'کاربر') + (un ? ' ' + un : '') + ' · ' + u.telegram_id;
  }

  function applyUserSelection() {
    if (state.selectedUsers.length > 0) {
      state.target = 'users';
    }
    renderSelectedChips();
    renderTargetChips();
    updateSendDisabled();
    updateAudiencePreview();
  }

  function renderSelectedChips() {
    var el = document.getElementById('bcUserSelectedChips');
    if (!el) return;
    if (!state.selectedUsers.length) {
      el.innerHTML = '';
      return;
    }
    el.innerHTML = state.selectedUsers.map(function (u) {
      return '<span class="bc-selected-chip">' + esc(userLabel(u)) +
        '<button type="button" class="bc-selected-remove" data-id="' + esc(u.id) + '" title="حذف">×</button></span>';
    }).join('');
    el.querySelectorAll('.bc-selected-remove').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        state.selectedUsers = state.selectedUsers.filter(function (u) { return u.id !== id; });
        if (!state.selectedUsers.length && state.target === 'users') {
          state.target = 'all';
        }
        applyUserSelection();
      };
    });
  }

  function toggleUser(user) {
    var idx = state.selectedUsers.findIndex(function (x) { return x.id === user.id; });
    if (idx >= 0) state.selectedUsers.splice(idx, 1);
    else state.selectedUsers.push(user);
    applyUserSelection();
  }

  function renderTargetChips() {
    var list = document.getElementById('bcTargetList');
    if (!list || !state.targets.length) return;
    var activeTarget = state.selectedUsers.length > 0 ? 'users' : state.target;
    list.innerHTML = state.targets.map(function (t) {
      var active = t.id === activeTarget ? ' active' : '';
      var count = t.id === 'users' ? state.selectedUsers.length : t.count;
      return '<button type="button" class="bc-target-chip' + active + '" data-target="' + esc(t.id) + '">' +
        esc(t.label) + ' <span class="bc-target-count">(' + fa(count) + ')</span></button>';
    }).join('');
    list.querySelectorAll('.bc-target-chip').forEach(function (btn) {
      btn.onclick = function () {
        var picked = btn.getAttribute('data-target');
        if (picked !== 'users') {
          state.selectedUsers = [];
          renderSelectedChips();
          document.getElementById('bcUserResults').innerHTML = '';
        }
        state.target = picked;
        renderTargetChips();
        updateSendDisabled();
        updateAudiencePreview();
      };
    });
  }

  function updateAudiencePreview() {
    var target = state.selectedUsers.length > 0 ? 'users' : state.target;
    var meta = state.targets.find(function (t) { return t.id === target; }) || currentTargetMeta();
    var label = meta.label;
    var detail = '';
    if (target === 'users') {
      label = fa(state.selectedUsers.length) + ' کاربر انتخابی';
      detail = state.selectedUsers.length
        ? state.selectedUsers.map(function (u) { return u.telegram_id; }).join(', ')
        : 'از جعبه جستجو بالا یک کاربر انتخاب کنید';
    } else {
      var count = meta.count || 0;
      label = meta.label + ' (' + fa(count) + ' نفر)';
      if (count > 0) detail = 'تخمین زمان: حدود ' + fa(estimateMinutes(count)) + ' دقیقه';
    }
    document.getElementById('bcPreviewAudience').textContent = label;
    document.getElementById('bcPreviewAudienceDetail').textContent = detail;
  }

  function renderUserResults(users) {
    var el = document.getElementById('bcUserResults');
    if (!users.length) {
      el.innerHTML = '<p class="field-hint">نتیجه‌ای نیست — آیدی عددی، @username یا نام را امتحان کنید</p>';
      return;
    }
    el.innerHTML = users.map(function (u) {
      var on = state.selectedUsers.some(function (x) { return x.id === u.id; });
      var blocked = u.is_blocked ? ' <span class="tag tag-warn">مسدود</span>' : '';
      var un = u.username ? '@' + esc(u.username) : esc(u.telegram_id);
      return '<button type="button" class="bc-user-item' + (on ? ' selected' : '') + '" data-id="' + esc(u.id) + '">' +
        '<span>' + esc(u.first_name) + ' · ' + un + blocked + '</span>' +
        (on ? '<span class="tag tag-ok">✓</span>' : '') + '</button>';
    }).join('');
    el.querySelectorAll('.bc-user-item').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        var user = users.find(function (u) { return u.id === id; });
        if (!user) return;
        toggleUser(user);
        renderUserResults(users);
      };
    });
  }

  function searchUsers() {
    var q = document.getElementById('bcUserSearch').value.trim();
    if (!q) {
      document.getElementById('bcUserResults').innerHTML = '<p class="field-hint">عبارت جستجو را وارد کنید</p>';
      return;
    }
    api('search_users', { q: q }).then(function (d) {
      if (!d.ok) return;
      var users = d.users || [];
      if (users.length === 1 && /^\d+$/.test(q.replace(/^@/, ''))) {
        var u = users[0];
        if (!state.selectedUsers.some(function (x) { return x.id === u.id; })) {
          state.selectedUsers.push(u);
          applyUserSelection();
        }
      }
      renderUserResults(users);
    });
  }

  function statusBadge(c) {
    if (c.paused) return '<span class="tag tag-warn">متوقف</span>';
    var s = STATUS_LABEL[c.status] || c.status;
    var cls = c.status === 'completed' ? 'tag-ok' : (c.status === 'sending' ? 'tag-info' : 'tag-plain');
    return '<span class="tag ' + cls + '">' + esc(s) + '</span>';
  }

  function renderCampaigns(items) {
    var el = document.getElementById('bcCampaignList');
    if (!items || !items.length) {
      el.innerHTML = '<p class="field-hint">هنوز کمپینی ثبت نشده.</p>';
      return;
    }
    el.innerHTML = items.map(function (c) {
      var badges = '<span class="tag">' + esc(TYPE_LABEL[c.message_type] || c.message_type) + '</span>' + statusBadge(c);
      if (c.reply_markup_json) badges += '<span class="tag">دکمه شیشه‌ای</span>';
      if (c.pin_after_send) badges += '<span class="tag tag-warn">پین</span>';
      return '<article class="bc-campaign" data-id="' + c.id + '">' +
        '<div class="bc-campaign-top">' +
          '<div class="bc-campaign-badges">' + badges + '</div>' +
          '<div class="bc-campaign-actions">' +
            (c.status === 'sending' && !c.paused ? '<button type="button" class="btn btn-ghost btn-sm bc-pause" data-id="' + c.id + '">توقف</button>' : '') +
            (c.paused ? '<button type="button" class="btn btn-primary btn-sm bc-resume" data-id="' + c.id + '">ادامه</button>' : '') +
            '<button type="button" class="btn btn-ghost btn-sm bc-preview-c" data-id="' + c.id + '">پیش‌نمایش</button>' +
            '<button type="button" class="btn btn-ghost btn-sm bc-delete" data-id="' + c.id + '">حذف</button>' +
          '</div>' +
        '</div>' +
        '<p class="bc-campaign-text">' + esc(c.text_body || c.text || '—') + '</p>' +
        '<div class="bc-campaign-stats">' +
          '<div class="bc-stat ok"><strong>' + fa(c.sent_count) + '</strong><span>ارسال</span></div>' +
          '<div class="bc-stat' + (c.failed_count > 0 ? ' err' : '') + '"><strong>' + fa(c.failed_count) + '</strong><span>خطا</span></div>' +
          '<div class="bc-stat"><strong>' + fa(c.total_recipients) + '</strong><span>کل</span></div>' +
        '</div>' +
        '<p class="field-hint">' + esc(c.target_label) + ' · ' + esc(c.created_at) + '</p>' +
        '<div class="bc-mini-bar"><span style="width:' + c.progress + '%"></span></div>' +
      '</article>';
    }).join('');

    el.querySelectorAll('.bc-delete').forEach(function (btn) {
      btn.onclick = function () {
        if (!confirm('کمپین حذف شود؟')) return;
        api('delete', { campaign_id: btn.getAttribute('data-id') }, 'POST').then(function (d) {
          if (d.ok && window.toast) toast('حذف شد', 'ok');
          loadCampaigns();
        });
      };
    });
    el.querySelectorAll('.bc-pause').forEach(function (btn) {
      btn.onclick = function () { pauseCampaign(parseInt(btn.getAttribute('data-id'), 10)); };
    });
    el.querySelectorAll('.bc-resume').forEach(function (btn) {
      btn.onclick = function () { resumeCampaign(parseInt(btn.getAttribute('data-id'), 10)); };
    });
    el.querySelectorAll('.bc-preview-c').forEach(function (btn) {
      btn.onclick = function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var c = items.find(function (x) { return x.id === id; });
        if (!c) return;
        state.buttonRows = parseButtonRows(c.reply_markup_json);
        document.getElementById('bcText').value = c.text_body || c.text || '';
        document.getElementById('bcParseMode').value = c.parse_mode || 'HTML';
        document.getElementById('bcPin').checked = !!c.pin_after_send;
        renderButtonEditor();
        updatePreview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      };
    });
  }

  function loadCampaigns() {
    api('list', { filter: state.filter }).then(function (d) {
      if (d.ok) renderCampaigns(d.items || []);
    });
  }

  function loadAudienceMeta() {
    api('targets').then(function (d) {
      if (!d.ok) return;
      state.targets = d.items || [];
      if (!state.targets.some(function (t) { return t.id === state.target; })) {
        state.target = state.targets[0] ? state.targets[0].id : 'all';
      }
      renderTargetChips();
      updateAudiencePreview();
    });
  }

  function showProgress(c, eta) {
    var box = document.getElementById('bcProgressBox');
    box.classList.remove('hidden');
    document.getElementById('bcProgressTitle').textContent = 'کمپین #' + c.id;
    document.getElementById('bcProgressPct').textContent = fa(c.progress || c.percent || 0) + '٪';
    document.getElementById('bcProgressFill').style.width = (c.progress || c.percent || 0) + '%';
    document.getElementById('bcProgressMeta').textContent =
      fa(c.offset_cursor || 0) + ' از ' + fa(c.total_recipients) +
      ' — موفق: ' + fa(c.sent_count) + ' — خطا: ' + fa(c.failed_count) +
      (eta ? ' · ' + eta : '');
    document.getElementById('bcPause').classList.toggle('hidden', c.paused || c.done);
    document.getElementById('bcResume').classList.toggle('hidden', !c.paused || c.done);
  }

  function runCampaign(id) {
    if (state.sending) return;
    state.sending = true;
    state.activeId = id;
    updateSendDisabled();
    function step() {
      api('send_batch', { campaign_id: String(id), batch: '25' }, 'POST').then(function (d) {
        if (!d.ok) {
          state.sending = false;
          updateSendDisabled();
          if (window.toast) toast(d.msg || 'خطا', 'no');
          return;
        }
        var c = d.campaign;
        showProgress(c);
        loadCampaigns();
        if (!c.done && !c.paused) setTimeout(step, 500);
        else {
          state.sending = false;
          updateSendDisabled();
          if (c.done) {
            setResult('ارسال تمام شد: ' + fa(c.sent_count) + ' موفق، ' + fa(c.failed_count) + ' ناموفق', true);
            if (window.toast) toast('ارسال کمپین تمام شد', 'ok');
          }
        }
      }).catch(function () { state.sending = false; updateSendDisabled(); });
    }
    step();
  }

  function pauseCampaign(id) {
    api('pause', { campaign_id: String(id) }, 'POST').then(function (d) {
      if (d.ok && d.campaign) showProgress(d.campaign);
      loadCampaigns();
    });
  }

  function resumeCampaign(id) {
    api('resume', { campaign_id: String(id) }, 'POST').then(function (d) {
      if (d.ok) runCampaign(id);
    });
  }

  function sendCampaign() {
    clearResult();
    var fd = new FormData();
    fd.append('text', document.getElementById('bcText').value);
    fd.append('message_type', state.messageType);
    fd.append('target', state.selectedUsers.length > 0 ? 'users' : state.target);
    fd.append('parse_mode', document.getElementById('bcParseMode').value);
    fd.append('disable_web_page_preview', document.getElementById('bcDisablePreview').checked ? '1' : '0');
    fd.append('pin_after_send', document.getElementById('bcPin').checked ? '1' : '0');
    fd.append('auto_send_new_users', document.getElementById('bcAutoSend').checked ? '1' : '0');
    fd.append('auto_send_delay_minutes', document.getElementById('bcAutoDelay').value || '5');
    var buttons = serializeButtonRows(state.buttonRows);
    if (buttons) fd.append('buttons_json', buttons);
    if (state.selectedUsers.length > 0) {
      fd.append('user_ids', state.selectedUsers.map(function (u) { return u.telegram_id; }).join(','));
    }
    if (state.mediaFile) fd.append('media', state.mediaFile);

    state.sending = true;
    updateSendDisabled();
    document.getElementById('bcSend').textContent = 'در حال ارسال…';

    api('create', fd, 'POST', true).then(function (d) {
      document.getElementById('bcSend').textContent = 'ارسال';
      if (!d.ok) {
        state.sending = false;
        updateSendDisabled();
        setResult(d.msg || 'خطا', false);
        if (window.toast) toast(d.msg || 'خطا', 'no');
        return;
      }
      var total = d.total || (d.campaign && d.campaign.total_recipients) || 0;
      setResult('کمپین ایجاد شد — تخمین: حدود ' + fa(estimateMinutes(total)) + ' دقیقه', true);
      document.getElementById('bcText').value = '';
      state.buttonRows = [];
      state.selectedUsers = [];
      state.target = 'all';
      document.getElementById('bcUserSearch').value = '';
      document.getElementById('bcUserResults').innerHTML = '';
      renderSelectedChips();
      renderTargetChips();
      state.mediaFile = null;
      if (state.mediaUrl) URL.revokeObjectURL(state.mediaUrl);
      state.mediaUrl = null;
      document.getElementById('bcMedia').value = '';
      renderButtonEditor();
      updatePreview();
      loadCampaigns();
      runCampaign(d.campaign_id || d.campaign.id);
    }).catch(function () {
      state.sending = false;
      updateSendDisabled();
      document.getElementById('bcSend').textContent = 'ارسال';
    });
  }

  // Event bindings
  document.getElementById('bcTypeSeg').querySelectorAll('.bc-seg-btn').forEach(function (btn) {
    btn.onclick = function () {
      state.messageType = btn.getAttribute('data-type');
      document.getElementById('bcTypeSeg').querySelectorAll('.bc-seg-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var mediaWrap = document.getElementById('bcMediaWrap');
      var mediaInput = document.getElementById('bcMedia');
      var isMedia = state.messageType !== 'text';
      mediaWrap.classList.toggle('hidden', !isMedia);
      document.getElementById('bcTextLabel').textContent = isMedia ? 'کپشن / توضیح' : 'متن پیام';
      document.getElementById('bcMediaLabel').textContent = state.messageType === 'photo' ? 'فایل عکس' : 'فایل ویدیو';
      mediaInput.accept = state.messageType === 'photo' ? 'image/*' : 'video/*';
      updateSendDisabled();
      updatePreview();
    };
  });

  document.getElementById('bcAddRow').onclick = function () {
    state.buttonRows.push([emptyButton()]);
    renderButtonEditor();
  };

  document.getElementById('bcText').addEventListener('input', function (e) {
    if (e.target.value.length > 4096) e.target.value = e.target.value.slice(0, 4096);
    updatePreview();
    updateSendDisabled();
  });

  ['bcParseMode', 'bcPin', 'bcDisablePreview'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', updatePreview);
  });

  document.getElementById('bcMedia').addEventListener('change', function (e) {
    var file = e.target.files && e.target.files[0];
    if (state.mediaUrl) URL.revokeObjectURL(state.mediaUrl);
    state.mediaFile = file || null;
    state.mediaUrl = file ? URL.createObjectURL(file) : null;
    updatePreview();
    updateSendDisabled();
  });

  document.getElementById('bcAutoSend').addEventListener('change', function () {
    var on = document.getElementById('bcAutoSend').checked;
    document.getElementById('bcAutoDelay').disabled = !on;
    document.getElementById('bcAutoSendLabel').textContent = on ? 'فعال' : 'غیرفعال';
  });

  document.getElementById('bcUserSearchBtn').onclick = searchUsers;
  document.getElementById('bcUserSearch').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') searchUsers();
  });

  document.getElementById('bcSend').onclick = sendCampaign;
  document.getElementById('bcPause').onclick = function () { if (state.activeId) pauseCampaign(state.activeId); };
  document.getElementById('bcResume').onclick = function () { if (state.activeId) resumeCampaign(state.activeId); };
  document.getElementById('bcReloadAll').onclick = function () { loadCampaigns(); loadAudienceMeta(); };

  document.getElementById('bcFilterTabs').querySelectorAll('button').forEach(function (btn) {
    btn.onclick = function () {
      state.filter = btn.getAttribute('data-filter') || '';
      document.getElementById('bcFilterTabs').querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      loadCampaigns();
    };
  });

  renderButtonEditor();
  updatePreview();
  updateSendDisabled();
  loadAudienceMeta();
  loadCampaigns();
}());
