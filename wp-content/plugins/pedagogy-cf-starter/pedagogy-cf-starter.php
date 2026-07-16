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
        add_action( 'admin_head', array( $this, 'admin_inline_css' ) );
        add_filter( 'theme_templates', array( $this, 'register_page_template' ), 10, 4 );
        add_filter( 'theme_page_templates', array( $this, 'register_page_template' ), 10, 4 );
        add_filter( 'template_include', array( $this, 'load_page_template' ) );
    }

    public function register_page_template( $templates, $theme, $post, $post_type ) {
        if ( 'page' !== $post_type ) {
            return $templates;
        }

        $templates['page-post-cards.php'] = __( 'Post Cards Search', 'pedagogy-cf-starter' );
        $templates['page-post-cards'] = __( 'Post Cards Search', 'pedagogy-cf-starter' );
        return $templates;
    }

    public function load_page_template( $template ) {
        if ( ! is_page() ) {
            return $template;
        }

        $post_template = get_page_template_slug( get_queried_object_id() );
        if ( ! in_array( $post_template, array( 'page-post-cards.php', 'page-post-cards' ), true ) ) {
            return $template;
        }

        $plugin_template = plugin_dir_path( __FILE__ ) . 'page-post-cards.php';
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
                                <option value="text" <?php selected( $editing && $edit_def['type'] === 'text' ); ?>>Text</option>
                                <option value="textarea" <?php selected( $editing && $edit_def['type'] === 'textarea' ); ?>>Textarea</option>
                                <option value="number" <?php selected( $editing && $edit_def['type'] === 'number' ); ?>>Number</option>
                                <option value="date" <?php selected( $editing && $edit_def['type'] === 'date' ); ?>>Date</option>
                                <option value="select" <?php selected( $editing && $edit_def['type'] === 'select' ); ?>>Select (comma-separated options)</option>
                                <option value="url" <?php selected( $editing && $edit_def['type'] === 'url' ); ?>>URL (link + label)</option>
                                <option value="linked" <?php selected( $editing && $edit_def['type'] === 'linked' ); ?>>Linked Field Options</option>
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
                            <input type="hidden" id="pcf_options" name="pcf_options" value="<?php echo $editing && isset( $edit_def['options'] ) ? esc_attr( implode( ', ', $edit_def['options'] ) ) : ''; ?>">
                            <div id="pcf_chips_container" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:32px;"></div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input id="pcf_option_input" type="text" placeholder="Add option and press Enter or Add" style="width:260px;">
                                <button type="button" id="pcf_add_option_btn" class="button">Add</button>
                            </div>
                            <p class="description">Type an option and press Enter or click Add. Options are alphabetized automatically. Click &times; to remove.</p>
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
                                            $display_opts = $d['options'];
                                            natcasesort( $display_opts );
                                            echo esc_html( implode( ', ', $display_opts ) );
                                        }
                                    }
                                ?></td>
                                <td>
                                    <div class="pcf-action-buttons">
                                        <a class="pcf-action-button pcf-action-button-edit" href="<?php echo esc_url( add_query_arg( array( 'page' => 'pedagogy-cf', 'edit' => $name ), admin_url( 'admin.php' ) ) ); ?>">
                                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                            <span><?php esc_html_e( 'Edit', 'pedagogy-cf-starter' ); ?></span>
                                        </a>
                                        <a class="pcf-action-button pcf-action-button-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pedagogy_cf_delete&name=' . $name ), 'pedagogy_cf_delete' ) ); ?>">
                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                            <span><?php esc_html_e( 'Delete', 'pedagogy-cf-starter' ); ?></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
        .pcf-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #2563EB;
            color: #fff;
            border-radius: 999px;
            padding: 4px 12px 4px 14px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.4;
        }
        .pcf-chip-remove {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
            padding: 0;
            margin-left: 2px;
            opacity: 0.8;
        }
        .pcf-chip-remove:hover { opacity: 1; }
        </style>
        <script>
        (function(){
            var type = document.getElementById('pcf_type');
            var optionsRow = document.getElementById('options_row');
            var linkedSourceRow = document.getElementById('linked_source_row');
            var linkedMultipleRow = document.getElementById('linked_multiple_row');
            var selectMultipleRow = document.getElementById('select_multiple_row');
            var urlRow = document.getElementById('url_row');
            var hidden = document.getElementById('pcf_options');
            var container = document.getElementById('pcf_chips_container');
            var optInput = document.getElementById('pcf_option_input');
            var addBtn = document.getElementById('pcf_add_option_btn');

            function getOptions() {
                var val = hidden.value.trim();
                if (!val) return [];
                return val.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
            }

            function setOptions(arr) {
                arr.sort(function(a,b){ return a.toLowerCase().localeCompare(b.toLowerCase()); });
                hidden.value = arr.join(', ');
                renderChips(arr);
            }

            function renderChips(arr) {
                container.innerHTML = '';
                arr.forEach(function(opt) {
                    var chip = document.createElement('span');
                    chip.className = 'pcf-chip';
                    chip.textContent = opt;
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pcf-chip-remove';
                    btn.innerHTML = '&times;';
                    btn.setAttribute('aria-label', 'Remove ' + opt);
                    btn.addEventListener('click', function(){
                        var opts = getOptions().filter(function(o){ return o !== opt; });
                        setOptions(opts);
                    });
                    chip.appendChild(btn);
                    container.appendChild(chip);
                });
            }

            function addOption() {
                var val = optInput.value.trim();
                if (!val) return;
                var opts = getOptions();
                if (opts.indexOf(val) === -1) {
                    opts.push(val);
                    setOptions(opts);
                }
                optInput.value = '';
                optInput.focus();
            }

            addBtn.addEventListener('click', addOption);
            optInput.addEventListener('keydown', function(e){
                if (e.key === 'Enter') { e.preventDefault(); addOption(); }
            });

            // Initialize chips from existing hidden value
            setOptions(getOptions());

            function update(){
                optionsRow.style.display = type.value === 'select' ? '' : 'none';
                linkedSourceRow.style.display = type.value === 'linked' ? '' : 'none';
                linkedMultipleRow.style.display = type.value === 'linked' ? '' : 'none';
                if ( selectMultipleRow ) {
                    selectMultipleRow.style.display = type.value === 'select' ? '' : 'none';
                }
                if ( urlRow ) {
                    urlRow.style.display = type.value === 'url' ? '' : 'none';
                }
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
        $order = isset( $_POST['pcf_order'] ) ? intval( wp_unslash( $_POST['pcf_order'] ) ) : 0;
        $select_multiple = isset( $_POST['pcf_select_multiple'] ) ? true : false;
        $original = isset( $_POST['original_name'] ) ? sanitize_text_field( wp_unslash( $_POST['original_name'] ) ) : '';

        if ( ! preg_match( '/^[a-z0-9_]+$/', $name ) ) {
            wp_redirect( add_query_arg( 'error', 'invalid_name', admin_url( 'admin.php?page=pedagogy-cf' ) ) );
            exit;
        }

        $defs = $this->get_definitions();

        $entry = array( 'title' => $title, 'type' => $type );
        if ( 'select' === $type ) {
            $options = array_filter( array_map( 'trim', explode( ',', $opts ) ) );
            natcasesort( $options );
            $entry['options'] = array_values( $options );
            $entry['multiple'] = $select_multiple;
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
                $select_name = esc_attr( $meta_key . ( $is_multiple ? '[]' : '' ) );
                echo '<select name="' . $select_name . '" class="widefat"' . ( $is_multiple ? ' multiple size="5"' : '' ) . '>';
                $opts = $def['options'] ?? array();
                if ( ! empty( $opts ) && is_array( $opts ) ) {
                    usort( $opts, 'strcasecmp' );
                }
                if ( ! $is_multiple ) {
                    echo '<option value="">' . esc_html__( '-- Select option --', 'pedagogy' ) . '</option>';
                }
                foreach ( $opts as $o ) {
                    $selected = false;
                    if ( $is_multiple ) {
                        $selected = is_array( $value ) && in_array( $o, $value, true );
                    } else {
                        $selected = $value === $o;
                    }
                    $sel = selected( $selected, true, false );
                    echo '<option value="' . esc_attr( $o ) . '" ' . $sel . '>' . esc_html( $o ) . '</option>';
                }
                echo '</select>';
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
                $opts = array();
                if ( $source ) {
                    $source_defs = $this->get_definitions();
                    $source_def = isset( $source_defs[ $source ] ) ? $source_defs[ $source ] : null;
                    if ( $source_def && isset( $source_def['options'] ) ) {
                        $opts = $source_def['options'];
                    }
                }
                if ( ! empty( $opts ) && is_array( $opts ) ) {
                    usort( $opts, 'strcasecmp' );
                }
                if ( empty( $opts ) ) {
                    echo '<p>' . esc_html__( 'No source options found. Please select a valid source field or edit the source field options.', 'pedagogy' ) . '</p>';
                    break;
                }
                $is_multiple = ! empty( $def['multiple'] );
                $select_name = esc_attr( $meta_key . ( $is_multiple ? '[]' : '' ) );
                echo '<select name="' . $select_name . '" class="widefat"' . ( $is_multiple ? ' multiple size="5"' : '' ) . '>';
                if ( ! $is_multiple ) {
                    echo '<option value="">' . esc_html__( '-- Select option --', 'pedagogy' ) . '</option>';
                }
                foreach ( $opts as $o ) {
                    $selected = false;
                    if ( $is_multiple ) {
                        $selected = is_array( $value ) && in_array( $o, $value, true );
                    } else {
                        $selected = $value === $o;
                    }
                    $sel = selected( $selected, true, false );
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
                    $val = wp_kses_post( $raw );
                    break;
                case 'linked':
                    if ( is_array( $raw ) ) {
                        $val = array_map( 'sanitize_text_field', $raw );
                    } else {
                        $val = sanitize_text_field( $raw );
                    }
                    break;
                case 'select':
                    if ( is_array( $raw ) ) {
                        $val = array_map( 'sanitize_text_field', $raw );
                    } else {
                        $val = sanitize_text_field( $raw );
                    }
                    break;
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
            if ( $val === '' || $val === null ) {
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

}

new Pedagogy_CF_Starter();
