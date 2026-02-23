<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Main plugin file follows WordPress plugin naming convention.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Main plugin file requires both function and class declarations.

/**
 * Plugin Name: myPOS Checkout
 * Plugin URI: https://www.mypos.com
 * Description: Accept payments with myPOS - instant settlement, all major cards, Apple Pay and Google Pay. No setup costs or monthly fees.
 * Version: 1.4.3
 * Author: myPOS Europe LTD
 * Author URI: https://www.mypos.com
 * Developer: Intercard Finance
 * Developer URI: https://www.mypos.com
 * Text Domain: mypos-payments
 * Requires at least: 6.1
 * Tested up to: 6.9
 * WC requires at least: 7.0
 * WC tested up to: 10.4.3
 * Requires PHP: 7.4
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


use blocks\WC_Gateway_Mypos_Blocks_Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'MYPOS_PLUGIN_FILE' ) ) {
	define( 'MYPOS_PLUGIN_FILE', __FILE__ );
}

// Makes sure the plugin is defined before trying to use it
if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
	require_once ABSPATH . '/wp-admin/includes/plugin.php';
}

// Include the MyPOS class.
if ( ! class_exists( 'MyPOS', false ) ) {
	require_once dirname( MYPOS_PLUGIN_FILE ) . '/includes/class-mypos.php';
}

// Initialize MyPOS instance.
function mypos_init() {
	return MyPOS::instance();
}

// Initialize on plugins_loaded to ensure WooCommerce is available.
add_action( 'plugins_loaded', 'mypos_init', 5 );

class WC_Mypos_Payments {


	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
		
		// Register WooCommerce API endpoint for myPOS webhook callbacks
		// This ensures the endpoint is available before WooCommerce API processes requests
		add_action( 'init', array( __CLASS__, 'register_api_endpoint' ), 5 );
		
		add_action(
			'woocommerce_blocks_loaded',
			array( __CLASS__, 'woocommerce_gateway_mypos_woocommerce_block_support' )
		);
		add_action( 'init', 'mypos_checkout_for_woocommerce_mypos_checkout_for_woocommerce_block_init' );
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
		
		// Add Settings link in plugins list page
		add_filter( 'plugin_action_links_' . plugin_basename( MYPOS_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
	}
	
	/**
	 * Register WooCommerce API endpoint for webhook callbacks
	 * This method ensures the gateway class is available when webhook callbacks are processed
	 *
	 * @return void
	 */
	public static function register_api_endpoint() {
		// Nothing to do here - WooCommerce will automatically route requests to ?wc-api=wc_gateway_mypos
		// to the gateway's check_ipc_response() method via the hook registered in the gateway constructor
		// We just need to make sure the class is loaded early
		self::includes();
	}

	/**
	 * Add Settings link to plugin action links in plugins list page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public static function plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mypos_virtual' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'mypos-payments' ) . '</a>';
		
		// Add Settings link at the beginning of the array
		array_unshift( $links, $settings_link );
		
		return $links;
	}

	/**
	 * Declare compatibility with WooCommerce High Performance Order Storage (HPOS).
	 *
	 * @return void
	 */
	public static function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				MYPOS_PLUGIN_FILE,
				true
			);
		}
	}

	public static function add_gateway( $gateways ) {
		$options = get_option( 'woocommerce_mypos_virtual_settings', array() );

		if ( isset( $options['hide_for_non_admin_users'] ) ) {
			$hide_for_non_admin_users = $options['hide_for_non_admin_users'];
		} else {
			$hide_for_non_admin_users = 'no';
		}

		// Only add gateway if hide_for_non_admin_users is 'no' OR user is admin.
		if ( 'yes' !== $hide_for_non_admin_users || current_user_can( 'manage_options' ) ) {
			$gateways[] = WC_Gateway_Mypos::class;
		}
		return $gateways;
	}

	public static function includes() {
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-mypos-pending-payments-manager.php';
			require_once 'includes/class-wc-gateway-mypos.php';
		}
	}

	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	public static function woocommerce_gateway_mypos_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-mypos-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_Mypos_Blocks_Support() );
				}
			);

			add_filter(
				'__experimental_woocommerce_blocks_add_data_attributes_to_block',
				function ( $allowed_blocks ) {
					$allowed_blocks[] = 'mypos/checkout';
					return $allowed_blocks;
				},
				10,
				1
			);
		}
	}
}

function mypos_checkout_for_woocommerce_mypos_checkout_for_woocommerce_block_init() {
	register_block_type( __DIR__ . '/build' );
}

//Returns the main instance of MyPOS.
WC_Mypos_Payments::init();
