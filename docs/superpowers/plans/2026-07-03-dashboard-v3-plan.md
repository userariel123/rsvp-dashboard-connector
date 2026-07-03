# Dashboard v3 (Visual Polish) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the visual redesign validated in the mockup session: solid Lime/Red colors, a pie chart with a live "updated Xs ago" indicator, sortable + paginated guest table, a redefined "Déclinés" metric (response count, not headcount), and Excel export + print.

**Architecture:** Same plugin, same files as v1/v2 — this pass touches `includes/class-rest-api.php` (one metric fix), `includes/class-shortcode.php` (new CDN script), `templates/dashboard-markup.php` (markup), `assets/css/dashboard.css` (colors + print), and `assets/js/dashboard.js` (chart, sort, paginate, export, print, live indicator). No new files, no build step.

**Tech Stack:** Unchanged, plus [SheetJS](https://cdn.jsdelivr.net/npm/xlsx@latest/dist/xlsx.full.min.js) (MIT, CDN, verified reachable) for client-side `.xlsx` generation.

## Global Constraints

- No build step: every new dependency (SheetJS) loads from CDN, same pattern as Tabler/Chart.js.
- Colors are exact: Lime `#74b816` for confirmed/positive, Red `#d63939` for declined/negative — used consistently across the chart, badges, and avatars.
- **`declined` changes meaning**: it is now a **count of declined responses** (one per RSVP that said no), not a sum of `adultes`+`enfants` — those fields are typically 0 on a decline, which made the old metric always show 0 and therefore useless. `confirmed`/`adults`/`children` keep their existing headcount-sum meaning, unchanged.
- Sorting and pagination are entirely client-side: the full guest list is already fetched in one `/stats` response (existing behavior, unchanged) — no new endpoint, no server round-trip per sort/page click.
- Pagination: 10 rows per page.
- Badge copy: the Confirmés stat card's small badge reads **"total invités"**; the Déclinés stat card's small badge reads **"Déclinés"** (not "réponses", not "personnes" — confirmed explicitly). Adultes/Enfants cards keep no badge, matching current behavior.
- Chart type is `pie` (Chart.js), not `doughnut` — no cutout, solid wedges, legend at the bottom.

---

## File Structure (no new files, except plan/spec docs)

```
includes/class-rest-api.php     declined: count instead of headcount sum (one-line change)
includes/class-shortcode.php    + enqueue SheetJS, localize dashboardTitle for the export filename
templates/dashboard-markup.php  card header (subtitle + Print/Excel buttons), search icon wrapper,
                                 sortable column headers, pagination footer, live-indicator span,
                                 badge text spans, table id, solid badge/avatar CSS classes
assets/css/dashboard.css        --tblr-primary → Lime, .rsvp-badge-confirmed/-declined classes,
                                 @media print rules
assets/js/dashboard.js          pie chart, live "updated Xs ago" ticker, sort+paginate pipeline,
                                 solid badge/avatar classes, Excel export via SheetJS, print handler
```

---

### Task 1: Stats endpoint — Déclinés becomes a response count

**Files:**
- Modify: `includes/class-rest-api.php`

**Interfaces:**
- Produces: `/stats`'s `declined` field is now `int` (count of declined entries), not a headcount sum. `confirmed`/`adults`/`children` unchanged.

- [ ] **Step 1: Change the aggregation**

In `includes/class-rest-api.php`, inside `get_stats()`, find:
```php
            if ( ! $want_trash ) {
                if ( $is_yes ) {
                    $result['confirmed'] += $nb_adults + $nb_children;
                    $result['adults']    += $nb_adults;
                    $result['children']  += $nb_children;
                } else {
                    $result['declined'] += $nb_adults + $nb_children;
                }
            }
```
Replace with:
```php
            if ( ! $want_trash ) {
                if ( $is_yes ) {
                    $result['confirmed'] += $nb_adults + $nb_children;
                    $result['adults']    += $nb_adults;
                    $result['children']  += $nb_children;
                } else {
                    $result['declined']++;
                }
            }
```

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, visit `/wp-json/rsvp-dashboard/v1/stats?token=<your token>`.
2. Expected: `declined` now equals the number of declined RSVP entries (e.g. `3` if three people said no), not `0`.

- [ ] **Step 3: Commit**

```bash
git add includes/class-rest-api.php
git commit -m "Change declined stat to a response count instead of a headcount sum"
```

---

### Task 2: Shortcode — enqueue SheetJS, localize dashboard title

**Files:**
- Modify: `includes/class-shortcode.php`

**Interfaces:**
- Produces: script handle `sheetjs` (dependency of `rsvp-dashboard-js`); `RSVP_DASHBOARD.dashboardTitle` (string) available to `assets/js/dashboard.js` for the export filename.

- [ ] **Step 1: Replace the whole file**

`includes/class-shortcode.php`:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSVP_Dashboard_Shortcode {

    public static function init() {
        add_shortcode( 'rsvp_dashboard', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts ) {
        // Local assets are versioned by file modification time, not a static constant, so
        // every browser/edge cache is forced to fetch the new file the moment it changes on
        // disk — no more "I edited the code but nobody sees it" cache-staleness bugs.
        $css_path = RSVP_DASHBOARD_DIR . 'assets/css/dashboard.css';
        $js_path  = RSVP_DASHBOARD_DIR . 'assets/js/dashboard.js';
        $css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : RSVP_DASHBOARD_VERSION;
        $js_ver   = file_exists( $js_path ) ? filemtime( $js_path ) : RSVP_DASHBOARD_VERSION;

        wp_enqueue_style( 'tabler-core', 'https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css', array(), '1.0.0' );
        wp_enqueue_style( 'tabler-icons', 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css', array(), '1.0.0' );
        wp_enqueue_style( 'rsvp-dashboard-css', RSVP_DASHBOARD_URL . 'assets/css/dashboard.css', array( 'tabler-core' ), $css_ver );

        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4', array(), '4.0.0', true );
        wp_enqueue_script( 'tabler-js', 'https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js', array(), '1.0.0', true );
        wp_enqueue_script( 'sheetjs', 'https://cdn.jsdelivr.net/npm/xlsx@latest/dist/xlsx.full.min.js', array(), '1.0.0', true );
        wp_enqueue_script( 'rsvp-dashboard-js', RSVP_DASHBOARD_URL . 'assets/js/dashboard.js', array( 'chartjs', 'tabler-js', 'sheetjs' ), $js_ver, true );

        $settings = RSVP_Dashboard_Settings::get_settings();
        $token    = RSVP_Dashboard_Settings::get_or_create_token();

        wp_localize_script( 'rsvp-dashboard-js', 'RSVP_DASHBOARD', array(
            'apiUrl'         => esc_url_raw( add_query_arg( 'token', $token, rest_url( 'rsvp-dashboard/v1/stats' ) ) ),
            'trashApiUrl'    => esc_url_raw( add_query_arg( array( 'token' => $token, 'trash' => 1 ), rest_url( 'rsvp-dashboard/v1/stats' ) ) ),
            'entriesApiUrl'  => esc_url_raw( rest_url( 'rsvp-dashboard/v1/entries' ) ),
            'token'          => $token,
            'dashboardTitle' => $settings['dashboard_title'] ?: get_bloginfo( 'name' ),
        ) );

        $dashboard_title = $settings['dashboard_title'];
        $extra_columns   = $settings['extra_columns'];

        ob_start();
        include RSVP_DASHBOARD_DIR . 'templates/dashboard-markup.php';
        return ob_get_clean();
    }
}
```

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, view the dashboard page, open devtools console, run `RSVP_DASHBOARD.dashboardTitle` — expected: your configured dashboard title (or site name if empty).
2. Check the Network tab — expected: `xlsx.full.min.js` loads with HTTP 200.

- [ ] **Step 3: Commit**

```bash
git add includes/class-shortcode.php
git commit -m "Enqueue SheetJS and localize dashboardTitle for Excel export"
```

---

### Task 3: Dashboard markup — header, sortable columns, pagination, live indicator

**Files:**
- Modify: `templates/dashboard-markup.php`

**Interfaces:**
- Consumes: `$dashboard_title`, `$extra_columns` (unchanged from v2).
- Produces new DOM ids/classes Task 5's JS depends on: `rsvp-dash-updated`, `rsvp-dash-total-count`, `rsvp-dash-print-btn`, `rsvp-dash-export-btn`, `rsvp-dash-page-info`, `rsvp-dash-pagination`, table id `rsvp-dash-table`, sortable header links with class `rsvp-dash-sort` and `data-sort="prenom|nom|presence|adultes|enfants"` (each containing one `<i>` icon Task 5 will swap between `ti-selector`/`ti-chevron-up`/`ti-chevron-down`). All DOM ids from v2 (`rsvp-dash-confirmed`, `-declined`, `-adults`, `-children`, `-chart`, `-search`, `-table-body`, `-filter-menu`, `-filter-label`, `-trash-toggle`, `-trash-panel`, `-trash-body`) are unchanged.

- [ ] **Step 1: Replace the whole file**

`templates/dashboard-markup.php`:
```php
<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="rsvp-dash">
  <div class="navbar navbar-expand-md navbar-light d-print-none">
    <div class="container-xl">
      <h1 class="navbar-brand navbar-brand-autodark">
        <?php echo esc_html( $dashboard_title ?: get_bloginfo( 'name' ) ); ?>
      </h1>
    </div>
  </div>

  <div class="container-xl" style="padding-top:20px">
    <div class="row row-deck row-cards mb-3">
      <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body">
          <div class="subheader">Confirmés</div>
          <div class="d-flex align-items-baseline">
            <div class="h1 mb-0 me-2" id="rsvp-dash-confirmed">-</div>
            <span class="badge rsvp-badge-confirmed">total invités</span>
          </div>
        </div></div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body">
          <div class="subheader">Déclinés</div>
          <div class="d-flex align-items-baseline">
            <div class="h1 mb-0 me-2" id="rsvp-dash-declined">-</div>
            <span class="badge rsvp-badge-declined">Déclinés</span>
          </div>
        </div></div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body">
          <div class="subheader">Adultes</div>
          <div class="h1 mb-0" id="rsvp-dash-adults">-</div>
        </div></div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body">
          <div class="subheader">Enfants</div>
          <div class="h1 mb-0" id="rsvp-dash-children">-</div>
        </div></div>
      </div>
    </div>

    <div class="row row-cards">
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0">Répartition</h3>
            <span class="text-secondary" style="font-size:11px" id="rsvp-dash-updated">
              <i class="ti ti-refresh"></i> mis à jour à l'instant
            </span>
          </div>
          <canvas id="rsvp-dash-chart" height="220"></canvas>
        </div></div>
      </div>
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:8px">
            <div>
              <h3 class="card-title mb-0">Invités</h3>
              <div class="text-secondary" style="font-size:13px" id="rsvp-dash-total-count">-</div>
            </div>
            <div class="d-flex" style="gap:8px">
              <div class="dropdown">
                <button class="btn dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                  <span id="rsvp-dash-filter-label">Tous</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end" id="rsvp-dash-filter-menu">
                  <a class="dropdown-item" href="#" data-filter="">Tous</a>
                  <a class="dropdown-item" href="#" data-filter="confirmé">Confirmés</a>
                  <a class="dropdown-item" href="#" data-filter="décliné">Déclinés</a>
                </div>
              </div>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="rsvp-dash-print-btn">
                <i class="ti ti-printer"></i>
              </button>
              <button type="button" class="btn btn-outline-primary btn-sm" id="rsvp-dash-export-btn">
                <i class="ti ti-file-spreadsheet"></i> Excel
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="rsvp-dash-trash-toggle">
                <i class="ti ti-trash"></i> Corbeille
              </button>
            </div>
          </div>
          <div class="card-body border-bottom py-2 d-print-none">
            <div class="input-icon">
              <span class="input-icon-addon"><i class="ti ti-search"></i></span>
              <input type="text" id="rsvp-dash-search" class="form-control" placeholder="Rechercher un invité...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-striped" id="rsvp-dash-table">
              <thead>
                <tr>
                  <th><a href="#" class="rsvp-dash-sort text-secondary text-decoration-none" data-sort="prenom">Prénom <i class="ti ti-selector icon-sm"></i></a></th>
                  <th><a href="#" class="rsvp-dash-sort text-secondary text-decoration-none" data-sort="nom">Nom <i class="ti ti-selector icon-sm"></i></a></th>
                  <th><a href="#" class="rsvp-dash-sort text-secondary text-decoration-none" data-sort="presence">Présence <i class="ti ti-selector icon-sm"></i></a></th>
                  <th class="text-end"><a href="#" class="rsvp-dash-sort text-secondary text-decoration-none" data-sort="adultes">Adultes <i class="ti ti-selector icon-sm"></i></a></th>
                  <th class="text-end"><a href="#" class="rsvp-dash-sort text-secondary text-decoration-none" data-sort="enfants">Enfants <i class="ti ti-selector icon-sm"></i></a></th>
                  <?php foreach ( $extra_columns as $col ) : ?>
                    <th><?php echo esc_html( $col['label'] ); ?></th>
                  <?php endforeach; ?>
                  <th class="d-print-none"></th>
                </tr>
              </thead>
              <tbody id="rsvp-dash-table-body"></tbody>
            </table>
          </div>
          <div class="card-footer d-flex align-items-center d-print-none">
            <p class="m-0 text-secondary" style="font-size:13px" id="rsvp-dash-page-info"></p>
            <ul class="pagination pagination-sm m-0 ms-auto" id="rsvp-dash-pagination"></ul>
          </div>
        </div>

        <div class="card mt-3 d-print-none" id="rsvp-dash-trash-panel" style="display:none">
          <div class="card-header">
            <h3 class="card-title">Corbeille</h3>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>Prénom</th><th>Nom</th><th>Présence</th><th></th></tr></thead>
              <tbody id="rsvp-dash-trash-body"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, view the dashboard page. Expected: Confirmés badge reads "total invités", Déclinés badge reads "Déclinés", a "mis à jour à l'instant" label next to "Répartition", column headers show a sort icon, a pagination row appears below the table (empty until Task 5's JS fills it), Print and Excel buttons appear next to Corbeille.

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard-markup.php
git commit -m "Add sortable headers, pagination footer, live-update indicator, print/export buttons to markup"
```

---

### Task 4: CSS — Lime color, solid badge/avatar classes, print styles

**Files:**
- Modify: `assets/css/dashboard.css`

**Interfaces:**
- Produces: `.rsvp-badge-confirmed`, `.rsvp-badge-declined`, `.rsvp-avatar-confirmed`, `.rsvp-avatar-declined` classes (consumed by Task 5's JS when building table rows).

- [ ] **Step 1: Replace the whole file**

`assets/css/dashboard.css`:
```css
:root {
  --tblr-primary: #74b816;
}

.rsvp-dash .card {
  border-radius: 12px;
}

.rsvp-dash .h1 {
  font-weight: 700;
}

.rsvp-dash .subheader {
  text-transform: uppercase;
  letter-spacing: .04em;
}

.rsvp-badge-confirmed,
.rsvp-avatar-confirmed {
  background: #74b816;
  color: #fff;
}

.rsvp-badge-declined,
.rsvp-avatar-declined {
  background: #d63939;
  color: #fff;
}

@media print {
  .rsvp-dash .navbar,
  .rsvp-dash .d-print-none {
    display: none !important;
  }
  .rsvp-dash .card {
    border: none;
    box-shadow: none;
  }
}
```

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, reload the dashboard page. Expected: Tabler's primary-colored elements (active dropdown item, focus rings) now use Lime instead of the previous blue.
2. Once Task 5 is also deployed: confirm badges/avatars render solid Lime or Red (checked together at the end of Task 5's verification, since this task alone has no JS consuming these classes yet).

- [ ] **Step 3: Commit**

```bash
git add assets/css/dashboard.css
git commit -m "Switch primary color to Lime, add solid badge/avatar classes and print styles"
```

---

### Task 5: Front-end script — pie chart, sort, paginate, export, print, live indicator

**Files:**
- Modify: `assets/js/dashboard.js`

**Interfaces:**
- Consumes: `RSVP_DASHBOARD.apiUrl/.trashApiUrl/.entriesApiUrl/.token/.dashboardTitle` (Task 2); DOM ids/classes from Task 3 (`rsvp-dash-updated`, `rsvp-dash-total-count`, `rsvp-dash-print-btn`, `rsvp-dash-export-btn`, `rsvp-dash-page-info`, `rsvp-dash-pagination`, `#rsvp-dash-table`, `.rsvp-dash-sort[data-sort]`); CSS classes from Task 4 (`.rsvp-badge-confirmed/-declined`, `.rsvp-avatar-confirmed/-declined`); global `XLSX` (SheetJS, Task 2); `/stats` response shape from Task 1 (`declined` is now a count).

- [ ] **Step 1: Replace the whole file**

`assets/js/dashboard.js`:
```javascript
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
    currentPage = 1;
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

  function exportExcel() {
    if (typeof XLSX === 'undefined') return;
    var headers = [];
    document.querySelectorAll('#rsvp-dash-table thead th').forEach(function (th, i, arr) {
      if (i === arr.length - 1) return;
      headers.push(th.textContent.trim());
    });
    var rows = allGuests.map(function (g) {
      return [g.prenom, g.nom, g.presence, g.adultes, g.enfants].concat(g.extra || []);
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
```

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, reload the dashboard. Expected: pie chart (no hole) in Lime/Red, "mis à jour à l'instant" ticking up every second ("il y a 1s", "il y a 2s"...) and resetting on each 20s refresh.
2. Click a column header (e.g. "Adultes") — expected: table re-sorts, icon changes to an up/down chevron; click again — expected: sort direction flips.
3. With more than 10 guests: expected: pagination shows multiple pages, clicking a page number updates the visible rows and the "Affiche X à Y sur Z" text; with 10 or fewer guests, only one page shows and prev/next are disabled.
4. Click "Excel" — expected: a `.xlsx` file downloads named `<dashboard-title>-<today's date>.xlsx`, opens in Excel with a totals block at the top and the full guest list (including any extra columns) below.
5. Click the print icon — expected: the browser's print dialog opens showing a clean view (navbar, search, buttons, pagination, and the trash panel all hidden per the `d-print-none` classes from Task 3/4).
6. Trash/restore a test entry — expected: still works exactly as before (badges/avatars now solid Lime/Red instead of the previous light backgrounds).

- [ ] **Step 3: Commit**

```bash
git add assets/js/dashboard.js
git commit -m "Add pie chart, live update ticker, client-side sort/pagination, Excel export, print handler"
```

---

### Task 6: Full end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Full flow on real data**

Confirm every element from the mockup renders correctly together: navbar, 4 stat cards with correct badge text, pie chart with live ticker, guest table with sortable striped rows, pagination, search+filter combined with sort, Print, Excel export with correct filename and totals, Corbeille trash/restore.

- [ ] **Step 2: Second site sanity check**

If a second Fluent Forms form with different fields is available, repeat Step 1 there — confirms extra columns still appear correctly in both the table and the Excel export, and that sorting/pagination work with a different guest count.

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "Complete end-to-end verification of dashboard v3 visual redesign"
```
