(function () {
  var root = document.querySelector('.broadcast-hub');
  if (!root) return;

  function base() {
    var b = document.body && document.body.getAttribute('data-panel-base');
    if (b) return b;
    var p = window.location.pathname || '';
    return p.replace(/[^/]*$/, '');
  }

  function csrf() {
    return root.getAttribute('data-csrf') || '';
  }

  function api(action, data, method) {
    method = method || 'GET';
    var url = base() + 'api/campaigns.php?action=' + encodeURIComponent(action);
    var opts = { method: method, credentials: 'same-origin' };
    if (method === 'POST') {
      var fd = new FormData();
      fd.append('_csrf', csrf());
      fd.append('action', action);
      Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
      opts.body = fd;
    }
    return fetch(url, opts).then(function (r) { return r.json(); });
  }

  var activeId = 0;
  var sending = false;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function statusTag(status, paused) {
    if (paused) return '<span class="tag tag-warn">متوقف</span>';
    if (status === 'completed') return '<span class="tag tag-ok">تکمیل</span>';
    if (status === 'sending') return '<span class="tag tag-info">در حال ارسال</span>';
    return '<span class="tag tag-plain">' + esc(status) + '</span>';
  }

  function renderCampaigns(items) {
    var el = document.getElementById('bcCampaignList');
    if (!items || !items.length) {
      el.innerHTML = '<p class="field-hint">هنوز کمپینی ثبت نشده.</p>';
      return;
    }
    el.innerHTML = items.map(function (c) {
      return '<div class="bc-campaign" data-id="' + c.id + '">' +
        '<div class="bc-campaign-head">' +
          '<strong>#' + c.id + ' — ' + esc(c.target_label) + '</strong>' +
          statusTag(c.status, c.paused) +
        '</div>' +
        '<div class="bc-campaign-text">' + esc(c.text_body) + '</div>' +
        '<div class="bc-campaign-meta">' +
          '<span>ارسال: ' + c.sent_count + '</span>' +
          '<span>خطا: ' + c.failed_count + '</span>' +
          '<span>کل: ' + c.total_recipients + '</span>' +
          '<span>' + esc(c.created_at) + '</span>' +
        '</div>' +
        '<div class="bc-mini-bar"><span style="width:' + c.progress + '%"></span></div>' +
        '<div class="broadcast-actions" style="margin-top:8px">' +
          (c.status !== 'completed' && !c.paused ? '<button type="button" class="btn btn-ghost btn-sm bc-resume-send" data-id="' + c.id + '">ادامه ارسال</button>' : '') +
          '<button type="button" class="btn btn-ghost btn-sm bc-delete" data-id="' + c.id + '">حذف</button>' +
        '</div>' +
      '</div>';
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
    el.querySelectorAll('.bc-resume-send').forEach(function (btn) {
      btn.onclick = function () {
        activeId = parseInt(btn.getAttribute('data-id'), 10);
        runCampaign(activeId);
      };
    });
  }

  function loadCampaigns() {
    api('list').then(function (d) {
      if (d.ok) renderCampaigns(d.items || []);
    });
  }

  function updateActivePanel(c) {
    var panel = document.getElementById('bcActivePanel');
    panel.classList.remove('hidden');
    document.getElementById('bcActiveTitle').textContent = 'کمپین #' + c.id;
    document.getElementById('bcActivePct').textContent = c.progress + '٪';
    document.getElementById('bcActiveBar').style.width = c.progress + '%';
    document.getElementById('bcActiveMeta').textContent =
      'ارسال شده: ' + c.offset_cursor + ' / ' + c.total_recipients +
      ' — موفق: ' + c.sent_count + ' — خطا: ' + c.failed_count;
    document.getElementById('bcPause').classList.toggle('hidden', c.paused || c.done);
    document.getElementById('bcResume').classList.toggle('hidden', !c.paused || c.done);
  }

  function runCampaign(id) {
    if (sending) return;
    sending = true;
    activeId = id;
    function step() {
      api('send_batch', { campaign_id: String(id), batch: '25' }, 'POST').then(function (d) {
        if (!d.ok) {
          sending = false;
          if (window.toast) toast(d.msg || 'خطا', 'no');
          return;
        }
        var c = d.campaign;
        updateActivePanel(c);
        loadCampaigns();
        if (!c.done && !c.paused) {
          setTimeout(step, 600);
        } else {
          sending = false;
          if (c.done && window.toast) toast('ارسال کمپین تمام شد', 'ok');
        }
      }).catch(function () { sending = false; });
    }
    step();
  }

  function preview() {
    var text = document.getElementById('bcText').value.trim() || 'متن پیام اینجا نمایش داده می‌شود…';
    document.getElementById('bcPreviewBubble').innerHTML = text;
  }

  document.getElementById('bcPreview').onclick = preview;
  document.getElementById('bcText').addEventListener('input', preview);
  document.getElementById('bcReload').onclick = loadCampaigns;

  document.getElementById('bcStart').onclick = function () {
    var text = document.getElementById('bcText').value.trim();
    if (!text) { if (window.toast) toast('متن را وارد کنید', 'no'); return; }
    if (!confirm('کمپین جدید شروع شود؟')) return;
    api('create', {
      text_body: text,
      target_type: document.getElementById('bcTarget').value,
      reply_markup_json: document.getElementById('bcMarkup').value.trim(),
      pin_after_send: document.getElementById('bcPin').checked ? '1' : '0',
    }, 'POST').then(function (d) {
      if (!d.ok) { if (window.toast) toast(d.msg || 'خطا', 'no'); return; }
      if (window.toast) toast('کمپین ایجاد شد', 'ok');
      loadCampaigns();
      runCampaign(d.campaign.id);
    });
  };

  document.getElementById('bcPause').onclick = function () {
    if (!activeId) return;
    api('pause', { campaign_id: String(activeId) }, 'POST').then(function (d) {
      if (d.ok && d.campaign) updateActivePanel(d.campaign);
      loadCampaigns();
    });
  };

  document.getElementById('bcResume').onclick = function () {
    if (!activeId) return;
    api('resume', { campaign_id: String(activeId) }, 'POST').then(function (d) {
      if (d.ok) runCampaign(activeId);
    });
  };

  preview();
  loadCampaigns();
}());
