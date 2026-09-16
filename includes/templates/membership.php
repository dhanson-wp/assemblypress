<?php
/**
 * Theme-led email confirmation surface.
 *
 * @package AssemblyPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( wp_is_block_theme() ) {
	?>
	<!DOCTYPE html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head><body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<div class="wp-site-blocks">
	<?php block_header_area(); ?>
	<?php
} else {
	get_header();
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core and AssemblyPress blocks escape their output.
echo do_blocks( '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group"><!-- wp:heading {"level":1} --><h1 class="wp-block-heading">' . esc_html__( 'Membership', 'assemblypress' ) . '</h1><!-- /wp:heading --><!-- wp:assemblypress/registration /--></main><!-- /wp:group -->' );
if ( wp_is_block_theme() ) {
	block_footer_area();
	echo '</div>';
	wp_footer();
	echo '</body></html>';
} else {
	get_footer();
}
