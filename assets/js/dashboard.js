(function () {
  var REFRESH_MS = 20000;
  var PAGE_SIZE = 10;
  var chartInstance = null;
  var allGuests = [];
  var lastStats = { confirmed: 0, declined: 0, adults: 0, children: 0 };
  var currentFilter = '';
  var currentSort = { key: '', dir: 1 };
  var currentPage = 1;
  var trashVisible = false;
  var lastUpdateAt = null;

  // Appends a unique query param so no cache layer (browser, LiteSpeed, or a host's own
  // CDN in front of it) can ever serve a stale response for a URL it has seen before —
  // response Cache-Control headers alone were confirmed insufficient on this host.
  function bust( url ) {
    return url + ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + '_=' + Date.now();
  }

  function fetchStats() {
    fetch(bust(RSVP_DASHBOARD.apiUrl), { cache: 'no-store' })
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
    lastStats = data;
    lastUpdateAt = Date.now();
    setText('rsvp-dash-confirmed', data.confirmed);
    setText('rsvp-dash-declined', data.declined);
    setText('rsvp-dash-adults', data.adults);
    setText('rsvp-dash-children', data.children);
    renderChart(data.confirmed, data.declined);
    allGuests = data.guests || [];
    setText('rsvp-dash-total-count', allGuests.length + ' réponses au total');
    renderTable();
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
      type: 'pie',
      data: {
        labels: ['Confirmés', 'Déclinés'],
        datasets: [{ data: [confirmed, declined], backgroundColor: ['#74b816', '#d63939'], borderWidth: 2, borderColor: '#fff' }],
      },
      options: {
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 11 } } } },
      },
    });
  }

  function tickUpdatedLabel() {
    var el = document.getElementById('rsvp-dash-updated');
    if (!el || !lastUpdateAt) return;
    var secs = Math.round((Date.now() - lastUpdateAt) / 1000);
    var label = secs < 5 ? "mis à jour à l'instant" : 'mis à jour il y a ' + secs + 's';
    el.innerHTML = '<i class="ti ti-refresh"></i> ' + label;
  }

  function compareValues(a, b) {
    var na = Number(a), nb = Number(b);
    var bothNumeric = a !== '' && b !== '' && !isNaN(na) && !isNaN(nb);
    if (bothNumeric) return na - nb;
    return String(a || '').localeCompare(String(b || ''));
  }

  function sortedGuests() {
    if (!currentSort.key) return allGuests.slice();
    var key = currentSort.key;
    var dir = currentSort.dir;
    return allGuests.slice().sort(function (a, b) {
      return compareValues(a[key], b[key]) * dir;
    });
  }

  function renderTable() {
    var searchEl = document.getElementById('rsvp-dash-search');
    var term = (searchEl && searchEl.value ? searchEl.value : '').toLowerCase();
    var body = document.getElementById('rsvp-dash-table-body');
    if (!body) return;

    var filtered = sortedGuests().filter(function (g) {
      var matchesSearch = ((g.prenom || '') + ' ' + (g.nom || '')).toLowerCase().indexOf(term) !== -1;
      var matchesFilter = !currentFilter || g.presence === currentFilter;
      return matchesSearch && matchesFilter;
    });

    var totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;
    var start = (currentPage - 1) * PAGE_SIZE;
    var pageItems = filtered.slice(start, start + PAGE_SIZE);

    body.innerHTML = pageItems.map(function (g) {
      var extraCells = (g.extra || []).map(function (v) {
        return '<td>' + escapeHtml(v) + '</td>';
      }).join('');
      var isConfirmed = g.presence === 'confirmé';
      var badgeClass = isConfirmed ? 'rsvp-badge-confirmed' : 'rsvp-badge-declined';
      var avatarClass = isConfirmed ? 'rsvp-avatar-confirmed' : 'rsvp-avatar-declined';
      var initials = initialsOf(g.prenom, g.nom);
      return '<tr><td><div class="d-flex align-items-center"><span class="avatar avatar-sm rounded-circle ' + avatarClass + ' me-2">' +
        escapeHtml(initials) + '</span>' + escapeHtml(g.prenom) + '</div></td><td>' + escapeHtml(g.nom) + '</td><td>' +
        '<span class="badge ' + badgeClass + '">' + escapeHtml(g.presence) + '</span></td><td class="text-end">' +
        escapeHtml(g.adultes) + '</td><td class="text-end">' + escapeHtml(g.enfants) + '</td>' + extraCells +
        '<td class="d-print-none"><button type="button" class="btn btn-icon btn-sm rsvp-dash-trash-btn" data-id="' + escapeHtml(g.id) + '">' +
        '<i class="ti ti-trash"></i></button></td></tr>';
    }).join('');

    renderPagination(filtered.length, totalPages);
  }

  function renderPagination(totalItems, totalPages) {
    var info = document.getElementById('rsvp-dash-page-info');
    var nav = document.getElementById('rsvp-dash-pagination');
    if (!info || !nav) return;

    if (totalItems === 0) {
      info.textContent = 'Aucun invité';
      nav.innerHTML = '';
      return;
    }

    var start = (currentPage - 1) * PAGE_SIZE + 1;
    var end = Math.min(currentPage * PAGE_SIZE, totalItems);
    info.textContent = 'Affiche ' + start + ' à ' + end + ' sur ' + totalItems + ' invités';

    var items = '';
    items += '<li class="page-item' + (currentPage === 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (currentPage - 1) + '"><i class="ti ti-chevron-left"></i></a></li>';
    for (var p = 1; p <= totalPages; p++) {
      items += '<li class="page-item' + (p === currentPage ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
    }
    items += '<li class="page-item' + (currentPage === totalPages ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (currentPage + 1) + '"><i class="ti ti-chevron-right"></i></a></li>';
    nav.innerHTML = items;
  }

  function initialsOf(prenom, nom) {
    var a = (prenom || '').trim().charAt(0);
    var b = (nom || '').trim().charAt(0);
    return (a + b).toUpperCase();
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
    fetch(bust(RSVP_DASHBOARD.trashApiUrl), { cache: 'no-store' })
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
      var isConfirmed = g.presence === 'confirmé';
      var badgeClass = isConfirmed ? 'rsvp-badge-confirmed' : 'rsvp-badge-declined';
      return '<tr><td>' + escapeHtml(g.prenom) + '</td><td>' + escapeHtml(g.nom) + '</td><td>' +
        '<span class="badge ' + badgeClass + '">' + escapeHtml(g.presence) + '</span></td>' +
        '<td><button type="button" class="btn btn-icon btn-sm rsvp-dash-restore-btn" data-id="' + escapeHtml(g.id) + '">' +
        '<i class="ti ti-arrow-back-up"></i></button></td></tr>';
    }).join('');
  }

  // Neutralizes spreadsheet formula injection (CWE-1236): guest-submitted strings that
  // start with =, +, -, @, tab, or CR would otherwise be evaluated as live formulas by
  // Excel/LibreOffice when the admin opens the exported file.
  function safeCell(v) {
    var s = (v === null || v === undefined) ? '' : String(v);
    return /^[=+\-@\t\r]/.test(s) ? "'" + s : s;
  }

  function exportExcel() {
    if (typeof XLSX === 'undefined') return;
    var headers = [];
    document.querySelectorAll('#rsvp-dash-table thead th').forEach(function (th, i, arr) {
      if (i === arr.length - 1) return;
      headers.push(th.textContent.trim());
    });
    var rows = allGuests.map(function (g) {
      return [safeCell(g.prenom), safeCell(g.nom), g.presence, g.adultes, g.enfants].concat(
        (g.extra || []).map(safeCell)
      );
    });
    var summary = [
      ['Total invités confirmés', lastStats.confirmed],
      ['Déclinés (réponses)', lastStats.declined],
      ['Adultes', lastStats.adults],
      ['Enfants', lastStats.children],
      [],
    ];
    var sheetData = summary.concat([headers]).concat(rows);
    var ws = XLSX.utils.aoa_to_sheet(sheetData);
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Invités');

    var titleSlug = String(RSVP_DASHBOARD.dashboardTitle || 'RSVP').replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    var dateStr = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, titleSlug + '-' + dateStr + '.xlsx');
  }

  document.addEventListener('DOMContentLoaded', function () {
    fetchStats();
    setInterval(fetchStats, REFRESH_MS);
    setInterval(tickUpdatedLabel, 1000);

    var search = document.getElementById('rsvp-dash-search');
    if (search) search.addEventListener('input', function () { currentPage = 1; renderTable(); });

    var filterMenu = document.getElementById('rsvp-dash-filter-menu');
    if (filterMenu) {
      filterMenu.addEventListener('click', function (e) {
        var item = e.target.closest('[data-filter]');
        if (!item) return;
        e.preventDefault();
        currentFilter = item.getAttribute('data-filter');
        var label = document.getElementById('rsvp-dash-filter-label');
        if (label) label.textContent = item.textContent;
        currentPage = 1;
        renderTable();
      });
    }

    document.querySelectorAll('.rsvp-dash-sort').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var key = link.getAttribute('data-sort');
        if (currentSort.key === key) {
          currentSort.dir = -currentSort.dir;
        } else {
          currentSort = { key: key, dir: 1 };
        }
        document.querySelectorAll('.rsvp-dash-sort i').forEach(function (icon) {
          icon.className = 'ti ti-selector icon-sm';
        });
        var icon = link.querySelector('i');
        if (icon) icon.className = 'ti ' + (currentSort.dir === 1 ? 'ti-chevron-up' : 'ti-chevron-down') + ' icon-sm';
        currentPage = 1;
        renderTable();
      });
    });

    var pagination = document.getElementById('rsvp-dash-pagination');
    if (pagination) {
      pagination.addEventListener('click', function (e) {
        var link = e.target.closest('[data-page]');
        if (!link || link.closest('.disabled')) return;
        e.preventDefault();
        var page = parseInt(link.getAttribute('data-page'), 10);
        if (!page || page < 1) return;
        currentPage = page;
        renderTable();
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

    var printBtn = document.getElementById('rsvp-dash-print-btn');
    if (printBtn) printBtn.addEventListener('click', function () { window.print(); });

    var exportBtn = document.getElementById('rsvp-dash-export-btn');
    if (exportBtn) exportBtn.addEventListener('click', exportExcel);
  });
})();
