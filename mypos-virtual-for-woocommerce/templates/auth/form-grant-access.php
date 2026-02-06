<?php
/**
 * Auth form grant access
 *
 * This template can be overridden by copying it to yourtheme/mypos/auth/form-grant-access.php.
 *
 * @var string  $store_name  Store name.
 * @var string  $return_url  Return URL for deny action.
 * @var string  $granted_url URL for approve action.
 * @var string  $logout_url  Logout URL.
 * @var WP_User $user        Current user object.
 */

defined( 'ABSPATH' ) || exit;

// Ensure template variables have defaults if not passed.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables extracted from args.
$store_name  = ! empty( $store_name ) ? $store_name : get_bloginfo( 'name' );
$return_url  = ! empty( $return_url ) ? $return_url : home_url();
$granted_url = ! empty( $granted_url ) ? $granted_url : '';
$logout_url  = ! empty( $logout_url ) ? $logout_url : wp_logout_url();
$user        = ( $user instanceof WP_User ) ? $user : wp_get_current_user();
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

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
<?php
/**
 * Fires in the myPOS auth page header.
 *
 * @since 1.0.0
 */
do_action( 'mypos_auth_page_header' );
?>

<h1 class="wc-logged-in-title">

	MyPOS would like to connect to your store <strong>"<?php echo esc_html( $store_name ); ?>"</strong>?
</h1>

<?php wc_print_notices(); ?>
<!-- Logged in header -->
<div class="wc-auth-header wc-auth-logged-in-header">
	<div>
		<img src="<?php echo esc_url( plugins_url( '/mypos-virtual-for-woocommerce/assets/images/mypos_logo.png' ) ); ?>"
			alt="myPOS" class="mypos-logo"/>
		<span>+</span>
		<h1 id="wc-logo"><img src="<?php echo esc_url( WC()->plugin_url() ); ?>/assets/images/woocommerce_logo.png"
								alt="<?php esc_attr_e( 'WooCommerce', 'mypos-payments' ); ?>"/></h1>
	</div>

	<div class="wc-auth-logged-in-as">
		<div>
			<p>
				<?php
				/* Translators: %s display name. */
				printf( esc_html__( 'Logged in as %s', 'mypos-payments' ), esc_html( $user->display_name ) );
				?>
			</p>
			<a href="<?php echo esc_url( $logout_url ); ?>"
				class="wc-auth-logout"><?php esc_html_e( 'Logout?', 'mypos-payments' ); ?></a>
		</div>
		<?php echo get_avatar( $user->ID, 40 ); ?>
	</div>
</div>

<p class="wc-auth-actions">
	<a href="<?php echo esc_url( $return_url ); ?>" class="button button-ghost wc-auth-deny"><?php esc_html_e( 'Deny', 'mypos-payments' ); ?></a>
	<a href="<?php echo esc_url( $granted_url ); ?>" class="button button-primary wc-auth-approve"><?php esc_html_e( 'Approve', 'mypos-payments' ); ?></a>
</p>

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
