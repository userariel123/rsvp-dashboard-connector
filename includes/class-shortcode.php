<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSVP_Dashboard_Shortcode {

    public static function init() {
        add_shortcode( 'rsvp_dashboard', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts ) {
        wp_enqueue_style( 'tabler-core', 'https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css', array(), '1.0.0' );
        wp_enqueue_style( 'rsvp-dashboard-css', RSVP_DASHBOARD_URL . 'assets/css/dashboard.css', array( 'tabler-core' ), RSVP_DASHBOARD_VERSION );

        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4', array(), '4.0.0', true );
        wp_enqueue_script( 'rsvp-dashboard-js', RSVP_DASHBOARD_URL . 'assets/js/dashboard.js', array( 'chartjs' ), RSVP_DASHBOARD_VERSION, true );

        wp_localize_script( 'rsvp-dashboard-js', 'RSVP_DASHBOARD', array(
            'apiUrl' => esc_url_raw( add_query_arg( 'token', RSVP_Dashboard_Settings::get_or_create_token(), rest_url( 'rsvp-dashboard/v1/stats' ) ) ),
        ) );

        ob_start();
        include RSVP_DASHBOARD_DIR . 'templates/dashboard-markup.php';
        return ob_get_clean();
    }
}
