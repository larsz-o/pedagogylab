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
		$google_fonts_url = 'https://fonts.googleapis.com/css2?family=Lexend:wght@100..900&family=Lora:ital,wght@0,400..700;1,400..700&display=swap';

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
			$header_logo_src = get_theme_file_uri( 'assets/images/lightpurpleicon.png' );
			$header_logo_alt = get_bloginfo( 'name' );

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

			$site_title_nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-site-title ")]' );
			if ( $site_title_nodes instanceof DOMNodeList && $site_title_nodes->length > 0 ) {
				foreach ( $site_title_nodes as $site_title_node ) {
					$logo_link = $dom->createElement( 'a' );
					$logo_link->setAttribute( 'href', home_url( '/' ) );
					$logo_link->setAttribute( 'class', 'pcf-header-logo-link' );
					$logo_link->setAttribute( 'aria-label', $header_logo_alt );

					$logo_img = $dom->createElement( 'img' );
					$logo_img->setAttribute( 'src', $header_logo_src );
					$logo_img->setAttribute( 'alt', $header_logo_alt );
					$logo_img->setAttribute( 'class', 'pcf-header-logo-image' );

					$logo_link->appendChild( $logo_img );
					$site_title_node->parentNode->replaceChild( $logo_link, $site_title_node );
				}
			}

			$header_html = $dom->saveHTML();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		echo $header_html;
	}
endif;

if ( ! function_exists( 'twentytwentyfive_replace_header_site_title_with_logo' ) ) :
	/**
	 * Replaces rendered site-title blocks with the Brand Mark logo inside header template parts.
	 *
	 * This runs as a runtime fallback so existing Site Editor-saved headers also get the logo.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	function twentytwentyfive_replace_header_site_title_with_logo( $block_content, $block ) {
		if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'core/template-part' ) {
			return $block_content;
		}

		$slug = (string) ( $block['attrs']['slug'] ?? '' );
		$area = (string) ( $block['attrs']['area'] ?? '' );

		if ( ! str_contains( $slug, 'header' ) && 'header' !== $area ) {
			return $block_content;
		}

		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
			return $block_content;
		}

		$previous = libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $block_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$xpath = new DOMXPath( $dom );

		$site_title_nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-site-title ")]' );
		if ( $site_title_nodes instanceof DOMNodeList && $site_title_nodes->length > 0 ) {
			$header_logo_src = get_theme_file_uri( 'assets/images/lightpurpleicon.png' );
			$header_logo_alt = get_bloginfo( 'name' );

			foreach ( $site_title_nodes as $site_title_node ) {
				$logo_link = $dom->createElement( 'a' );
				$logo_link->setAttribute( 'href', home_url( '/' ) );
				$logo_link->setAttribute( 'class', 'pcf-header-logo-link' );
				$logo_link->setAttribute( 'aria-label', $header_logo_alt );

				$logo_img = $dom->createElement( 'img' );
				$logo_img->setAttribute( 'src', $header_logo_src );
				$logo_img->setAttribute( 'alt', $header_logo_alt );
				$logo_img->setAttribute( 'class', 'pcf-header-logo-image' );

				$logo_link->appendChild( $logo_img );

				if ( $site_title_node->parentNode ) {
					$site_title_node->parentNode->replaceChild( $logo_link, $site_title_node );
				}
			}
		}

		$updated_html = $dom->saveHTML();

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return is_string( $updated_html ) ? $updated_html : $block_content;
	}
endif;
add_filter( 'render_block', 'twentytwentyfive_replace_header_site_title_with_logo', 20, 2 );

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

if ( ! function_exists( 'twentytwentyfive_register_news_post_template' ) ) :
	/**
	 * Registers the News Template for posts in the editor template dropdown.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param array       $templates Existing templates.
	 * @param WP_Theme    $theme     Active theme object.
	 * @param WP_Post     $post      Post object.
	 * @param string|null $post_type Post type.
	 * @return array
	 */
	function twentytwentyfive_register_news_post_template( $templates, $theme, $post, $post_type ) {
		if ( 'post' !== $post_type ) {
			return $templates;
		}

		$templates['news-template.php'] = __( 'News Template', 'twentytwentyfive' );
		return $templates;
	}
endif;
add_filter( 'theme_templates', 'twentytwentyfive_register_news_post_template', 10, 4 );
add_filter( 'theme_post_templates', 'twentytwentyfive_register_news_post_template', 10, 4 );

