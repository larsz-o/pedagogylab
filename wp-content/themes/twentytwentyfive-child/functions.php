<?php
/**
 * Twenty Twenty-Five Child Theme functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package twentytwentyfive-child
 */

add_action( 'wp_enqueue_scripts', 'twentytwentyfive_child_parent_theme_enqueue_styles' );

/**
 * Enqueue scripts and styles.
 */
function twentytwentyfive_child_parent_theme_enqueue_styles() {
	wp_enqueue_style(
		'twentytwentyfive-child-fonts',
		'https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700;800&family=Lora:wght@400;500;700;800&family=Noto+Sans:wght@400;500;700;800&family=Noto+Serif:wght@400;500;700;800&family=Noto+Sans+Symbols+2&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'twentytwentyfive-style', get_template_directory_uri() . '/style.css', array(), '0.1.0' );
	wp_enqueue_style(
		'twentytwentyfive-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'twentytwentyfive-style', 'twentytwentyfive-child-fonts' ),
		'0.1.0'
	);
}

/**
 * Remove stray XML declaration text injected by DOMDocument during block rendering.
 *
 * @param string $block_content Rendered block HTML.
 * @return string
 */
function twentytwentyfive_child_strip_xml_declaration( $block_content ) {
	if ( ! is_string( $block_content ) || '' === $block_content ) {
		return $block_content;
	}

	return preg_replace( '/^\s*<\?xml[^>]*>\s*/i', '', $block_content );
}
add_filter( 'render_block', 'twentytwentyfive_child_strip_xml_declaration', 99 );

/**
 * Filter Latest Posts block on News & Events page by tag query parameter.
 *
 * Uses `?pcf_tag=tag-slug` and preserves existing category constraints in the block.
 *
 * @param array $query_args Latest Posts block query args.
 * @return array
 */
function twentytwentyfive_child_filter_news_latest_posts_by_tag( $query_args ) {
	if ( is_admin() || ! is_page() ) {
		return $query_args;
	}

	if ( empty( $_GET['pcf_tag'] ) ) {
		return $query_args;
	}

	$news_page = get_page_by_path( 'news-events' );
	if ( ! $news_page || (int) get_queried_object_id() !== (int) $news_page->ID ) {
		return $query_args;
	}

	$raw_tag_value = sanitize_text_field( wp_unslash( $_GET['pcf_tag'] ) );
	if ( '' === $raw_tag_value ) {
		return $query_args;
	}

	$tag_parts = preg_split( '/\s*,\s*/', $raw_tag_value );
	if ( ! is_array( $tag_parts ) ) {
		$tag_parts = array( $raw_tag_value );
	}

	$tag_slugs = array_values(
		array_filter(
			array_map(
				'sanitize_title',
				array_map( 'trim', $tag_parts )
			),
			'strlen'
		)
	);

	if ( empty( $tag_slugs ) ) {
		return $query_args;
	}

	$query_args['suppress_filters'] = false;
	$query_args['tag_slug__in'] = $tag_slugs;

	return $query_args;
}
add_filter( 'block_core_latest_posts_query_args', 'twentytwentyfive_child_filter_news_latest_posts_by_tag', 10 );

/**
 * Apply News & Events tag filtering to post queries rendered on that page.
 *
 * This is used as a compatibility path for WordPress builds where the
 * Latest Posts block does not expose query-args hooks.
 *
 * @param WP_Query $query Query instance.
 * @return void
 */
function twentytwentyfive_child_filter_news_posts_pre_get_posts( $query ) {
	if ( is_admin() || ! ( $query instanceof WP_Query ) ) {
		return;
	}

	if ( empty( $_GET['pcf_tag'] ) ) {
		return;
	}

	$news_page = get_page_by_path( 'news-events' );
	if ( ! $news_page || ! is_page( (int) $news_page->ID ) ) {
		return;
	}

	if ( $query->is_main_query() ) {
		return;
	}

	$post_type = $query->get( 'post_type' );
	$is_post_query = false;
	if ( is_string( $post_type ) ) {
		$is_post_query = ( 'post' === $post_type );
	} elseif ( is_array( $post_type ) ) {
		$is_post_query = in_array( 'post', $post_type, true );
	} elseif ( empty( $post_type ) ) {
		$is_post_query = true;
	}

	if ( ! $is_post_query ) {
		return;
	}

	$raw_tag_value = sanitize_text_field( wp_unslash( $_GET['pcf_tag'] ) );
	if ( '' === $raw_tag_value ) {
		return;
	}

	$tag_parts = preg_split( '/\s*,\s*/', $raw_tag_value );
	if ( ! is_array( $tag_parts ) ) {
		$tag_parts = array( $raw_tag_value );
	}

	$tag_slugs = array_values(
		array_filter(
			array_map(
				'sanitize_title',
				array_map( 'trim', $tag_parts )
			),
			'strlen'
		)
	);

	if ( empty( $tag_slugs ) ) {
		return;
	}

	$tax_query = (array) $query->get( 'tax_query' );
	$tax_query[] = array(
		'taxonomy' => 'post_tag',
		'field'    => 'slug',
		'terms'    => $tag_slugs,
		'operator' => 'IN',
	);

	$query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'twentytwentyfive_child_filter_news_posts_pre_get_posts', 20 );

/**
 * Render-time fallback: filter Latest Posts block output by selected tag(s).
 *
 * Some WordPress builds do not expose query hooks for this block. This keeps
 * filtering behavior consistent by pruning rendered list items.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Full block data.
 * @return string
 */
function twentytwentyfive_child_filter_news_latest_posts_rendered_html( $block_content, $block ) {
	if ( is_admin() || ! is_page() ) {
		return $block_content;
	}

	if ( empty( $_GET['pcf_tag'] ) ) {
		return $block_content;
	}

	if ( ! isset( $block['blockName'] ) || 'core/latest-posts' !== $block['blockName'] ) {
		return $block_content;
	}

	$news_page = get_page_by_path( 'news-events' );
	if ( ! $news_page || (int) get_queried_object_id() !== (int) $news_page->ID ) {
		return $block_content;
	}

	$raw_tag_value = sanitize_text_field( wp_unslash( $_GET['pcf_tag'] ) );
	if ( '' === $raw_tag_value ) {
		return $block_content;
	}

	$tag_parts = preg_split( '/\s*,\s*/', $raw_tag_value );
	if ( ! is_array( $tag_parts ) ) {
		$tag_parts = array( $raw_tag_value );
	}

	$tag_slugs = array_values(
		array_filter(
			array_map(
				'sanitize_title',
				array_map( 'trim', $tag_parts )
			),
			'strlen'
		)
	);

	if ( empty( $tag_slugs ) ) {
		return $block_content;
	}

	if ( false === strpos( $block_content, '<li' ) ) {
		return $block_content;
	}

	$filtered_html = preg_replace_callback(
		'/<li\b[^>]*>.*?<\/li>/is',
		static function ( $matches ) use ( $tag_slugs ) {
			$item_html = $matches[0];

			if ( ! preg_match( '/href=("|\')([^"\']+)\1/i', $item_html, $link_match ) ) {
				return '';
			}

			$post_id = url_to_postid( $link_match[2] );
			if ( ! $post_id ) {
				return '';
			}

			if ( has_term( $tag_slugs, 'post_tag', $post_id ) ) {
				return $item_html;
			}

			return '';
		},
		$block_content
	);

	if ( ! is_string( $filtered_html ) ) {
		return $block_content;
	}

	if ( false === strpos( $filtered_html, '<li' ) ) {
		$filtered_html .= '<p class="post-cards-empty">' . esc_html__( 'No posts found for this tag.', 'twentytwentyfive' ) . '</p>';
	}

	return $filtered_html;
}
add_filter( 'render_block', 'twentytwentyfive_child_filter_news_latest_posts_rendered_html', 30, 2 );

/**
 * Prepend a tag filter form above the first Latest Posts block on News & Events.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Full block data.
 * @return string
 */
function twentytwentyfive_child_render_news_tag_filter_ui( $block_content, $block ) {
	if ( is_admin() || ! is_page() ) {
		return $block_content;
	}

	$news_page = get_page_by_path( 'news-events' );
	if ( ! $news_page || (int) get_queried_object_id() !== (int) $news_page->ID ) {
		return $block_content;
	}

	if ( ! isset( $block['blockName'] ) || 'core/latest-posts' !== $block['blockName'] ) {
		return $block_content;
	}

	static $rendered = false;
	if ( $rendered ) {
		return $block_content;
	}
	$rendered = true;

	$selected_raw = isset( $_GET['pcf_tag'] ) ? sanitize_text_field( wp_unslash( $_GET['pcf_tag'] ) ) : '';
	$selected_slug = sanitize_title( $selected_raw );

	$terms = get_terms(
		array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}

	$news_url = get_permalink( $news_page );
	$control  = '<form class="post-cards-filter-drawer news-tag-filter" method="get" action="' . esc_url( $news_url ) . '">';
	$control .= '<div class="post-cards-filters news-tag-filter-controls">';
	$control .= '<div class="post-cards-filter-item news-tag-filter-item">';
	$control .= '<label for="pcf-tag-filter">' . esc_html__( 'Filter', 'twentytwentyfive' ) . '</label>';
	$control .= '<select id="pcf-tag-filter" name="pcf_tag">';
	$control .= '<option value="">' . esc_html__( 'All tags', 'twentytwentyfive' ) . '</option>';

	foreach ( $terms as $term ) {
		if ( ! isset( $term->slug, $term->name ) ) {
			continue;
		}

		$selected_attr = selected( $selected_slug, $term->slug, false );
		$control      .= '<option value="' . esc_attr( $term->slug ) . '" ' . $selected_attr . '>' . esc_html( $term->name ) . '</option>';
	}

	$control .= '</select>';
	$control .= '</div>';
	$control .= '<div class="post-cards-search-row news-tag-filter-actions">';
	$control .= '<button type="submit">' . esc_html__( 'Apply Filter', 'twentytwentyfive' ) . '</button>';
	if ( '' !== $selected_slug ) {
		$control .= '<a class="post-cards-clear" href="' . esc_url( $news_url ) . '">' . esc_html__( 'Clear', 'twentytwentyfive' ) . '</a>';
	}
	$control .= '</div>';
	$control .= '</div>';
	$control .= '</form>';

	return $control . $block_content;
}
add_filter( 'render_block', 'twentytwentyfive_child_render_news_tag_filter_ui', 20, 2 );

/**
 * Add copyright sign and current year inside the footer title h2.
 *
 * @param string $block_content Rendered block HTML.
 * @return string
 */
function twentytwentyfive_child_footer_title_with_year( $block_content ) {
	if ( ! is_string( $block_content ) || false === strpos( $block_content, 'footer-title' ) ) {
		return $block_content;
	}

	$current_year = wp_date( 'Y' );

	return preg_replace_callback(
		'/(<div[^>]*class="[^"]*footer-title[^"]*"[^>]*>\s*<h2[^>]*>)(.*?)(<\/h2>)/is',
		static function ( $matches ) use ( $current_year ) {
			$title = isset( $matches[2] ) ? trim( wp_strip_all_tags( $matches[2] ) ) : '';
			$title = preg_replace( '/\s*(?:©|&copy;)\s*\d{4}\s*$/iu', '', $title );
			if ( '' === $title ) {
				$title = 'The Pedagogy Lab';
			}

			return $matches[1] . esc_html( $title . ' © ' . $current_year ) . $matches[3];
		},
		$block_content,
		1
	);
}
add_filter( 'render_block', 'twentytwentyfive_child_footer_title_with_year', 40 );
