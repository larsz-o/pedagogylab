<?php
/**
 * Template Name: Post Cards Search
 * Description: Page template for listing posts as cards with search over post metadata.
 */
get_header();
if ( function_exists( 'twentytwentyfive_render_inline_header' ) ) {
    twentytwentyfive_render_inline_header();
}

if ( ! function_exists( 'pedagogy_build_search_stems' ) ) {
    function pedagogy_build_search_stems( $token ) {
        $token = mb_strtolower( trim( (string) $token ) );
        if ( mb_strlen( $token ) < 4 ) {
            return array();
        }

        $stems = array();
        $suffixes = array( 'izations', 'ization', 'ational', 'fulness', 'ousness', 'iveness', 'tional', 'biliti', 'lessly', 'ingly', 'ments', 'ment', 'tions', 'tion', 'ships', 'ship', 'ences', 'ance', 'ence', 'ities', 'ity', 'ably', 'ably', 'edly', 'edly', 'ally', 'ably', 'ing', 'ers', 'ies', 'ied', 'est', 'ism', 'ist', 'ous', 'ive', 'ize', 'ise', 'ed', 'es', 'er', 'ly', 's' );

        foreach ( $suffixes as $suffix ) {
            $suffix_len = mb_strlen( $suffix );
            if ( mb_strlen( $token ) <= $suffix_len + 2 ) {
                continue;
            }
            if ( mb_substr( $token, -$suffix_len ) === $suffix ) {
                $stem = mb_substr( $token, 0, mb_strlen( $token ) - $suffix_len );
                if ( mb_strlen( $stem ) >= 3 ) {
                    $stems[] = $stem;
                }
            }
        }

        if ( mb_strlen( $token ) >= 7 ) {
            $stems[] = mb_substr( $token, 0, mb_strlen( $token ) - 1 );
            $stems[] = mb_substr( $token, 0, mb_strlen( $token ) - 2 );
        }

        $stems = array_filter( array_unique( $stems ), static function( $stem ) use ( $token ) {
            return $stem !== $token && mb_strlen( $stem ) >= 3;
        } );

        return array_values( $stems );
    }
}

if ( ! function_exists( 'pedagogy_option_titles' ) ) {
    function pedagogy_option_titles( $options ) {
        if ( ! is_array( $options ) ) {
            return array();
        }

        $titles = array();
        foreach ( $options as $option ) {
            if ( is_array( $option ) ) {
                if ( isset( $option['title'] ) ) {
                    $title = sanitize_text_field( $option['title'] );
                } elseif ( isset( $option['label'] ) ) {
                    $title = sanitize_text_field( $option['label'] );
                } elseif ( isset( $option['value'] ) ) {
                    $title = sanitize_text_field( $option['value'] );
                } else {
                    $title = '';
                }
            } else {
                $title = sanitize_text_field( (string) $option );
            }

            if ( '' !== $title ) {
                $titles[] = $title;
            }
        }

        $titles = array_values( array_unique( $titles ) );
        usort( $titles, 'strcasecmp' );
        return $titles;
    }
}

if ( ! function_exists( 'pedagogy_option_description_map' ) ) {
    function pedagogy_option_description_map( $options ) {
        if ( ! is_array( $options ) ) {
            return array();
        }

        $map = array();
        foreach ( $options as $option ) {
            $title = '';
            $description = '';

            if ( is_array( $option ) ) {
                if ( isset( $option['title'] ) ) {
                    $title = sanitize_text_field( $option['title'] );
                } elseif ( isset( $option['label'] ) ) {
                    $title = sanitize_text_field( $option['label'] );
                } elseif ( isset( $option['value'] ) ) {
                    $title = sanitize_text_field( $option['value'] );
                }
                if ( isset( $option['description'] ) ) {
                    $description = sanitize_text_field( $option['description'] );
                }
            } else {
                $title = sanitize_text_field( (string) $option );
            }

            if ( '' === $title ) {
                continue;
            }

            $map[ strtolower( $title ) ] = array(
                'title'       => $title,
                'description' => $description,
            );
        }

        return $map;
    }
}

$search_term = sanitize_text_field( wp_unslash( $_GET['pcf_search'] ?? '' ) );
$paged = max( 1, get_query_var( 'paged', 1 ) );
$defs = array();
if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
    $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
    if ( ! is_array( $defs ) ) {
        $defs = array();
    }

    uasort( $defs, static function( $a, $b ) {
        $a_order = isset( $a['order'] ) ? intval( $a['order'] ) : PHP_INT_MAX;
        $b_order = isset( $b['order'] ) ? intval( $b['order'] ) : PHP_INT_MAX;
        if ( $a_order === $b_order ) {
            return 0;
        }
        return $a_order < $b_order ? -1 : 1;
    } );
}

