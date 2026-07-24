<?php
/**
 * Twenty Twenty-Five functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */

if ( ! function_exists( 'twentytwentyfive_post_format_setup' ) ) :
	/**
	 * Adds theme support for post formats.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_post_format_setup() {
		add_theme_support( 'post-formats', array( 'aside', 'audio', 'chat', 'gallery', 'image', 'link', 'quote', 'status', 'video' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_post_format_setup' );

if ( ! function_exists( 'twentytwentyfive_editor_style' ) ) :
	/**
	 * Enqueues editor-style.css in the editors.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_editor_style() {
		add_editor_style( 'assets/css/editor-style.css' );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_editor_style' );

if ( ! function_exists( 'twentytwentyfive_enqueue_styles' ) ) :
	/**
	 * Enqueues the theme stylesheet on the front.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_enqueue_styles() {
		$src = 'style.css';
		$google_fonts_url = 'https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap';

		wp_enqueue_style(
			'twentytwentyfive-google-fonts',
			$google_fonts_url,
			array(),
			null
		);

		wp_enqueue_style(
			'twentytwentyfive-style',
			get_parent_theme_file_uri( $src ),
			array( 'twentytwentyfive-google-fonts' ),
			wp_get_theme()->get( 'Version' )
		);
		wp_style_add_data(
			'twentytwentyfive-style',
			'path',
			get_parent_theme_file_path( $src )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'twentytwentyfive_enqueue_styles' );

if ( ! function_exists( 'twentytwentyfive_render_inline_header' ) ) :
	/**
	 * Renders an inline header with site title and right-aligned navigation,
	 * excluding the home page from the navigation list.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_render_inline_header() {
		if ( ! function_exists( 'do_blocks' ) ) {
			return;
		}

		$header_markup = '<!-- wp:group --><div id="header" class="pcf-inline-header"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} --><div class="wp-block-group alignwide"><!-- wp:site-title {"level":0} /--><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} --><div class="wp-block-group"><!-- wp:navigation {"overlayBackgroundColor":"contrast","overlayTextColor":"base","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /--></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group -->';
		$header_html   = do_blocks( $header_markup );

		if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
			$previous = libxml_use_internal_errors( true );

			$dom = new DOMDocument();
			$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $header_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			$xpath = new DOMXPath( $dom );

			$home_url_parts = wp_parse_url( home_url( '/' ) );
			$home_host      = strtolower( $home_url_parts['host'] ?? '' );
			$home_path      = '/' . ltrim( (string) ( $home_url_parts['path'] ?? '/' ), '/' );
			$home_path      = '/' === $home_path ? '/' : untrailingslashit( $home_path );

			foreach ( $xpath->query( '//li[contains(@class, "wp-block-navigation-item")]' ) as $item ) {
				$link = $xpath->query( './/a[@href]', $item )->item( 0 );
				if ( ! $link ) {
					continue;
				}

				$href       = html_entity_decode( $link->getAttribute( 'href' ) );
				$href_parts = wp_parse_url( $href );
				$href_host  = strtolower( $href_parts['host'] ?? '' );
				$href_path  = '/' . ltrim( (string) ( $href_parts['path'] ?? '/' ), '/' );
				$href_path  = '/' === $href_path ? '/' : untrailingslashit( $href_path );

				$is_home_link = false;

				if ( '' === $href_host ) {
					$is_home_link = '/' === $href_path || $href_path === $home_path;
				} else {
					$is_home_link = $href_host === $home_host && $href_path === $home_path;
				}

				if ( $is_home_link && $item->parentNode ) {
					$item->parentNode->removeChild( $item );
				}
			}

			$header_html = $dom->saveHTML();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		echo $header_html;
	}
endif;

if ( ! function_exists( 'twentytwentyfive_google_fonts_resource_hints' ) ) :
	/**
	 * Adds preconnect hints for Google Fonts.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for.
	 * @return array
	 */
	function twentytwentyfive_google_fonts_resource_hints( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}

		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);

		return $urls;
	}
endif;
add_filter( 'wp_resource_hints', 'twentytwentyfive_google_fonts_resource_hints', 10, 2 );

if ( ! function_exists( 'twentytwentyfive_block_styles' ) ) :
	/**
	 * Registers custom block styles.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_block_styles() {
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'twentytwentyfive' ),
				'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_block_styles' );

if ( ! function_exists( 'twentytwentyfive_pattern_categories' ) ) :
	/**
	 * Registers pattern categories.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_pattern_categories() {

		register_block_pattern_category(
			'twentytwentyfive_page',
			array(
				'label'       => __( 'Pages', 'twentytwentyfive' ),
				'description' => __( 'A collection of full page layouts.', 'twentytwentyfive' ),
			)
		);

		register_block_pattern_category(
			'twentytwentyfive_post-format',
			array(
				'label'       => __( 'Post formats', 'twentytwentyfive' ),
				'description' => __( 'A collection of post format patterns.', 'twentytwentyfive' ),
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_pattern_categories' );

if ( ! function_exists( 'twentytwentyfive_register_block_bindings' ) ) :
	/**
	 * Registers the post format block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_register_block_bindings() {
		register_block_bindings_source(
			'twentytwentyfive/format',
			array(
				'label'              => _x( 'Post format name', 'Label for the block binding placeholder in the editor', 'twentytwentyfive' ),
				'get_value_callback' => 'twentytwentyfive_format_binding',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_register_block_bindings' );

if ( ! function_exists( 'twentytwentyfive_format_binding' ) ) :
	/**
	 * Callback function for the post format name block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return string|void Post format name, or nothing if the format is 'standard'.
	 */
	function twentytwentyfive_format_binding() {
		$post_format_slug = get_post_format();

		if ( $post_format_slug && 'standard' !== $post_format_slug ) {
			return get_post_format_string( $post_format_slug );
		}
	}
endif;

if ( ! function_exists( 'twentytwentyfive_force_single_meta_bottom' ) ) :
	/**
	 * Forces Pedagogy single-post metadata layout to bottom.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param string $layout Current metadata layout.
	 * @return string
	 */
	function twentytwentyfive_force_single_meta_bottom( $layout ) {
		return 'bottom';
	}
endif;
add_filter( 'pcf_single_meta_layout', 'twentytwentyfive_force_single_meta_bottom', 99 );
