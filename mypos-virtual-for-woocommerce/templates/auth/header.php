<?php
/**
 * Auth header
 */

defined( 'ABSPATH' ) || exit;

$mypos_instance = new MyPOS();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta name="viewport" content="width=device-width"/>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<meta name="robots" content="noindex, nofollow"/>
	<title><?php esc_html_e( 'Application authentication request', 'mypos-payments' ); ?></title>

	<?php
		/**
		 * Enqueue scripts and styles for the login page.
		 *
		 * @since 3.1.0
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login hook
		do_action( 'login_enqueue_scripts' );

		/**
		 * Fires in the login page header after scripts are enqueued.
		 *
		 * @since 2.1.0
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login hook
		do_action( 'login_head' );

		// Enqueue Google Fonts
		wp_enqueue_style(
			'mypos-auth-google-fonts',
			'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap',
			array(),
			null
		);

		// Enqueue custom auth styles
		wp_enqueue_style(
			'mypos-auth-styles',
			$mypos_instance->plugin_url() . '/assets/css/auth.css',
			array(),
			$mypos_instance->version
		);

		wp_admin_css( 'install', true );
		?>
</head>
<body class="wc-auth wp-core-ui">
<script type="text/javascript">
	document.body.className = document.body.className.replace('no-js','js');
</script>
	<?php
	/**
	 * Fires in the login page header after the body tag is opened.
	 *
	 * @since 4.6.0
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login hook
	do_action( 'login_header' );

	?>
	<div class="wc-auth-content">