$meta_keys = array();
$filter_definitions = array();
$filter_values = array();
$people_source_fields = array( 'creators', 'contributors' );
$people_options = array();
$is_file_format_field = static function ( $field_name, $field_def ) {
    $name_key = strtolower( trim( str_replace( '-', '_', (string) $field_name ) ) );
    $title_key = strtolower( trim( str_replace( array( '-', '_' ), ' ', (string) ( $field_def['title'] ?? '' ) ) ) );

    return in_array( $name_key, array( 'file_format', 'file_formats' ), true )
        || false !== strpos( $name_key, 'file_format' )
        || in_array( $title_key, array( 'file format', 'file formats' ), true )
        || false !== strpos( $title_key, 'file format' );
};

foreach ( $defs as $name => $def ) {
    if ( ! in_array( $name, $people_source_fields, true ) ) {
        continue;
    }

    if ( isset( $def['type'] ) && in_array( $def['type'], array( 'select', 'linked' ), true ) ) {
        $options = array();
        if ( 'select' === $def['type'] ) {
            $options = isset( $def['options'] ) && is_array( $def['options'] ) ? pedagogy_option_titles( $def['options'] ) : array();
        } elseif ( 'linked' === $def['type'] && isset( $def['source_field'] ) ) {
            $source_name = $def['source_field'];
            if ( isset( $defs[ $source_name ] ) && isset( $defs[ $source_name ]['options'] ) && is_array( $defs[ $source_name ]['options'] ) ) {
                $options = pedagogy_option_titles( $defs[ $source_name ]['options'] );
            }
        }
        if ( ! empty( $options ) ) {
            $people_options = array_merge( $people_options, $options );
        }
    }
}

if ( ! empty( $people_options ) ) {
    $people_options = array_values( array_unique( $people_options ) );
    usort( $people_options, 'strcasecmp' );
}

$people_filter_inserted = false;
foreach ( $defs as $name => $def ) {
    $meta_keys[] = 'pcf_' . $name;

    if ( 'people' === $name ) {
        continue;
    }

    if ( $is_file_format_field( $name, $def ) ) {
        continue;
    }

    if ( in_array( $name, $people_source_fields, true ) ) {
        if ( ! $people_filter_inserted && ! empty( $people_options ) ) {
            $filter_definitions['people'] = array(
                'title'   => 'People',
                'options' => $people_options,
            );

            $raw_people_filter_value = wp_unslash( $_GET['pcf_filter_people'] ?? array() );
            if ( is_array( $raw_people_filter_value ) ) {
                $filter_values['people'] = array_map( 'sanitize_text_field', $raw_people_filter_value );
            } else {
                $filter_values['people'] = array( sanitize_text_field( $raw_people_filter_value ) );
            }

            $people_filter_inserted = true;
        }
        continue;
    }

    if ( isset( $def['type'] ) && in_array( $def['type'], array( 'select', 'linked' ), true ) ) {
        $options = array();

        if ( 'select' === $def['type'] ) {
            $options = isset( $def['options'] ) && is_array( $def['options'] ) ? pedagogy_option_titles( $def['options'] ) : array();
        } elseif ( 'linked' === $def['type'] && isset( $def['source_field'] ) ) {
            $source_name = $def['source_field'];
            if ( isset( $defs[ $source_name ] ) && isset( $defs[ $source_name ]['options'] ) && is_array( $defs[ $source_name ]['options'] ) ) {
                $options = pedagogy_option_titles( $defs[ $source_name ]['options'] );
            }
        }

        if ( ! empty( $options ) ) {
            usort( $options, 'strcasecmp' );

            $filter_definitions[ $name ] = array(
                'title'   => isset( $def['title'] ) ? $def['title'] : $name,
                'options' => $options,
            );
            $raw_filter_value = wp_unslash( $_GET[ 'pcf_filter_' . $name ] ?? array() );
            if ( is_array( $raw_filter_value ) ) {
                $filter_values[ $name ] = array_map( 'sanitize_text_field', $raw_filter_value );
            } else {
                $filter_values[ $name ] = array( sanitize_text_field( $raw_filter_value ) );
            }
        }
    }
}

