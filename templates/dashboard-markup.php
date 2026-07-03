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
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                  <i class="ti ti-columns"></i> Colonnes
                </button>
                <div class="dropdown-menu dropdown-menu-end p-2" id="rsvp-dash-columns-menu" style="min-width:200px">
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="1">
                    <span class="form-check-label">Prénom</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="2">
                    <span class="form-check-label">Nom</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="3">
                    <span class="form-check-label">Présence</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="4">
                    <span class="form-check-label">Adultes</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="5">
                    <span class="form-check-label">Enfants</span>
                  </label>
                  <?php $rsvp_col_index = 6; foreach ( $extra_columns as $col ) : ?>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="<?php echo (int) $rsvp_col_index; ?>">
                    <span class="form-check-label"><?php echo esc_html( $col['label'] ); ?></span>
                  </label>
                  <?php $rsvp_col_index++; endforeach; ?>
                </div>
              </div>
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
