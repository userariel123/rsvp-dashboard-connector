(function () {
  var REFRESH_MS = 20000;
  var chartInstance = null;
  var allGuests = [];
  var currentFilter = '';
  var trashVisible = false;

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
      var matchesSearch = ((g.prenom || '') + ' ' + (g.nom || '')).toLowerCase().indexOf(term) !== -1;
      var matchesFilter = !currentFilter || g.presence === currentFilter;
      return matchesSearch && matchesFilter;
    });

    body.innerHTML = filtered.map(function (g) {
      var extraCells = (g.extra || []).map(function (v) {
        return '<td>' + escapeHtml(v) + '</td>';
      }).join('');
      var badgeClass = g.presence === 'confirmé' ? 'bg-green-lt' : 'bg-red-lt';
      return '<tr><td>' + escapeHtml(g.prenom) + '</td><td>' + escapeHtml(g.nom) + '</td><td>' +
        '<span class="badge ' + badgeClass + '">' + escapeHtml(g.presence) + '</span></td><td>' +
        escapeHtml(g.adultes) + '</td><td>' + escapeHtml(g.enfants) + '</td>' + extraCells +
        '<td><button type="button" class="btn btn-icon btn-sm rsvp-dash-trash-btn" data-id="' + escapeHtml(g.id) + '">' +
        '<i class="ti ti-trash"></i></button></td></tr>';
    }).join('');
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str === null || str === undefined ? '' : String(str);
    return div.innerHTML;
  }

  function entryActionUrl(id, action) {
    return RSVP_DASHBOARD.entriesApiUrl + '/' + id + '/' + action + '?token=' + encodeURIComponent(RSVP_DASHBOARD.token);
  }

  function trashEntry(id) {
    fetch(entryActionUrl(id, 'trash'), { method: 'POST' })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        fetchStats();
        if (trashVisible) fetchTrash();
      })
      .catch(function (err) {
        console.error('RSVP Dashboard trash error:', err);
      });
  }

  function restoreEntry(id) {
    fetch(entryActionUrl(id, 'restore'), { method: 'POST' })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        fetchStats();
        fetchTrash();
      })
      .catch(function (err) {
        console.error('RSVP Dashboard restore error:', err);
      });
  }

  function fetchTrash() {
    fetch(RSVP_DASHBOARD.trashApiUrl, { cache: 'no-store' })
      .then(function (res) { return res.json(); })
      .then(renderTrash)
      .catch(function (err) {
        console.error('RSVP Dashboard trash fetch error:', err);
      });
  }

  function renderTrash(data) {
    var body = document.getElementById('rsvp-dash-trash-body');
    if (!body) return;
    var guests = data.guests || [];
    body.innerHTML = guests.map(function (g) {
      var badgeClass = g.presence === 'confirmé' ? 'bg-green-lt' : 'bg-red-lt';
      return '<tr><td>' + escapeHtml(g.prenom) + '</td><td>' + escapeHtml(g.nom) + '</td><td>' +
        '<span class="badge ' + badgeClass + '">' + escapeHtml(g.presence) + '</span></td>' +
        '<td><button type="button" class="btn btn-icon btn-sm rsvp-dash-restore-btn" data-id="' + escapeHtml(g.id) + '">' +
        '<i class="ti ti-arrow-back-up"></i></button></td></tr>';
    }).join('');
  }

  document.addEventListener('DOMContentLoaded', function () {
    fetchStats();
    setInterval(fetchStats, REFRESH_MS);

    var search = document.getElementById('rsvp-dash-search');
    if (search) search.addEventListener('input', applyFilter);

    var filterMenu = document.getElementById('rsvp-dash-filter-menu');
    if (filterMenu) {
      filterMenu.addEventListener('click', function (e) {
        var item = e.target.closest('[data-filter]');
        if (!item) return;
        e.preventDefault();
        currentFilter = item.getAttribute('data-filter');
        var label = document.getElementById('rsvp-dash-filter-label');
        if (label) label.textContent = item.textContent;
        applyFilter();
      });
    }

    var tableBody = document.getElementById('rsvp-dash-table-body');
    if (tableBody) {
      tableBody.addEventListener('click', function (e) {
        var btn = e.target.closest('.rsvp-dash-trash-btn');
        if (btn) trashEntry(btn.getAttribute('data-id'));
      });
    }

    var trashBody = document.getElementById('rsvp-dash-trash-body');
    if (trashBody) {
      trashBody.addEventListener('click', function (e) {
        var btn = e.target.closest('.rsvp-dash-restore-btn');
        if (btn) restoreEntry(btn.getAttribute('data-id'));
      });
    }

    var trashToggle = document.getElementById('rsvp-dash-trash-toggle');
    var trashPanel = document.getElementById('rsvp-dash-trash-panel');
    if (trashToggle && trashPanel) {
      trashToggle.addEventListener('click', function () {
        trashVisible = !trashVisible;
        trashPanel.style.display = trashVisible ? '' : 'none';
        if (trashVisible) fetchTrash();
      });
    }
  });
})();
