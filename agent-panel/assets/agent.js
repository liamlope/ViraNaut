(function () {
  var chartEl = document.getElementById('agent-chart');
  if (!chartEl || typeof Chart === 'undefined') return;
  fetch('api/dashboard.php?days=14', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) return;
      new Chart(chartEl, {
        type: 'line',
        data: {
          labels: d.labels,
          datasets: [
            { label: 'فروش', data: d.orders, borderColor: '#38bdf8', tension: 0.3 },
            { label: 'درآمد', data: d.revenue, borderColor: '#4ade80', tension: 0.3 }
          ]
        },
        options: { responsive: true, plugins: { legend: { labels: { color: '#cbd5e1' } } }, scales: { x: { ticks: { color: '#94a3b8' } }, y: { ticks: { color: '#94a3b8' } } } }
      });
    });
  document.querySelectorAll('[data-agent-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var fd = new FormData();
      fd.append('csrf', document.querySelector('meta[name=csrf]')?.content || '');
      fd.append('action', btn.dataset.agentAction);
      fd.append('username', btn.dataset.username);
      if (btn.dataset.gb) fd.append('gb', btn.dataset.gb);
      fetch('api/service_action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { alert(j.msg || (j.ok ? 'OK' : 'Error')); if (j.ok) location.reload(); });
    });
  });
})();
