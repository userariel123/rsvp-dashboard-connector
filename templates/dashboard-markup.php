<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="rsvp-dash">
  <div class="row row-deck row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="subheader">Confirmés</div>
        <div class="h1 mb-0" id="rsvp-dash-confirmed">-</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="subheader">Déclinés</div>
        <div class="h1 mb-0" id="rsvp-dash-declined">-</div>
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
      <div class="card"><div class="card-body">
        <h3 class="card-title">Invités</h3>
        <input type="text" id="rsvp-dash-search" class="form-control mb-3" placeholder="Rechercher un invité...">
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr><th>Prénom</th><th>Nom</th><th>Présence</th><th>Adultes</th><th>Enfants</th></tr>
            </thead>
            <tbody id="rsvp-dash-table-body"></tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>
</div>
