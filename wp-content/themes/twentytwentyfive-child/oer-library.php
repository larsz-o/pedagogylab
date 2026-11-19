<?php
/**
 * Template Name: OER Library (short)
 * Template Post Type: page
 * Description: Page template for listing posts as cards with search over post metadata.
 */

get_header();

$search_term = sanitize_text_field( wp_unslash( $_GET['pcf_search'] ?? '' ) );
$license_filter = sanitize_text_field( wp_unslash( $_GET['pcf_license'] ?? '' ) );
$paged = max( 1, get_query_var( 'paged', 1 ) );
$defs = array();
if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
    $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
    if ( ! is_array( $defs ) ) {
        $defs = array();
    }
}

$meta_keys = array();
foreach ( $defs as $name => $def ) {
    $meta_keys[] = 'pcf_' . $name;
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

$license_candidates = array( 'license', 'licence', 'license_type', 'licence_type', 'copyright_license' );
$license_field_name = '';
$license_meta_keys = array();
$license_options = array();

foreach ( $defs as $name => $def ) {
    $key = strtolower( str_replace( array( '-', ' ' ), '_', (string) $name ) );
    $title = strtolower( str_replace( array( '-', ' ' ), '_', (string) ( $def['title'] ?? '' ) ) );
    $matches_license = in_array( $key, $license_candidates, true )
        || in_array( $title, $license_candidates, true )
        || false !== strpos( $key, 'license' )
        || false !== strpos( $key, 'licence' )
        || false !== strpos( $title, 'license' )
        || false !== strpos( $title, 'licence' );

    if ( ! $matches_license ) {
        continue;
    }

    $license_field_name = $name;
    $license_meta_keys[] = 'pcf_' . $name;

    if ( isset( $def['options'] ) && is_array( $def['options'] ) ) {
        foreach ( $def['options'] as $opt ) {
            if ( is_array( $opt ) && isset( $opt['title'] ) ) {
                $title_value = trim( (string) $opt['title'] );
            } elseif ( is_scalar( $opt ) ) {
                $title_value = trim( (string) $opt );
            } else {
                $title_value = '';
            }

            if ( '' !== $title_value ) {
                $license_options[] = $title_value;
            }
        }
    }

    break;
}

if ( empty( $license_meta_keys ) ) {
    foreach ( $license_candidates as $candidate ) {
        $license_meta_keys[] = 'pcf_' . $candidate;
    }
}

$license_meta_keys = array_values( array_unique( array_filter( $license_meta_keys, 'strlen' ) ) );

if ( ! empty( $license_meta_keys ) ) {
    $placeholders = implode( ',', array_fill( 0, count( $license_meta_keys ), '%s' ) );
    $sql = "
        SELECT DISTINCT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key IN ($placeholders)
            AND p.post_type = %s
            AND p.post_status = %s
            AND pm.meta_value <> ''
        ORDER BY pm.meta_value ASC
    ";
    $sql_args = array_merge( $license_meta_keys, array( 'post', 'publish' ) );
    $db_license_values = $wpdb->get_col( $wpdb->prepare( $sql, $sql_args ) );

    if ( is_array( $db_license_values ) ) {
        foreach ( $db_license_values as $db_license_value ) {
            if ( is_serialized( $db_license_value ) ) {
                continue;
            }
            $clean_value = trim( sanitize_text_field( (string) $db_license_value ) );
            if ( '' !== $clean_value ) {
                $license_options[] = $clean_value;
            }
        }
    }
}

$license_options = array_values( array_unique( array_filter( array_map( 'trim', $license_options ), 'strlen' ) ) );
natcasesort( $license_options );
$license_options = array_values( $license_options );

$search_ids = null;
$force_no_results = false;
if ( $search_term !== '' ) {
    $content_query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        's'              => $search_term,
        'no_found_rows'  => true,
    ) );

    $meta_query = array( 'relation' => 'OR' );
    foreach ( $meta_keys as $meta_key ) {
        $meta_query[] = array(
            'key'     => $meta_key,
            'value'   => $search_term,
            'compare' => 'LIKE',
        );
    }

    $metadata_query_args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    );

    if ( count( $meta_query ) > 1 ) {
        $metadata_query_args['meta_query'] = $meta_query;
    }

    $metadata_query = new WP_Query( $metadata_query_args );

    $content_ids = $content_query->have_posts() ? $content_query->posts : array();
    $metadata_ids = $metadata_query->have_posts() ? $metadata_query->posts : array();
    $search_ids = array_values( array_unique( array_merge( $content_ids, $metadata_ids ) ) );

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

if ( '' !== $license_filter ) {
    $license_meta_query = array( 'relation' => 'OR' );
    foreach ( $license_meta_keys as $license_meta_key ) {
        $license_meta_query[] = array(
            'key'     => $license_meta_key,
            'value'   => $license_filter,
            'compare' => '=',
        );
    }

    if ( count( $license_meta_query ) > 1 ) {
        $query_args['meta_query'] = $license_meta_query;
    }
}

