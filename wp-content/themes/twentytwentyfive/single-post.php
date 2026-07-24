<?php
/**
 * Single post template with Pedagogy custom field output.
 *
 * Place this file in your active theme to render custom fields inside the post body.
 */

get_header();

if ( function_exists( 'twentytwentyfive_render_inline_header' ) ) {
    twentytwentyfive_render_inline_header();
}
?>

<main id="site-content" role="main" class="wrapper">

    <?php
    $pcf_back_link = home_url( '/' );
    $pcf_cards_page = get_posts(
        array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'page-post-cards.php',
            'fields'         => 'ids',
        )
    );

    if ( ! empty( $pcf_cards_page ) ) {
        $pcf_back_link = get_permalink( $pcf_cards_page[0] );
    }
    ?>

  

    <?php
    while ( have_posts() ) :
        the_post();

        $post_id = get_the_ID();
        $pcf_meta_layout = 'bottom';

        if ( isset( $_GET['pcf_meta_layout'] ) ) {
            $requested_layout = sanitize_key( wp_unslash( $_GET['pcf_meta_layout'] ) );
            if ( in_array( $requested_layout, array( 'side', 'bottom' ), true ) ) {
                $pcf_meta_layout = $requested_layout;
            }
        }

        $pcf_meta_layout = apply_filters( 'pcf_single_meta_layout', $pcf_meta_layout, $post_id );
        if ( ! in_array( $pcf_meta_layout, array( 'side', 'bottom' ), true ) ) {
            $pcf_meta_layout = 'bottom';
        }
    ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class( 'pcf-post-shell pcf-meta-layout-' . $pcf_meta_layout ); ?>>

            <div class="pcf-post-layout">

            <div class="pcf-post-main">

            <header class="entry-header">
                <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>

                <?php
                // Small helpers for normalization/link formatting used only in this template
                if ( ! function_exists( 'pcf_normalize_value' ) ) {
                    function pcf_normalize_value( $val ) {
                        if ( is_array( $val ) ) {
                            $normalized = array();
                            foreach ( $val as $item ) {
                                if ( is_array( $item ) || is_object( $item ) ) {
                                    $normalized[] = is_scalar( $item ) ? (string) $item : wp_json_encode( $item );
                                } elseif ( is_bool( $item ) ) {
                                    $normalized[] = $item ? '1' : '0';
                                } elseif ( is_null( $item ) ) {
                                    $normalized[] = '';
                                } else {
                                    $normalized[] = (string) $item;
                                }
                            }
                            return implode( ', ', array_filter( $normalized, 'strlen' ) );
                        }
                        if ( is_object( $val ) ) {
                            return method_exists( $val, '__toString' ) ? (string) $val : wp_json_encode( $val );
                        }
                        return trim( (string) $val );
                    }
                }

                if ( ! function_exists( 'pcf_format_link_value' ) ) {
                    function pcf_format_link_value( $value ) {
                        if ( is_array( $value ) ) {
                            if ( ! empty( $value['url'] ) ) {
                                $link_text = '';
                                if ( ! empty( $value['title'] ) ) {
                                    $link_text = $value['title'];
                                } elseif ( ! empty( $value['text'] ) ) {
                                    $link_text = $value['text'];
                                } elseif ( ! empty( $value['label'] ) ) {
                                    $link_text = $value['label'];
                                } else {
                                    $link_text = $value['url'];
                                }
                                return '<a href="' . esc_url( $value['url'] ) . '">' . esc_html( $link_text ) . '</a>';
                            }
                            if ( ! empty( $value['href'] ) ) {
                                $link_text = '';
                                if ( ! empty( $value['title'] ) ) {
                                    $link_text = $value['title'];
                                } elseif ( ! empty( $value['text'] ) ) {
                                    $link_text = $value['text'];
                                } elseif ( ! empty( $value['label'] ) ) {
                                    $link_text = $value['label'];
                                } else {
                                    $link_text = $value['href'];
                                }
                                return '<a href="' . esc_url( $value['href'] ) . '">' . esc_html( $link_text ) . '</a>';
                            }
                            $links = array();
                            foreach ( $value as $item ) {
                                if ( is_array( $item ) && ! empty( $item['url'] ) ) {
                                    $link_text = ! empty( $item['title'] ) ? $item['title'] : ( ! empty( $item['text'] ) ? $item['text'] : ( ! empty( $item['label'] ) ? $item['label'] : $item['url'] ) );
                                    $links[] = '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $link_text ) . '</a>';
                                } elseif ( is_string( $item ) && filter_var( trim( $item ), FILTER_VALIDATE_URL ) ) {
                                    $links[] = '<a href="' . esc_url( trim( $item ) ) . '">' . esc_html( trim( $item ) ) . '</a>';
                                }
                            }
                            if ( ! empty( $links ) ) {
                                return implode( '<br/>', $links );
                            }
                            return esc_html( pcf_normalize_value( $value ) );
                        }
                        if ( is_string( $value ) ) {
                            $trimmed = trim( $value );
                            if ( filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
                                return '<a href="' . esc_url( $trimmed ) . '">' . esc_html( $trimmed ) . '</a>';
                            }
                            if ( false !== strpos( $trimmed, '|' ) ) {
                                list( $title, $url ) = array_map( 'trim', explode( '|', $trimmed, 2 ) );
                                if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
                                    return '<a href="' . esc_url( $url ) . '">' . esc_html( $title ?: $url ) . '</a>';
                                }
                            }
                            return esc_html( $trimmed );
                        }
                        return esc_html( pcf_normalize_value( $value ) );
                    }
                }

                ?>
            </header>

            <?php
            // --- Media embed: look for an 'embed' type definition or common keys and render centered iframe/oembed ---
            $embed_url = '';
            if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
                if ( is_array( $defs ) ) {
                    foreach ( $defs as $name => $def ) {
                        if ( isset( $def['type'] ) && $def['type'] === 'embed' ) {
                            $val = Pedagogy_CF_Starter::get_value( $post_id, $name );
                            if ( is_array( $val ) ) {
                                $val = reset( $val );
                            }
                            if ( is_string( $val ) && strlen( trim( $val ) ) ) {
                                $embed_url = trim( $val );
                                break;
                            }
                        }
                    }
                }
            }
            // also check a few common meta keys
            if ( ! $embed_url ) {
                $candidates = array( 'media_embed', 'embed', 'media', 'mediaEmbed', 'media-embed' );
                foreach ( $candidates as $k ) {
                    $v = '';
                    if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                        $v = Pedagogy_CF_Starter::get_value( $post_id, $k );
                    }
                    if ( empty( $v ) ) {
                        $v = get_post_meta( $post_id, 'pcf_' . $k, true );
                        if ( empty( $v ) ) {
                            $v = get_post_meta( $post_id, $k, true );
                        }
                    }
                    if ( is_array( $v ) ) {
                        $v = $v['href'] ?? $v['url'] ?? ( $v[0] ?? '' );
                    }
                    if ( is_string( $v ) && $v ) {
                        $embed_url = trim( $v );
                        break;
                    }
                }
            }

            $media_html = '';
            if ( $embed_url ) {
                $safe = esc_url_raw( $embed_url );
                $oembed_html = wp_oembed_get( $safe );
                $media_html .= '<div class="pcf-embed-wrap">';
                $media_html .= '<div class="pcf-embed-inner">';
                if ( $oembed_html ) {
                    $media_html .= $oembed_html;
                } else {
                    $media_html .= '<iframe src="' . esc_url( $safe ) . '" frameborder="0" allowfullscreen sandbox="allow-same-origin allow-scripts" class="pcf-embed-iframe"></iframe>';
                }
                $media_html .= '</div></div>';
            } elseif ( has_post_thumbnail( $post_id ) ) {
                $media_html .= '<div class="pcf-embed-wrap">';
                $media_html .= '<div class="pcf-embed-inner">';
                $media_html .= get_the_post_thumbnail( $post_id, 'large', array( 'class' => 'pcf-cover-image' ) );
                $media_html .= '</div></div>';
            }

            ?>

            <?php
            $description_html = '';
            if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                $description_raw = Pedagogy_CF_Starter::get_value( $post_id, 'description' );
                if ( is_array( $description_raw ) ) {
                    $description_raw = reset( $description_raw );
                }
                if ( is_string( $description_raw ) && trim( $description_raw ) !== '' ) {
                    $description_html = wp_kses_post( wpautop( $description_raw ) );
                }
            }
            if ( ! $description_html ) {
                $description_fallback = get_post_meta( $post_id, 'pcf_description', true );
                if ( is_array( $description_fallback ) ) {
                    $description_fallback = reset( $description_fallback );
                }
                if ( is_string( $description_fallback ) && trim( $description_fallback ) !== '' ) {
                    $description_html = wp_kses_post( wpautop( $description_fallback ) );
                }
            }

            $creator_display = '';
            $date_display = '';

            $creator_raw = '';
            $creator_field_candidates = array( 'people', 'creators', 'creator' );

            if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                foreach ( $creator_field_candidates as $creator_field ) {
                    $candidate_value = Pedagogy_CF_Starter::get_value( $post_id, $creator_field );
                    if ( '' !== $candidate_value && null !== $candidate_value ) {
                        $creator_raw = $candidate_value;
                        break;
                    }
                }

                if ( '' === $creator_raw || null === $creator_raw ) {
                    $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
                    if ( is_array( $defs ) ) {
                        foreach ( $defs as $field_name => $field_def ) {
                            $field_title = isset( $field_def['title'] ) ? strtolower( trim( $field_def['title'] ) ) : '';
                            if ( false === strpos( $field_title, 'creator' ) && false === strpos( $field_title, 'people' ) ) {
                                continue;
                            }

                            $candidate_value = Pedagogy_CF_Starter::get_value( $post_id, $field_name );
                            if ( '' !== $candidate_value && null !== $candidate_value ) {
                                $creator_raw = $candidate_value;
                                break;
                            }
                        }
                    }
                }
            }

            if ( '' === $creator_raw || null === $creator_raw ) {
                foreach ( $creator_field_candidates as $creator_field ) {
                    $candidate_value = get_post_meta( $post_id, 'pcf_' . $creator_field, true );
                    if ( '' === $candidate_value || null === $candidate_value ) {
                        $candidate_value = get_post_meta( $post_id, $creator_field, true );
                    }

                    if ( '' !== $candidate_value && null !== $candidate_value ) {
                        $creator_raw = $candidate_value;
                        break;
                    }
                }
            }

            $date_created_raw = class_exists( 'Pedagogy_CF_Starter' ) ? Pedagogy_CF_Starter::get_value( $post_id, 'date_created' ) : '';
            if ( '' === $date_created_raw || null === $date_created_raw ) {
                $date_created_raw = get_post_meta( $post_id, 'pcf_date_created', true );
                if ( '' === $date_created_raw || null === $date_created_raw ) {
                    $date_created_raw = get_post_meta( $post_id, 'date_created', true );
                }
            }

            $year_raw = class_exists( 'Pedagogy_CF_Starter' ) ? Pedagogy_CF_Starter::get_value( $post_id, 'year' ) : '';
            if ( '' === $year_raw || null === $year_raw ) {
                $year_raw = get_post_meta( $post_id, 'pcf_year', true );
                if ( '' === $year_raw || null === $year_raw ) {
                    $year_raw = get_post_meta( $post_id, 'year', true );
                }
            }

            $creator_display = pcf_normalize_value( $creator_raw );
            $date_created_display = pcf_normalize_value( $date_created_raw );
            $year_display = pcf_normalize_value( $year_raw );

            if ( '' !== $date_created_display ) {
                $timestamp = strtotime( $date_created_display );
                if ( false !== $timestamp ) {
                    $date_display = wp_date( 'F j, Y', $timestamp );
                } else {
                    $date_display = $date_created_display;
                }
            } elseif ( '' !== $year_display ) {
                $year_candidate = trim( $year_display );
                if ( preg_match( '/^\d{4}$/', $year_candidate ) ) {
                    $date_display = $year_candidate;
                } else {
                    $year_timestamp = strtotime( $year_candidate );
                    if ( false !== $year_timestamp ) {
                        $date_display = wp_date( 'F j, Y', $year_timestamp );
                    } else {
                        $date_display = $year_candidate;
                    }
                }
            }

            $post_meta_items = array();
            $top_meta_items = array();
            if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
                $skip = array( 'media_embed', 'embed', 'media', 'people', 'creator', 'creators', 'date_created', 'year', 'description' );
                if ( is_array( $defs ) && ! empty( $defs ) ) {
                    foreach ( $defs as $name => $def ) {
                        if ( in_array( $name, $skip, true ) ) {
                            continue;
                        }
                        $value = Pedagogy_CF_Starter::get_value( $post_id, $name );
                        if ( '' === $value || null === $value ) {
                            continue;
                        }
                        $label = isset( $def['title'] ) ? $def['title'] : ucwords( str_replace( array( '_', '-' ), ' ', $name ) );
                        $label_key = strtolower( trim( $label ) );
                        if ( in_array( $label_key, array( 'media embed', 'people', 'creator', 'creators', 'description', 'date created', 'year' ), true ) ) {
                            continue;
                        }
                        $is_link = ( isset( $def['type'] ) && in_array( $def['type'], array( 'linked', 'url', 'link' ), true ) );
                        $is_top_meta_item = in_array( $name, array( 'material_type', 'material_types', 'file_format', 'file_formats', 'format' ), true )
                            || false !== strpos( $label_key, 'material type' )
                            || false !== strpos( $label_key, 'file format' );

                        if ( $is_link ) {
                            $formatted_value = pcf_format_link_value( $value );
                        } elseif ( isset( $def['type'] ) && 'textarea' === $def['type'] ) {
                            $formatted_value = wp_kses_post( $value );
                        } else {
                            $formatted_value = esc_html( pcf_normalize_value( $value ) );
                        }

                        if ( $is_top_meta_item ) {
                            $top_meta_items[ $label ] = $formatted_value;
                        } else {
                            $post_meta_items[ $label ] = $formatted_value;
                        }
                    }
                }
            }

            $identity_meta_items = array();
            if ( '' !== $creator_display ) {
                $identity_meta_items['Creator'] = esc_html( $creator_display );
            }

            if ( '' !== $date_display ) {
                $identity_meta_items['Created'] = esc_html( $date_display );
            }

            if ( ! empty( $identity_meta_items ) ) {
                $top_meta_items = $identity_meta_items + $top_meta_items;
            }

            $has_entry_content = '' !== trim( (string) get_post_field( 'post_content', $post_id ) );
            $combined_meta_items = $top_meta_items + $post_meta_items;

            if ( $media_html || $description_html || ! empty( $top_meta_items ) || ! empty( $post_meta_items ) ) :
            ?>
                <section class="pcf-single-content-grid">
                    <?php if ( 'side' === $pcf_meta_layout && ! empty( $post_meta_items ) ) : ?>
                        <div class="pcf-single-column pcf-single-column-meta">
                            <aside class="pcf-metadata-card">
                                <div class="pcf-meta-list">
                                    <?php foreach ( $post_meta_items as $label => $val ) : ?>
                                        <div class="pcf-meta-item">
                                            <div class="pcf-meta-label"><?php echo esc_html( $label ); ?></div>
                                            <div class="pcf-meta-value"><?php echo $val; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </aside>
                        </div>
                    <?php endif; ?>

                    <div class="pcf-single-column pcf-single-column-main">
                        <div class="pcf-single-column pcf-single-column-media">
                            <?php echo $media_html; ?>
                        </div>

                        <div class="pcf-single-column pcf-single-column-description">
                          

                                <div class="pcf-description-wrap">
                                    <div class="pcf-description-label ">Description</div>
                                    <div class="pcf-description-inner">
                                        <?php echo $description_html; ?>
                                    </div>
                                </div>
                            <?php if ( $has_entry_content && ! empty( $top_meta_items ) ) : ?>
                                <aside class="pcf-metadata-card pcf-metadata-card-bottom pcf-metadata-card-inline">
                                    <div class="pcf-meta-list">
                                        <?php foreach ( $top_meta_items as $label => $val ) : ?>
                                            <div class="pcf-meta-item">
                                                <div class="pcf-meta-label"><?php echo esc_html( $label ); ?></div>
                                                <div class="pcf-meta-value"><?php echo $val; ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </aside>
                            <?php elseif ( ! $has_entry_content && ! empty( $combined_meta_items ) ) : ?>
                                <aside class="pcf-metadata-card pcf-metadata-card-bottom pcf-metadata-card-inline pcf-metadata-card-no-content">
                                  
                                    <div class="pcf-meta-list">
                                        <?php foreach ( $combined_meta_items as $label => $val ) : ?>
                                            <div class="pcf-meta-item">
                                                <div class="pcf-meta-label"><?php echo esc_html( $label ); ?></div>
                                                <div class="pcf-meta-value"><?php echo $val; ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </aside>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                 
            <?php endif; ?>

            <?php if ( $has_entry_content ) : ?>
                <div class="entry-content">
                    <?php add_filter( 'pedagogy_cf_disable_content_injection', '__return_true' ); ?>
                    <?php the_content(); ?>
                    <?php remove_filter( 'pedagogy_cf_disable_content_injection', '__return_true' ); ?>
                </div>
            <?php endif; ?>

            <?php if ( $has_entry_content && 'bottom' === $pcf_meta_layout && ! empty( $post_meta_items ) ) : ?>
                <aside class="pcf-metadata-card pcf-metadata-card-bottom">
                          <h3>More Info</h3>
                    <div class="pcf-meta-list">
                    
                        <?php foreach ( $post_meta_items as $label => $val ) : ?>
                            <div class="pcf-meta-item">
                                <div class="pcf-meta-label"><?php echo esc_html( $label ); ?></div>
                                <div class="pcf-meta-value"><?php echo $val; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>
            <?php endif; ?>

            </div>

            </div>

        </article>

    <?php endwhile; ?>

</main>

<?php get_footer();
