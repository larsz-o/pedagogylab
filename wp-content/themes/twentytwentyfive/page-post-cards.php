<?php
/**
 * Template Name: Post Cards Search
 * Template Post Type: page
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
foreach ( $defs as $name => $def ) {
    $meta_keys[] = 'pcf_' . $name;
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
            return Pedagogy_CF_Starter::normalize_display_value( $value );
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
    <div class="post-cards-header">
        <div>
            <h1><?php the_title(); ?></h1>
            <p class="post-cards-intro">Search publications and metadata across all posts.</p>
        </div>
        <form class="post-cards-search" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
            <label for="pcf_search" class="screen-reader-text"><?php esc_html_e( 'Search posts and metadata', 'twentytwentyfive' ); ?></label>
            <input id="pcf_search" name="pcf_search" type="search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="Search titles, descriptions, formats, tags...">
            <button type="submit"><?php esc_html_e( 'Search', 'twentytwentyfive' ); ?></button>
        </form>
    </div>

    <?php if ( $post_query->have_posts() ) : ?>
        <div class="post-cards-grid">
            <?php while ( $post_query->have_posts() ) : $post_query->the_post(); ?>
                <?php
                $cover_url = pedagogy_post_cover_image_url( get_the_ID() );
                $formats = pedagogy_post_formats( get_the_ID(), $defs );
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
            <p><?php esc_html_e( 'Try another keyword or check the metadata values you are searching for.', 'twentytwentyfive' ); ?></p>
        </div>
    <?php endif; ?>

    <?php wp_reset_postdata(); ?>
</div>

<?php get_footer();
