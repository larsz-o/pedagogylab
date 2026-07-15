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

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

            <header class="entry-header">
                <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
            </header>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <?php if ( class_exists( 'Pedagogy_CF_Starter' ) ) : ?>
                <div class="pcf-custom-fields">
                    <?php
                    $defs = get_option( Pedagogy_CF_Starter::OPTION_KEY, array() );
                    if ( is_array( $defs ) && ! empty( $defs ) ) :
                        foreach ( $defs as $name => $def ) :
                            $value = Pedagogy_CF_Starter::get_value( get_the_ID(), $name );
                            if ( '' === $value || null === $value ) {
                                continue;
                            }
                    ?>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </div>
            <?php endif; ?>

        </article>

    <?php endwhile; ?>

</main>

<?php get_footer();
