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
}