if ( ! function_exists( 'twentytwentyfive_load_news_post_template' ) ) :
	/**
	 * Loads the PHP News Template when selected for a single post.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	function twentytwentyfive_load_news_post_template( $template ) {
		if ( ! is_singular( 'post' ) ) {
			return $template;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $template;
		}

		$post_template = get_page_template_slug( $post_id );
		if ( ! in_array( $post_template, array( 'news-template.php', 'news-template' ), true ) ) {
			return $template;
		}

		$news_template = get_theme_file_path( 'news-template.php' );
		if ( file_exists( $news_template ) ) {
			return $news_template;
		}

		return $template;
	}
endif;
add_filter( 'template_include', 'twentytwentyfive_load_news_post_template', 20 );

if ( ! function_exists( 'twentytwentyfive_register_template_selector_metabox' ) ) :
	/**
	 * Adds a template selector metabox for post and page editing screens.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_register_template_selector_metabox() {
		add_meta_box(
			'twentytwentyfive-template-selector',
			__( 'Template Selector', 'twentytwentyfive' ),
			'twentytwentyfive_render_template_selector_metabox',
			array( 'post', 'page' ),
			'side',
			'default'
		);
	}
endif;
add_action( 'add_meta_boxes', 'twentytwentyfive_register_template_selector_metabox' );

if ( ! function_exists( 'twentytwentyfive_render_template_selector_metabox' ) ) :
	/**
	 * Renders the template selector metabox.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	function twentytwentyfive_render_template_selector_metabox( $post ) {
		wp_nonce_field( 'twentytwentyfive_save_template_selector', 'twentytwentyfive_template_selector_nonce' );

		$current_template = get_page_template_slug( $post->ID );
		$templates        = wp_get_theme()->get_page_templates( $post, $post->post_type );
		?>
		<p>
			<label for="twentytwentyfive_template_selector"><?php esc_html_e( 'Choose template:', 'twentytwentyfive' ); ?></label>
		</p>
		<p>
			<select id="twentytwentyfive_template_selector" name="twentytwentyfive_template_selector" style="width:100%;">
				<option value="default" <?php selected( empty( $current_template ) ); ?>><?php esc_html_e( 'OER Library Template', 'twentytwentyfive' ); ?></option>
				<?php foreach ( $templates as $template_file => $template_name ) : ?>
					<option value="<?php echo esc_attr( $template_file ); ?>" <?php selected( $current_template, $template_file ); ?>><?php echo esc_html( $template_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}
endif;

if ( ! function_exists( 'twentytwentyfive_save_template_selector_metabox' ) ) :
	/**
	 * Saves template selection from the template selector metabox.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param int $post_id Current post ID.
	 * @return void
	 */
	function twentytwentyfive_save_template_selector_metabox( $post_id ) {
		if ( ! isset( $_POST['twentytwentyfive_template_selector_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['twentytwentyfive_template_selector_nonce'] ) ), 'twentytwentyfive_save_template_selector' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['twentytwentyfive_template_selector'] ) ) {
			return;
		}

		$template = sanitize_text_field( wp_unslash( $_POST['twentytwentyfive_template_selector'] ) );

		if ( 'default' === $template || '' === $template ) {
			delete_post_meta( $post_id, '_wp_page_template' );
			return;
		}

		update_post_meta( $post_id, '_wp_page_template', $template );
	}
endif;
add_action( 'save_post', 'twentytwentyfive_save_template_selector_metabox', 20 );

if ( ! function_exists( 'twentytwentyfive_auto_assign_news_template' ) ) :
	/**
	 * Assigns News Template to posts in the "news" category when no custom template is set.
	 * Runs after terms are stored to ensure category checks are accurate.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post object.
	 * @param bool         $update      Whether this is an existing post update.
	 * @param WP_Post|null $post_before Post object before the update.
	 * @return void
	 */
	function twentytwentyfive_auto_assign_news_template( $post_id, $post, $update, $post_before ) {
		unset( $update, $post_before );

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$has_news_category = has_term( 'news', 'category', $post_id );
		$current_template = get_post_meta( $post_id, '_wp_page_template', true );

		if ( $has_news_category ) {
			if ( ! empty( $current_template ) && ! in_array( $current_template, array( 'default', 'default.php' ), true ) ) {
				return;
			}

			update_post_meta( $post_id, '_wp_page_template', 'news-template.php' );
			return;
		}

		if ( in_array( $current_template, array( 'news-template.php', 'news-template' ), true ) ) {
			delete_post_meta( $post_id, '_wp_page_template' );
		}
	}
endif;
add_action( 'wp_after_insert_post', 'twentytwentyfive_auto_assign_news_template', 30, 4 );
