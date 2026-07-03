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
