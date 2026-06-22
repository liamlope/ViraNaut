(function () {
  var salesCanvas = document.getElementById('dashboardChart');
  var userCanvas = document.getElementById('userGrowthChart');
  var healthEl = document.getElementById('healthGrid');
  var userChartMode = 'bar';
  var userChartDays = 30;
  var userGrowthData = null;

  function ensureChartJs(cb) {
    if (typeof Chart !== 'undefined') {
      cb();
      return;
    }
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
    s.onload = cb;
    document.head.appendChild(s);
  }

  function fmt(n) {
    try { return Number(n || 0).toLocaleString('fa-IR'); } catch (e) { return String(n || 0); }
  }

  function loadSalesChart() {
    if (!salesCanvas) return;
    fetch('api/dashboard.php?days=14', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        renderHealth(data.health || {});
        drawSalesChart(data);
      })
      .catch(function () {});
  }

  function renderHealth(h) {
    if (!healthEl) return;
    var items = [
      ['پایگاه داده', h.db ? 'متصل' : 'خطا', h.db ? 'ok' : 'no'],
      ['ربات', h.bot || '—', 'info'],
      ['کران', h.cron || '—', 'info'],
      ['پرداخت معلق', String(h.pending_pay || 0), h.pending_pay > 0 ? 'warn' : 'ok'],
    ];
    healthEl.innerHTML = items.map(function (x) {
      return '<div class="health-pill"><span>' + x[0] + '</span><strong class="tag tag-' + x[2] + '">' + x[1] + '</strong></div>';
    }).join('');
  }

  function drawSalesChart(data) {
    var ctx = salesCanvas.getContext('2d');
    if (window._dashSalesChart) window._dashSalesChart.destroy();
    window._dashSalesChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels || [],
        datasets: [
          { label: 'سفارش', data: data.orders || [], backgroundColor: 'rgba(34,211,238,.55)', borderRadius: 4 },
          { label: 'درآمد (هزار ت)', data: (data.revenue || []).map(function (v) { return Math.round(v / 1000); }), type: 'line', borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.15)', tension: 0.3, yAxisID: 'y1' },
        ],
      },
      options: chartOptions(),
    });
  }

  function chartOptions() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { font: { family: 'Vazirmatn' } } } },
      scales: {
        y: { beginAtZero: true, ticks: { font: { family: 'Vazirmatn' } } },
        y1: { position: 'left', beginAtZero: true, grid: { drawOnChartArea: false } },
      },
    };
  }

  function loadUserSummary() {
    fetch('api/stats.php?action=summary', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.summary) return;
        var s = d.summary;
        var map = {
          usTotal: s.total_users,
          usToday: s.users_today,
          us7d: s.users_7d,
          us30d: s.users_30d,
          usActive: s.active_users,
          usBlocked: s.blocked_users,
        };
        Object.keys(map).forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.textContent = fmt(map[id]);
        });
      })
      .catch(function () {});
  }

  function loadUserGrowth() {
    if (!userCanvas) return;
    fetch('api/stats.php?action=growth&days=' + userChartDays, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.growth) return;
        userGrowthData = d.growth;
        drawUserGrowthChart();
      })
      .catch(function () {});
  }

  function drawUserGrowthChart() {
    if (!userCanvas || !userGrowthData) return;
    var ctx = userCanvas.getContext('2d');
    if (window._dashUserChart) window._dashUserChart.destroy();
    var counts = userGrowthData.counts || [];
    var cumulative = userGrowthData.cumulative || [];
    var datasets;
    if (userChartMode === 'line') {
      datasets = [
        { label: 'عضو جدید', data: counts, type: 'line', borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,.12)', tension: 0.35, fill: true },
        { label: 'مجموع تجمعی', data: cumulative, type: 'line', borderColor: '#34d399', backgroundColor: 'rgba(52,211,153,.08)', tension: 0.35, yAxisID: 'y1' },
      ];
    } else {
      datasets = [
        { label: 'عضو جدید', data: counts, backgroundColor: 'rgba(139,92,246,.65)', borderRadius: 4 },
        { label: 'مجموع تجمعی', data: cumulative, type: 'line', borderColor: '#34d399', backgroundColor: 'rgba(52,211,153,.1)', tension: 0.3, yAxisID: 'y1' },
      ];
    }
    window._dashUserChart = new Chart(ctx, {
      type: userChartMode === 'line' ? 'line' : 'bar',
      data: { labels: userGrowthData.labels || [], datasets: datasets },
      options: chartOptions(),
    });
  }

  var modeTabs = document.getElementById('userChartModeTabs');
  if (modeTabs) {
    modeTabs.querySelectorAll('button').forEach(function (btn) {
      btn.onclick = function () {
        modeTabs.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        userChartMode = btn.getAttribute('data-mode') || 'bar';
        drawUserGrowthChart();
      };
    });
  }

  var rangeTabs = document.getElementById('userChartRangeTabs');
  if (rangeTabs) {
    rangeTabs.querySelectorAll('button').forEach(function (btn) {
      btn.onclick = function () {
        rangeTabs.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        userChartDays = parseInt(btn.getAttribute('data-days') || '30', 10);
        loadUserGrowth();
      };
    });
  }

  ensureChartJs(function () {
    loadSalesChart();
    loadUserSummary();
    loadUserGrowth();
    setInterval(loadUserSummary, 15000);
  });
}());
