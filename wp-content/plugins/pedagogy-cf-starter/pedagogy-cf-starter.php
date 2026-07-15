<?php
/**
 * Plugin Name: Pedagogy Custom Fields Starter
 * Description: Starter plugin to define custom fields (title + datatype) via an admin UI and automatically add meta boxes to posts/pages.
 * Version: 0.1
 * Author: Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pedagogy_CF_Starter {

    const OPTION_KEY = 'pedagogy_cf_definitions';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_post_pedagogy_cf_add', array( $this, 'handle_add_field' ) );
        add_action( 'admin_post_pedagogy_cf_delete', array( $this, 'handle_delete_field' ) );

        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_post_fields' ) );
        add_filter( 'the_content', array( $this, 'inject_fields_into_content' ), 5 );
    }

    /* Admin: menu and page */
    public function admin_menu() {
        add_menu_page( 'Pedagogy Fields', 'Pedagogy Fields', 'manage_options', 'pedagogy-cf', array( $this, 'render_admin_page' ), 'dashicons-list-view', 60 );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $defs = $this->get_definitions();
        $editing = false;
        $edit_name = '';
        $edit_def = null;
        if ( isset( $_GET['edit'] ) ) {
            $edit_name = sanitize_text_field( wp_unslash( $_GET['edit'] ) );
            if ( isset( $defs[ $edit_name ] ) ) {
                $editing = true;
                $edit_def = $defs[ $edit_name ];
            }
        }
        ?>
        <div class="wrap">
            <h1>Pedagogy Custom Fields</h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'pedagogy_cf_add' ); ?>
                <input type="hidden" name="action" value="pedagogy_cf_add" />
                <?php if ( $editing ) : ?>
                    <input type="hidden" name="original_name" value="<?php echo esc_attr( $edit_name ); ?>" />
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pcf_title">Field Title</label></th>
                        <td><input id="pcf_title" name="pcf_title" type="text" required value="<?php echo $editing ? esc_attr( $edit_def['title'] ) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="pcf_name">Field Name (slug)</label></th>
                        <td>
                            <input id="pcf_name" name="pcf_name" type="text" pattern="[a-z0-9_]+" required value="<?php echo $editing ? esc_attr( $edit_name ) : ''; ?>" <?php echo $editing ? 'readonly' : ''; ?> >
                            <?php if ( $editing ) : ?>
                                <p class="description">Field name cannot be changed. To rename, delete and re-create.</p>
                            <?php else: ?>
                                <p class="description">Lowercase letters, numbers and underscore only.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pcf_type">Datatype</label></th>
                        <td>
                            <select id="pcf_type" name="pcf_type">
                                <option value="text" <?php selected( $editing && $edit_def['type'] === 'text' ); ?>>Text</option>
                                <option value="textarea" <?php selected( $editing && $edit_def['type'] === 'textarea' ); ?>>Textarea</option>
                                <option value="number" <?php selected( $editing && $edit_def['type'] === 'number' ); ?>>Number</option>
                                <option value="date" <?php selected( $editing && $edit_def['type'] === 'date' ); ?>>Date</option>
                                <option value="select" <?php selected( $editing && $edit_def['type'] === 'select' ); ?>>Select (comma-separated options)</option>
                                <option value="linked" <?php selected( $editing && $edit_def['type'] === 'linked' ); ?>>Linked Field Options</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="options_row" style="display:<?php echo ( $editing && isset( $edit_def['options'] ) ) ? 'table-row' : 'none'; ?>;">
                        <th><label for="pcf_options">Options</label></th>
                        <td><input id="pcf_options" name="pcf_options" type="text" value="<?php echo $editing && isset( $edit_def['options'] ) ? esc_attr( implode( ', ', $edit_def['options'] ) ) : ''; ?>"><p class="description">Comma-separated values for select.</p></td>
                    </tr>
                    <tr id="linked_source_row" style="display:<?php echo ( $editing && isset( $edit_def['type'] ) && $edit_def['type'] === 'linked' ) ? 'table-row' : 'none'; ?>;">
                        <th><label for="pcf_linked_source">Linked Source Field</label></th>
                        <td>
                            <select id="pcf_linked_source" name="pcf_linked_source">
                                <option value="">-- Select source field --</option>
                                <?php foreach ( $defs as $source_name => $source_def ) : ?>
                                    <?php if ( $source_name === $edit_name ) { continue; } ?>
                                    <?php if ( ! in_array( $source_def['type'], array( 'select' ), true ) ) { continue; } ?>
                                    <option value="<?php echo esc_attr( $source_name ); ?>" <?php selected( $editing && isset( $edit_def['source_field'] ) && $edit_def['source_field'] === $source_name ); ?>><?php echo esc_html( $source_def['title'] . ' (' . $source_name . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Choose another field that provides options for this linked field.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( $editing ? 'Update Field' : 'Add Field' ); ?>
            </form>

            <h2>Defined Fields</h2>
            <?php if ( empty( $defs ) ) : ?>
                <p>No fields defined yet.</p>
            <?php else: ?>
                <table class="widefat">
                    <thead><tr><th>Title</th><th>Name</th><th>Type</th><th>Options</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ( $defs as $name => $d ) : ?>
                            <tr>
                                <td><?php echo esc_html( $d['title'] ); ?></td>
                                <td><?php echo esc_html( $name ); ?></td>
                                <td><?php echo esc_html( $d['type'] ); ?></td>
                                <td><?php
                                    if ( isset( $d['type'] ) && $d['type'] === 'linked' ) {
                                        echo isset( $d['source_field'] ) ? esc_html( 'linked to ' . $d['source_field'] ) : esc_html( 'no source set' );
                                    } else {
                                        echo isset( $d['options'] ) ? esc_html( implode( ', ', $d['options'] ) ) : '';
                                    }
                                ?></td>
                                <td>
                                            <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'pedagogy-cf', 'edit' => $name ), admin_url( 'admin.php' ) ) ); ?>">Edit</a>
                                            <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pedagogy_cf_delete&name=' . $name ), 'pedagogy_cf_delete' ) ); ?>">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var type = document.getElementById('pcf_type');
            var optionsRow = document.getElementById('options_row');
            var linkedSourceRow = document.getElementById('linked_source_row');
            function update(){
                optionsRow.style.display = type.value === 'select' ? '' : 'none';
                linkedSourceRow.style.display = type.value === 'linked' ? '' : 'none';
            }
            type.addEventListener('change', update);
            update();
        })();
        </script>
        <?php
    }

    /* Handlers for add/delete */
    public function handle_add_field() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }
        check_admin_referer( 'pedagogy_cf_add' );

        $title = sanitize_text_field( wp_unslash( $_POST['pcf_title'] ?? '' ) );
        $name  = sanitize_text_field( wp_unslash( $_POST['pcf_name'] ?? '' ) );
        $type  = sanitize_text_field( wp_unslash( $_POST['pcf_type'] ?? 'text' ) );
        $opts  = sanitize_text_field( wp_unslash( $_POST['pcf_options'] ?? '' ) );
        $original = isset( $_POST['original_name'] ) ? sanitize_text_field( wp_unslash( $_POST['original_name'] ) ) : '';

        if ( ! preg_match( '/^[a-z0-9_]+$/', $name ) ) {
            wp_redirect( add_query_arg( 'error', 'invalid_name', admin_url( 'admin.php?page=pedagogy-cf' ) ) );
            exit;
        }

        $defs = $this->get_definitions();

        $entry = array( 'title' => $title, 'type' => $type );
        if ( 'select' === $type ) {
            $entry['options'] = array_map( 'trim', explode( ',', $opts ) );
        } elseif ( 'linked' === $type ) {
            $source = sanitize_text_field( wp_unslash( $_POST['pcf_linked_source'] ?? '' ) );
            if ( $source && isset( $defs[ $source ] ) && isset( $defs[ $source ]['type'] ) && $defs[ $source ]['type'] === 'select' ) {
                $entry['source_field'] = $source;
            } else {
                $entry['source_field'] = '';
            }
        }

        if ( $original ) {
            // Update existing definition but do not allow changing the slug/name.
            if ( isset( $defs[ $original ] ) ) {
                $defs[ $original ] = $entry;
                update_option( self::OPTION_KEY, $defs );
            }
        } else {
            if ( isset( $defs[ $name ] ) ) {
                wp_redirect( add_query_arg( 'error', 'exists', admin_url( 'admin.php?page=pedagogy-cf' ) ) );
                exit;
            }
            $defs[ $name ] = $entry;
            update_option( self::OPTION_KEY, $defs );
        }

        wp_redirect( admin_url( 'admin.php?page=pedagogy-cf' ) );
        exit;
    }

    public function handle_delete_field() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }
        check_admin_referer( 'pedagogy_cf_delete' );
        $name = sanitize_text_field( wp_unslash( $_GET['name'] ?? '' ) );
        $defs = $this->get_definitions();
        if ( isset( $defs[ $name ] ) ) {
            unset( $defs[ $name ] );
            update_option( self::OPTION_KEY, $defs );
        }
        wp_redirect( admin_url( 'admin.php?page=pedagogy-cf' ) );
        exit;
    }

    private function get_definitions() {
        $defs = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $defs ) ) {
            $defs = array();
        }
        return $defs;
    }

    /* Add meta boxes per definition */
    public function add_meta_boxes() {
        $defs = $this->get_definitions();
        if ( empty( $defs ) ) {
            return;
        }
        foreach ( array( 'post', 'page' ) as $pt ) {
            foreach ( $defs as $name => $d ) {
                add_meta_box( 'pcf_' . $name, $d['title'], function( $post, $box ) use ( $name, $d ) {
                    $this->render_field_box( $post, $name, $d );
                }, $pt, 'normal', 'default' );
            }
        }
    }

    private function render_field_box( $post, $name, $def ) {
        $meta_key = 'pcf_' . $name;
        wp_nonce_field( 'pedagogy_cf_save', 'pedagogy_cf_nonce' );
        $value = get_post_meta( $post->ID, $meta_key, true );
        switch ( $def['type'] ) {
            case 'textarea':
                echo '<textarea style="width:100%;" name="' . esc_attr( $meta_key ) . '">' . esc_textarea( $value ) . '</textarea>';
                break;
            case 'number':
                echo '<input type="number" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
                break;
            case 'date':
                echo '<input type="date" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
                break;
            case 'select':
                echo '<select name="' . esc_attr( $meta_key ) . '" class="widefat">';
                $opts = $def['options'] ?? array();
                foreach ( $opts as $o ) {
                    $sel = selected( $value, $o, false );
                    echo '<option value="' . esc_attr( $o ) . '" ' . $sel . '>' . esc_html( $o ) . '</option>';
                }
                echo '</select>';
                break;
            case 'linked':
                $source = isset( $def['source_field'] ) ? $def['source_field'] : '';
                $opts = array();
                if ( $source ) {
                    $source_def = isset( $this->get_definitions()[ $source ] ) ? $this->get_definitions()[ $source ] : null;
                    if ( $source_def && isset( $source_def['options'] ) ) {
                        $opts = $source_def['options'];
                    }
                }
                echo '<select name="' . esc_attr( $meta_key ) . '" class="widefat">';
                echo '<option value="">' . esc_html__( '-- Select option --', 'pedagogy' ) . '</option>';
                foreach ( $opts as $o ) {
                    $sel = selected( $value, $o, false );
                    echo '<option value="' . esc_attr( $o ) . '" ' . $sel . '>' . esc_html( $o ) . '</option>';
                }
                echo '</select>';
                break;
            case 'text':
            default:
                echo '<input type="text" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
                break;
        }
    }

    public function save_post_fields( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['pedagogy_cf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pedagogy_cf_nonce'] ) ), 'pedagogy_cf_save' ) ) {
            return;
        }

        $defs = $this->get_definitions();
        if ( empty( $defs ) ) {
            return;
        }

        foreach ( $defs as $name => $d ) {
            $meta_key = 'pcf_' . $name;
            if ( ! isset( $_POST[ $meta_key ] ) ) {
                continue;
            }
            $raw = wp_unslash( $_POST[ $meta_key ] );
            switch ( $d['type'] ) {
                case 'number':
                    $val = floatval( $raw );
                    break;
                case 'date':
                    $val = sanitize_text_field( $raw );
                    break;
                case 'textarea':
                    $val = sanitize_textarea_field( $raw );
                    break;
                case 'linked':
                    $val = sanitize_text_field( $raw );
                    break;
                case 'select':
                case 'text':
                default:
                    $val = sanitize_text_field( $raw );
                    break;
            }
            update_post_meta( $post_id, $meta_key, $val );
        }
    }

    /* Helpers for themes */
    public static function get_value( $post_id, $name ) {
        $meta_key = 'pcf_' . $name;
        return get_post_meta( $post_id, $meta_key, true );
    }

