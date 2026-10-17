<?php
/**
 * Title: Header with large title
 * Slug: twentytwentyfive/header-large-title
 * Categories: header
 * Block Types: core/template-part/header
 * Description: Site header with large site title and right-aligned navigation.
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */

?>
<!-- wp:group -->
<div id="header">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|30"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--30)">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pcf-header-logo-link" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/lightpurpleicon.png' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="pcf-header-logo-image" /></a>
		<!-- wp:group {"style":{"spacing":{"padding":{"right":"0","left":"0"}}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group" style="padding-right:0;padding-left:0">
			<!-- wp:navigation {"overlayBackgroundColor":"base","overlayTextColor":"contrast","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","justifyContent":"right","orientation":"vertical"}} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
