(function () {
  'use strict';

  function csrf() {
    var m = document.querySelector('meta[name="csrf"]');
    return m ? m.content : '';
  }

  function agentFetch(url, opts) {
    opts = opts || {};
    opts.headers = opts.headers || {};
    if (opts.method === 'POST') {
      opts.headers['X-CSRF-Token'] = csrf();
    }
    return fetch(url, opts).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok && j && j.code === 'session') {
          window.location.href = 'login.php?err=session';
        }
        return j;
      });
    });
  }

  window.agentFetch = agentFetch;

  function initChart(days) {
    var canvas = document.getElementById('agent-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    agentFetch('api/dashboard.php?days=' + (days || 30)).then(function (j) {
      if (!j.ok) return;
      var d = j.data;
      if (window._agentChart) window._agentChart.destroy();
      window._agentChart = new Chart(canvas, {
        type: 'line',
        data: {
          labels: d.labels,
          datasets: [
            { label: 'فروش', data: d.revenue, borderColor: '#38bdf8', tension: 0.3 },
            { label: 'تعداد', data: d.orders, borderColor: '#a78bfa', tension: 0.3, yAxisID: 'y1' }
          ]
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } }
        }
      });
    });
  }

  document.querySelectorAll('.agent-chart-days').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.agent-chart-days').forEach(function (b) { b.classList.remove('active', 'btn-primary'); b.classList.add('btn-ghost'); });
      btn.classList.add('active', 'btn-primary');
      btn.classList.remove('btn-ghost');
      initChart(parseInt(btn.dataset.days, 10));
    });
  });
  if (document.getElementById('agent-chart')) initChart(30);

  document.querySelectorAll('[data-agent-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var action = btn.dataset.agentAction;
      var username = btn.dataset.username;
      var gb = btn.dataset.gb;
      var days = btn.dataset.days;
      var run = function () {
        var body = new URLSearchParams({ action: action, username: username, csrf: csrf() });
        if (gb) body.set('gb', gb);
        if (days) body.set('days', days);
        agentFetch('api/service_action.php', { method: 'POST', body: body }).then(function (j) {
          if (typeof toast === 'function') toast(j.msg || (j.ok ? 'انجام شد' : 'خطا'), j.ok ? 'ok' : 'no');
          else alert(j.msg);
          if (j.ok) setTimeout(function () { location.reload(); }, 800);
        });
      };
      if (typeof showConfirm === 'function') showConfirm('عملیات «' + action + '» روی ' + username + '؟', run);
      else if (confirm('ادامه؟')) run();
    });
  });

  window.agentCheckoutPreview = function (type, fields, onConfirm) {
    var body = new URLSearchParams({ action: 'preview', type: type, csrf: csrf() });
    Object.keys(fields).forEach(function (k) { body.set(k, fields[k]); });
    agentFetch('api/buy.php', { method: 'POST', body: body }).then(function (j) {
      if (!j.ok || !j.preview) { toast(j.msg || 'خطا', 'no'); return; }
      var p = j.preview;
      var html = '<p>قیمت: <strong>' + Number(p.price).toLocaleString('fa-IR') + '</strong> تومان</p>';
      html += '<p>موجودی فعلی: ' + Number(p.balance).toLocaleString('fa-IR') + '</p>';
      html += '<p>موجودی بعد: <strong>' + Number(p.after).toLocaleString('fa-IR') + '</strong></p>';
      if (p.needs_gateway) html += '<p class="notice notice-warn">نیاز به شارژ: ' + Number(p.gateway_amount).toLocaleString('fa-IR') + ' تومان</p>';
      var el = document.getElementById('checkout-body');
      if (el) el.innerHTML = html;
      openModal('checkout-modal');
      var btn = document.getElementById('checkout-confirm');
      btn.onclick = function () {
        closeModal('checkout-modal');
        if (p.needs_gateway && typeof onGateway === 'function') { onGateway(p.gateway_amount); return; }
        if (onConfirm) onConfirm();
      };
    });
  };

  window.agentBuySubmit = function (action, fields) {
    var body = new URLSearchParams(Object.assign({ action: action, csrf: csrf() }, fields));
    return agentFetch('api/buy.php', { method: 'POST', body: body }).then(function (j) {
      if (typeof toast === 'function') toast(j.msg || (j.ok ? 'موفق' : 'خطا'), j.ok ? 'ok' : 'no');
      if (j.needs_gateway) {
        agentFetch('api/buy.php', { method: 'POST', body: new URLSearchParams({ action: 'gateway_intent', amount: j.gateway_amount, csrf: csrf() }) }).then(function (g) {
          if (g.ok && g.gateways && g.gateways[0]) window.open(g.gateways[0].url + '?from_id=' + encodeURIComponent(document.body.dataset.agentId || ''), '_blank');
        });
      }
      return j;
    });
  };

  var selectAll = document.getElementById('select-all-services');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.svc-check').forEach(function (c) { c.checked = selectAll.checked; });
      updateBulkBar();
    });
  }
  document.querySelectorAll('.svc-check').forEach(function (c) { c.addEventListener('change', updateBulkBar); });

  function updateBulkBar() {
    var n = document.querySelectorAll('.svc-check:checked').length;
    var bar = document.getElementById('bulk-bar');
    if (bar) bar.classList.toggle('show', n > 0);
  }

  var bulkRenew = document.getElementById('bulk-renew');
  if (bulkRenew) {
    bulkRenew.addEventListener('click', function () {
      var usernames = Array.from(document.querySelectorAll('.svc-check:checked')).map(function (c) { return c.value; });
      var body = new URLSearchParams({ action: 'bulk_renew', usernames: JSON.stringify(usernames), csrf: csrf() });
      agentFetch('api/service_action.php', { method: 'POST', body: body }).then(function (j) {
        toast(j.msg || 'done', j.ok ? 'ok' : 'no');
        if (j.ok) location.reload();
      });
    });
  }

  var apiTest = document.getElementById('api-test-btn');
  if (apiTest) {
    apiTest.addEventListener('click', function () {
      var action = document.getElementById('api-test-action').value;
      var raw = document.getElementById('api-test-body').value;
      var token = document.getElementById('api-test-token').value;
      var body;
      try { body = raw ? JSON.parse(raw) : {}; } catch (e) { toast('JSON نامعتبر', 'no'); return; }
      body.action = action;
      fetch('../api/agent.php', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function (r) { return r.json(); }).then(function (j) {
        document.getElementById('api-test-result').textContent = JSON.stringify(j, null, 2);
      });
    });
  }
})();
