<?php
/**
 * Title: Centered site header
 * Slug: twentytwentyfive/header-centered
 * Categories: header
 * Block Types: core/template-part/header
 * Description: Site header with centered site title and navigation.
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */

?>
<!-- wp:group -->
<div id="header">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--30)">
		<div style="text-align:center;"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pcf-header-logo-link" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/lightpurpleicon.png' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="pcf-header-logo-image" /></a></div>
		<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
		<div class="wp-block-group alignwide">
			<!-- wp:navigation {"overlayBackgroundColor":"base","overlayTextColor":"contrast","layout":{"type":"flex","justifyContent":"center"}} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
