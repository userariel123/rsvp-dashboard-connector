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