// Show key taxonomy-like filters first in the sidebar when they exist.
$preferred_filter_order = array(
    'material_type',
    'audience_types',
    'subjects_topics',
    'disciplinary_areas',
    'intended_use',
);

if ( ! empty( $filter_definitions ) ) {
    $ordered_filter_definitions = array();

    foreach ( $preferred_filter_order as $preferred_filter_name ) {
        if ( isset( $filter_definitions[ $preferred_filter_name ] ) ) {
            $ordered_filter_definitions[ $preferred_filter_name ] = $filter_definitions[ $preferred_filter_name ];
        }
    }

    foreach ( $filter_definitions as $filter_name => $filter_definition ) {
        if ( ! isset( $ordered_filter_definitions[ $filter_name ] ) ) {
            $ordered_filter_definitions[ $filter_name ] = $filter_definition;
        }
    }

    $filter_definitions = $ordered_filter_definitions;
}

global $wpdb;
$db_meta_keys = $wpdb->get_col(
    $wpdb->prepare(
        "
        SELECT DISTINCT pm.meta_key
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key LIKE %s
            AND p.post_type = %s
            AND p.post_status = %s
        ",
        $wpdb->esc_like( 'pcf_' ) . '%',
        'post',
        'publish'
    )
);

if ( is_array( $db_meta_keys ) && ! empty( $db_meta_keys ) ) {
    $meta_keys = array_values( array_unique( array_merge( $meta_keys, $db_meta_keys ) ) );
}

$broader_collection_options_map = array();
$broader_collections_def = null;
$broader_collection_field_names = array();

if ( isset( $defs['broader_collections'] ) && is_array( $defs['broader_collections'] ) ) {
    $broader_collections_def = $defs['broader_collections'];
    $broader_collection_field_names[] = 'broader_collections';
}
if ( isset( $defs['broader_collection'] ) && is_array( $defs['broader_collection'] ) ) {
    if ( null === $broader_collections_def ) {
        $broader_collections_def = $defs['broader_collection'];
    }
    $broader_collection_field_names[] = 'broader_collection';
}
if ( empty( $broader_collection_field_names ) ) {
    $broader_collection_field_names[] = 'broader_collections';
}

if ( is_array( $broader_collections_def ) ) {

    if ( isset( $broader_collections_def['type'] ) && 'linked' === $broader_collections_def['type'] && ! empty( $broader_collections_def['source_field'] ) ) {
        $source_field = $broader_collections_def['source_field'];
        if ( isset( $defs[ $source_field ]['options'] ) && is_array( $defs[ $source_field ]['options'] ) ) {
            $broader_collection_options_map = pedagogy_option_description_map( $defs[ $source_field ]['options'] );
        }
    } elseif ( isset( $broader_collections_def['options'] ) && is_array( $broader_collections_def['options'] ) ) {
        $broader_collection_options_map = pedagogy_option_description_map( $broader_collections_def['options'] );
    }
}

$search_ids = null;
$force_no_results = false;
if ( $search_term !== '' ) {
    // Strict override mode: only exact broader_collections matches are allowed.
    $strict_broader_collections_meta_query = array( 'relation' => 'OR' );
    foreach ( $broader_collection_field_names as $broader_collection_field_name ) {
        $strict_broader_collections_meta_query[] = array(
            'key'     => 'pcf_' . $broader_collection_field_name,
            'value'   => $search_term,
            'compare' => '=',
        );
        $strict_broader_collections_meta_query[] = array(
            'key'     => 'pcf_' . $broader_collection_field_name,
            'value'   => '"' . $search_term . '"',
            'compare' => 'LIKE',
        );
    }

    $broader_collections_query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => $strict_broader_collections_meta_query,
    ) );

    $search_ids = $broader_collections_query->have_posts() ? $broader_collections_query->posts : array();

    if ( empty( $search_ids ) ) {
        $force_no_results = true;
        $search_ids = array( 0 );
    }
}

$query_args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
);
if ( is_array( $search_ids ) ) {
    $query_args['post__in'] = $search_ids;
    $query_args['orderby'] = 'post__in';
}

if ( $force_no_results ) {
    $query_args['post__in'] = array( 0 );
}

