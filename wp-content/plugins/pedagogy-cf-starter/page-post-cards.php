<?php
/**
 * Template Name: Post Cards Search
 * Description: Page template for listing posts as cards with search over post metadata.
 */

get_header();

$search_term = sanitize_text_field( wp_unslash( $_GET['pcf_search'] ?? '' ) );
$paged = max( 1, get_query_var( 'paged', 1 ) );
$defs = array();
if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
    $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
    if ( ! is_array( $defs ) ) {
        $defs = array();
    }
}

$meta_keys = array();
$filter_definitions = array();
$filter_values = array();
foreach ( $defs as $name => $def ) {
    $meta_keys[] = 'pcf_' . $name;

    if ( 'people' === $name ) {
        continue;
    }

    if ( isset( $def['type'] ) && in_array( $def['type'], array( 'select', 'linked' ), true ) ) {
        $options = array();

        if ( 'select' === $def['type'] ) {
            $options = isset( $def['options'] ) && is_array( $def['options'] ) ? $def['options'] : array();
        } elseif ( 'linked' === $def['type'] && isset( $def['source_field'] ) ) {
            $source_name = $def['source_field'];
            if ( isset( $defs[ $source_name ] ) && isset( $defs[ $source_name ]['options'] ) && is_array( $defs[ $source_name ]['options'] ) ) {
                $options = $defs[ $source_name ]['options'];
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

$search_ids = null;
if ( $search_term !== '' ) {
    $search_ids = array();

    $search_query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        's'              => $search_term,
    ) );

    if ( $search_query->have_posts() ) {
        $search_ids = array_merge( $search_ids, $search_query->posts );
    }

    if ( ! empty( $meta_keys ) ) {
        $meta_query = array( 'relation' => 'OR' );
        foreach ( $meta_keys as $meta_key ) {
            $meta_query[] = array(
                'key'     => $meta_key,
                'value'   => $search_term,
                'compare' => 'LIKE',
            );
        }

        $meta_search_query = new WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ) );

        if ( $meta_search_query->have_posts() ) {
            $search_ids = array_unique( array_merge( $search_ids, $meta_search_query->posts ) );
        }
    }

    if ( empty( $search_ids ) ) {
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

$filter_meta_query = array( 'relation' => 'AND' );
$has_filters = false;
foreach ( $filter_definitions as $name => $filter ) {
    $values = $filter_values[ $name ] ?? array();
    $values = is_array( $values ) ? array_filter( $values ) : array( $values );
    if ( ! empty( $values ) ) {
        $has_filters = true;
        if ( count( $values ) > 1 ) {
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

function pedagogy_post_formats( $post_id, $defs ) {
    $format_names = array( 'file_format', 'file_formats', 'format', 'formats' );
    $formats = array();

    foreach ( $format_names as $field_name ) {
        if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
            $value = Pedagogy_CF_Starter::get_value( $post_id, $field_name );
            if ( $value ) {
                if ( is_array( $value ) ) {
                    $formats = array_merge( $formats, $value );
                } else {
                    $formats[] = $value;
                }
            }
        }
    }

    if ( empty( $formats ) ) {
        foreach ( $defs as $name => $def ) {
            if ( false !== stripos( $name, 'format' ) ) {
                if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                    $value = Pedagogy_CF_Starter::get_value( $post_id, $name );
                    if ( $value ) {
                        if ( is_array( $value ) ) {
                            $formats = array_merge( $formats, $value );
                        } else {
                            $formats[] = $value;
                        }
                    }
                }
            }
        }
    }

    $formats = array_filter( array_map( 'trim', $formats ) );
    return array_unique( $formats );
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
    <div class="post-cards-header">
        <div>
            <h1><?php the_title(); ?></h1>
            <p class="post-cards-intro">Search publications and metadata across all entries.</p>
        </div>
        <form class="post-cards-search" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
            <div class="post-cards-search-row">
                <label for="pcf_search" class="screen-reader-text"><?php esc_html_e( 'Search entries and metadata', 'twentytwentyfive' ); ?></label>
                <input id="pcf_search" name="pcf_search" type="search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="Search titles, descriptions, formats, tags...">
                <button type="submit"><?php esc_html_e( 'Search', 'twentytwentyfive' ); ?></button>
                <?php if ( $search_term !== '' || $has_filters ) : ?>
                    <a class="post-cards-clear" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Clear', 'twentytwentyfive' ); ?></a>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $filter_definitions ) ) : ?>
                <details class="post-cards-filter-drawer" <?php if ( $has_filters || $search_term !== '' ) : ?>open<?php endif; ?> >
                    <summary><?php esc_html_e( 'Filter by', 'twentytwentyfive' ); ?></summary>
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
                </details>
            <?php endif; ?>
        </form>
    </div>

    <?php if ( $search_term !== '' || $has_filters ) : ?>
        <div class="post-cards-summary">
            <p><?php echo sprintf( esc_html__( 'Showing %s results for selected search and filters.', 'twentytwentyfive' ), intval( $post_query->found_posts ) ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $post_query->have_posts() ) : ?>
        <div class="post-cards-grid">
            <?php while ( $post_query->have_posts() ) : $post_query->the_post(); ?>
                <?php
                $cover_url = pedagogy_post_cover_image_url( get_the_ID() );
                $formats = pedagogy_post_formats( get_the_ID(), $defs );
                ?>
                <article class="post-card">
                    <a class="post-card-link" href="<?php the_permalink(); ?>">
                        <div class="post-card-image" style="background-image: url('<?php echo esc_url( $cover_url ? $cover_url : get_template_directory_uri() . '/assets/default-card.png' ); ?>');"></div>
                        <div class="post-card-body">
                            <h2 class="post-card-title"><?php the_title(); ?></h2>
                            <?php if ( ! empty( $formats ) ) : ?>
                                <div class="post-card-formats">
                                    <?php foreach ( $formats as $format ) : ?>
                                        <span class="post-card-format"><?php echo esc_html( $format ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="post-card-formats post-card-formats-empty"><?php esc_html_e( 'No format metadata available', 'twentytwentyfive' ); ?></div>
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

    <?php wp_reset_postdata(); ?>
</div>

<?php get_footer();
