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

        register_rest_route( 'rsvp-dashboard/v1', '/field-values/(?P<form_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'field_values' ),
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

    // These endpoints return live, fast-changing data (or trigger a write), so page/edge
    // caches (LiteSpeed Cache confirmed caching /stats for 7 days on a real site, silently
    // freezing the "auto-updates every 20s" feature) must never cache them. nocache_headers()
    // covers the general case; the LiteSpeed-specific header is a targeted fix for the exact
    // cache plugin that was observed serving a week-old response.
    private static function send_no_cache_headers() {
        nocache_headers();
        header( 'X-LiteSpeed-Cache-Control: no-cache' );
    }

    public static function debug_form( $request ) {
        self::send_no_cache_headers();
        $form_id = (int) $request['form_id'];

        if ( ! function_exists( 'fluentFormApi' ) ) {
            return new WP_Error( 'ff_missing', 'fluentFormApi() introuvable - Fluent Forms Pro est-il actif ?', array( 'status' => 500 ) );
        }

        $formApi  = fluentFormApi( 'forms' )->entryInstance( $form_id );
        $entries  = $formApi->entries( array( 'per_page' => 1, 'page' => 1 ), true );
        $decoded  = json_decode( wp_json_encode( $entries ), true );
        $response = ( is_array( $decoded ) && isset( $decoded['data'][0]['response'] ) && is_array( $decoded['data'][0]['response'] ) )
            ? $decoded['data'][0]['response']
            : array();

        // Fluent Forms mixes real form fields with its own housekeeping keys
        // (nonce, referer, embedded post id) in the same "response" array; those
        // all start with "_" and aren't things an admin would ever map a column to.
        $champs = array();
        foreach ( $response as $key => $value ) {
            if ( 0 === strpos( $key, '_' ) ) {
                continue;
            }
            $champs[ 'response.' . $key ] = is_scalar( $value ) ? $value : '';
        }

        return rest_ensure_response( array(
            'form_id' => $form_id,
            'champs'  => $champs,
        ) );
    }

    public static function field_values( $request ) {
        self::send_no_cache_headers();
        $form_id = (int) $request['form_id'];
        $key     = (string) $request->get_param( 'key' );

        if ( ! function_exists( 'fluentFormApi' ) || '' === $key || ! $form_id ) {
            return rest_ensure_response( array( 'values' => array() ) );
        }

        $entries = self::fetch_all_entries( $form_id );
        $values  = array();

        foreach ( $entries as $entry ) {
            $data  = is_array( $entry ) ? $entry : (array) $entry;
            $value = self::extract_value( $data, $key );
            if ( '' !== $value && ! in_array( (string) $value, $values, true ) ) {
                $values[] = (string) $value;
            }
        }

        return rest_ensure_response( array( 'values' => $values ) );
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
        self::send_no_cache_headers();
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
                    $result['declined']++;
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

    // Restored entries are always set to 'read': Fluent Forms' status prior to trashing
    // isn't recoverable through the API surface this plugin uses, so 'read' is used as an
    // intentional, safe default rather than a bug.
    public static function restore_entry( $request ) {
        return self::set_entry_status( $request, 'read' );
    }

    // Writes directly to Fluent Forms' internal `{$wpdb->prefix}fluentform_submissions`
    // table/`status` column and relies on the 'trashed' status value; this couples us to
    // Fluent Forms' internal schema (confirmed via live testing against a real site, not
    // official public API docs). Re-verify table/column/status-value here if trash/restore
    // stops working after a Fluent Forms Pro update.
    private static function set_entry_status( $request, $status ) {
        self::send_no_cache_headers();
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