$filter_meta_query = array( 'relation' => 'AND' );
$has_filters = false;
foreach ( $filter_definitions as $name => $filter ) {
    $values = $filter_values[ $name ] ?? array();
    $values = is_array( $values ) ? array_filter( $values ) : array( $values );
    if ( ! empty( $values ) ) {
        $has_filters = true;

        if ( 'people' === $name ) {
            $people_query = array( 'relation' => 'OR' );
            foreach ( $values as $value ) {
                foreach ( $people_source_fields as $people_field ) {
                    $people_query[] = array(
                        'key'     => 'pcf_' . $people_field,
                        'value'   => $value,
                        'compare' => 'LIKE',
                    );
                }
            }
            $filter_meta_query[] = $people_query;
        } elseif ( in_array( $name, array( 'broader_collections', 'broader_collection' ), true ) ) {
            $broader_collections_query = array( 'relation' => 'OR' );
            foreach ( $values as $value ) {
                $broader_collections_query[] = array(
                    'key'     => 'pcf_' . $name,
                    'value'   => $value,
                    'compare' => '=',
                );
                $broader_collections_query[] = array(
                    'key'     => 'pcf_' . $name,
                    'value'   => '"' . $value . '"',
                    'compare' => 'LIKE',
                );
            }
            $filter_meta_query[] = $broader_collections_query;
        } elseif ( count( $values ) > 1 ) {
            $sub_query = array( 'relation' => 'OR' );
            foreach ( $values as $value ) {
                $sub_query[] = array(
                    'key'     => 'pcf_' . $name,
                    'value'   => $value,
                    'compare' => 'LIKE',
                );
            }
            $filter_meta_query[] = $sub_query;
        } else {
            $filter_meta_query[] = array(
                'key'     => 'pcf_' . $name,
                'value'   => reset( $values ),
                'compare' => 'LIKE',
            );
        }
    }
}

if ( $has_filters ) {
    $query_args['meta_query'] = $filter_meta_query;
}

$post_query = new WP_Query( $query_args );
$results_anchor = 'post-cards-results';

$active_filter_chips = array();
$clear_filters_url = '';
$broader_collections_descriptions = array();

$broader_collections_context_values = array();
if ( '' !== $search_term ) {
    $broader_collections_context_values[] = $search_term;
}

$selected_broader_collections = array_filter( (array) ( $filter_values['broader_collections'] ?? array() ), 'strlen' );
if ( empty( $selected_broader_collections ) ) {
    $selected_broader_collections = array_filter( (array) ( $filter_values['broader_collection'] ?? array() ), 'strlen' );
}
if ( ! empty( $selected_broader_collections ) ) {
    $broader_collections_context_values = array_merge( $broader_collections_context_values, $selected_broader_collections );
}

$broader_collections_context_values = array_values( array_unique( array_map( 'sanitize_text_field', $broader_collections_context_values ) ) );
foreach ( $broader_collections_context_values as $broader_collections_value ) {
    $lookup_key = strtolower( $broader_collections_value );
    if ( isset( $broader_collection_options_map[ $lookup_key ] ) && '' !== trim( (string) $broader_collection_options_map[ $lookup_key ]['description'] ) ) {
        $broader_collections_descriptions[] = $broader_collection_options_map[ $lookup_key ];
    }
}

if ( ! empty( $broader_collections_descriptions ) ) {
    $deduped_descriptions = array();
    $seen_titles = array();
    foreach ( $broader_collections_descriptions as $description_item ) {
        $title_key = strtolower( $description_item['title'] );
        if ( isset( $seen_titles[ $title_key ] ) ) {
            continue;
        }
        $seen_titles[ $title_key ] = true;
        $deduped_descriptions[] = $description_item;
    }
    $broader_collections_descriptions = $deduped_descriptions;
}

if ( $has_filters ) {
    foreach ( $filter_definitions as $name => $filter ) {
        $selected_values = array_filter( (array) ( $filter_values[ $name ] ?? array() ), 'strlen' );

        foreach ( $selected_values as $selected_value ) {
            $remove_args = array();

            if ( '' !== $search_term ) {
                $remove_args['pcf_search'] = $search_term;
            }

            foreach ( $filter_definitions as $inner_name => $inner_filter ) {
                $inner_values = array_filter( (array) ( $filter_values[ $inner_name ] ?? array() ), 'strlen' );

                if ( $inner_name === $name ) {
                    $inner_values = array_values(
                        array_filter(
                            $inner_values,
                            static function ( $value ) use ( $selected_value ) {
                                return $value !== $selected_value;
                            }
                        )
                    );
                }

                if ( ! empty( $inner_values ) ) {
                    $remove_args[ 'pcf_filter_' . $inner_name ] = $inner_values;
                }
            }

            $active_filter_chips[] = array(
                'label' => sprintf( '%s: %s', $filter['title'], $selected_value ),
                'url'   => add_query_arg( $remove_args, get_permalink() ) . '#' . $results_anchor,
            );
        }
    }

    $clear_filters_args = array();
    if ( '' !== $search_term ) {
        $clear_filters_args['pcf_search'] = $search_term;
    }

    $clear_filters_url = add_query_arg( $clear_filters_args, get_permalink() ) . '#' . $results_anchor;
}

