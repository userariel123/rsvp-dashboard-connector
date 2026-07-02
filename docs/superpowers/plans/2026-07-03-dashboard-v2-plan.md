# Dashboard v2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the existing RSVP Dashboard plugin: a more authentic Tabler look (navbar, badges, real Tabler buttons/dropdown), per-site optional extra guest-data columns, headcount-based Confirmés/Déclinés totals, and soft-delete/restore of a guest entry synced with Fluent Forms' own trash mechanism.

**Architecture:** Same plugin, same files — this is a v2 pass over `includes/class-settings.php`, `includes/class-rest-api.php`, `includes/class-shortcode.php`, `templates/dashboard-markup.php`, and `assets/js/dashboard.js`. No new files, no new dependencies.

**Tech Stack:** Unchanged — WordPress Plugin API/REST API, Fluent Forms Pro PHP API (`fluentFormApi()`), direct `$wpdb` access to the `{prefix}fluentform_submissions` table for the trash/restore status field (confirmed to exist and hold this exact data — see Global Constraints), Tabler CSS/Icons + Chart.js via CDN, vanilla JS.

## Global Constraints

- No build step: Tabler/Chart.js/Tabler Icons stay CDN-loaded, no new JS/CSS dependencies, no bundler.
- Never hardcode a Fluent Forms field name/key — extra columns and the 5 core roles are always admin-configured, exactly like today.
- `/stats` and the new trash/restore endpoints stay protected by the existing shared token (`RSVP_Dashboard_Settings::get_or_create_token()`) — no new auth model, no requirement that the dashboard's viewer be a logged-in WP user.
- **Confirmed via live testing on the real site** (not guessed): Fluent Forms submissions are stored in the `{$wpdb->prefix}fluentform_submissions` table with a `status` column. Fluent Forms' own admin UI uses the exact value `trashed` for its "Mark as Trashed" action (confirmed via network inspection: `entry_type=trashed` on Fluent Forms' own `fluentform/v1/submissions` REST route), and excludes `trashed` entries from its default "All Types" view. Our own `fluentFormApi(...)->entries()` calls do **not** auto-exclude trashed entries — Task 2 below filters them explicitly.
- Page-level password protection is out of scope for code — WordPress's native "Password Protected" page visibility, documented in the README, unchanged from before.

---

## File Structure (no new files)

```
includes/class-settings.php     + dashboard_title, extra_columns (5 optional {label,key} slots)
includes/class-rest-api.php     get_stats(): headcount totals, extra columns, id, excludes trashed
                                 + /entries/{id}/trash and /entries/{id}/restore routes
includes/class-shortcode.php    passes dashboard_title + extra_columns to the template and to JS
templates/dashboard-markup.php  navbar, badges, card-header, dynamic extra <th>, filter dropdown,
                                 trash/restore buttons, "Corbeille" panel markup
assets/js/dashboard.js          badges, extra <td>, filter dropdown logic, trash/restore handlers,
                                 corbeille panel fetch+render
```

---

### Task 1: Settings — dashboard title + extra columns

**Files:**
- Modify: `includes/class-settings.php`

**Interfaces:**
- Produces: `get_settings()` now also returns `dashboard_title` (string) and `extra_columns` (array of up to 5 `array('label'=>string,'key'=>string)`, empty-label entries dropped).

- [ ] **Step 1: Replace the whole file**

