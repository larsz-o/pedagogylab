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

$pcf_news_template_mode = defined( 'PCF_NEWS_TEMPLATE_MODE' ) && PCF_NEWS_TEMPLATE_MODE;

if ( ! $pcf_news_template_mode && is_singular( 'post' ) ) {
    $pcf_template_slug = get_page_template_slug( get_queried_object_id() );
    $pcf_news_template_mode = in_array( $pcf_template_slug, array( 'news-template.php', 'news-template' ), true );
}
?>

<main id="site-content" role="main" class="wrapper">

    <?php
    $pcf_oer_library_link = trailingslashit( home_url( '/oer-library/' ) );
    ?>

  

    <?php
    while ( have_posts() ) :
        the_post();

        $post_id = get_the_ID();
        $pcf_meta_layout = 'bottom';

        $pcf_callout_normalize = static function ( $value ) {
            if ( is_array( $value ) ) {
                $flattened = array();
                foreach ( $value as $item ) {
                    if ( is_scalar( $item ) ) {
                        $flattened[] = trim( (string) $item );
                    }
                }
                return implode( ', ', array_values( array_filter( $flattened, 'strlen' ) ) );
            }

            if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
                return trim( (string) $value );
            }

            if ( is_bool( $value ) ) {
                return $value ? 'Yes' : 'No';
            }

            if ( null === $value ) {
                return '';
            }

            return trim( (string) $value );
        };

        $pcf_callout_get_meta_value = static function ( $post_id, $field_names ) use ( $pcf_callout_normalize ) {
            foreach ( $field_names as $field_name ) {
                $value = '';

                if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                    $value = Pedagogy_CF_Starter::get_value( $post_id, $field_name );
                }

                if ( '' === $value || null === $value ) {
                    $value = get_post_meta( $post_id, 'pcf_' . $field_name, true );
                }

                if ( '' === $value || null === $value ) {
                    $value = get_post_meta( $post_id, $field_name, true );
                }

                $normalized = $pcf_callout_normalize( $value );
                if ( '' !== $normalized ) {
                    return $normalized;
                }
            }

            return '';
        };

        $pcf_callout_split_list = static function ( $value ) {
            if ( is_array( $value ) ) {
                $items = array();
                foreach ( $value as $item ) {
                    if ( is_scalar( $item ) ) {
                        $items[] = trim( (string) $item );
                    }
                }
                return array_values( array_filter( $items, 'strlen' ) );
            }

            $raw = trim( (string) $value );
            if ( '' === $raw ) {
                return array();
            }

            $parts = preg_split( '/\s*,\s*/', $raw );
            if ( ! is_array( $parts ) ) {
                return array( $raw );
            }

            return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
        };

        $pcf_callout_join_with_and = static function ( $items ) {
            $items = array_values( array_filter( array_map( 'trim', (array) $items ), 'strlen' ) );
            $count = count( $items );

            if ( 0 === $count ) {
                return '';
            }

            if ( 1 === $count ) {
                return $items[0];
            }

            if ( 2 === $count ) {
                return $items[0] . ' and ' . $items[1];
            }

            $last_item = array_pop( $items );
            return implode( ', ', $items ) . ', and ' . $last_item;
        };

        $pcf_intended_use = $pcf_callout_get_meta_value( $post_id, array( 'intended_use' ) );
        $pcf_material_type = $pcf_callout_get_meta_value( $post_id, array( 'material_type' ) );
        $pcf_audience_types = $pcf_callout_get_meta_value( $post_id, array( 'audience_types' ) );
        $pcf_teaching_note_raw = '';
        if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
            $pcf_teaching_note_raw = Pedagogy_CF_Starter::get_value( $post_id, 'teaching_note' );
        }
        if ( is_array( $pcf_teaching_note_raw ) ) {
            $pcf_teaching_note_raw = reset( $pcf_teaching_note_raw );
        }
        if ( '' === $pcf_teaching_note_raw || null === $pcf_teaching_note_raw ) {
            $pcf_teaching_note_raw = get_post_meta( $post_id, 'pcf_teaching_note', true );
        }
        if ( is_array( $pcf_teaching_note_raw ) ) {
            $pcf_teaching_note_raw = reset( $pcf_teaching_note_raw );
        }
        if ( '' === $pcf_teaching_note_raw || null === $pcf_teaching_note_raw ) {
            $pcf_teaching_note_raw = get_post_meta( $post_id, 'teaching_note', true );
        }
        if ( is_array( $pcf_teaching_note_raw ) ) {
            $pcf_teaching_note_raw = reset( $pcf_teaching_note_raw );
        }

        $pcf_teaching_note_html = '';
        if ( is_string( $pcf_teaching_note_raw ) && '' !== trim( $pcf_teaching_note_raw ) ) {
            $pcf_teaching_note_html = wp_kses_post( wpautop( $pcf_teaching_note_raw ) );
        }

        $pcf_resource_components = array_merge(
            $pcf_callout_split_list( $pcf_intended_use ),
            $pcf_callout_split_list( $pcf_material_type )
        );
        $pcf_resource_components = array_values( array_unique( array_filter( $pcf_resource_components, 'strlen' ) ) );

        $pcf_resource_summary = ! empty( $pcf_resource_components ) ? $pcf_callout_join_with_and( $pcf_resource_components ) : 'Not specified';
        $pcf_audience_list = $pcf_callout_split_list( $pcf_audience_types );
        $pcf_audience_summary = ! empty( $pcf_audience_list ) ? $pcf_callout_join_with_and( $pcf_audience_list ) : 'Not specified';

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

                if ( ! function_exists( 'pcf_value_to_list' ) ) {
                    function pcf_value_to_list( $val ) {
                        $items = array();

                        if ( is_array( $val ) ) {
                            foreach ( $val as $item ) {
                                if ( is_scalar( $item ) ) {
                                    $items[] = trim( (string) $item );
                                }
                            }
                        } else {
                            $raw = trim( (string) $val );
                            if ( '' === $raw ) {
                                return array();
                            }
                            $split = preg_split( '/\s*,\s*/', $raw );
                            if ( is_array( $split ) ) {
                                $items = $split;
                            } else {
                                $items = array( $raw );
                            }
                        }

                        $items = array_values( array_filter( array_map( 'trim', $items ), 'strlen' ) );
                        return $items;
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

                if ( ! function_exists( 'pcf_has_display_value' ) ) {
                    function pcf_has_display_value( $value ) {
                        if ( is_array( $value ) ) {
                            foreach ( $value as $item ) {
                                if ( pcf_has_display_value( $item ) ) {
                                    return true;
                                }
                            }
                            return false;
                        }

                        if ( is_object( $value ) ) {
                            if ( method_exists( $value, '__toString' ) ) {
                                return '' !== trim( (string) $value );
                            }
                            return false;
                        }

                        if ( is_bool( $value ) ) {
                            return $value;
                        }

                        if ( null === $value ) {
                            return false;
                        }

                        return '' !== trim( (string) $value );
                    }
                }

                if ( ! function_exists( 'pcf_format_search_links' ) ) {
                    function pcf_format_search_links( $value, $base_url, $query_arg = 'pcf_search' ) {
                        if ( ! is_string( $base_url ) || '' === trim( $base_url ) ) {
                            return esc_html( pcf_normalize_value( $value ) );
                        }

                        $items = array();

                        if ( is_array( $value ) ) {
                            foreach ( $value as $item ) {
                                if ( is_scalar( $item ) ) {
                                    $items[] = trim( (string) $item );
                                } elseif ( is_object( $item ) && isset( $item->name ) ) {
                                    $items[] = trim( (string) $item->name );
                                } elseif ( is_array( $item ) ) {
                                    $candidate_keys = array( 'name', 'label', 'title', 'text', 'value' );
                                    foreach ( $candidate_keys as $candidate_key ) {
                                        if ( isset( $item[ $candidate_key ] ) && is_scalar( $item[ $candidate_key ] ) ) {
                                            $items[] = trim( (string) $item[ $candidate_key ] );
                                            break;
                                        }
                                    }
                                }
                            }
                        } elseif ( is_object( $value ) && isset( $value->name ) ) {
                            $items[] = trim( (string) $value->name );
                        } else {
                            $items = pcf_value_to_list( $value );
                        }

                        $items = array_values( array_unique( array_filter( array_map( 'trim', $items ), 'strlen' ) ) );
                        if ( empty( $items ) ) {
                            return esc_html( pcf_normalize_value( $value ) );
                        }

                        $links = array();
                        foreach ( $items as $item ) {
                            $item = trim( (string) $item );
                            if ( '' === $item ) {
                                continue;
                            }

                            $search_url = add_query_arg( $query_arg, $item, $base_url ) . '#post-cards-results';
                            $links[] = '<a href="' . esc_url( $search_url ) . '">' . esc_html( $item ) . '</a>';
                        }

                        if ( empty( $links ) ) {
                            return esc_html( pcf_normalize_value( $value ) );
                        }

                        return implode( ', ', $links );
                    }
                }

                if ( ! function_exists( 'pcf_add_iframe_fragment_to_content' ) ) {
                    function pcf_add_iframe_fragment_to_content( $content ) {
                        if ( ! is_string( $content ) || '' === trim( $content ) ) {
                            return $content;
                        }

                        $fragment = 'page=5&zoom=200&toolbar=1';

                        return preg_replace_callback(
                            '/<iframe\\b[^>]*\\bsrc=(["\'])([^"\']+)\\1[^>]*>/i',
                            static function ( $matches ) use ( $fragment ) {
                                if ( empty( $matches[2] ) ) {
                                    return $matches[0];
                                }

                                $quote = $matches[1];
                                $src = html_entity_decode( trim( (string) $matches[2] ), ENT_QUOTES, 'UTF-8' );
                                if ( '' === $src ) {
                                    return $matches[0];
                                }

                                if ( false !== strpos( $src, '#' ) ) {
                                    list( $base, $existing_fragment ) = explode( '#', $src, 2 );
                                    if ( '' === trim( $existing_fragment ) ) {
                                        $updated_src = $base . '#' . $fragment;
                                    } else {
                                        $updated_src = $base . '#' . rtrim( $existing_fragment, '&' ) . '&' . $fragment;
                                    }
                                } else {
                                    $updated_src = $src . '#' . $fragment;
                                }

                                $replacement = $quote . esc_url( $updated_src ) . $quote;
                                return str_replace( $quote . $matches[2] . $quote, $replacement, $matches[0] );
                            },
                            $content
                        );
                    }
                }

                if ( ! function_exists( 'pcf_add_h2_anchors_and_toc' ) ) {
                    function pcf_add_h2_anchors_and_toc( $content ) {
                        $result = array(
                            'content' => $content,
                            'toc'     => '',
                        );

                        if ( ! is_string( $content ) || '' === trim( $content ) ) {
                            return $result;
                        }

                        $headings = array();
                        $used_ids = array();

                        $processed_content = preg_replace_callback(
                            '/<h2\b([^>]*)>(.*?)<\/h2>/is',
                            static function ( $matches ) use ( &$headings, &$used_ids ) {
                                $attrs = isset( $matches[1] ) ? $matches[1] : '';
                                $inner_html = isset( $matches[2] ) ? $matches[2] : '';

                                $heading_text = trim( wp_strip_all_tags( html_entity_decode( $inner_html, ENT_QUOTES, 'UTF-8' ) ) );
                                if ( '' === $heading_text ) {
                                    return $matches[0];
                                }

                                $base_id = sanitize_title( $heading_text );
                                if ( '' === $base_id ) {
                                    $base_id = 'section';
                                }

                                $anchor_id = $base_id;
                                $counter = 2;
                                while ( isset( $used_ids[ $anchor_id ] ) ) {
                                    $anchor_id = $base_id . '-' . $counter;
                                    $counter++;
                                }
                                $used_ids[ $anchor_id ] = true;

                                $headings[] = array(
                                    'id'    => $anchor_id,
                                    'label' => $heading_text,
                                );

                                // Add an explicit anchor element and mirror the id on h2 for robust browser targeting.
                                $anchor_html = '<a id="' . esc_attr( $anchor_id ) . '" class="pcf-h2-anchor" aria-hidden="true"></a>';

                                if ( preg_match( '/\bid\s*=\s*(["\']).*?\1/i', $attrs ) ) {
                                    $attrs = preg_replace( '/\bid\s*=\s*(["\']).*?\1/i', ' id="' . esc_attr( $anchor_id ) . '"', $attrs, 1 );
                                } else {
                                    $attrs .= ' id="' . esc_attr( $anchor_id ) . '"';
                                }

                                return $anchor_html . '<h2' . $attrs . '>' . $inner_html . '</h2>';
                            },
                            $content
                        );

                        if ( null === $processed_content ) {
                            return $result;
                        }

                        $result['content'] = $processed_content;

                        if ( empty( $headings ) ) {
                            return $result;
                        }

                        $toc_items_html = '';
                        foreach ( $headings as $heading ) {
                            $toc_items_html .= '<li><a href="#' . esc_attr( $heading['id'] ) . '">' . esc_html( $heading['label'] ) . '</a></li>';
                        }

                        $result['toc'] = '<nav id="toc" class="pcf-content-toc" aria-label="' . esc_attr__( 'Table of contents', 'twentytwentyfive' ) . '">'
                            . '<div class="pcf-content-toc-title">' . esc_html__( 'Contents', 'twentytwentyfive' ) . '</div>'
                            . '<ul class="pcf-content-toc-list" style="display:flex; flex-wrap:wrap; gap:0.75rem 1rem; list-style:none; padding:0; margin:0 0 1.25rem;">' . $toc_items_html . '</ul>'
                            . '</nav>';

                        return $result;
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
            } elseif ( ! $pcf_news_template_mode && has_post_thumbnail( $post_id ) ) {
                $media_html .= '<div class="pcf-embed-wrap">';
                $media_html .= '<div class="pcf-embed-inner">';
                $media_html .= get_the_post_thumbnail( $post_id, 'large', array( 'class' => 'pcf-cover-image' ) );
                $media_html .= '</div></div>';
            }

            ?>

            <?php
           

            $creator_display = '';
            $date_display = '';

            $creator_raw_values = array();
            $creator_field_candidates = array( 'people', 'creators', 'creator', 'contributors', 'contributor' );

            if ( class_exists( 'Pedagogy_CF_Starter' ) ) {
                foreach ( $creator_field_candidates as $creator_field ) {
                    $candidate_value = Pedagogy_CF_Starter::get_value( $post_id, $creator_field );
                    if ( '' !== $candidate_value && null !== $candidate_value ) {
                        $creator_raw_values = array_merge( $creator_raw_values, pcf_value_to_list( $candidate_value ) );
                    }
                }

                if ( empty( $creator_raw_values ) ) {
                    $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
                    if ( is_array( $defs ) ) {
                        foreach ( $defs as $field_name => $field_def ) {
                            $field_title = isset( $field_def['title'] ) ? strtolower( trim( $field_def['title'] ) ) : '';
                            if ( false === strpos( $field_title, 'creator' ) && false === strpos( $field_title, 'people' ) && false === strpos( $field_title, 'contributor' ) ) {
                                continue;
                            }

                            $candidate_value = Pedagogy_CF_Starter::get_value( $post_id, $field_name );
                            if ( '' !== $candidate_value && null !== $candidate_value ) {
                                $creator_raw_values = array_merge( $creator_raw_values, pcf_value_to_list( $candidate_value ) );
                            }
                        }
                    }
                }
            }

            if ( empty( $creator_raw_values ) ) {
                foreach ( $creator_field_candidates as $creator_field ) {
                    $candidate_value = get_post_meta( $post_id, 'pcf_' . $creator_field, true );
                    if ( '' === $candidate_value || null === $candidate_value ) {
                        $candidate_value = get_post_meta( $post_id, $creator_field, true );
                    }

                    if ( '' !== $candidate_value && null !== $candidate_value ) {
                        $creator_raw_values = array_merge( $creator_raw_values, pcf_value_to_list( $candidate_value ) );
                    }
                }
            }

            $creator_raw_values = array_values( array_unique( array_filter( $creator_raw_values, 'strlen' ) ) );
            $creator_raw = implode( ', ', $creator_raw_values );

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
                $skip = array( 'media_embed', 'embed', 'media', 'people', 'creator', 'creators', 'contributor', 'contributors', 'date_created', 'year', 'description', 'teaching_note', 'file_format', 'file_formats' );
                if ( is_array( $defs ) && ! empty( $defs ) ) {
                    foreach ( $defs as $name => $def ) {
                        if ( in_array( $name, $skip, true ) ) {
                            continue;
                        }
                        $value = Pedagogy_CF_Starter::get_value( $post_id, $name );
                        if ( ! pcf_has_display_value( $value ) ) {
                            continue;
                        }
                        $label = isset( $def['title'] ) ? $def['title'] : ucwords( str_replace( array( '_', '-' ), ' ', $name ) );
                        $label_key = strtolower( trim( $label ) );
                        $name_key = strtolower( trim( str_replace( '-', '_', (string) $name ) ) );
                        if ( in_array( $name_key, array( 'file_format', 'file_formats' ), true ) || false !== strpos( $name_key, 'file_format' ) || false !== strpos( $label_key, 'file format' ) ) {
                            continue;
                        }
                        if ( in_array( $label_key, array( 'media embed', 'people', 'creator', 'creators', 'contributor', 'contributors', 'description', 'date created', 'year', 'teaching note' ), true ) ) {
                            continue;
                        }
                        $is_link = ( isset( $def['type'] ) && in_array( $def['type'], array( 'linked', 'url', 'link' ), true ) );
                        $is_top_meta_item = in_array( $name, array( 'material_type' ), true )
                            || false !== strpos( $label_key, 'material type' )
                            ;
                        if ( $is_link ) {
                            $formatted_value = pcf_format_link_value( $value );
                        } elseif ( isset( $def['type'] ) && 'textarea' === $def['type'] ) {
                            $formatted_value = wp_kses_post( $value );
                        } else {
                            $formatted_value = pcf_format_search_links( $value, $pcf_oer_library_link );
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
                $identity_meta_items['Creator(s)'] = esc_html( $creator_display );
            }

            if ( '' !== $date_display ) {
                $identity_meta_items['Created'] = esc_html( $date_display );
            }

            if ( ! empty( $identity_meta_items ) ) {
                $top_meta_items = $identity_meta_items + $top_meta_items;
            }

            $has_custom_tag_meta = false;
            foreach ( array_keys( $combined_meta_items = $top_meta_items + $post_meta_items ) as $existing_meta_label ) {
                $existing_meta_label_key = strtolower( trim( (string) $existing_meta_label ) );
                if ( false !== strpos( $existing_meta_label_key, 'tag' ) || false !== strpos( $existing_meta_label_key, 'keyword' ) ) {
                    $has_custom_tag_meta = true;
                    break;
                }
            }

            if ( ! $has_custom_tag_meta ) {
                $post_tags = get_the_terms( $post_id, 'post_tag' );
                if ( ! is_wp_error( $post_tags ) && ! empty( $post_tags ) ) {
                    $tag_values = array();
                    foreach ( $post_tags as $post_tag ) {
                        if ( isset( $post_tag->name ) ) {
                            $tag_values[] = $post_tag->name;
                        }
                    }

                    if ( ! empty( $tag_values ) ) {
                        $post_meta_items['Tags'] = pcf_format_search_links( $tag_values, $pcf_oer_library_link );
                    }
                }
            }
            $description_html = '';
            if ( ! $pcf_news_template_mode ) {
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
            }

            $has_entry_content = '' !== trim( (string) get_post_field( 'post_content', $post_id ) );
            $combined_meta_items = $top_meta_items + $post_meta_items;
            $has_main_content = $media_html || $description_html || $has_entry_content;
            ?>
            
            <?php if ( ! empty( $combined_meta_items ) || $has_main_content ) : ?>
                <section class="pcf-single-content-grid">

                    <div class="pcf-single-column pcf-single-column-content">
                        <?php if ( $media_html || $description_html ) : ?>
                            <div class="pcf-single-column pcf-single-column-main">
                                <div class="pcf-single-column pcf-single-column-media">
                                    <?php echo $media_html; ?>
                                </div>

                             
                                    <div class="pcf-description-wrap">
                                           <div class="pcf-single-column pcf-single-column-description">
                                       <div class="author-meta">
                                        <?php if ( '' !== $creator_display ) : ?>
                                            <div class="pcf-description-label"><?php esc_html_e( 'Creator(s)', 'twentytwentyfive' ); ?></div>
                                            <div class="pcf-description-value"><?php echo esc_html( $creator_display ); ?></div>
                                        <?php endif; ?>
                                    </div>
                                        <div class="pcf-description-label ">Description</div>
                                        <div class="pcf-description-inner">
                                            <?php echo $description_html; ?>
                                        </div>
                                    </div>
                                 
                                    <?php if ( ! $pcf_news_template_mode ) : ?>
                                        <section class="pcf-resource-callout entry-item-highlight" aria-label="How to use this resource">
                                            <h2 class="pcf-description-label">How to use this resource</h2>
                                            <p class="pcf-resource-callout-body">
                                                This resource is or includes: <?php echo esc_html( $pcf_resource_summary ); ?>. It's intended for the following audiences and educational settings: <?php echo esc_html( $pcf_audience_summary ); ?>.
                                            </p>
                                        </section>
                                    <?php endif; ?>

                                    <?php if ( '' !== $pcf_teaching_note_html ) : ?>
                                        <section class="entry-item-highlight" aria-label="Teaching note">
                                            <h2 class="pcf-description-label">Teaching Note</h2>
                                            <div class="pcf-resource-callout-body"><?php echo $pcf_teaching_note_html; ?></div>
                                        </section>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ( $has_entry_content ) : ?>
                            <div class="entry-content">
                                <?php add_filter( 'pedagogy_cf_disable_content_injection', '__return_true' ); ?>
                                <?php $entry_content_html = apply_filters( 'the_content', get_the_content() ); ?>
                                <?php remove_filter( 'pedagogy_cf_disable_content_injection', '__return_true' ); ?>
                                <?php $entry_content_with_iframe_params = pcf_add_iframe_fragment_to_content( $entry_content_html ); ?>
                                <?php if ( $pcf_news_template_mode ) : ?>
                                    <?php echo $entry_content_with_iframe_params; ?>
                                <?php else : ?>
                                    <?php $entry_content_with_toc = pcf_add_h2_anchors_and_toc( $entry_content_with_iframe_params ); ?>
                                    <?php if ( ! empty( $entry_content_with_toc['toc'] ) ) : ?>
                                        <?php echo $entry_content_with_toc['toc']; ?>
                                    <?php endif; ?>
                                    <?php echo $entry_content_with_toc['content']; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $combined_meta_items ) ) : ?>
                        <div class="row-center">
                            <img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/planet.png' ) ); ?>" alt="" class="pcf-meta-divider-image spin" />
                        </div>
                        <aside class="pcf-single-column pcf-single-column-meta pcf-single-column-meta-sticky">
                            <div class="pcf-metadata-card pcf-metadata-card-sidebar">
                                <div class="pcf-meta-list">
                                    <?php foreach ( $combined_meta_items as $label => $val ) : ?>
                                        <div class="pcf-meta-item">
                                            <div class="pcf-meta-label"><?php echo esc_html( $label ); ?></div>
                                            <div class="pcf-meta-value"><?php echo $val; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </aside>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            </div>

            </div>

        </article>

    <?php endwhile; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var darkBlocks = document.querySelectorAll('.entry-item-dark');

        darkBlocks.forEach(function (block, index) {
            if (block.classList.contains('is-accordion-ready')) {
                return;
            }

            var heading = block.querySelector('h1, h2, h3, h4, h5, h6');
            var titleText = heading ? heading.textContent.trim() : '';
            if (!titleText) {
                titleText = block.getAttribute('data-title') || 'Details';
            }

            if (heading) {
                heading.remove();
            }

            var contentNodes = Array.from(block.childNodes);
            var panelId = 'entry-item-dark-panel-' + index;

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'entry-item-dark-toggle';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-controls', panelId);

            var toggleLabel = document.createElement('span');
            toggleLabel.className = 'entry-item-dark-toggle-label';
            toggleLabel.textContent = titleText;

            var toggleIcon = document.createElement('span');
            toggleIcon.className = 'entry-item-dark-toggle-icon';
            toggleIcon.setAttribute('aria-hidden', 'true');
            toggleIcon.textContent = '+';

            toggle.appendChild(toggleLabel);
            toggle.appendChild(toggleIcon);

            var panel = document.createElement('div');
            panel.id = panelId;
            panel.className = 'entry-item-dark-panel';
            panel.hidden = true;

            block.textContent = '';
            block.appendChild(toggle);
            block.appendChild(panel);

            contentNodes.forEach(function (node) {
                panel.appendChild(node);
            });

            block.classList.add('is-accordion-ready');

            toggle.addEventListener('click', function () {
                var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                panel.hidden = isExpanded;
                toggleIcon.textContent = isExpanded ? '+' : '-';
                block.classList.toggle('is-open', !isExpanded);
            });
        });
    });
    </script>

</main>

<?php get_footer();
