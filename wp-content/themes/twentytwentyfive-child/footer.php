<?php
/**
 * Footer template for PHP-rendered templates in the child theme.
 *
 * @package twentytwentyfive-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'do_blocks' ) ) {
	echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
}
?>
</div>
<?php wp_footer(); ?>
</body>
</html>
