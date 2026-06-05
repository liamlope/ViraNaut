(function () {
  var canvas = document.getElementById('dashboardChart');
  var healthEl = document.getElementById('healthGrid');
  if (!canvas) return;

  function loadChart() {
    fetch('api/dashboard.php?days=14', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        renderHealth(data.health || {});
        if (typeof Chart === 'undefined') {
          var s = document.createElement('script');
          s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
          s.onload = function () { drawChart(data); };
          document.head.appendChild(s);
        } else {
          drawChart(data);
        }
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

  function drawChart(data) {
    var ctx = canvas.getContext('2d');
    if (window._dashChart) window._dashChart.destroy();
    window._dashChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels || [],
        datasets: [
          { label: 'سفارش', data: data.orders || [], backgroundColor: 'rgba(34,211,238,.55)', borderRadius: 4 },
          { label: 'درآمد (هزار ت)', data: (data.revenue || []).map(function (v) { return Math.round(v / 1000); }), type: 'line', borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.15)', tension: 0.3, yAxisID: 'y1' },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { font: { family: 'Vazirmatn' } } } },
        scales: {
          y: { beginAtZero: true, ticks: { font: { family: 'Vazirmatn' } } },
          y1: { position: 'left', beginAtZero: true, grid: { drawOnChartArea: false } },
        },
      },
    });
  }

  loadChart();
})();