function pedagogy_post_material_types( $post_id, $defs ) {
    $materials = array();

    if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
        $value = Pedagogy_CF_Starter::get_value( $post_id, 'material_type' );
        if ( $value ) {
            if ( is_array( $value ) ) {
                $materials = array_merge( $materials, $value );
            } else {
                $materials[] = $value;
            }
        }
    }

    $materials = array_filter( array_map( 'trim', $materials ) );
    return array_unique( $materials );
}

function pedagogy_post_cover_image_url( $post_id ) {
    if ( has_post_thumbnail( $post_id ) ) {
        return get_the_post_thumbnail_url( $post_id, 'medium_large' );
    }

    if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
        $cover_url = Pedagogy_CF_Starter::get_value( $post_id, 'cover_image' );
        if ( $cover_url ) {
            return esc_url_raw( $cover_url );
        }
    }

    return '';   
}
?>

<div class="post-cards-page">
    <div class="post-cards-page-title">
        <h1><?php the_title(); ?></h1>
        <p class="post-cards-intro">Search publications and metadata across all entries.</p>

        <form class="post-cards-search" method="get" action="<?php echo esc_url( get_permalink() . '#' . $results_anchor ); ?>">
            <div class="post-cards-search-row">
                <label for="pcf_search" class="screen-reader-text"><?php esc_html_e( 'Search entries and metadata', 'twentytwentyfive' ); ?></label>
                <input id="pcf_search" name="pcf_search" type="search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="Search titles, descriptions, formats, tags...">
                <button type="submit"><?php esc_html_e( 'Search', 'twentytwentyfive' ); ?></button>
                <?php if ( $search_term !== '' || $has_filters ) : ?>
                    <a class="post-cards-clear" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Clear', 'twentytwentyfive' ); ?></a>
                <?php endif; ?>
            </div>

            <?php foreach ( $filter_definitions as $name => $filter ) : ?>
                <?php $selected_values = array_filter( (array) ( $filter_values[ $name ] ?? array() ) ); ?>
                <?php foreach ( $selected_values as $selected_value ) : ?>
                    <input type="hidden" name="pcf_filter_<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $selected_value ); ?>">
                <?php endforeach; ?>
            <?php endforeach; ?>
        </form>
    </div>
    <div class="post-cards-layout">
 <aside class="post-cards-sidebar">
 <div class="post-cards-header">
            <?php if ( ! empty( $filter_definitions ) ) : ?>
                <form class="post-cards-filter-form" method="get" action="<?php echo esc_url( get_permalink() . '#' . $results_anchor ); ?>">
                    <input type="hidden" name="pcf_search" value="<?php echo esc_attr( $search_term ); ?>">
                <details class="post-cards-filter-drawer" open>
                    <summary class="post-cards-filter-drawer-title pcf-meta-label"><?php esc_html_e( 'Filter by', 'twentytwentyfive' ); ?></summary>
                    <div class="post-cards-filter-drawer-body">
                    <div class="post-cards-filters">
                        <?php foreach ( $filter_definitions as $name => $filter ) : ?>
                            <?php $selected_values = array_filter( (array) $filter_values[ $name ] ); ?>
                            <div class="post-cards-filter-item">
                                <label for="pcf_filter_<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $filter['title'] ); ?></label>
                                <select id="pcf_filter_<?php echo esc_attr( $name ); ?>" name="pcf_filter_<?php echo esc_attr( $name ); ?>[]" multiple size="4" onchange="this.form.submit()">
                                    <option value="" <?php echo empty( $selected_values ) ? 'selected' : ''; ?>><?php esc_html_e( 'All', 'twentytwentyfive' ); ?></option>
                                    <?php foreach ( $filter['options'] as $option ) : ?>
                                        <option value="<?php echo esc_attr( $option ); ?>" <?php echo in_array( $option, $selected_values, true ) ? 'selected' : ''; ?>><?php echo esc_html( $option ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                </details>
                </form>
                <script>
                (function() {
                    const drawer = document.querySelector('.post-cards-filter-drawer');
                    const summary = drawer ? drawer.querySelector('summary') : null;
                    if (!drawer) return;
                    const mq = window.matchMedia('(max-width: 760px)');
                    const syncDrawer = () => {
                        drawer.open = !mq.matches;
                    };
                    syncDrawer();
                    if (summary) {
                        summary.addEventListener('click', function(event) {
                            if (!mq.matches) {
                                event.preventDefault();
                            }
                        });
                    }
                    if (mq.addEventListener) {
                        mq.addEventListener('change', syncDrawer);
                    } else if (mq.addListener) {
                        mq.addListener(syncDrawer);
                    }
                })();
                </script>
            <?php endif; ?>
    </div>
    </aside>
   
    <div class="post-cards-content">
    <div id="<?php echo esc_attr( $results_anchor ); ?>">
    <?php if ( ! empty( $active_filter_chips ) ) : ?>
        <div class="post-cards-active-filters" aria-label="<?php esc_attr_e( 'Active filters', 'twentytwentyfive' ); ?>">
            <?php foreach ( $active_filter_chips as $chip ) : ?>
                <a class="post-cards-filter-chip" href="<?php echo esc_url( $chip['url'] ); ?>">
                    <span class="post-cards-filter-chip-label"><?php echo esc_html( $chip['label'] ); ?></span>
                    <span class="post-cards-filter-chip-remove" aria-hidden="true">&times;</span>
                    <span class="screen-reader-text"><?php esc_html_e( 'Remove filter', 'twentytwentyfive' ); ?></span>
                </a>
            <?php endforeach; ?>

            <?php if ( $clear_filters_url ) : ?>
                <a class="post-cards-filter-chip post-cards-filter-chip-clear" href="<?php echo esc_url( $clear_filters_url ); ?>">
                    <?php esc_html_e( 'Clear all filters', 'twentytwentyfive' ); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ( $search_term !== '' || $has_filters ) : ?>
        <div class="post-cards-summary">
            <p><?php echo sprintf( esc_html__( 'Showing %s results for selected search and filters.', 'twentytwentyfive' ), intval( $post_query->found_posts ) ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $broader_collections_descriptions ) && intval( $post_query->found_posts ) > 0 ) : ?>
        <div class="post-cards-summary">
            <?php foreach ( $broader_collections_descriptions as $broader_collections_description ) : ?>
                <div class="post-cards-broader-collection-context" style="text-align:center; margin:0 0 1.25rem;">
                    <div class="post-cards-broader-collection-title" style="font-size:1.5rem; font-weight:700; line-height:1.25; margin-bottom:0.35rem;"><?php echo esc_html( $broader_collections_description['title'] ); ?></div>
                    <div class="post-cards-broader-collection-description" style="font-size:1rem; font-weight:400; line-height:1.5;"><?php echo esc_html( $broader_collections_description['description'] ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( $post_query->have_posts() ) : ?>
        <div class="post-cards-grid">
            <?php while ( $post_query->have_posts() ) : $post_query->the_post(); ?>
                <?php
                $cover_url = pedagogy_post_cover_image_url( get_the_ID() );
                $material_types = pedagogy_post_material_types( get_the_ID(), $defs );
                ?>
                <article class="post-card">
                    <a class="post-card-link" href="<?php the_permalink(); ?>">
                        <div class="post-card-image" style="background-image: url('<?php echo esc_url( $cover_url ? $cover_url : get_template_directory_uri() . '/assets/default-card.png' ); ?>');"></div>
                        <div class="post-card-body">
                            <h2 class="post-card-title"><?php the_title(); ?></h2>
                            <?php if ( ! empty( $material_types ) ) : ?>
                                <ul class="post-card-material-types">
                                    <?php foreach ( $material_types as $material_type ) : ?>
                                        <li class="post-card-material-type"><?php echo esc_html( $material_type ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="post-cards-pagination">
            <?php
            echo paginate_links( array(
                'total'   => $post_query->max_num_pages,
                'current' => $paged,
                'mid_size' => 1,
                'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'twentytwentyfive' ),
                'next_text' => esc_html__( 'Next', 'twentytwentyfive' ) . ' &raquo;',
            ) );
            ?>
        </div>
    <?php else : ?>
        <div class="post-cards-empty">
            <h2><?php esc_html_e( 'No posts matched your search', 'twentytwentyfive' ); ?></h2>
            <p><?php esc_html_e( 'Try another keyword or adjust the filters to see matching content.', 'twentytwentyfive' ); ?></p>
        </div>
    <?php endif; ?>
    </div>
    </div>
    </div>

    <?php wp_reset_postdata(); ?>
</div>

<?php get_footer();