`includes/class-settings.php`:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSVP_Dashboard_Settings {

    const OPTION_KEY = 'rsvp_dashboard_settings';
    const EXTRA_COLUMN_SLOTS = 5;

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function add_settings_page() {
        add_options_page(
            'RSVP Dashboard',
            'RSVP Dashboard',
            'manage_options',
            'rsvp-dashboard',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function register_settings() {
        register_setting( 'rsvp_dashboard_group', self::OPTION_KEY, array(
            'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
            'default'           => array(),
        ) );
    }

    public static function sanitize_settings( $input ) {
        $clean = array();
        $clean['form_id'] = isset( $input['form_id'] ) ? absint( $input['form_id'] ) : 0;
        $clean['map']     = array();
        foreach ( array( 'prenom', 'nom', 'presence', 'adultes', 'enfants' ) as $role ) {
            $clean['map'][ $role ] = isset( $input['map'][ $role ] ) ? sanitize_text_field( $input['map'][ $role ] ) : '';
        }
        $clean['presence_yes_value'] = isset( $input['presence_yes_value'] ) ? sanitize_text_field( $input['presence_yes_value'] ) : '';
        $clean['dashboard_title']    = isset( $input['dashboard_title'] ) ? sanitize_text_field( $input['dashboard_title'] ) : '';

        $clean['extra_columns'] = array();
        if ( isset( $input['extra_columns'] ) && is_array( $input['extra_columns'] ) ) {
            for ( $i = 0; $i < self::EXTRA_COLUMN_SLOTS; $i++ ) {
                $label = isset( $input['extra_columns'][ $i ]['label'] ) ? sanitize_text_field( $input['extra_columns'][ $i ]['label'] ) : '';
                $key   = isset( $input['extra_columns'][ $i ]['key'] ) ? sanitize_text_field( $input['extra_columns'][ $i ]['key'] ) : '';
                if ( '' !== $label && '' !== $key ) {
                    $clean['extra_columns'][] = array( 'label' => $label, 'key' => $key );
                }
            }
        }

        return $clean;
    }

    public static function get_settings() {
        return wp_parse_args( get_option( self::OPTION_KEY, array() ), array(
            'form_id'            => 0,
            'map'                => array(),
            'presence_yes_value' => '',
            'dashboard_title'    => '',
            'extra_columns'      => array(),
        ) );
    }

    public static function get_or_create_token() {
        $token = get_option( 'rsvp_dashboard_token' );
        if ( ! $token ) {
            $token = wp_generate_password( 32, false );
            update_option( 'rsvp_dashboard_token', $token );
        }
        return $token;
    }

    public static function get_forms_list() {
        global $wpdb;
        $table = $wpdb->prefix . 'fluentform_forms';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return array();
        }
        return $wpdb->get_results( "SELECT id, title FROM {$table} ORDER BY id DESC" );
    }

    public static function render_settings_page() {
        $settings = self::get_settings();
        $forms    = self::get_forms_list();
        $extra    = $settings['extra_columns'];
        while ( count( $extra ) < self::EXTRA_COLUMN_SLOTS ) {
            $extra[] = array( 'label' => '', 'key' => '' );
        }
        ?>
        <div class="wrap">
            <h1>RSVP Dashboard - Réglages</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'rsvp_dashboard_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="rsvp_form_id">Formulaire RSVP à suivre</label></th>
                        <td>
                            <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[form_id]" id="rsvp_form_id">
                                <option value="0">-- Choisir --</option>
                                <?php foreach ( $forms as $form ) : ?>
                                    <option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $settings['form_id'], $form->id ); ?>>
                                        <?php echo esc_html( $form->title . ' (#' . $form->id . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Titre du dashboard</label></th>
                        <td>
                            <input type="text" style="width:350px"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[dashboard_title]"
                                   value="<?php echo esc_attr( $settings['dashboard_title'] ); ?>"
                                   placeholder="ex: Yoela &amp; Shalev — RSVP" />
                        </td>
                    </tr>
                    <?php foreach ( array(
                        'prenom'   => 'Champ Prénom (clé exacte, ex: names.first_name)',
                        'nom'      => 'Champ Nom (clé exacte, ex: names.last_name)',
                        'presence' => 'Champ Présence (clé exacte)',
                        'adultes'  => 'Champ Nb Adultes (clé exacte)',
                        'enfants'  => 'Champ Nb Enfants (clé exacte)',
                    ) as $role => $label ) : ?>
                        <tr>
                            <th><label><?php echo esc_html( $label ); ?></label></th>
                            <td>
                                <input type="text" style="width:350px"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[map][<?php echo esc_attr( $role ); ?>]"
                                       value="<?php echo esc_attr( $settings['map'][ $role ] ?? '' ); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th><label>Valeur qui signifie "présence confirmée"</label></th>
                        <td>
                            <input type="text" style="width:200px"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[presence_yes_value]"
                                   value="<?php echo esc_attr( $settings['presence_yes_value'] ); ?>"
                                   placeholder="ex: Oui" />
                        </td>
                    </tr>
                    <?php foreach ( $extra as $i => $col ) : ?>
                        <tr>
                            <th><label>Colonne libre <?php echo (int) ( $i + 1 ); ?></label></th>
                            <td>
                                Étiquette
                                <input type="text" style="width:180px"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[extra_columns][<?php echo (int) $i; ?>][label]"
                                       value="<?php echo esc_attr( $col['label'] ); ?>" />
                                Clé exacte
                                <input type="text" style="width:220px"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[extra_columns][<?php echo (int) $i; ?>][key]"
                                       value="<?php echo esc_attr( $col['key'] ); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <?php if ( $settings['form_id'] ) : ?>
                <p><a href="<?php echo esc_url( rest_url( 'rsvp-dashboard/v1/debug/' . $settings['form_id'] ) ); ?>" target="_blank">
                    Voir un exemple de réponse brute (aide pour remplir les clés de champs ci-dessus)
                </a></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
```

Note: `get_or_create_token()` moved here unchanged from the existing file (already present) — this Step 1 reproduces it verbatim so the whole file replacement doesn't lose it.

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, reload Réglages → RSVP Dashboard.
2. Expected: a new "Titre du dashboard" field and 5 "Colonne libre N" rows (each with Étiquette + Clé exacte), all previously-saved values (form, mapping, présence) still intact.
3. Fill "Colonne libre 1" with e.g. Étiquette=`Régime`, Clé=`response.dropdown_3` (adjust to a real field on a test form if available), save, reload, confirm it persisted.

- [ ] **Step 3: Commit**

```bash
git add includes/class-settings.php
git commit -m "Add dashboard_title and 5 optional extra_columns settings"
```

---

### Task 2: Stats endpoint — headcount totals, extra columns, id, exclude trashed

**Files:**
- Modify: `includes/class-rest-api.php`

**Interfaces:**
- Consumes: `RSVP_Dashboard_Settings::get_settings()['extra_columns']` (Task 1) — used server-side only to pull the right field values into each guest's `extra` array; column *labels* are rendered by Task 4's PHP template directly from settings, not from this response.
- Produces: `/stats` now returns `{confirmed:int, declined:int, adults:int, children:int, guests:[{id:int, prenom, nom, presence, adultes, enfants, extra:[string]}]}`. `confirmed`/`declined` are headcount sums (adultes+enfants), not entry counts. Trashed entries excluded by default; `?trash=1` (still token-gated) returns only trashed guests in the same shape, with `confirmed`/`declined`/`adults`/`children` all `0` (not meaningful for the trash view).

- [ ] **Step 1: Replace the whole file**

`includes/class-rest-api.php`:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSVP_Dashboard_Rest_Api {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( 'rsvp-dashboard/v1', '/debug/(?P<form_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'debug_form' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( 'rsvp-dashboard/v1', '/stats', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_stats' ),
            'permission_callback' => array( __CLASS__, 'check_token' ),
        ) );

        register_rest_route( 'rsvp-dashboard/v1', '/entries/(?P<id>\d+)/trash', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'trash_entry' ),
            'permission_callback' => array( __CLASS__, 'check_token' ),
        ) );

        register_rest_route( 'rsvp-dashboard/v1', '/entries/(?P<id>\d+)/restore', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'restore_entry' ),
            'permission_callback' => array( __CLASS__, 'check_token' ),
        ) );
    }

    public static function check_token( $request ) {
        $token = (string) $request->get_param( 'token' );
        return '' !== $token && hash_equals( RSVP_Dashboard_Settings::get_or_create_token(), $token );
    }

    public static function debug_form( $request ) {
        $form_id = (int) $request['form_id'];

        if ( ! function_exists( 'fluentFormApi' ) ) {
            return new WP_Error( 'ff_missing', 'fluentFormApi() introuvable - Fluent Forms Pro est-il actif ?', array( 'status' => 500 ) );
        }

        $formApi = fluentFormApi( 'forms' )->entryInstance( $form_id );
        $entries = $formApi->entries( array( 'per_page' => 1, 'page' => 1 ), true );

        return rest_ensure_response( array(
            'form_id' => $form_id,
            'sample'  => $entries,
        ) );
    }

    private static function fetch_all_entries( $form_id ) {
        if ( ! function_exists( 'fluentFormApi' ) ) {
            return array();
        }
        $formApi     = fluentFormApi( 'forms' )->entryInstance( $form_id );
        $all_entries = array();
        $page        = 1;
        $per_page    = 200;
        $max_pages   = 25;

        do {
            $raw_page    = $formApi->entries( array( 'per_page' => $per_page, 'page' => $page ), true );
            $result_page = json_decode( wp_json_encode( $raw_page ), true );
            $page_data   = ( is_array( $result_page ) && isset( $result_page['data'] ) && is_array( $result_page['data'] ) )
                ? $result_page['data']
                : array();
            $all_entries = array_merge( $all_entries, $page_data );
            $page++;
        } while ( count( $page_data ) === $per_page && $page <= $max_pages );

        return $all_entries;
    }

    public static function get_stats( $request ) {
        $settings = RSVP_Dashboard_Settings::get_settings();
        $form_id  = (int) $settings['form_id'];
        $want_trash = (bool) $request->get_param( 'trash' );

        $result = array(
            'confirmed' => 0,
            'declined'  => 0,
            'adults'    => 0,
            'children'  => 0,
            'guests'    => array(),
        );

        if ( ! $form_id ) {
            return rest_ensure_response( $result );
        }

        $map     = $settings['map'];
        $entries = self::fetch_all_entries( $form_id );

        foreach ( $entries as $entry ) {
            $data       = is_array( $entry ) ? $entry : (array) $entry;
            $is_trashed = isset( $data['status'] ) && 'trashed' === $data['status'];

            if ( $want_trash !== $is_trashed ) {
                continue;
            }

            $presence = self::extract_value( $data, $map['presence'] ?? '' );
            $is_yes   = ( (string) $presence === (string) $settings['presence_yes_value'] );

            $nb_adults   = (int) self::extract_value( $data, $map['adultes'] ?? '' );
            $nb_children = (int) self::extract_value( $data, $map['enfants'] ?? '' );

            if ( ! $want_trash ) {
                if ( $is_yes ) {
                    $result['confirmed'] += $nb_adults + $nb_children;
                    $result['adults']    += $nb_adults;
                    $result['children']  += $nb_children;
                } else {
                    $result['declined'] += $nb_adults + $nb_children;
                }
            }

            $extra_values = array();
            foreach ( $settings['extra_columns'] as $col ) {
                $extra_values[] = self::extract_value( $data, $col['key'] );
            }

            $result['guests'][] = array(
                'id'       => isset( $data['id'] ) ? (int) $data['id'] : 0,
                'prenom'   => self::extract_value( $data, $map['prenom'] ?? '' ),
                'nom'      => self::extract_value( $data, $map['nom'] ?? '' ),
                'presence' => $is_yes ? 'confirmé' : 'décliné',
                'adultes'  => $nb_adults,
                'enfants'  => $nb_children,
                'extra'    => $extra_values,
            );
        }

        return rest_ensure_response( $result );
    }

    public static function trash_entry( $request ) {
        return self::set_entry_status( $request, 'trashed' );
    }

    public static function restore_entry( $request ) {
        return self::set_entry_status( $request, 'read' );
    }

    private static function set_entry_status( $request, $status ) {
        global $wpdb;

        $settings = RSVP_Dashboard_Settings::get_settings();
        $form_id  = (int) $settings['form_id'];
        $entry_id = (int) $request['id'];

        if ( ! $form_id || ! $entry_id ) {
            return new WP_Error( 'rsvp_bad_request', 'form_id ou entry id manquant', array( 'status' => 400 ) );
        }

        $table   = $wpdb->prefix . 'fluentform_submissions';
        $updated = $wpdb->update(
            $table,
            array( 'status' => $status ),
            array( 'id' => $entry_id, 'form_id' => $form_id ),
            array( '%s' ),
            array( '%d', '%d' )
        );

        if ( false === $updated ) {
            return new WP_Error( 'rsvp_db_error', 'Échec de la mise à jour du statut', array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $entry_id, 'status' => $status ) );
    }

    private static function extract_value( $data, $path ) {
        if ( '' === $path ) {
            return '';
        }
        $segments = explode( '.', $path );
        $value    = $data;
        foreach ( $segments as $segment ) {
            if ( is_array( $value ) && isset( $value[ $segment ] ) ) {
                $value = $value[ $segment ];
            } elseif ( is_object( $value ) && isset( $value->$segment ) ) {
                $value = $value->$segment;
            } else {
                return '';
            }
        }
        return is_scalar( $value ) ? $value : '';
    }
}
```

Note on `restore_entry`: it always sets status back to `read` rather than whatever it was before trashing (Fluent Forms doesn't expose the prior value through the API surface we use) — the guest simply reappears as a normal, already-viewed entry. This is a deliberate simplification, not a gap.

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, visit `/wp-json/rsvp-dashboard/v1/stats?token=<your token>` — expected: `confirmed`/`declined` are now headcount sums (compare against known adults/children totals), each guest has an `id` and an `extra` array (empty arrays if no extra columns configured), `columns` lists your configured extra columns.
2. `POST` (e.g. via browser devtools `fetch(..., {method:'POST'})` or a REST client) to `/entries/<a real entry id>/trash?token=...` on a disposable test entry, then re-fetch `/stats` — expected: that guest disappears from the normal response and appears in `/stats?trash=1&token=...`.
3. `POST` to `/entries/<same id>/restore?token=...`, re-fetch `/stats` — expected: guest is back in the normal response, gone from the trash view.

- [ ] **Step 3: Commit**

```bash
git add includes/class-rest-api.php
git commit -m "Add headcount totals, extra columns, and trash/restore endpoints to /stats"
```

---

### Task 3: Shortcode — pass title and extra columns to template/JS

**Files:**
- Modify: `includes/class-shortcode.php`

**Interfaces:**
- Consumes: `RSVP_Dashboard_Settings::get_settings()['dashboard_title']`, `['extra_columns']` (Task 1).
- Produces: template-local `$dashboard_title` (string) and `$extra_columns` (array of `{label,key}`) available to `templates/dashboard-markup.php`; `RSVP_DASHBOARD.apiUrl`/`.trashApiUrl` unchanged in shape (`/stats` URLs with token already in the query string); **new** `RSVP_DASHBOARD.entriesApiUrl` is the bare `/entries` base URL with **no** query string (Task 5 appends `/{id}/trash?token=...` or `/{id}/restore?token=...` itself — token must come after the id segment, not before it); **new** `RSVP_DASHBOARD.token` is the raw token string, for Task 5 to build those URLs.

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
        wp_enqueue_style( 'tabler-core', 'https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css', array(), '1.0.0' );
        wp_enqueue_style( 'tabler-icons', 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css', array(), '1.0.0' );
        wp_enqueue_style( 'rsvp-dashboard-css', RSVP_DASHBOARD_URL . 'assets/css/dashboard.css', array( 'tabler-core' ), RSVP_DASHBOARD_VERSION );

        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4', array(), '4.0.0', true );
        wp_enqueue_script( 'rsvp-dashboard-js', RSVP_DASHBOARD_URL . 'assets/js/dashboard.js', array( 'chartjs' ), RSVP_DASHBOARD_VERSION, true );

        $settings = RSVP_Dashboard_Settings::get_settings();

        $token = RSVP_Dashboard_Settings::get_or_create_token();

        wp_localize_script( 'rsvp-dashboard-js', 'RSVP_DASHBOARD', array(
            'apiUrl'        => esc_url_raw( add_query_arg( 'token', $token, rest_url( 'rsvp-dashboard/v1/stats' ) ) ),
            'trashApiUrl'   => esc_url_raw( add_query_arg( array( 'token' => $token, 'trash' => 1 ), rest_url( 'rsvp-dashboard/v1/stats' ) ) ),
            'entriesApiUrl' => esc_url_raw( rest_url( 'rsvp-dashboard/v1/entries' ) ),
            'token'         => $token,
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

1. Re-upload, view the dashboard page, open browser devtools console, run `RSVP_DASHBOARD` — expected: `apiUrl`, `trashApiUrl`, `entriesApiUrl` all present with the token baked into the query string.

- [ ] **Step 3: Commit**

```bash
git add includes/class-shortcode.php
git commit -m "Pass dashboard title, extra columns, and trash/entries URLs to the front end"
```

---

### Task 4: Dashboard markup — navbar, badges, extra columns, filter, trash UI

**Files:**
- Modify: `templates/dashboard-markup.php`

**Interfaces:**
- Consumes: `$dashboard_title`, `$extra_columns` (Task 3, in-scope PHP variables from the including file).
- Produces: DOM ids consumed by Task 5's `dashboard.js`: `rsvp-dash-confirmed`, `rsvp-dash-declined`, `rsvp-dash-adults`, `rsvp-dash-children`, `rsvp-dash-chart`, `rsvp-dash-search`, `rsvp-dash-table-body`, `rsvp-dash-filter-menu` (the filter dropdown's `<div class="dropdown-menu">`), `rsvp-dash-filter-label` (text shown on the filter button), `rsvp-dash-trash-toggle` (button to show/hide the corbeille panel), `rsvp-dash-trash-panel`, `rsvp-dash-trash-body`. Table `<thead>` row gets one extra `<th>` per configured extra column, in the same order Task 2's `/stats` returns `columns`.

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
```

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, view the dashboard page. Expected: navbar shows the configured title (or the site name if empty), 4 stat cards with green/red "personnes" badges, a "Corbeille" button next to "Invités", a filter dropdown next to the search box, and one extra `<th>` per configured extra column (visible even before Task 5's JS fills in data).

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard-markup.php
git commit -m "Redesign dashboard markup: navbar, badges, filter dropdown, trash panel, extra columns"
```

---

### Task 5: Front-end script — badges, extra columns, filter, trash/restore

**Files:**
- Modify: `assets/js/dashboard.js`

**Interfaces:**
- Consumes: `RSVP_DASHBOARD.apiUrl`, `.trashApiUrl`, `.entriesApiUrl` (Task 3); DOM ids from Task 4; `/stats` response shape from Task 2 (`columns`, `guests[].id`, `.extra`).

- [ ] **Step 1: Replace the whole file**

`assets/js/dashboard.js`:
```javascript
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
        '<td><button type="button" class="btn btn-icon btn-sm rsvp-dash-trash-btn" data-id="' + g.id + '">' +
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
        '<td><button type="button" class="btn btn-icon btn-sm rsvp-dash-restore-btn" data-id="' + g.id + '">' +
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
```

Note: `entriesApiUrl` (Task 3) is deliberately the bare `/entries` URL with no query string, so the id can be inserted as a path segment (`/entries/123/trash`) before the `?token=...` query string is appended — putting the token before the id, as an earlier draft of this plan did, would have produced an invalid URL (`/entries?token=X/123/trash`).

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, reload the dashboard page. Expected: badges (green/red) render instead of plain text, extra column values appear if configured, filter dropdown changes the label and filters the table, search still works combined with the filter.
2. Click a trash icon on a real test row — expected: the row disappears from the main table; click "Corbeille" — expected: the panel opens and shows that guest with a restore icon.
3. Click restore — expected: guest disappears from the corbeille panel and reappears in the main table within the next 20s refresh (or immediately, since `restoreEntry` also calls `fetchStats()`).

- [ ] **Step 3: Commit**

```bash
git add assets/js/dashboard.js
git commit -m "Add badges, extra columns, filter dropdown, and trash/restore UI to dashboard.js"
```

---

### Task 6: Full end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Fresh look, real data**

On the live site: confirm the navbar title, all 4 stat cards, the donut chart, badges, and at least one configured extra column render correctly with real data.

- [ ] **Step 2: Trash/restore round-trip on a real (test) entry**

Trash a genuine test entry via the dashboard's own trash icon (not Fluent Forms' admin), confirm it vanishes from the main list and appears in the Corbeille panel with correct data, restore it, confirm it's back. Then independently check in Fluent Forms' own admin entries list that the entry shows as no longer trashed there too (proving the two systems share the same `status` column, not a separate parallel one).

- [ ] **Step 3: Filter + search combined**

Type a partial name in the search box while a filter (Confirmés/Déclinés) is active — expected: both conditions apply together (AND, not OR).

- [ ] **Step 4: Second, differently-structured site**

Repeat Task 1-5's manual verification steps on a second Fluent Forms form with a different field layout and at least one extra column configured differently (or none at all) — confirms the "no code change between sites" property still holds after this v2 pass.

- [ ] **Step 5: Update the README**

Add to `README.md`: a line documenting the "Titre du dashboard" and "Colonnes supplémentaires" settings, the new headcount-based meaning of Confirmés/Déclinés, the trash/restore workflow (and that it shares Fluent Forms' own trash status — restoring from Fluent Forms' admin also works), and a step in the installation checklist pointing at WordPress's native Page Attributes → Visibility → "Protégé par mot de passe" for gating the dashboard page itself.

- [ ] **Step 6: Final commit**

```bash
git add -A
git commit -m "Complete end-to-end verification of dashboard v2"
```
