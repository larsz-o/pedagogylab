<?php
/**
 * Plugin Name: library-page
 * Description: Starter plugin to define custom fields (title + datatype) via an admin UI and automatically add meta boxes to posts/pages.
 * Version: 0.1
 * Author: Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pedagogy_CF_Starter {

    const OPTION_KEY = 'pedagogy_cf_definitions';
    const NOTICE_TRANSIENT_PREFIX = 'pedagogy_cf_master_options_added_';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_post_pedagogy_cf_add', array( $this, 'handle_add_field' ) );
        add_action( 'admin_post_pedagogy_cf_delete', array( $this, 'handle_delete_field' ) );

        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_post_fields' ) );
        add_action( 'admin_notices', array( $this, 'maybe_render_master_options_notice' ) );
        add_filter( 'the_content', array( $this, 'inject_fields_into_content' ), 5 );
        add_action( 'admin_head', array( $this, 'admin_inline_css' ) );
        add_filter( 'theme_templates', array( $this, 'register_page_template' ), 10, 4 );
        add_filter( 'theme_page_templates', array( $this, 'register_page_template' ), 10, 4 );
        add_filter( 'template_include', array( $this, 'load_page_template' ) );
        add_filter( 'get_user_option_meta-box-order_post', array( $this, 'filter_meta_box_order_post' ), 10, 3 );
        add_filter( 'get_user_option_meta-box-order_page', array( $this, 'filter_meta_box_order_page' ), 10, 3 );
    }

    public function register_page_template( $templates, $theme, $post, $post_type ) {
        if ( 'page' !== $post_type ) {
            return $templates;
        }

        $templates['oer-library.php'] = __( 'OER Library (short)', 'library-page' );
        $templates['oer-library'] = __( 'OER Library (short)', 'library-page' );
        return $templates;
    }

    public function load_page_template( $template ) {
        if ( ! is_page() ) {
            return $template;
        }

        $post_template = get_page_template_slug( get_queried_object_id() );
        if ( ! in_array( $post_template, array( 'long-item-page.php', 'long-item-page', 'page-post-cards.php', 'page-post-cards' ), true ) ) {
            return $template;
        }

        $plugin_template = plugin_dir_path( __FILE__ ) . 'oer-library.php';
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        return $template;
    }

    public function admin_inline_css() {
        echo "<style>\n";
        echo "/* Hide meta box move controls and panel toggle for pedagogy pcf_ meta boxes */\n";
        echo "div[id^=\"pcf_\"] .handle-actions, div[id^=\"pcf_\"] .handlediv { display: none !important; }\n";
        echo "div[id^=\"pcf_\"] .hndle { cursor: default; }\n";
        echo ".pcf-defined-fields-table{border-collapse:separate;border-spacing:0;}\n";
        echo ".pcf-defined-fields-table thead th{border-bottom:2px solid #cbd5e1;background:#f8fafc;}\n";
        echo ".pcf-defined-fields-table tbody td{border-bottom:1px solid #dbe4ee;padding-top:14px;padding-bottom:14px;vertical-align:middle;}\n";
        echo ".pcf-defined-fields-table tbody tr:last-child td{border-bottom:none;}\n";
        echo ".pcf-defined-fields-table tbody tr:nth-child(even) td{background:#fcfdff;}\n";
        echo ".pcf-action-buttons{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}\n";
        echo ".pcf-action-button{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;text-decoration:none;font-weight:600;line-height:1;}\n";
        echo ".pcf-action-button .dashicons{font-size:16px;width:16px;height:16px;}\n";
        echo ".pcf-action-button-edit{background:#eff6ff;border:1px solid #93c5fd;color:#1d4ed8;}\n";
        echo ".pcf-action-button-delete{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;}\n";
        echo ".pcf-action-button:hover{filter:brightness(.98);}\n";
        echo "</style>\n";
    }

    /* Admin: menu and page */
    public function admin_menu() {
        add_menu_page( 'OER Metadata', 'OER Metadata', 'manage_options', 'pedagogy-cf', array( $this, 'render_admin_page' ), 'dashicons-list-view', 60 );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Fix any duplicate order values left from earlier saves.
        $this->normalize_orders();

        if ( isset( $_GET['error'] ) && 'source_in_use' === $_GET['error'] && isset( $_GET['fields'] ) ) {
            $fields = explode( ',', sanitize_text_field( wp_unslash( $_GET['fields'] ) ) );
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Cannot delete a source field while it is used by linked fields:', 'pedagogy' ) . ' ' . esc_html( implode( ', ', $fields ) ) . '</p></div>';
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

        $edit_options_input = '';
        if ( $editing && isset( $edit_def['options'] ) ) {
            $edit_options_input = $this->options_to_admin_lines( $edit_def['options'] );
        }

        $new_order = 1;
        foreach ( $defs as $def ) {
            if ( isset( $def['order'] ) ) {
                $new_order = max( $new_order, intval( $def['order'] ) + 1 );
            }
        }
        ?>
        <div class="wrap">
            <h1>OER Metadata Fields</h1>
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
                                <option value="text" <?php selected( $editing && $edit_def['type'] === 'text' ); ?>>Short text</option>
                                <option value="textarea" <?php selected( $editing && $edit_def['type'] === 'textarea' ); ?>>Rich text</option>
                                <option value="number" <?php selected( $editing && $edit_def['type'] === 'number' ); ?>>Number</option>
                                <option value="date" <?php selected( $editing && $edit_def['type'] === 'date' ); ?>>Date Created (Full Date)</option>
                                 <option value="year" <?php selected( $editing && $edit_def['type'] === 'year' ); ?>>Date Created (year)</option>
                                <option value="select" <?php selected( $editing && $edit_def['type'] === 'select' ); ?>>Select (title + optional description per line)</option>
                                <option value="option_links" <?php selected( $editing && $edit_def['type'] === 'option_links' ); ?>>Options + Link (title + URL per option)</option>
                                <option value="url" <?php selected( $editing && $edit_def['type'] === 'url' ); ?>>Hyperlink</option>
                                <option value="linked" <?php selected( $editing && $edit_def['type'] === 'linked' ); ?>>Linked Field</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="pcf_order">Display Order</label></th>
                        <td>
                            <input id="pcf_order" name="pcf_order" type="number" min="0" value="<?php echo esc_attr( $editing ? ( isset( $edit_def['order'] ) ? $edit_def['order'] : $new_order ) : $new_order ); ?>">
                            <p class="description">Lower values appear earlier in the field output order.</p>
                        </td>
                    </tr>
                    <tr id="options_row" style="display:<?php echo ( $editing && isset( $edit_def['options'] ) ) ? 'table-row' : 'none'; ?>;">
                        <th><label>Options</label></th>
                        <td>
                            <textarea id="pcf_options" name="pcf_options" rows="8" class="large-text" placeholder="Option title | Optional description\nAnother option | Another description"><?php echo esc_textarea( $edit_options_input ); ?></textarea>
                            <p class="description" id="pcf_options_help">One option per line. Use <code>Title | Description</code>. Description is optional.</p>
                        </td>
                    </tr>
                    <tr id="url_row" style="display:<?php echo ( $editing && isset( $edit_def['type'] ) && $edit_def['type'] === 'url' ) ? 'table-row' : 'none'; ?>;">
                        <th>URL Fields</th>
                        <td>
                            <p class="description">Provide a link and an optional display name in the post editor meta box.</p>
                        </td>
                    </tr>
                    <tr id="select_multiple_row" style="display:<?php echo ( $editing && isset( $edit_def['type'] ) && $edit_def['type'] === 'select' ) ? 'table-row' : 'none'; ?>;">
                        <th>Allow multiple selection (select)</th>
                        <td><label><input type="checkbox" name="pcf_select_multiple" value="1" <?php echo $editing && ! empty( $edit_def['multiple'] ) ? 'checked' : ''; ?>> Allow multiple values</label></td>
                    </tr>
                    <tr id="allow_post_options_row" style="display:<?php echo ( $editing && isset( $edit_def['type'] ) && in_array( $edit_def['type'], array( 'select', 'linked' ), true ) ) ? 'table-row' : 'none'; ?>;">
                        <th>Allow adding options while editing posts</th>
                        <td>
                            <label><input type="checkbox" name="pcf_allow_post_options" value="1" <?php checked( $editing && ! empty( $edit_def['allow_post_options'] ) ); ?>> Editors can add new options from the post editor</label>
                            <p class="description">For linked fields, new options are added to the linked source field.</p>
                        </td>
                    </tr>
                    <tr id="option_sort_row" style="display:<?php echo ( $editing && isset( $edit_def['type'] ) && in_array( $edit_def['type'], array( 'select', 'linked' ), true ) ) ? 'table-row' : 'none'; ?>;">
                        <th><label for="pcf_option_sort">Option Sort Order</label></th>
                        <td>
                            <select id="pcf_option_sort" name="pcf_option_sort">
                                <option value="asc" <?php selected( ! $editing || empty( $edit_def['option_sort'] ) || 'asc' === $edit_def['option_sort'] ); ?>>A to Z</option>
                                <option value="desc" <?php selected( $editing && isset( $edit_def['option_sort'] ) && 'desc' === $edit_def['option_sort'] ); ?>>Z to A</option>
                            </select>
                            <p class="description">Applies to select and linked field option dropdowns.</p>
                        </td>
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
                    <tr id="linked_multiple_row" style="display:<?php echo ( $editing && isset( $edit_def['type'] ) && $edit_def['type'] === 'linked' ) ? 'table-row' : 'none'; ?>;">
                        <th>Allow multiple selection</th>
                        <td><label><input type="checkbox" name="pcf_linked_multiple" value="1" <?php checked( $editing && ! empty( $edit_def['multiple'] ) ); ?>> Allow multiple values</label></td>
                    </tr>
                </table>
                <?php submit_button( $editing ? 'Update Field' : 'Add Field' ); ?>
            </form>

            <h2>Defined Fields</h2>
            <?php if ( empty( $defs ) ) : ?>
                <p>No fields defined yet.</p>
            <?php else: ?>
                <table class="widefat pcf-defined-fields-table">
                    <thead><tr><th>Order</th><th>Title</th><th>Name</th><th>Type</th><th>Options</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ( $defs as $name => $d ) : ?>
                            <tr>
                                <td><?php echo esc_html( isset( $d['order'] ) ? intval( $d['order'] ) : '' ); ?></td>
                                <td><?php echo esc_html( $d['title'] ); ?></td>
                                <td><?php echo esc_html( $name ); ?></td>
                                <td><?php echo esc_html( $d['type'] ); ?></td>
                                <td><?php
                                    if ( isset( $d['type'] ) && $d['type'] === 'linked' ) {
                                        echo isset( $d['source_field'] ) ? esc_html( 'linked to ' . $d['source_field'] . ( ! empty( $d['multiple'] ) ? ' (multiple)' : '' ) ) : esc_html( 'no source set' );
                                    } else {
                                        if ( isset( $d['options'] ) ) {
                                            $display_opts = $this->normalize_options_list( $d['options'] );
                                            $display_parts = array();
                                            foreach ( $display_opts as $display_opt ) {
                                                $part = $display_opt['title'];
                                                if ( '' !== $display_opt['description'] ) {
                                                    $part .= ' (' . $display_opt['description'] . ')';
                                                }
                                                $display_parts[] = $part;
                                            }
                                            echo esc_html( implode( ', ', $display_parts ) );
                                        }
                                    }
                                ?></td>
                                <td>
                                    <div class="pcf-action-buttons">
                                        <a class="pcf-action-button pcf-action-button-edit" href="<?php echo esc_url( add_query_arg( array( 'page' => 'pedagogy-cf', 'edit' => $name ), admin_url( 'admin.php' ) ) ); ?>">
                                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                            <span><?php esc_html_e( 'Edit', 'library-page' ); ?></span>
                                        </a>
                                        <a class="pcf-action-button pcf-action-button-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pedagogy_cf_delete&name=' . $name ), 'pedagogy_cf_delete' ) ); ?>">
                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                            <span><?php esc_html_e( 'Delete', 'library-page' ); ?></span>
                                        </a>
                                    </div>
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
            var optionsInput = document.getElementById('pcf_options');
            var optionsHelp = document.getElementById('pcf_options_help');
            var linkedSourceRow = document.getElementById('linked_source_row');
            var linkedMultipleRow = document.getElementById('linked_multiple_row');
            var selectMultipleRow = document.getElementById('select_multiple_row');
            var allowPostOptionsRow = document.getElementById('allow_post_options_row');
            var optionSortRow = document.getElementById('option_sort_row');
            var urlRow = document.getElementById('url_row');

            var selectOptionsPlaceholder = 'Option title | Optional description\nAnother option | Another description';
            var optionLinksPlaceholder = 'Title | URL\nAnother title | https://example.com';

            function updateOptionsHelp() {
                if (!optionsHelp || !optionsInput) {
                    return;
                }

                if (type.value === 'option_links') {
                    optionsHelp.textContent = 'One option per line. Use Title | URL.';
                    optionsInput.setAttribute('placeholder', optionLinksPlaceholder);
                } else {
                    optionsHelp.textContent = 'One option per line. Use Title | Description. Description is optional.';
                    optionsInput.setAttribute('placeholder', selectOptionsPlaceholder);
                }
            }

            function update(){
                optionsRow.style.display = ( type.value === 'select' || type.value === 'option_links' ) ? '' : 'none';
                linkedSourceRow.style.display = type.value === 'linked' ? '' : 'none';
                linkedMultipleRow.style.display = type.value === 'linked' ? '' : 'none';
                if ( selectMultipleRow ) {
                    selectMultipleRow.style.display = type.value === 'select' ? '' : 'none';
                }
                if ( allowPostOptionsRow ) {
                    allowPostOptionsRow.style.display = ( type.value === 'select' || type.value === 'linked' ) ? '' : 'none';
                }
                if ( optionSortRow ) {
                    optionSortRow.style.display = ( type.value === 'select' || type.value === 'linked' ) ? '' : 'none';
                }
                if ( urlRow ) {
                    urlRow.style.display = type.value === 'url' ? '' : 'none';
                }

                updateOptionsHelp();
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
        $opts  = sanitize_textarea_field( wp_unslash( $_POST['pcf_options'] ?? '' ) );
        $order = isset( $_POST['pcf_order'] ) ? intval( wp_unslash( $_POST['pcf_order'] ) ) : 0;
        $select_multiple = isset( $_POST['pcf_select_multiple'] ) ? true : false;
        $allow_post_options = isset( $_POST['pcf_allow_post_options'] ) ? true : false;
        $option_sort = sanitize_text_field( wp_unslash( $_POST['pcf_option_sort'] ?? 'asc' ) );
        if ( ! in_array( $option_sort, array( 'asc', 'desc' ), true ) ) {
            $option_sort = 'asc';
        }
        $original = isset( $_POST['original_name'] ) ? sanitize_text_field( wp_unslash( $_POST['original_name'] ) ) : '';

        if ( ! preg_match( '/^[a-z0-9_]+$/', $name ) ) {
            wp_redirect( add_query_arg( 'error', 'invalid_name', admin_url( 'admin.php?page=pedagogy-cf' ) ) );
            exit;
        }

        $defs = $this->get_definitions();

        $entry = array( 'title' => $title, 'type' => $type );
        if ( in_array( $type, array( 'select', 'option_links' ), true ) ) {
            $entry['options'] = $this->parse_options_input( $opts );
            if ( 'select' === $type ) {
                $entry['multiple'] = $select_multiple;
                $entry['allow_post_options'] = $allow_post_options;
                $entry['option_sort'] = $option_sort;
            }
        } elseif ( 'url' === $type ) {
            // no definition-level extras required for URL fields; per-post values are stored separately
        } elseif ( 'linked' === $type ) {
            $source = sanitize_text_field( wp_unslash( $_POST['pcf_linked_source'] ?? '' ) );
            if ( $source && isset( $defs[ $source ] ) && isset( $defs[ $source ]['type'] ) && $defs[ $source ]['type'] === 'select' ) {
                $entry['source_field'] = $source;
            } else {
                $entry['source_field'] = '';
            }
            $entry['multiple'] = isset( $_POST['pcf_linked_multiple'] ) ? true : false;
            $entry['allow_post_options'] = $allow_post_options;
            $entry['option_sort'] = $option_sort;
        }

        if ( $order <= 0 ) {
            if ( $original && isset( $defs[ $original ]['order'] ) ) {
                $order = intval( $defs[ $original ]['order'] );
            } else {
                $order = $this->get_default_order( $defs );
            }
        }
        $entry['order'] = $order;

        // Resolve order collisions: if another field already occupies this order,
        // shift all fields at >= $order (excluding the one being edited) up by 1.
        $collision = false;
        foreach ( $defs as $check_name => $check_def ) {
            if ( $check_name === $original ) { continue; }
            if ( isset( $check_def['order'] ) && intval( $check_def['order'] ) === $order ) {
                $collision = true;
                break;
            }
        }
        if ( $collision ) {
            foreach ( $defs as $shift_name => $shift_def ) {
                if ( $shift_name === $original ) { continue; }
                if ( isset( $shift_def['order'] ) && intval( $shift_def['order'] ) >= $order ) {
                    $defs[ $shift_name ]['order'] = intval( $shift_def['order'] ) + 1;
                }
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
        $linked_using = array();
        foreach ( $defs as $field_name => $field_def ) {
            if ( isset( $field_def['type'] ) && $field_def['type'] === 'linked' && isset( $field_def['source_field'] ) && $field_def['source_field'] === $name ) {
                $linked_using[] = $field_name;
            }
        }
        if ( ! empty( $linked_using ) ) {
            $redirect = add_query_arg( array(
                'page' => 'pedagogy-cf',
                'error' => 'source_in_use',
                'fields' => implode( ',', $linked_using ),
            ), admin_url( 'admin.php' ) );
            wp_redirect( $redirect );
            exit;
        }
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

        foreach ( $defs as $field_name => $def ) {
            if ( isset( $def['type'] ) && in_array( $def['type'], array( 'select', 'option_links' ), true ) && isset( $def['options'] ) && is_array( $def['options'] ) ) {
                $defs[ $field_name ]['options'] = $this->normalize_options_list( $def['options'] );
            }
        }

        uasort( $defs, function( $a, $b ) {
            $a_order = isset( $a['order'] ) ? intval( $a['order'] ) : PHP_INT_MAX;
            $b_order = isset( $b['order'] ) ? intval( $b['order'] ) : PHP_INT_MAX;
            if ( $a_order === $b_order ) {
                return 0;
            }
            return $a_order < $b_order ? -1 : 1;
        } );

        return $defs;
    }

    private function normalize_options_list( $options ) {
        $normalized = array();
        if ( ! is_array( $options ) ) {
            return $normalized;
        }

        foreach ( $options as $option ) {
            if ( is_array( $option ) ) {
                $title = '';
                if ( isset( $option['title'] ) ) {
                    $title = sanitize_text_field( $option['title'] );
                } elseif ( isset( $option['label'] ) ) {
                    $title = sanitize_text_field( $option['label'] );
                } elseif ( isset( $option['value'] ) ) {
                    $title = sanitize_text_field( $option['value'] );
                }

                $description = isset( $option['description'] ) ? sanitize_text_field( $option['description'] ) : '';
                if ( '' === $title ) {
                    continue;
                }

                $normalized[] = array(
                    'title'       => $title,
                    'description' => $description,
                );
            } elseif ( is_scalar( $option ) ) {
                $title = sanitize_text_field( (string) $option );
                if ( '' === $title ) {
                    continue;
                }

                $normalized[] = array(
                    'title'       => $title,
                    'description' => '',
                );
            }
        }

        $deduped = array();
        $seen_titles = array();
        foreach ( $normalized as $option ) {
            $title_key = strtolower( trim( $option['title'] ) );
            if ( '' === $title_key || isset( $seen_titles[ $title_key ] ) ) {
                continue;
            }

            $seen_titles[ $title_key ] = true;
            $deduped[] = $option;
        }

        usort( $deduped, static function( $a, $b ) {
            return strcasecmp( $a['title'], $b['title'] );
        } );

        return $deduped;
    }

    private function options_to_admin_lines( $options ) {
        $normalized = $this->normalize_options_list( $options );
        if ( empty( $normalized ) ) {
            return '';
        }

        $lines = array();
        foreach ( $normalized as $option ) {
            $line = $option['title'];
            if ( '' !== $option['description'] ) {
                $line .= ' | ' . $option['description'];
            }
            $lines[] = $line;
        }

        return implode( "\n", $lines );
    }

    private function parse_options_input( $raw_options ) {
        $raw_options = is_string( $raw_options ) ? trim( $raw_options ) : '';
        if ( '' === $raw_options ) {
            return array();
        }

        $lines = preg_split( '/\r\n|\r|\n/', $raw_options );
        if ( ! is_array( $lines ) ) {
            $lines = array( $raw_options );
        }

        $options = array();
        foreach ( $lines as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }

            $title = $line;
            $description = '';
            if ( false !== strpos( $line, '|' ) ) {
                list( $maybe_title, $maybe_description ) = array_map( 'trim', explode( '|', $line, 2 ) );
                $title = $maybe_title;
                $description = $maybe_description;
            }

            $options[] = array(
                'title'       => sanitize_text_field( $title ),
                'description' => sanitize_text_field( $description ),
            );
        }

        return $this->normalize_options_list( $options );
    }

    private function append_missing_options_from_values( $options, $values ) {
        $normalized_options = $this->normalize_options_list( $options );
        $existing = array();
        foreach ( $normalized_options as $option ) {
            $existing[ strtolower( $option['title'] ) ] = true;
        }

        foreach ( (array) $values as $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }

            $title = sanitize_text_field( (string) $value );
            if ( '' === $title ) {
                continue;
            }

            $key = strtolower( $title );
            if ( isset( $existing[ $key ] ) ) {
                continue;
            }

            $existing[ $key ] = true;
            $normalized_options[] = array(
                'title'       => $title,
                'description' => '',
            );
        }

        return $this->normalize_options_list( $normalized_options );
    }

    private function option_token_from_title( $title ) {
        $title = sanitize_text_field( (string) $title );
        $token = sanitize_title( $title );
        if ( '' === $token ) {
            $token = substr( md5( $title ), 0, 12 );
        }
        return $token;
    }

    private function allow_master_option_sync() {
        $enabled = ! ( defined( 'PEDAGOGY_CF_DISABLE_MASTER_SYNC' ) && PEDAGOGY_CF_DISABLE_MASTER_SYNC );
        return (bool) apply_filters( 'pedagogy_cf_allow_master_option_sync', $enabled );
    }

    private function sanitize_posted_ordered_values( $raw_payload ) {
        if ( ! is_string( $raw_payload ) ) {
            return array();
        }

        $decoded = json_decode( $raw_payload, true );
        if ( ! is_array( $decoded ) ) {
            return array();
        }

        $values = array();
        foreach ( $decoded as $item ) {
            if ( ! is_scalar( $item ) ) {
                continue;
            }
            $value = sanitize_text_field( (string) $item );
            if ( '' === $value ) {
                continue;
            }
            $values[] = $value;
        }

        return array_values( array_unique( $values ) );
    }

    private function apply_selected_value_order( $selected_values, $ordered_values ) {
        if ( ! is_array( $selected_values ) || empty( $selected_values ) ) {
            return array();
        }

        $selected_values = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $selected_values ), 'strlen' ) ) );
        $ordered_values = is_array( $ordered_values ) ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $ordered_values ), 'strlen' ) ) ) : array();

        if ( empty( $ordered_values ) ) {
            return $selected_values;
        }

        $selected_lookup = array_fill_keys( $selected_values, true );
        $ordered = array();

        foreach ( $ordered_values as $value ) {
            if ( isset( $selected_lookup[ $value ] ) ) {
                $ordered[] = $value;
                unset( $selected_lookup[ $value ] );
            }
        }

        foreach ( $selected_values as $value ) {
            if ( isset( $selected_lookup[ $value ] ) ) {
                $ordered[] = $value;
            }
        }

        return $ordered;
    }

    private function render_multi_value_order_ui( $meta_key, $value ) {
        $selected_values = array();
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( ! is_scalar( $item ) ) {
                    continue;
                }
                $item = sanitize_text_field( (string) $item );
                if ( '' !== $item ) {
                    $selected_values[] = $item;
                }
            }
            $selected_values = array_values( array_unique( $selected_values ) );
        }

        $hidden_name = $meta_key . '_ordered_values';
        $hidden_id = $meta_key . '_ordered_values';
        $list_id = $meta_key . '_ordered_values_list';

        echo '<div class="pcf-selected-order" style="margin-top:10px;">';
        echo '<strong>' . esc_html__( 'Selected item order (drag to reorder)', 'pedagogy' ) . '</strong>';
        echo '<ul id="' . esc_attr( $list_id ) . '" style="margin:8px 0 6px;padding:0;list-style:none;display:grid;gap:6px;"></ul>';
        echo '<input type="hidden" id="' . esc_attr( $hidden_id ) . '" name="' . esc_attr( $hidden_name ) . '" value="' . esc_attr( wp_json_encode( $selected_values ) ) . '">';
        echo '<p class="description">' . esc_html__( 'This controls the display order for selected values on this post.', 'pedagogy' ) . '</p>';
        echo '</div>';
        ?>
        <script>
        (function(){
            var select = document.getElementsByName(<?php echo wp_json_encode( $meta_key . '[]' ); ?>)[0];
            var hidden = document.getElementById(<?php echo wp_json_encode( $hidden_id ); ?>);
            var list = document.getElementById(<?php echo wp_json_encode( $list_id ); ?>);
            if (!select || !hidden || !list) { return; }

            function getSelectedValues() {
                return Array.prototype.filter.call(select.options, function(opt){
                    return opt.selected && opt.value !== '';
                }).map(function(opt){
                    return opt.value;
                });
            }

            function getLabelByValue(value) {
                var label = value;
                Array.prototype.forEach.call(select.options, function(opt){
                    if (opt.value === value) {
                        label = opt.text;
                    }
                });
                return label;
            }

            function parseHiddenOrder() {
                try {
                    var parsed = JSON.parse(hidden.value || '[]');
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            }

            function reconcileOrder(selected, ordered) {
                var selectedSet = {};
                selected.forEach(function(v){ selectedSet[v] = true; });

                var next = [];
                ordered.forEach(function(v){
                    if (selectedSet[v]) {
                        next.push(v);
                        delete selectedSet[v];
                    }
                });

                selected.forEach(function(v){
                    if (selectedSet[v]) {
                        next.push(v);
                    }
                });

                return next;
            }

            function renderList(order) {
                list.innerHTML = '';
                order.forEach(function(value){
                    var item = document.createElement('li');
                    item.draggable = true;
                    item.dataset.value = value;
                    item.style.cursor = 'move';
                    item.style.padding = '6px 8px';
                    item.style.border = '1px solid #cbd5e1';
                    item.style.borderRadius = '6px';
                    item.style.background = '#f8fafc';
                    item.textContent = getLabelByValue(value);
                    list.appendChild(item);
                });
                hidden.value = JSON.stringify(order);
            }

            function currentOrder() {
                return Array.prototype.map.call(list.querySelectorAll('li'), function(li){
                    return li.dataset.value;
                });
            }

            var dragValue = null;
            list.addEventListener('dragstart', function(e){
                var li = e.target.closest('li');
                if (!li) { return; }
                dragValue = li.dataset.value;
                e.dataTransfer.effectAllowed = 'move';
            });

            list.addEventListener('dragover', function(e){
                e.preventDefault();
            });

            list.addEventListener('drop', function(e){
                e.preventDefault();
                var target = e.target.closest('li');
                if (!target || !dragValue) { return; }

                var order = currentOrder();
                var from = order.indexOf(dragValue);
                var to = order.indexOf(target.dataset.value);
                if (from < 0 || to < 0 || from === to) { return; }

                var moved = order.splice(from, 1)[0];
                order.splice(to, 0, moved);
                renderList(order);
            });

            function refreshFromSelect() {
                var selected = getSelectedValues();
                var ordered = parseHiddenOrder();
                renderList(reconcileOrder(selected, ordered));
            }

            select.addEventListener('change', refreshFromSelect);
            refreshFromSelect();
        })();
        </script>
        <?php
    }

    private function sort_options_for_display( $options, $sort_order = 'asc', $custom_sort_map = array() ) {
        $normalized = $this->normalize_options_list( $options );
        if ( 'desc' === $sort_order ) {
            usort( $normalized, static function( $a, $b ) {
                return strcasecmp( $b['title'], $a['title'] );
            } );
        }
        return $normalized;
    }

    private function get_default_order( $defs ) {
        $order = 1;
        foreach ( $defs as $def ) {
            if ( isset( $def['order'] ) ) {
                $order = max( $order, intval( $def['order'] ) + 1 );
            }
        }
        return $order;
    }

    /**
     * Repair any duplicate order values in the stored definitions.
     * Sorts all fields by their current order value, then reassigns
     * sequential integers (1, 2, 3…) so no two fields share an order.
     */
    public function normalize_orders() {
        $defs = $this->get_definitions();
        if ( empty( $defs ) ) { return; }

        $indexed   = array();
        $unordered = array();
        foreach ( $defs as $name => $def ) {
            if ( isset( $def['order'] ) ) {
                $indexed[ $name ] = intval( $def['order'] );
            } else {
                $unordered[] = $name;
            }
        }
        asort( $indexed );

        // Only touch the DB if duplicates actually exist.
        $values = array_values( $indexed );
        if ( count( $values ) === count( array_unique( $values ) ) && empty( $unordered ) ) {
            return;
        }

        $counter = 1;
        foreach ( $indexed as $name => $old_order ) {
            $defs[ $name ]['order'] = $counter++;
        }
        foreach ( $unordered as $name ) {
            $defs[ $name ]['order'] = $counter++;
        }
        update_option( self::OPTION_KEY, $defs );
    }

    private function get_acf_field_names() {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return array();
        }

        $names = array();
        $groups = acf_get_field_groups();
        if ( empty( $groups ) ) {
            return $names;
        }

        foreach ( $groups as $group ) {
            $fields = acf_get_fields( $group );
            if ( empty( $fields ) ) {
                continue;
            }
            foreach ( $fields as $field ) {
                if ( isset( $field['name'] ) ) {
                    $names[] = $field['name'];
                }
            }
        }

        return array_unique( $names );
    }

    /* Add meta boxes per definition */
    public function add_meta_boxes() {
        $defs = $this->get_definitions();
        if ( empty( $defs ) ) {
            return;
        }
        foreach ( array( 'post', 'page' ) as $pt ) {
            foreach ( $defs as $name => $d ) {
                if ( 'post' === $pt && 'people' === $name ) {
                    continue;
                }
                add_meta_box( 'pcf_' . $name, $d['title'], function( $post, $box ) use ( $name, $d ) {
                    $this->render_field_box( $post, $name, $d );
                }, $pt, 'normal', 'default' );
            }
        }
    }

    private function get_ordered_pcf_box_ids( $post_type ) {
        $defs = $this->get_definitions();
        if ( empty( $defs ) ) {
            return array();
        }

        $ids = array();
        foreach ( $defs as $name => $d ) {
            if ( 'post' === $post_type && 'people' === $name ) {
                continue;
            }
            $ids[] = 'pcf_' . $name;
        }

        return $ids;
    }

    private function parse_meta_box_order_ids( $ids ) {
        if ( ! is_string( $ids ) || '' === $ids ) {
            return array();
        }

        $parts = array_map( 'trim', explode( ',', $ids ) );
        return array_values( array_filter( $parts, static function( $id ) {
            return '' !== $id;
        } ) );
    }

    private function enforce_pcf_meta_box_order( $sorted, $post_type ) {
        $pcf_ids = $this->get_ordered_pcf_box_ids( $post_type );
        if ( empty( $pcf_ids ) ) {
            return $sorted;
        }

        $normalized = is_array( $sorted ) ? $sorted : array();
        $contexts = array( 'normal', 'advanced', 'side' );
        foreach ( $contexts as $ctx ) {
            if ( ! isset( $normalized[ $ctx ] ) ) {
                $normalized[ $ctx ] = '';
            }
        }

        $remaining = array();
        foreach ( $normalized as $ctx => $ids ) {
            $current_ids = $this->parse_meta_box_order_ids( $ids );
            $remaining[ $ctx ] = array_values( array_filter( $current_ids, static function( $id ) use ( $pcf_ids ) {
                return ! in_array( $id, $pcf_ids, true );
            } ) );
        }

        $remaining_normal = isset( $remaining['normal'] ) ? $remaining['normal'] : array();
        $remaining['normal'] = array_values( array_unique( array_merge( $remaining_normal, $pcf_ids ) ) );

        $out = array();
        foreach ( $remaining as $ctx => $ids ) {
            $out[ $ctx ] = implode( ',', $ids );
        }

        return $out;
    }

    public function filter_meta_box_order_post( $result, $option, $user ) {
        return $this->enforce_pcf_meta_box_order( $result, 'post' );
    }

    public function filter_meta_box_order_page( $result, $option, $user ) {
        return $this->enforce_pcf_meta_box_order( $result, 'page' );
    }

    private function render_field_box( $post, $name, $def ) {
        $meta_key = 'pcf_' . $name;
        wp_nonce_field( 'pedagogy_cf_save', 'pedagogy_cf_nonce' );
        $value = get_post_meta( $post->ID, $meta_key, true );
        switch ( $def['type'] ) {
            case 'textarea':
                if ( function_exists( 'wp_editor' ) ) {
                    // Use an anonymized editor ID so the custom field name isn't exposed in the DOM or UI.
                    $editor_id = 'pcf_editor_' . substr( md5( $meta_key . microtime() ), 0, 8 );
                    $editor_settings = array(
                        'textarea_name' => $meta_key, // keeps saving bound to the correct meta key
                        'textarea_rows' => 6,
                        'media_buttons' => false,
                        'teeny' => false,
                        'tinymce' => array(
                            'menubar' => false,
                            'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,blockquote',
                            'toolbar2' => '',
                        ),
                        'quicktags' => false,
                    );
                    // Remove any previously injected content in the editor value that might include names
                    $initial_value = is_string( $value ) ? $value : '';
                    wp_editor( wp_kses_post( $initial_value ), $editor_id, $editor_settings );
                } else {
                    echo '<textarea style="width:100%;" name="' . esc_attr( $meta_key ) . '">' . esc_textarea( $value ) . '</textarea>';
                }
                break;
            case 'number':
                echo '<input type="number" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
                break;
            case 'date':
                echo '<input type="date" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
                break;
            case 'select':
                $is_multiple = ! empty( $def['multiple'] );
                $sort_order = ( isset( $def['option_sort'] ) && in_array( $def['option_sort'], array( 'asc', 'desc' ), true ) ) ? $def['option_sort'] : 'asc';
                $allow_post_options = ! empty( $def['allow_post_options'] );
                $select_name = esc_attr( $meta_key . ( $is_multiple ? '[]' : '' ) );
                $new_option_name = esc_attr( $meta_key . '_new_option' );
                $new_option_id = esc_attr( $meta_key . '_new_option' );
                echo '<select name="' . $select_name . '" class="widefat"' . ( $is_multiple ? ' multiple size="5"' : '' ) . '>';
                $opts = isset( $def['options'] ) ? $this->sort_options_for_display( $def['options'], $sort_order ) : array();
                if ( ! $is_multiple ) {
                    echo '<option value="">' . esc_html__( '-- Select option --', 'pedagogy' ) . '</option>';
                }
                foreach ( $opts as $option ) {
                    $option_value = $option['title'];
                    $option_label = $option['title'];
                    if ( '' !== $option['description'] ) {
                        $option_label .= ' - ' . $option['description'];
                    }

                    $selected = false;
                    if ( $is_multiple ) {
                        $selected = is_array( $value ) && in_array( $option_value, $value, true );
                    } else {
                        $selected = $value === $option_value;
                    }
                    $sel = selected( $selected, true, false );
                    echo '<option value="' . esc_attr( $option_value ) . '" ' . $sel . '>' . esc_html( $option_label ) . '</option>';
                }
                echo '</select>';
                if ( $is_multiple ) {
                    $this->render_multi_value_order_ui( $meta_key, $value );
                }
                if ( $allow_post_options ) {
                    echo '<div class="pcf-post-option-add" style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
                    echo '<input type="text" id="' . $new_option_id . '" name="' . $new_option_name . '" value="" placeholder="' . esc_attr__( 'Add a new option', 'pedagogy' ) . '" class="regular-text">';
                    echo '<button type="button" class="button" id="' . esc_attr( $meta_key . '_add_option_btn' ) . '">' . esc_html__( 'Add option', 'pedagogy' ) . '</button>';
                    echo '</div>';
                    echo '<p class="description" id="' . esc_attr( $meta_key . '_add_option_status' ) . '" style="margin-top:6px;display:none;"></p>';
                    echo '<p class="description">' . esc_html__( 'Adds to this field option list for future posts.', 'pedagogy' ) . '</p>';
                    ?>
                    <script>
                    (function(){
                        var input = document.getElementById(<?php echo wp_json_encode( $meta_key . '_new_option' ); ?>);
                        var button = document.getElementById(<?php echo wp_json_encode( $meta_key . '_add_option_btn' ); ?>);
                        var select = document.getElementsByName(<?php echo wp_json_encode( $meta_key . ( $is_multiple ? '[]' : '' ) ); ?>)[0];
                        var status = document.getElementById(<?php echo wp_json_encode( $meta_key . '_add_option_status' ); ?>);
                        if (!input || !button || !select) { return; }

                        function setStatus(message) {
                            if (!status) { return; }
                            status.textContent = message;
                            status.style.display = 'block';
                        }

                        function addOptionFromInput() {
                            var val = input.value.trim();
                            if (!val) { return; }
                            var exists = false;
                            var selectedValues = Array.prototype.filter.call(select.options, function(opt){
                                return opt.selected && opt.value !== '';
                            }).map(function(opt){
                                return opt.value;
                            });
                            Array.prototype.forEach.call(select.options, function(opt){
                                if (opt.value.toLowerCase() === val.toLowerCase()) {
                                    exists = true;
                                    opt.selected = true;
                                }
                            });
                            if (!exists) {
                                var option = new Option(val, val, true, true);
                                select.add(option);
                                selectedValues.push(val);
                            }
                            var options = Array.prototype.slice.call(select.options);
                            var placeholder = null;
                            if (options.length && options[0].value === '') {
                                placeholder = options.shift();
                            }
                            options.sort(function(a, b){
                                var compare = a.text.toLowerCase().localeCompare(b.text.toLowerCase());
                                return <?php echo wp_json_encode( 'desc' === $sort_order ); ?> ? -compare : compare;
                            });
                            select.innerHTML = '';
                            if (placeholder) {
                                select.add(placeholder);
                            }
                            options.forEach(function(opt){
                                opt.selected = selectedValues.indexOf(opt.value) !== -1;
                                select.add(opt);
                            });
                            if (!<?php echo wp_json_encode( $is_multiple ); ?>) {
                                select.value = val;
                            }

                            select.dispatchEvent(new Event('change', { bubbles: true }));

                            setStatus(exists ? 'Already in list. Click Publish to save.' : 'Added. Click Publish to save.');
                            input.value = '';
                            input.focus();
                        }

                        button.addEventListener('click', function(){
                            addOptionFromInput();
                        });

                        input.addEventListener('keydown', function(e){
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                addOptionFromInput();
                            }
                        });
                    })();
                    </script>
                    <?php
                }
                break;
            case 'option_links':
                $opts = isset( $def['options'] ) ? $this->normalize_options_list( $def['options'] ) : array();
                if ( empty( $opts ) ) {
                    echo '<p>' . esc_html__( 'No options found. Edit this field definition and add options.', 'pedagogy' ) . '</p>';
                    break;
                }

                $selected_title = '';
                if ( is_array( $value ) ) {
                    if ( isset( $value['title'] ) ) {
                        $selected_title = sanitize_text_field( (string) $value['title'] );
                    } else {
                        foreach ( $value as $row ) {
                            if ( is_array( $row ) && ! empty( $row['title'] ) ) {
                                $selected_title = sanitize_text_field( (string) $row['title'] );
                                break;
                            }
                        }
                    }
                } elseif ( is_scalar( $value ) ) {
                    $selected_title = sanitize_text_field( (string) $value );
                }

                echo '<select name="' . esc_attr( $meta_key ) . '" class="widefat">';
                echo '<option value="">' . esc_html__( '-- Select option --', 'pedagogy' ) . '</option>';
                foreach ( $opts as $option ) {
                    $option_title = $option['title'];
                    $sel = selected( $selected_title === $option_title, true, false );
                    echo '<option value="' . esc_attr( $option_title ) . '" ' . $sel . '>' . esc_html( $option_title ) . '</option>';
                }
                echo '</select>';
                echo '<p class="description">' . esc_html__( 'Select one item. Its predefined URL will be used automatically.', 'pedagogy' ) . '</p>';
                break;
            case 'url':
                $href_key = esc_attr( $meta_key . '_href' );
                $label_key = esc_attr( $meta_key . '_label' );
                $href_val = '';
                $label_val = '';
                if ( is_array( $value ) ) {
                    $href_val = isset( $value['href'] ) ? $value['href'] : '';
                    $label_val = isset( $value['label'] ) ? $value['label'] : '';
                }
                echo '<p><label>' . esc_html__( 'Link URL', 'pedagogy' ) . ': <input type="url" name="' . $href_key . '" value="' . esc_attr( $href_val ) . '" class="widefat"></label></p>';
                echo '<p><label>' . esc_html__( 'Display name', 'pedagogy' ) . ': <input type="text" name="' . $label_key . '" value="' . esc_attr( $label_val ) . '" class="widefat"></label></p>';
                break;
            case 'linked':
                $source = isset( $def['source_field'] ) ? $def['source_field'] : '';
                $sort_order = ( isset( $def['option_sort'] ) && in_array( $def['option_sort'], array( 'asc', 'desc' ), true ) ) ? $def['option_sort'] : 'asc';
                $allow_post_options = ! empty( $def['allow_post_options'] );
                $opts = array();
                if ( $source ) {
                    $source_defs = $this->get_definitions();
                    $source_def = isset( $source_defs[ $source ] ) ? $source_defs[ $source ] : null;
                    if ( $source_def && isset( $source_def['options'] ) ) {
                        $opts = $this->sort_options_for_display( $source_def['options'], $sort_order );
                    }
                }
                if ( empty( $opts ) ) {
                    echo '<p>' . esc_html__( 'No source options found. Please select a valid source field or edit the source field options.', 'pedagogy' ) . '</p>';
                    break;
                }
                $is_multiple = ! empty( $def['multiple'] );
                $select_name = esc_attr( $meta_key . ( $is_multiple ? '[]' : '' ) );
                $new_option_name = esc_attr( $meta_key . '_new_option' );
                $new_option_id = esc_attr( $meta_key . '_new_option' );
                echo '<select name="' . $select_name . '" class="widefat"' . ( $is_multiple ? ' multiple size="5"' : '' ) . '>';
                if ( ! $is_multiple ) {
                    echo '<option value="">' . esc_html__( '-- Select option --', 'pedagogy' ) . '</option>';
                }
                foreach ( $opts as $option ) {
                    $option_value = $option['title'];
                    $option_label = $option['title'];
                    if ( '' !== $option['description'] ) {
                        $option_label .= ' - ' . $option['description'];
                    }

                    $selected = false;
                    if ( $is_multiple ) {
                        $selected = is_array( $value ) && in_array( $option_value, $value, true );
                    } else {
                        $selected = $value === $option_value;
                    }
                    $sel = selected( $selected, true, false );
                    echo '<option value="' . esc_attr( $option_value ) . '" ' . $sel . '>' . esc_html( $option_label ) . '</option>';
                }
                echo '</select>';
                if ( $is_multiple ) {
                    $this->render_multi_value_order_ui( $meta_key, $value );
                }
                if ( $allow_post_options ) {
                    echo '<div class="pcf-post-option-add" style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
                    echo '<input type="text" id="' . $new_option_id . '" name="' . $new_option_name . '" value="" placeholder="' . esc_attr__( 'Add a new option', 'pedagogy' ) . '" class="regular-text">';
                    echo '<button type="button" class="button" id="' . esc_attr( $meta_key . '_add_option_btn' ) . '">' . esc_html__( 'Add option', 'pedagogy' ) . '</button>';
                    echo '</div>';
                    echo '<p class="description" id="' . esc_attr( $meta_key . '_add_option_status' ) . '" style="margin-top:6px;display:none;"></p>';
                    echo '<p class="description">' . esc_html__( 'Adds to the linked source field option list for future posts.', 'pedagogy' ) . '</p>';
                    ?>
                    <script>
                    (function(){
                        var input = document.getElementById(<?php echo wp_json_encode( $meta_key . '_new_option' ); ?>);
                        var button = document.getElementById(<?php echo wp_json_encode( $meta_key . '_add_option_btn' ); ?>);
                        var select = document.getElementsByName(<?php echo wp_json_encode( $meta_key . ( $is_multiple ? '[]' : '' ) ); ?>)[0];
                        var status = document.getElementById(<?php echo wp_json_encode( $meta_key . '_add_option_status' ); ?>);
                        if (!input || !button || !select) { return; }

                        function setStatus(message) {
                            if (!status) { return; }
                            status.textContent = message;
                            status.style.display = 'block';
                        }

                        function addOptionFromInput() {
                            var val = input.value.trim();
                            if (!val) { return; }
                            var exists = false;
                            var selectedValues = Array.prototype.filter.call(select.options, function(opt){
                                return opt.selected && opt.value !== '';
                            }).map(function(opt){
                                return opt.value;
                            });
                            Array.prototype.forEach.call(select.options, function(opt){
                                if (opt.value.toLowerCase() === val.toLowerCase()) {
                                    exists = true;
                                    opt.selected = true;
                                }
                            });
                            if (!exists) {
                                var option = new Option(val, val, true, true);
                                select.add(option);
                                selectedValues.push(val);
                            }
                            var options = Array.prototype.slice.call(select.options);
                            var placeholder = null;
                            if (options.length && options[0].value === '') {
                                placeholder = options.shift();
                            }
                            options.sort(function(a, b){
                                var compare = a.text.toLowerCase().localeCompare(b.text.toLowerCase());
                                return <?php echo wp_json_encode( 'desc' === $sort_order ); ?> ? -compare : compare;
                            });
                            select.innerHTML = '';
                            if (placeholder) {
                                select.add(placeholder);
                            }
                            options.forEach(function(opt){
                                opt.selected = selectedValues.indexOf(opt.value) !== -1;
                                select.add(opt);
                            });
                            if (!<?php echo wp_json_encode( $is_multiple ); ?>) {
                                select.value = val;
                            }

                            select.dispatchEvent(new Event('change', { bubbles: true }));

                            setStatus(exists ? 'Already in list. Click Publish to save.' : 'Added. Click Publish to save.');
                            input.value = '';
                            input.focus();
                        }

                        button.addEventListener('click', function(){
                            addOptionFromInput();
                        });

                        input.addEventListener('keydown', function(e){
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                addOptionFromInput();
                            }
                        });
                    })();
                    </script>
                    <?php
                }
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

        $defs_updated = false;
        $allow_master_sync = $this->allow_master_option_sync();

        foreach ( $defs as $name => $d ) {
            if ( 'post' === get_post_type( $post_id ) && 'people' === $name ) {
                continue;
            }

            $meta_key = 'pcf_' . $name;
            $posted_ordered_values = array();

            $ordered_values_key = $meta_key . '_ordered_values';
            if ( isset( $_POST[ $ordered_values_key ] ) ) {
                $posted_ordered_values = $this->sanitize_posted_ordered_values( wp_unslash( $_POST[ $ordered_values_key ] ) );
            }

            if ( isset( $d['type'] ) && 'option_links' === $d['type'] ) {
                $selected_title = '';
                if ( isset( $_POST[ $meta_key ] ) ) {
                    $selected_title = sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) );
                }

                if ( '' === $selected_title ) {
                    // Backward compatibility with previously rendered row inputs.
                    $raw_hrefs = isset( $_POST[ $meta_key . '_href' ] ) && is_array( $_POST[ $meta_key . '_href' ] ) ? wp_unslash( $_POST[ $meta_key . '_href' ] ) : array();
                    $raw_titles = isset( $_POST[ $meta_key . '_title' ] ) && is_array( $_POST[ $meta_key . '_title' ] ) ? wp_unslash( $_POST[ $meta_key . '_title' ] ) : array();

                    $rows = array();
                    $tokens = array_unique( array_merge( array_keys( $raw_hrefs ), array_keys( $raw_titles ) ) );
                    foreach ( $tokens as $token ) {
                        $token = sanitize_text_field( (string) $token );
                        if ( '' === $token ) {
                            continue;
                        }

                        $title = isset( $raw_titles[ $token ] ) ? sanitize_text_field( (string) $raw_titles[ $token ] ) : '';
                        $href = isset( $raw_hrefs[ $token ] ) ? esc_url_raw( (string) $raw_hrefs[ $token ] ) : '';
                        if ( '' === $href ) {
                            continue;
                        }

                        $rows[ $token ] = array(
                            'title' => $title,
                            'href'  => $href,
                        );
                    }

                    if ( empty( $rows ) ) {
                        delete_post_meta( $post_id, $meta_key );
                    } else {
                        update_post_meta( $post_id, $meta_key, $rows );
                    }

                    continue;
                }

                $matched_title = '';
                $matched_href = '';
                $defined_options = isset( $d['options'] ) ? $this->normalize_options_list( $d['options'] ) : array();
                foreach ( $defined_options as $option ) {
                    if ( ! isset( $option['title'] ) ) {
                        continue;
                    }
                    if ( 0 !== strcasecmp( $option['title'], $selected_title ) ) {
                        continue;
                    }

                    $matched_title = sanitize_text_field( (string) $option['title'] );
                    $matched_href = isset( $option['description'] ) ? esc_url_raw( (string) $option['description'] ) : '';
                    break;
                }

                if ( '' === $matched_title || '' === $matched_href ) {
                    delete_post_meta( $post_id, $meta_key );
                } else {
                    update_post_meta( $post_id, $meta_key, array(
                        'title' => $matched_title,
                        'href'  => $matched_href,
                    ) );
                }

                continue;
            }

            // Special handling for URL fields (separate href + label inputs)
            if ( isset( $d['type'] ) && $d['type'] === 'url' ) {
                $href_key = $meta_key . '_href';
                $label_key = $meta_key . '_label';
                if ( ! isset( $_POST[ $href_key ] ) && ! isset( $_POST[ $label_key ] ) ) {
                    continue;
                }
                $raw_href = wp_unslash( $_POST[ $href_key ] ?? '' );
                $raw_label = wp_unslash( $_POST[ $label_key ] ?? '' );
                $val = array(
                    'href' => esc_url_raw( $raw_href ),
                    'label' => sanitize_text_field( $raw_label ),
                );
                update_post_meta( $post_id, $meta_key, $val );
                continue;
            }

            if ( ! isset( $_POST[ $meta_key ] ) ) {
                $raw = null;
            } else {
                $raw = wp_unslash( $_POST[ $meta_key ] );
            }

            $new_option_key = $meta_key . '_new_option';
            $new_option = isset( $_POST[ $new_option_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $new_option_key ] ) ) : '';
            // Always sync newly entered option text when provided for select/linked fields.
            // This keeps master definitions up to date even if per-field toggle state drifts.
            if ( $allow_master_sync && $new_option !== '' && in_array( $d['type'], array( 'select', 'linked' ), true ) ) {
                if ( 'linked' === $d['type'] && ! empty( $d['source_field'] ) && isset( $defs[ $d['source_field'] ] ) ) {
                    $source_options = $this->normalize_options_list( $defs[ $d['source_field'] ]['options'] ?? array() );
                    $existing = array_map( static function( $option ) {
                        return strtolower( $option['title'] );
                    }, $source_options );

                    if ( ! in_array( strtolower( $new_option ), $existing, true ) ) {
                        $source_options[] = array(
                            'title'       => $new_option,
                            'description' => '',
                        );
                        $defs[ $d['source_field'] ]['options'] = $this->normalize_options_list( $source_options );
                        $defs_updated = true;
                    }
                } elseif ( 'select' === $d['type'] ) {
                    $field_options = $this->normalize_options_list( $defs[ $name ]['options'] ?? array() );
                    $existing = array_map( static function( $option ) {
                        return strtolower( $option['title'] );
                    }, $field_options );

                    if ( ! in_array( strtolower( $new_option ), $existing, true ) ) {
                        $field_options[] = array(
                            'title'       => $new_option,
                            'description' => '',
                        );
                        $defs[ $name ]['options'] = $this->normalize_options_list( $field_options );
                        $defs_updated = true;
                    }
                }
            }

            if ( null === $raw ) {
                continue;
            }

            switch ( $d['type'] ) {
                case 'number':
                    $val = floatval( $raw );
                    break;
                case 'date':
                    $val = sanitize_text_field( $raw );
                    break;
                case 'textarea':
                    $val = wp_kses_post( $raw );
                    break;
                case 'linked':
                    if ( is_array( $raw ) ) {
                        $val = array_values( array_filter( array_map( 'sanitize_text_field', $raw ), 'strlen' ) );
                        if ( ! empty( $posted_ordered_values ) ) {
                            $val = $this->apply_selected_value_order( $val, $posted_ordered_values );
                        }
                    } else {
                        $val = sanitize_text_field( $raw );
                    }
                    break;
                case 'select':
                    if ( is_array( $raw ) ) {
                        $val = array_values( array_filter( array_map( 'sanitize_text_field', $raw ), 'strlen' ) );
                        if ( ! empty( $posted_ordered_values ) ) {
                            $val = $this->apply_selected_value_order( $val, $posted_ordered_values );
                        }
                    } else {
                        $val = sanitize_text_field( $raw );
                    }
                    break;
                case 'text':
                default:
                    $val = sanitize_text_field( $raw );
                    break;
            }

            // Keep master option lists in sync with what editors selected in post-level fields.
            if ( $allow_master_sync && isset( $d['type'] ) && in_array( $d['type'], array( 'select', 'linked' ), true ) ) {
                $selected_values = array();
                if ( is_array( $val ) ) {
                    $selected_values = array_values( array_filter( array_map( 'sanitize_text_field', $val ), 'strlen' ) );
                } elseif ( is_string( $val ) && '' !== trim( $val ) ) {
                    $selected_values = array( sanitize_text_field( $val ) );
                }

                if ( ! empty( $selected_values ) ) {
                    if ( 'linked' === $d['type'] && ! empty( $d['source_field'] ) && isset( $defs[ $d['source_field'] ] ) ) {
                        $before = isset( $defs[ $d['source_field'] ]['options'] ) ? $this->normalize_options_list( $defs[ $d['source_field'] ]['options'] ) : array();
                        $after = $this->append_missing_options_from_values( $before, $selected_values );
                        if ( wp_json_encode( $before ) !== wp_json_encode( $after ) ) {
                            $defs[ $d['source_field'] ]['options'] = $after;
                            $defs_updated = true;
                        }
                    } elseif ( 'select' === $d['type'] ) {
                        $before = isset( $defs[ $name ]['options'] ) ? $this->normalize_options_list( $defs[ $name ]['options'] ) : array();
                        $after = $this->append_missing_options_from_values( $before, $selected_values );
                        if ( wp_json_encode( $before ) !== wp_json_encode( $after ) ) {
                            $defs[ $name ]['options'] = $after;
                            $defs_updated = true;
                        }
                    }
                }
            }

            if ( is_array( $val ) && empty( $val ) ) {
                delete_post_meta( $post_id, $meta_key );
            } elseif ( ! is_array( $val ) && trim( (string) $val ) === '' ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, $val );
            }
        }

        if ( $defs_updated ) {
            update_option( self::OPTION_KEY, $defs );
            $this->set_master_options_notice_flag();
        }
    }

    private function set_master_options_notice_flag() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        set_transient( self::NOTICE_TRANSIENT_PREFIX . $user_id, 1, 5 * MINUTE_IN_SECONDS );
    }

    public function maybe_render_master_options_notice() {
        if ( ! is_admin() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $transient_key = self::NOTICE_TRANSIENT_PREFIX . $user_id;
        $show_notice = get_transient( $transient_key );
        if ( ! $show_notice ) {
            return;
        }

        delete_transient( $transient_key );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'New options were added to master list.', 'pedagogy' ) . '</p></div>';
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
        if ( isset( $def['type'] ) && $def['type'] === 'url' ) {
            if ( is_array( $val ) ) {
                $href = isset( $val['href'] ) ? esc_url( $val['href'] ) : '';
                $label = '';
                if ( isset( $val['label'] ) && $val['label'] !== '' ) {
                    $label = esc_html( $val['label'] );
                } elseif ( isset( $val['title'] ) && $val['title'] !== '' ) {
                    $label = esc_html( $val['title'] );
                } elseif ( isset( $val['text'] ) && $val['text'] !== '' ) {
                    $label = esc_html( $val['text'] );
                } else {
                    $label = esc_html( $href );
                }
            } else {
                $href = esc_url( $val );
                $label = esc_html( $val );
            }
            if ( $href !== '' ) {
                echo '<div class="pcf-' . esc_attr( $name ) . '"><a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . wp_kses_post( $label ) . '</a></div>';
            }
            return;
        }

        if ( isset( $def['type'] ) && $def['type'] === 'option_links' ) {
            if ( ! is_array( $val ) || empty( $val ) ) {
                return;
            }

            if ( isset( $val['href'] ) ) {
                $href = esc_url( $val['href'] );
                if ( '' === $href ) {
                    return;
                }
                $title = isset( $val['title'] ) ? sanitize_text_field( $val['title'] ) : '';
                $display = '' !== $title ? $title : $href;
                echo '<div class="pcf-' . esc_attr( $name ) . '"><a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $display ) . '</a></div>';
                return;
            }

            $items = array();
            foreach ( $val as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $href = isset( $row['href'] ) ? esc_url( $row['href'] ) : '';
                if ( '' === $href ) {
                    continue;
                }
                $title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
                $display = $title;
                if ( '' === $display ) {
                    $display = $href;
                }
                $items[] = '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $display ) . '</a>';
            }

            if ( empty( $items ) ) {
                return;
            }

            echo '<div class="pcf-' . esc_attr( $name ) . '"><ul><li>' . implode( '</li><li>', $items ) . '</li></ul></div>';
            return;
        }

        if ( isset( $def['type'] ) && ( $def['type'] === 'linked' || $def['type'] === 'select' ) ) {
            $is_multiple = ! empty( $def['multiple'] );
            if ( $is_multiple ) {
                $values = (array) $val;
                $label = implode( ', ', array_map( 'esc_html', $values ) );
            } else {
                $label = esc_html( $val );
            }
            echo '<div class="pcf-' . esc_attr( $name ) . '">' . wp_kses_post( wpautop( $label ) ) . '</div>';
            return;
        }
        if ( isset( $def['type'] ) && $def['type'] === 'textarea' ) {
            echo '<div class="pcf-' . esc_attr( $name ) . '">' . wp_kses_post( $val ) . '</div>';
            return;
        }
        echo '<div class="pcf-' . esc_attr( $name ) . '">' . wp_kses_post( wpautop( $val ) ) . '</div>';
    }

    /**
     * Auto-inject defined fields into post/page content if values exist.
     */
    public function inject_fields_into_content( $content ) {
        if ( apply_filters( 'pedagogy_cf_disable_content_injection', false ) ) {
            return $content;
        }

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
        $skip_fields = array();
        foreach ( $defs as $def_name => $def ) {
            if ( isset( $def['type'] ) && $def['type'] === 'linked' && ! empty( $def['source_field'] ) ) {
                $skip_fields[] = $def['source_field'];
            }
        }

        $skip_fields = array_unique( $skip_fields );
        $acf_fields = $this->get_acf_field_names();
        if ( ! empty( $acf_fields ) ) {
            $skip_fields = array_unique( array_merge( $skip_fields, $acf_fields ) );
        }

        // Do not auto-inject the `people` custom field into the content.
        $skip_fields[] = 'people';
        $skip_fields = array_unique( $skip_fields );

        foreach ( $defs as $name => $d ) {
            // Skip any definition that is either a linked source, matches an ACF field name, or is excluded explicitly.
            if ( in_array( $name, $skip_fields, true ) ) {
                continue;
            }
            $meta_key = 'pcf_' . $name;
            $val = get_post_meta( $post_id, $meta_key, true );
            if ( ! $this->has_meaningful_meta_value( $val, $d ) ) {
                continue;
            }

            $field_html = '';
            $label = isset( $d['title'] ) ? esc_html( $d['title'] ) : esc_html( $name );
            $field_html .= '<div class="pcf-field pcf-' . esc_attr( $name ) . '">';
            $field_html .= '<h3 class="pcf-label">' . $label . '</h3>';
            switch ( $d['type'] ) {
                case 'textarea':
                    $field_html .= '<div class="pcf-value">' . wp_kses_post( $val ) . '</div>';
                    break;
                case 'linked':
                    if ( is_array( $val ) ) {
                        $field_html .= '<div class="pcf-value">' . esc_html( implode( ', ', $val ) ) . '</div>';
                    } else {
                        $field_html .= '<div class="pcf-value">' . esc_html( $val ) . '</div>';
                    }
                    break;
                case 'url':
                    if ( is_array( $val ) ) {
                        $href = isset( $val['href'] ) ? esc_url( $val['href'] ) : '';
                        if ( isset( $val['label'] ) && $val['label'] !== '' ) {
                            $label_text = esc_html( $val['label'] );
                        } elseif ( isset( $val['title'] ) && $val['title'] !== '' ) {
                            $label_text = esc_html( $val['title'] );
                        } elseif ( isset( $val['text'] ) && $val['text'] !== '' ) {
                            $label_text = esc_html( $val['text'] );
                        } else {
                            $label_text = esc_html( $href );
                        }
                    } else {
                        $href = esc_url( $val );
                        $label_text = esc_html( $val );
                    }
                    if ( $href !== '' ) {
                        $field_html .= '<div class="pcf-value"><a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . $label_text . '</a></div>';
                    }
                    break;
                case 'option_links':
                    if ( is_array( $val ) ) {
                        if ( isset( $val['href'] ) ) {
                            $href = isset( $val['href'] ) ? esc_url( $val['href'] ) : '';
                            $title = isset( $val['title'] ) ? sanitize_text_field( $val['title'] ) : '';
                            $display_text = '' !== $title ? $title : $href;
                            if ( '' !== $href && '' !== $display_text ) {
                                $field_html .= '<div class="pcf-value"><a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $display_text ) . '</a></div>';
                            }
                            break;
                        }

                        $linked_items = array();
                        foreach ( $val as $row ) {
                            if ( ! is_array( $row ) ) {
                                continue;
                            }
                            $href = isset( $row['href'] ) ? esc_url( $row['href'] ) : '';
                            if ( '' === $href ) {
                                continue;
                            }
                            $title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
                            $display_text = '' !== $title ? $title : $href;
                            if ( '' === $display_text ) {
                                continue;
                            }
                            $linked_items[] = '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $display_text ) . '</a>';
                        }

                        if ( ! empty( $linked_items ) ) {
                            $field_html .= '<div class="pcf-value"><ul><li>' . implode( '</li><li>', $linked_items ) . '</li></ul></div>';
                        }
                    }
                    break;
                    case 'select':
                        if ( is_array( $val ) ) {
                            $field_html .= '<div class="pcf-value">' . esc_html( implode( ', ', $val ) ) . '</div>';
                        } else {
                            $field_html .= '<div class="pcf-value">' . esc_html( $val ) . '</div>';
                        }
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

    private function has_meaningful_meta_value( $val, $def = array() ) {
        if ( $val === null ) {
            return false;
        }

        if ( is_array( $val ) ) {
            // URL field values are arrays with href/label keys.
            if ( isset( $def['type'] ) && 'url' === $def['type'] ) {
                $href = isset( $val['href'] ) ? trim( (string) $val['href'] ) : '';
                return '' !== $href;
            }

            foreach ( $val as $item ) {
                if ( is_array( $item ) || is_object( $item ) ) {
                    return true;
                }
                if ( trim( (string) $item ) !== '' ) {
                    return true;
                }
            }
            return false;
        }

        return trim( (string) $val ) !== '';
    }

}

new Pedagogy_CF_Starter();
