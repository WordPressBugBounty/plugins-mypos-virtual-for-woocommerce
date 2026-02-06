<?php
/**
 * Upsells page template
 *
 * @package myPOS
 * @var string $products Comma-separated list of product IDs.
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Ensure template variables have defaults if not passed.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable extracted from args.
$products = ! empty( $products ) ? $products : '';

get_header(); ?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<div>
			<h2>Recommended Products</h2>
		</div>

		<?php echo do_shortcode( '[products ids="' . $products . '"]' ); ?>

		<div style="margin: 20px">
			<a href="<?php echo esc_url( wp_get_referer() ); ?>"
				style="text-decoration:none; border: 2px solid #00ACEC; padding:10px 20px 10px 20px; border-radius:2px; color:#00ACEC; margin:5px;"
				class="standard-btn btn-lg">Go Back</a>
			<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>"
				style="text-decoration:none; border: 2px solid #00ACEC; padding:10px 20px 10px 20px; border-radius:2px; color:#00ACEC; margin:5px;"
				class="standard-btn btn-lg">Continue</a>
		</div>
	</main><!-- .site-main -->
</div><!-- .content-area -->
<?php get_footer(); ?>
