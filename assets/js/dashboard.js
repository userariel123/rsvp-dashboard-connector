(function () {
  var REFRESH_MS = 20000;
  var chartInstance = null;
  var allGuests = [];

  function fetchStats() {
    fetch(RSVP_DASHBOARD.apiUrl, { cache: 'no-store' })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(renderStats)
      .catch(function (err) {
        console.error('RSVP Dashboard fetch error:', err);
      });
  }

  function renderStats(data) {
    setText('rsvp-dash-confirmed', data.confirmed);
    setText('rsvp-dash-declined', data.declined);
    setText('rsvp-dash-adults', data.adults);
    setText('rsvp-dash-children', data.children);
    renderChart(data.confirmed, data.declined);
    allGuests = data.guests || [];
    applyFilter();
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function renderChart(confirmed, declined) {
    var ctx = document.getElementById('rsvp-dash-chart');
    if (!ctx || typeof Chart === 'undefined') return;
    if (chartInstance) {
      chartInstance.data.datasets[0].data = [confirmed, declined];
      chartInstance.update();
      return;
    }
    chartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Confirmés', 'Déclinés'],
        datasets: [{ data: [confirmed, declined], backgroundColor: ['#2fb344', '#d63939'] }],
      },
    });
  }

  function applyFilter() {
    var searchEl = document.getElementById('rsvp-dash-search');
    var term = (searchEl && searchEl.value ? searchEl.value : '').toLowerCase();
    var body = document.getElementById('rsvp-dash-table-body');
    if (!body) return;

    var filtered = allGuests.filter(function (g) {
      return ((g.prenom || '') + ' ' + (g.nom || '')).toLowerCase().indexOf(term) !== -1;
    });

    body.innerHTML = filtered.map(function (g) {
      return '<tr><td>' + escapeHtml(g.prenom) + '</td><td>' + escapeHtml(g.nom) + '</td><td>' +
        escapeHtml(g.presence) + '</td><td>' + escapeHtml(g.adultes) + '</td><td>' + escapeHtml(g.enfants) + '</td></tr>';
    }).join('');
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str === null || str === undefined ? '' : String(str);
    return div.innerHTML;
  }

  document.addEventListener('DOMContentLoaded', function () {
    fetchStats();
    setInterval(fetchStats, REFRESH_MS);
    var search = document.getElementById('rsvp-dash-search');
    if (search) search.addEventListener('input', applyFilter);
  });
})();
