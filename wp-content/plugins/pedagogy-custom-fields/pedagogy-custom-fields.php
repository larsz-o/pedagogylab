<?php
/**
 * Plugin Name: Pedagogy Custom Fields
 * Description: Simple plugin to add custom meta boxes (subtitle, featured note) to posts and pages and save them securely.
 * Version: 0.1
 * Author: Automated Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pedagogy_Custom_Fields {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta' ) );
        add_action( 'init', array( $this, 'register_meta_for_rest' ) );

        if ( function_exists( 'acf_add_local_field_group' ) ) {
            add_action( 'acf/init', array( $this, 'register_acf_fields' ) );
        } else {
            add_action( 'admin_notices', array( $this, 'acf_missing_notice' ) );
        }

        // Display helpers: auto-inject into the_content and provide a template tag
        add_filter( 'the_content', array( $this, 'filter_the_content' ), 5 );
    }

    public function add_meta_boxes() {
        $post_types = array( 'post', 'page' );
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'pedagogy_custom_fields',
                __( 'Pedagogy Fields', 'pedagogy' ),
                array( $this, 'render_meta_box' ),
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'pedagogy_save_meta', 'pedagogy_meta_nonce' );

        $subtitle = get_post_meta( $post->ID, '_pcf_subtitle', true );
        $featured = get_post_meta( $post->ID, '_pcf_featured_note', true );
        ?>
        <p>
            <label for="pcf_subtitle"><strong><?php esc_html_e( 'Subtitle', 'pedagogy' ); ?></strong></label><br />
            <input type="text" id="pcf_subtitle" name="pcf_subtitle" value="<?php echo esc_attr( $subtitle ); ?>" style="width:100%;" />
        </p>
        <p>
            <label for="pcf_featured_note"><strong><?php esc_html_e( 'Featured Note', 'pedagogy' ); ?></strong></label><br />
            <textarea id="pcf_featured_note" name="pcf_featured_note" rows="4" style="width:100%;"><?php echo esc_textarea( $featured ); ?></textarea>
        </p>
        <?php
    }

    public function save_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['pedagogy_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pedagogy_meta_nonce'] ) ), 'pedagogy_save_meta' ) ) {
            return;
        }

        if ( isset( $_POST['pcf_subtitle'] ) ) {
            $subtitle = sanitize_text_field( wp_unslash( $_POST['pcf_subtitle'] ) );
            update_post_meta( $post_id, '_pcf_subtitle', $subtitle );
        }

        if ( isset( $_POST['pcf_featured_note'] ) ) {
            $featured = sanitize_textarea_field( wp_unslash( $_POST['pcf_featured_note'] ) );
            update_post_meta( $post_id, '_pcf_featured_note', $featured );
        }
    }

    public function register_acf_fields() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( array(
            'key' => 'group_pedagogy_fields',
            'title' => 'Pedagogy Fields',
            'fields' => array(
                array(
                    'key' => 'field_pcf_subtitle',
                    'label' => 'Subtitle',
                    'name' => 'pcf_subtitle',
                    'type' => 'text',
                ),
                array(
                    'key' => 'field_pcf_featured_note',
                    'label' => 'Featured Note',
                    'name' => 'pcf_featured_note',
                    'type' => 'textarea',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'page',
                    ),
                ),
            ),
            'position' => 'normal',
            'style' => 'default',
        ) );
    }

    public function acf_missing_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        $install_url = esc_url( admin_url( 'plugin-install.php?tab=search&s=Advanced+Custom+Fields' ) );
        echo '<div class="notice notice-warning"><p>'; 
        echo sprintf( __( 'Pedagogy Custom Fields: Advanced Custom Fields plugin not found. For a nicer UI, install/activate <a href="%s">Advanced Custom Fields</a>.', 'pedagogy' ), $install_url );
        echo '</p></div>';
    }

    public function register_meta_for_rest() {
        register_post_meta( 'post', '_pcf_subtitle', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );

        register_post_meta( 'post', '_pcf_featured_note', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );

        register_post_meta( 'page', '_pcf_subtitle', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );

        register_post_meta( 'page', '_pcf_featured_note', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );

        // Also expose ACF-backed keys (without leading underscore) for REST
        register_post_meta( 'post', 'pcf_subtitle', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );

        register_post_meta( 'post', 'pcf_featured_note', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );

        register_post_meta( 'page', 'pcf_subtitle', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );

        register_post_meta( 'page', 'pcf_featured_note', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ) );
    }

    /**
     * Retrieve a field value, preferring ACF's get_field when available.
     */
    public static function get_field_value( $post_id, $field_name ) {
        if ( function_exists( 'get_field' ) ) {
            $v = get_field( $field_name, $post_id );
            if ( $v !== null ) {
                return $v;
            }
        }

        $val = get_post_meta( $post_id, $field_name, true );
        if ( '' === $val ) {
            $val = get_post_meta( $post_id, '_' . $field_name, true );
        }
        return $val;
    }

    /**
     * Template tag developers can call in theme templates.
     */
    public static function pedagogy_display_fields( $post_id = null ) {
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }
        if ( ! $post_id ) {
            return;
        }

        $subtitle = self::get_field_value( $post_id, 'pcf_subtitle' );
        $featured = self::get_field_value( $post_id, 'pcf_featured_note' );

        if ( $subtitle ) {
            echo '<p class="pedagogy-subtitle">' . esc_html( $subtitle ) . '</p>';
        }
        if ( $featured ) {
            echo '<div class="pedagogy-featured">' . wp_kses_post( wpautop( $featured ) ) . '</div>';
        }
    }

    /**
     * Auto-inject subtitle/featured note above post content on singular posts/pages.
     */
    public function filter_the_content( $content ) {
        if ( is_admin() || ! is_singular( array( 'post', 'page' ) ) || ! in_the_loop() ) {
            return $content;
        }

        $post_id = get_the_ID();
        $subtitle = self::get_field_value( $post_id, 'pcf_subtitle' );
        $featured = self::get_field_value( $post_id, 'pcf_featured_note' );

        if ( ! $subtitle && ! $featured ) {
            return $content;
        }

        $out = '';
        if ( $subtitle ) {
            $out .= '<p class="pedagogy-subtitle">' . esc_html( $subtitle ) . '</p>';
        }
        if ( $featured ) {
            $out .= '<div class="pedagogy-featured">' . wp_kses_post( wpautop( $featured ) ) . '</div>';
        }

        return $out . $content;
    }

}

new Pedagogy_Custom_Fields();
