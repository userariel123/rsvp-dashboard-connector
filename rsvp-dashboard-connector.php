<?php
/**
 * Plugin Name: RSVP Dashboard
 * Description: Displays a live RSVP dashboard (Tabler UI) fed by Fluent Forms Pro submissions.
 * Version: 1.0.0
 * Author: Ariel
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RSVP_DASHBOARD_VERSION', '1.0.0' );
define( 'RSVP_DASHBOARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSVP_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

require_once RSVP_DASHBOARD_DIR . 'includes/class-settings.php';
require_once RSVP_DASHBOARD_DIR . 'includes/class-rest-api.php';
require_once RSVP_DASHBOARD_DIR . 'includes/class-shortcode.php';

function rsvp_dashboard_init() {
    if ( ! function_exists( 'fluentFormApi' ) ) {
        add_action( 'admin_notices', 'rsvp_dashboard_missing_dependency_notice' );
        return;
    }

    RSVP_Dashboard_Settings::init();
    RSVP_Dashboard_Rest_Api::init();
    RSVP_Dashboard_Shortcode::init();
}
add_action( 'plugins_loaded', 'rsvp_dashboard_init' );

function rsvp_dashboard_missing_dependency_notice() {
    echo '<div class="notice notice-error"><p>RSVP Dashboard nécessite Fluent Forms Pro (fonction fluentFormApi() introuvable).</p></div>';
}
