<?php
/**
 * Theme-compatible member surface for classic themes.
 *
 * @package AssemblyPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic blocks escape their output.
	echo do_blocks( get_query_var( 'assemblypress_edit' ) ? '<!-- wp:assemblypress/edit-profile-form /-->' : '<!-- wp:assemblypress/member-profile /-->' );
	?>
</main>
<?php
get_footer();
