<?php
/**
 * Single post template with Pedagogy custom field output.
 *
 * Place this file in your active theme to render custom fields inside the post body.
 */

get_header();
?>

<main id="site-content" role="main" class="wrapper">

    <?php
    while ( have_posts() ) :
        the_post();
    ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class( 'pcf-post-shell' ); ?>>

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

                // Collect creators and date_created (display beneath title)
                $post_id = get_the_ID();
                $creators = '';
                $date_created = '';
                if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                    $creators = Pedagogy_CF_Starter::get_value( $post_id, 'creators' );
                    $date_created = Pedagogy_CF_Starter::get_value( $post_id, 'date_created' );
                }
                if ( empty( $creators ) ) {
                    $creators = get_post_meta( $post_id, 'pcf_creators', true );
                    if ( empty( $creators ) ) {
                        $creators = get_post_meta( $post_id, 'creators', true );
                    }
                }
                if ( empty( $date_created ) ) {
                    $date_created = get_post_meta( $post_id, 'pcf_date_created', true );
                    if ( empty( $date_created ) ) {
                        $date_created = get_post_meta( $post_id, 'date_created', true );
                    }
                }
                if ( is_array( $creators ) ) {
                    $creators = implode( ', ', array_map( 'strval', $creators ) );
                }
                $creators = trim( (string) $creators );
                if ( $date_created ) {
                    $date_display = date_i18n( get_option( 'date_format' ), strtotime( $date_created ) );
                } else {
                    $date_display = get_the_date();
                }
                if ( $creators || $date_display ) : ?>
                    <div class="entry-title-meta">
                        <?php if ( $creators ) : ?>
                            <span class="entry-creators">Created by: <?php echo esc_html( $creators ); ?></span>
                        <?php endif; ?>
                        <?php if ( $date_display ) : ?>
                            <span class="entry-date-created">& published on <?php echo esc_html( $date_display ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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

            if ( $embed_url ) {
                $safe = esc_url_raw( $embed_url );
                $oembed_html = wp_oembed_get( $safe );
                echo '<div class="pcf-embed-wrap">';
                echo '<div class="pcf-embed-inner">';
                if ( $oembed_html ) {
                    echo $oembed_html;
                } else {
                    echo '<iframe src="' . esc_url( $safe ) . '" frameborder="0" allowfullscreen sandbox="allow-same-origin allow-scripts" class="pcf-embed-iframe"></iframe>';
                }
                echo '</div></div>';
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
            ?>

            <div class="pcf-description-wrap<?php echo $description_html ? '' : ' is-empty'; ?>">
                <?php if ( $description_html ) : ?>
                    <div class="pcf-description-inner">
                        <?php echo $description_html; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="entry-content">
                <?php add_filter( 'pedagogy_cf_disable_content_injection', '__return_true' ); ?>
                <?php the_content(); ?>
                <?php remove_filter( 'pedagogy_cf_disable_content_injection', '__return_true' ); ?>
            </div>

            <?php
            // Restore metadata output beneath the main content.
            if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                $post_meta_items = array();
                $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
                $skip = array( 'media_embed', 'embed', 'media', 'people', 'creators', 'date_created', 'description' );
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
                        if ( in_array( $label_key, array( 'media embed', 'people', 'creators', 'date created', 'description' ), true ) ) {
                            continue;
                        }
                        $is_link = ( isset( $def['type'] ) && in_array( $def['type'], array( 'linked', 'url', 'link' ), true ) );
                        if ( $is_link ) {
                            $post_meta_items[ $label ] = pcf_format_link_value( $value );
                        } elseif ( isset( $def['type'] ) && 'textarea' === $def['type'] ) {
                            $post_meta_items[ $label ] = wp_kses_post( $value );
                        } else {
                            $post_meta_items[ $label ] = esc_html( pcf_normalize_value( $value ) );
                        }
                    }
                }

                if ( ! empty( $post_meta_items ) ) {
                    echo '<aside class="pcf-metadata-card">';
                    echo '<div class="pcf-meta-list">';
                    foreach ( $post_meta_items as $label => $val ) {
                        echo '<div class="pcf-meta-item">';
                        echo '<div class="pcf-meta-label">' . esc_html( $label ) . '</div>';
                        echo '<div class="pcf-meta-value">' . $val . '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</aside>';
                }
            }
            ?>

            </div>

            </div>

        </article>

    <?php endwhile; ?>

</main>

<?php get_footer();
