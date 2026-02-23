<?php

defined( 'ABSPATH' ) || exit;

/**
 * MyPOS class.
 *
 * @class MyPOS
 */
final class MyPOS {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.4.3';

	/**
	 * The single instance of the class.
	 *
	 * @var MyPOS
	 */
	protected static $instance = null;

	/**
	 * Main MyPOS Instance.
	 *
	 * Ensures only one instance of WooCommerce is loaded or can be loaded.
	 *
	 * @static
	 * @see MyPOS()
	 * @return MyPOS - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * MyPOS Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define MyPOS Constants.
	 */
	private function define_constants() {
		$this->define( 'MYPOS_ABSPATH', dirname( MYPOS_PLUGIN_FILE ) . '/' );
		$this->define( 'MYPOS_PLUGIN_BASENAME', plugin_basename( MYPOS_PLUGIN_FILE ) );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string $name Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( $name, $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		if ( ! defined( $name ) ) {
			define( $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}
	}

	/**
	 * Returns true if the request is a non-legacy REST API request.
	 *
	 * @todo: replace this function once core WP function is available: https://core.trac.wordpress.org/ticket/42061.
	 *
	 * @return bool
	 */
	public function is_rest_api_request() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );

		// Properly sanitize REQUEST_URI before using it.
		$request_uri         = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$is_rest_api_request = ( str_contains( $request_uri, $rest_prefix ) );

		/**
		 * Filter to determine if the current request is a REST API request.
		 *
		 * @param bool $is_rest_api_request Whether the request is a REST API request.
		 * @return bool
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter name pattern used for compatibility.
		return apply_filters( 'is_rest_api_request', $is_rest_api_request );
	}

	/**
	 * What type of request is this?
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && wp_is_serving_rest_request();
			default:
				return false;
		}
	}

	public function includes() {
		/**
		 * Core classes.
		 */
		include_once MYPOS_ABSPATH . 'includes/mypos-core-functions.php';
		include_once MYPOS_ABSPATH . 'includes/class-mypos-install.php';

		/**
		 * REST API.
		 */
		include_once MYPOS_ABSPATH . 'includes/class-mypos-auth.php';
		include_once MYPOS_ABSPATH . 'rest-api/class-mypos-rest-upsell-controller.php';
		include_once MYPOS_ABSPATH . 'rest-api/class-mypos-rest-version-controller.php';

		if ( $this->is_request( 'frontend' ) ) {
			$this->frontend_includes();
		}
	}

	/**
	 * Include required frontend files.
	 */
	public function frontend_includes() {
		include_once MYPOS_ABSPATH . 'includes/mypos-template-hooks.php';
	}

	/**
	 * Function used to Init WooCommerce Template Functions - This makes them pluggable by plugins and themes.
	 */
	public function include_template_functions() {
		include_once MYPOS_ABSPATH . 'includes/mypos-template-functions.php';
	}

	/**
	 * Hook into actions and filters.
	 *
	 */
	private function init_hooks() {
		register_activation_hook( MYPOS_PLUGIN_FILE, array( 'MyPOS_Install', 'init' ) );

		//Register REST API namespaces and endpoints
		add_filter( 'woocommerce_rest_api_get_rest_namespaces', array( $this, 'register_custom_api' ) );

		add_action( 'after_setup_theme', array( $this, 'include_template_functions' ), 11 );

		// Load admin styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @return void
	 */
	public function admin_styles() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		// Load on WooCommerce settings pages.
		if ( 'woocommerce_page_wc-settings' === $screen_id ) {
			wp_enqueue_style(
				'mypos-admin-styles',
				$this->plugin_url() . '/assets/css/admin.css',
				array(),
				$this->version . '.' . time()
			);

			wp_enqueue_script(
				'mypos-admin-scripts',
				$this->plugin_url() . '/assets/js/admin.js',
				array( 'jquery' ),
				$this->version . '.' . time(),
				true
			);

			// Localize script with translatable strings.
			wp_localize_script(
				'mypos-admin-scripts',
				'myposAdminL10n',
				array(
					'testModeOnDesc'  => __( 'Test mode is enabled. Transactions will be processed in sandbox environment.', 'mypos-payments' ),
					'testModeOffDesc' => __( 'Test mode is disabled. Transactions will be processed in production environment.', 'mypos-payments' ),
				)
			);
		}
	}

	/**
	 * Register custom API controllers.
	 *
	 * @param array $controllers Existing controllers.
	 * @return array Modified controllers.
	 */
	public function register_custom_api( $controllers ) {
		$controllers['wc/v3']['upsell'] = 'MyPOS_REST_Upsell_Controller';
		$controllers['mp']['version']   = 'MyPOS_REST_Version_Controller';

		return $controllers;
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', MYPOS_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( MYPOS_PLUGIN_FILE ) );
	}

	/**
	 * Get the template path.
	 *
	 * @return string
	 */
	public function template_path() {
		/**
		 * Filter to allow modification of the template path for myPOS.
		 *
		 * @param string $template_path The template path.
		 * @return string
		 */
		return apply_filters( 'mypos_template_path', 'mypos/' );
	}

	/**
	 * Set table names inside WPDB object.
	 */
	public function wpdb_table_fix() {
		$this->define_tables();
	}

	/**
	 * Register custom tables within $wpdb object.
	 */
	private function define_tables() {
		global $wpdb;

		// Register custom table for myPOS upsells.
		// Using explicit property names to avoid dynamic method call security warnings.
		$wpdb->mp_upsells = $wpdb->prefix . 'mp_upsells';
		$wpdb->tables[]   = 'mp_upsells';
	}

	private function get_settings() {
		return json_decode( get_option( 'woocommerce_mypos_virtual_settings', array() ), false );
	}
}