$post_query = new WP_Query( $query_args );
$results_anchor = 'post-cards-results';

function pedagogy_post_material_types( $post_id, $defs ) {
    $material_names = array(
        'material_type',
        'material_types',
        'material type',
        'material types',
        'resource_type',
        'resource_types',
        'resource type',
        'resource types',
        'content_type',
        'content_types',
        'content type',
        'content types',
    );
    $materials = array();

    foreach ( $defs as $name => $def ) {
        $key   = strtolower( $name );
        $title = strtolower( isset( $def['title'] ) ? $def['title'] : '' );
        $key_normalized   = str_replace( array( '-', ' ' ), '_', $key );
        $title_normalized = str_replace( array( '-', ' ' ), '_', $title );
        $matches_exact = in_array( $key, $material_names, true )
            || in_array( $title, $material_names, true )
            || in_array( $key_normalized, $material_names, true )
            || in_array( $title_normalized, $material_names, true );
        $matches_phrase = ( false !== stripos( $key, 'material' ) && false !== stripos( $key, 'type' ) )
            || ( false !== stripos( $title, 'material' ) && false !== stripos( $title, 'type' ) )
            || ( false !== stripos( $key, 'resource' ) && false !== stripos( $key, 'type' ) )
            || ( false !== stripos( $title, 'resource' ) && false !== stripos( $title, 'type' ) );

        if ( ! $matches_exact && ! $matches_phrase ) {
            continue;
        }

        if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
            $value = Pedagogy_CF_Starter::get_value( $post_id, $name );
            if ( $value ) {
                if ( is_array( $value ) ) {
                    $materials = array_merge( $materials, $value );
                } else {
                    $materials[] = $value;
                }
            }
        }
    }

    if ( empty( $materials ) ) {
        foreach ( $defs as $name => $def ) {
            $title = isset( $def['title'] ) ? $def['title'] : '';
            if (
                false !== stripos( $name, 'material' )
                || false !== stripos( $title, 'material' )
                || false !== stripos( $name, 'resource type' )
                || false !== stripos( $title, 'resource type' )
            ) {
                if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                    $value = Pedagogy_CF_Starter::get_value( $post_id, $name );
                    if ( $value ) {
                        if ( is_array( $value ) ) {
                            $materials = array_merge( $materials, $value );
                        } else {
                            $materials[] = $value;
                        }
                    }
                }
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

function pedagogy_get_custom_field_value( $post_id, $defs, $candidates ) {
    if ( ! class_exists( 'Pedagogy_CF_Starter' ) || empty( $defs ) ) {
        return '';
    }

    $candidates = array_map( 'strtolower', $candidates );
    foreach ( $defs as $name => $def ) {
        $key = strtolower( $name );
        $title = strtolower( isset( $def['title'] ) ? $def['title'] : '' );
        if ( in_array( $key, $candidates, true ) || in_array( $title, $candidates, true ) ) {
            $value = Pedagogy_CF_Starter::get_value( $post_id, $name );
            if ( '' === $value || null === $value ) {
                continue;
            }
            if ( method_exists( 'Pedagogy_CF_Starter', 'normalize_display_value' ) ) {
                return Pedagogy_CF_Starter::normalize_display_value( $value );
            }

            if ( is_array( $value ) ) {
                $normalized = array();
                foreach ( $value as $item ) {
                    if ( is_scalar( $item ) ) {
                        $normalized[] = trim( (string) $item );
                    }
                }
                return implode( ', ', array_values( array_filter( $normalized, 'strlen' ) ) );
            }

            if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
                return trim( (string) $value );
            }

            if ( is_bool( $value ) ) {
                return $value ? 'Yes' : 'No';
            }

            return trim( (string) $value );
        }
    }
    return '';
}

function pedagogy_post_creator( $post_id, $defs ) {
    return pedagogy_get_custom_field_value( $post_id, $defs, array( 'creator', 'creators', 'author', 'authors' ) );
}

function pedagogy_post_date_meta( $post_id, $defs ) {
    $value = pedagogy_get_custom_field_value( $post_id, $defs, array( 'date', 'publication date', 'published date' ) );
    if ( $value ) {
        return $value;
    }
    return get_the_date( '', $post_id );
}

function pedagogy_post_embed_html( $post_id, $defs ) {
    if ( ! class_exists( 'Pedagogy_CF_Starter' ) || empty( $defs ) ) {
        return '';
    }

    foreach ( $defs as $name => $def ) {
        if ( isset( $def['type'] ) && 'embed' === $def['type'] ) {
            $value = Pedagogy_CF_Starter::get_value( $post_id, $name );
            if ( is_array( $value ) ) {
                $value = reset( $value );
            }
            if ( ! is_string( $value ) || '' === trim( $value ) ) {
                continue;
            }
            $value = trim( $value );
            $embed_html = wp_oembed_get( $value );
            if ( ! $embed_html ) {
                $safe_url = esc_url( $value );
                if ( $safe_url ) {
                    $embed_html = '<iframe src="' . $safe_url . '" width="100%" height="260" frameborder="0" allowfullscreen sandbox="allow-same-origin allow-scripts"></iframe>';
                }
            }
            if ( $embed_html ) {
                return '<div class="post-card-embed">' . $embed_html . '</div>';
            }
        }
    }
    return '';
}
?>

<div class="post-cards-page">
    <div class="post-cards-layout">
    <aside class="post-cards-sidebar">
    <div class="post-cards-header">
        <div>
            <h1><?php the_title(); ?></h1>
            <p class="post-cards-intro">Search publications and metadata across all posts.</p>
        </div>
        <form class="post-cards-search" method="get" action="<?php echo esc_url( get_permalink() . '#' . $results_anchor ); ?>">
            <label for="pcf_search" class="screen-reader-text"><?php esc_html_e( 'Search posts and metadata', 'twentytwentyfive' ); ?></label>
            <input id="pcf_search" name="pcf_search" type="search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="Search titles, descriptions, formats, tags...">
            <div class="post-cards-filters">
                <div class="post-cards-filter-item">
                    <label for="pcf_license"><?php esc_html_e( 'License', 'twentytwentyfive' ); ?></label>
                    <select id="pcf_license" name="pcf_license">
                        <option value=""><?php esc_html_e( 'All licenses', 'twentytwentyfive' ); ?></option>
                        <?php foreach ( $license_options as $license_option ) : ?>
                            <option value="<?php echo esc_attr( $license_option ); ?>" <?php selected( $license_filter, $license_option ); ?>><?php echo esc_html( $license_option ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit"><?php esc_html_e( 'Search', 'twentytwentyfive' ); ?></button>
        </form>
    </div>
    </aside>

    <div class="post-cards-content">
    <div id="<?php echo esc_attr( $results_anchor ); ?>">
    <?php if ( $post_query->have_posts() ) : ?>
        <div class="post-cards-grid">
            <?php while ( $post_query->have_posts() ) : $post_query->the_post(); ?>
                <?php
                $cover_url = pedagogy_post_cover_image_url( get_the_ID() );
                $material_types = pedagogy_post_material_types( get_the_ID(), $defs );
                $creator_meta = pedagogy_post_creator( get_the_ID(), $defs );
                $date_meta = pedagogy_post_date_meta( get_the_ID(), $defs );
                $embed_html = pedagogy_post_embed_html( get_the_ID(), $defs );
                ?>
                <article class="post-card">
                    <?php if ( $embed_html ) : ?>
                        <?php echo $embed_html; ?>
                    <?php endif; ?>
                    <a class="post-card-link" href="<?php the_permalink(); ?>">
                        <?php if ( ! $embed_html ) : ?>
                            <div class="post-card-image" style="background-image: url('<?php echo esc_url( $cover_url ? $cover_url : get_template_directory_uri() . '/assets/default-card.png' ); ?>');"></div>
                        <?php endif; ?>
                        <div class="post-card-body">
                            <h2 class="post-card-title"><?php the_title(); ?></h2>
                            <?php if ( $creator_meta || $date_meta ) : ?>
                                <div class="post-card-meta-row">
                                    <?php if ( $creator_meta ) : ?>
                                        <span class="post-card-meta-creator"><?php echo esc_html( $creator_meta ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $date_meta ) : ?>
                                        <span class="post-card-meta-date"><?php echo esc_html( $date_meta ); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $material_types ) ) : ?>
                                <div class="post-card-formats">
                                    <?php foreach ( $material_types as $material_type ) : ?>
                                        <span class="post-card-format"><?php echo esc_html( $material_type ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="post-card-formats post-card-formats-empty"><?php esc_html_e( 'No material type metadata available', 'twentytwentyfive' ); ?></div>
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
                'add_args' => array_filter( array(
                    'pcf_search'  => $search_term,
                    'pcf_license' => $license_filter,
                ), 'strlen' ),
            ) );
            ?>
        </div>
    <?php else : ?>
        <div class="post-cards-empty">
            <h2><?php esc_html_e( 'No posts matched your search', 'twentytwentyfive' ); ?></h2>
            <p><?php esc_html_e( 'Try another keyword or check the metadata values you are searching for.', 'twentytwentyfive' ); ?></p>
        </div>
    <?php endif; ?>
    </div>
    </div>
    </div>

    <?php wp_reset_postdata(); ?>
</div>

<?php get_footer();
