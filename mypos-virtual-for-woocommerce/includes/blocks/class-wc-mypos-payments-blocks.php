<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- WooCommerce Blocks integration requires specific file naming.

namespace blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WC_Gateway_Mypos;
use WC_Mypos_Payments;

/**
 * Back-end block init
 */
final class WC_Gateway_Mypos_Blocks_Support extends AbstractPaymentMethodType {


	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Mypos
	 */
	private $gateway;

	protected $name = 'mypos_virtual';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_mypos_virtual_settings', array() );
		$this->gateway  = new WC_Gateway_Mypos();
	}

	public function is_active() {
		return $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = WC_Mypos_Payments::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => '1.4.4',
			);
		$script_url        = WC_Mypos_Payments::plugin_url() . $script_path;

		wp_register_script(
			'mypos_virtual',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'mypos_virtual', 'mypos-payments', WC_Mypos_Payments::plugin_abspath() . 'languages/' );
		}

		return array( 'mypos_virtual' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'path'        => WC_Mypos_Payments::plugin_url(),
			'supports'    => $this->gateway->supports,
		);
	}
}
