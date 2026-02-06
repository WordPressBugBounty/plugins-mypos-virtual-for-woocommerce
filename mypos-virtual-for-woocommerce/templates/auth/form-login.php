<?php
/**
 * Auth form login
 *
 * This template can be overridden by copying it to yourtheme/mypos/auth/form-login.php.
 *
 * @var string $store_name   Store name.
 * @var string $return_url   Return URL.
 * @var string $redirect_url Redirect URL after login.
 */

defined( 'ABSPATH' ) || exit;

// Ensure template variables have defaults if not passed.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables extracted from args.
$store_name   = ! empty( $store_name ) ? $store_name : get_bloginfo( 'name' );
$return_url   = ! empty( $return_url ) ? $return_url : home_url();
$redirect_url = ! empty( $redirect_url ) ? $redirect_url : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$mypos_instance = new MyPOS();

do_action( 'mypos_auth_page_header' ); ?>

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
	document.body.className = document.body.className.replace('no-js', 'js');
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
	<h1 class="wc-auth-title">
		MyPOS would like to connect to your store <strong>"<?php echo esc_html( $store_name ); ?>"</strong>?
	</h1>

	<?php wc_print_notices(); ?>

	<p class="wc-auth-subtitle">
		<?php
		/* translators: %1$s: app name, %2$s: URL */
		echo wp_kses_post( sprintf( __( 'To connect to "%1$s" you need to be logged in. Log in to your store below.', 'mypos-payments' ), esc_html( wc_clean( $store_name ) ), esc_url( $return_url ) ) );
		?>
	</p>

	<!-- Not Logged in header -->
	<div class="wc-auth-header">
		<div>
			<img
				src="<?php echo esc_url( plugins_url( '/mypos-virtual-for-woocommerce/assets/images/mypos_logo.png' ) ); ?>"
				alt="myPOS" class="mypos-logo"/>
			<span>+</span>
			<h1 id="wc-logo"><img src="<?php echo esc_url( WC()->plugin_url() ); ?>/assets/images/woocommerce_logo.png"
									alt="<?php esc_attr_e( 'WooCommerce', 'mypos-payments' ); ?>"/></h1>
		</div>
	</div>

	<form method="post" class="wc-auth-login">
		<p class="form-row form-row-wide">
			<label for="username"><?php esc_html_e( 'Username or Е-mail address', 'mypos-payments' ); ?></label>
			<input type="text" class="input-text" name="username" id="username"
				   value="<?php echo (!empty($_POST['username'])) ? esc_attr($_POST['username']) : ''; ?>"/><?php //@codingStandardsIgnoreLine ?>
		</p>
		<p class="form-row form-row-wide">
			<label for="password"><?php esc_html_e( 'Password', 'mypos-payments' ); ?></label>
			<input class="input-text" type="password" name="password" id="password"/>
		</p>
		<?php

		/**
		 * Fires following the 'Password' field in the login form.
		 *
		 * @since 2.1.0
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login hook
		do_action( 'login_form' );

		?>
		<p class="wc-auth-actions">
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<button type="submit" class="button button-large button-primary wc-auth-login-button" name="login"
					value="<?php esc_attr_e( 'Login', 'mypos-payments' ); ?>"><?php esc_html_e( 'Login', 'mypos-payments' ); ?></button>
			<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect_url ); ?>"/>

			<?php
			/* translators: %1$s: URL */
			echo wp_kses_post( sprintf( __( '<a href="%1$s" class="wc-auth-cancel-link">Cancel and return</a>', 'mypos-payments' ), esc_url( $return_url ) ) );
			?>
		</p>

	</form>

	<?php
	/**
	 * Fires in the myPOS auth page footer.
	 *
	 * @since 1.0.0
	 */
	do_action( 'mypos_auth_page_footer' );
	?>
</div>
<?php
/**
 * Fires in the login page footer before closing the body tag.
 *
 * @since 4.6.0
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core login hook
do_action( 'login_footer' );
?>
</body>
</html>