public static function get_definition( $name ) {
        $defs = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $defs ) ) {
            return null;
        }
        return isset( $defs[ $name ] ) ? $defs[ $name ] : null;
    }

    public static function display_field( $name, $post_id = null ) {
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }
        $val = self::get_value( $post_id, $name );
        if ( '' === $val || null === $val ) {
            return;
        }
        $def = self::get_definition( $name );
        if ( isset( $def['type'] ) && $def['type'] === 'linked' ) {
            $linked = get_post( $val );
            if ( $linked ) {
                echo '<div class="pcf-' . esc_attr( $name ) . '"><a href="' . esc_url( get_permalink( $linked ) ) . '">' . esc_html( get_the_title( $linked ) ) . '</a></div>';
                return;
            }
        }
        echo '<div class="pcf-' . esc_attr( $name ) . '">' . wp_kses_post( wpautop( $val ) ) . '</div>';
    }

    /**
     * Auto-inject defined fields into post/page content if values exist.
     */
    public function inject_fields_into_content( $content ) {
        if ( is_admin() || ! is_singular( array( 'post', 'page' ) ) || ! in_the_loop() ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $content;
        }

        $defs = $this->get_definitions();
        if ( empty( $defs ) ) {
            return $content;
        }

        $out = '';
        foreach ( $defs as $name => $d ) {
            $meta_key = 'pcf_' . $name;
            $val = get_post_meta( $post_id, $meta_key, true );
            if ( $val === '' || $val === null ) {
                continue;
            }

            $field_html = '';
            $label = isset( $d['title'] ) ? esc_html( $d['title'] ) : esc_html( $name );
            $field_html .= '<div class="pcf-field pcf-' . esc_attr( $name ) . '">';
            $field_html .= '<div class="pcf-label">' . $label . '</div>';
            switch ( $d['type'] ) {
                case 'textarea':
                    $field_html .= '<div class="pcf-value">' . wp_kses_post( wpautop( $val ) ) . '</div>';
                    break;
                case 'number':
                case 'date':
                case 'select':
                case 'text':
                default:
                    $field_html .= '<div class="pcf-value">' . esc_html( $val ) . '</div>';
                    break;
            }
            $field_html .= '</div>';
            $out .= $field_html;
        }

        if ( $out === '' ) {
            return $content;
        }

        return $out . $content;
    }

}

new Pedagogy_CF_Starter();
