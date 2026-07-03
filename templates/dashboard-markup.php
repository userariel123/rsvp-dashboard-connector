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
            <span class="badge bg-green-lt">personnes</span>
          </div>
        </div></div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card"><div class="card-body">
          <div class="subheader">Déclinés</div>
          <div class="d-flex align-items-baseline">
            <div class="h1 mb-0 me-2" id="rsvp-dash-declined">-</div>
            <span class="badge bg-red-lt">personnes</span>
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
      <div class="col-lg-5">
        <div class="card"><div class="card-body">
          <h3 class="card-title">Répartition</h3>
          <canvas id="rsvp-dash-chart" height="220"></canvas>
        </div></div>
      </div>
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0">Invités</h3>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="rsvp-dash-trash-toggle">
              <i class="ti ti-trash"></i> Corbeille
            </button>
          </div>
          <div class="card-body">
            <div class="d-flex mb-3" style="gap:8px">
              <input type="text" id="rsvp-dash-search" class="form-control" placeholder="Rechercher un invité...">
              <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                  <span id="rsvp-dash-filter-label">Tous</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end" id="rsvp-dash-filter-menu">
                  <a class="dropdown-item" href="#" data-filter="">Tous</a>
                  <a class="dropdown-item" href="#" data-filter="confirmé">Confirmés</a>
                  <a class="dropdown-item" href="#" data-filter="décliné">Déclinés</a>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>Prénom</th><th>Nom</th><th>Présence</th><th>Adultes</th><th>Enfants</th>
                    <?php foreach ( $extra_columns as $col ) : ?>
                      <th><?php echo esc_html( $col['label'] ); ?></th>
                    <?php endforeach; ?>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="rsvp-dash-table-body"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card mt-3" id="rsvp-dash-trash-panel" style="display:none">
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
