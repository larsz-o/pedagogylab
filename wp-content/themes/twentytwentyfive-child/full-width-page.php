<?php
/**
 * Template Name: Full-Width Page
 * Template Post Type: page
 * Description: Full-width page template with no constrained content wrapper.
 */

get_header();
?>

<main id="primary" class="site-main full-width-page-template">
    <?php
    while ( have_posts() ) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'full-width-page-entry' ); ?>>
            <?php the_content(); ?>
        </article>
        <?php
    endwhile;
    ?>
</main>

<?php
get_footer();
