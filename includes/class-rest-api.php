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
            'permission_callback' => function ( $request ) {
                $token = (string) $request->get_param( 'token' );
                return '' !== $token && hash_equals( RSVP_Dashboard_Settings::get_or_create_token(), $token );
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

    public static function get_stats() {
        $settings = RSVP_Dashboard_Settings::get_settings();
        $form_id  = (int) $settings['form_id'];

        $result = array(
            'confirmed' => 0,
            'declined'  => 0,
            'adults'    => 0,
            'children'  => 0,
            'guests'    => array(),
        );

        if ( ! $form_id || ! function_exists( 'fluentFormApi' ) ) {
            return rest_ensure_response( $result );
        }

        $formApi = fluentFormApi( 'forms' )->entryInstance( $form_id );

        $all_entries = array();
        $page        = 1;
        $per_page    = 200;
        $max_pages   = 25; // safety cap: 25 * 200 = 5000 entries max

        do {
            $result_page = $formApi->entries( array( 'per_page' => $per_page, 'page' => $page ), true );
            $result_page = is_array( $result_page ) ? $result_page : (array) $result_page;
            $page_data   = isset( $result_page['data'] ) && is_array( $result_page['data'] ) ? $result_page['data'] : array();
            $all_entries = array_merge( $all_entries, $page_data );
            $page++;
        } while ( count( $page_data ) === $per_page && $page <= $max_pages );

        $map     = $settings['map'];

        foreach ( $all_entries as $entry ) {
            $data = is_array( $entry ) ? $entry : (array) $entry;

            $presence = self::extract_value( $data, $map['presence'] ?? '' );
            $is_yes   = ( (string) $presence === (string) $settings['presence_yes_value'] );

            $nb_adults   = (int) self::extract_value( $data, $map['adultes'] ?? '' );
            $nb_children = (int) self::extract_value( $data, $map['enfants'] ?? '' );

            if ( $is_yes ) {
                $result['confirmed']++;
                $result['adults']   += $nb_adults;
                $result['children'] += $nb_children;
            } else {
                $result['declined']++;
            }

            $result['guests'][] = array(
                'prenom'   => self::extract_value( $data, $map['prenom'] ?? '' ),
                'nom'      => self::extract_value( $data, $map['nom'] ?? '' ),
                'presence' => $is_yes ? 'confirmé' : 'décliné',
                'adultes'  => $nb_adults,
                'enfants'  => $nb_children,
            );
        }

        return rest_ensure_response( $result );
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
