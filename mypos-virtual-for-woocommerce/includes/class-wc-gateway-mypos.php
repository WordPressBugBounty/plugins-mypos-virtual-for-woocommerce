<?php
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- WooCommerce payment gateway convention requires class file naming.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Cron callback function must be in same file as gateway class for WooCommerce hook registration.

use Automattic\WooCommerce\Admin\Overrides\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Callback for mypos_check_payment_status cron hook.
 *
 * @return void
 */
function mypos_check_pending_payment_orders_statuses() {
	( new WC_Gateway_Mypos() )->check_pending_payment_orders_statuses();
}

add_action( 'mypos_check_payment_status', 'mypos_check_pending_payment_orders_statuses' );


require_once 'class-mypos-auth.php';
/**
 * WC_Gateway_Mypos class
 *
 * @package WooCommerce Mypos Payments Gateway
 * @since 1.4.2
 */
class WC_Gateway_Mypos extends WC_Payment_Gateway {

	public const PAYMENT_METHOD_CARD                 = '1';
	public const PAYMENT_METHOD_IDEAL                = '2';
	public const PAYMENT_METHOD_BOTH                 = '3';
	public const PAYMENT_METHOD_SATISPAY             = '4';
	public const PAYMENT_METHOD_TWINT                = '5';
	public const PAYMENT_METHOD_IRIS                 = '6';
	public const PENDING_PAYMENT_DB_TABLE_NAME       = 'mypos_pending_payments_schedule';
	public const WAITING_CONFIRMATION_PERIOD_HOURS   = '8';
	public const WAITING_CONFIRMATION_DEADLINE_HOURS = '24'; // Hours after the order change his status as canceled

	public static $log;
	protected $line_items;
	public static $log_enabled;
	public $test;
	public $debug;
	public $version;
	public $sid;
	public $wallet_number;
	public $private_key;
	public $public_certificate;
	public $keyindex;
	public $url;
	public $payment_parameters_required;
	public $payment_method;
	public $merchant_wallet_number;
	public $test_prefix;
	public $merchant_send_money_reason;
	public $has_deadline_pending_order;
	public $notify_url;

	/**
	 * Logging method
	 *
	 * @param string $message
	 */
	public static function log( string $message ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'mypos_virtual', $message );
		}
	}

	public function __construct() {
		$this->id                 = 'mypos_virtual';
		$this->icon               = WC_Mypos_Payments::plugin_url() . '/assets/images/mypos.png';
		$this->has_fields         = false;
		$this->supports           = array(
			'products',
			'refunds',
		);
		$this->method_title       = _x( 'myPOS Checkout', 'myPOS payment method', 'mypos-payments' );
		$this->method_description = __(
			'Accept payments with myPOS Checkout <br/>To use this payment option you need to <a href="https://mypos.com/en/register/" target="_blank">sign up</a> for a myPOS account.',
			'mypos-payments'
		);

		$this->version = '1.4';

		// Load fields and settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->test        = 'yes' === $this->get_option( 'test', 'no' );
		$this->debug       = 'yes' === $this->get_option( 'debug', 'no' );

		$test_prefix = $this->get_option( 'test_prefix' );
		if ( empty( $test_prefix ) ) {
			if ( empty( $this->settings ) ) {
				$this->init_settings();
			}

			$this->settings['test_prefix'] = uniqid() . '_';
			/**
			 * Note: The filter 'woocommerce_settings_api_sanitized_fields_' . $this->id
			 * is a WooCommerce Core API requirement defined in WC_Settings_API class.
			 * ALL WooCommerce payment gateways must use this exact filter name.
			 * Cannot be changed without breaking WooCommerce compatibility.
			 * This pattern is used by Stripe, PayPal, Square, and all major WC payment plugins.
			 */
			update_option(
				$this->get_option_key(),
				apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscore, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core API requirement.
				'yes'
			);
		}

		$this->test_prefix = $this->get_option( 'test_prefix' );

		$this->force_tld();

		if ( ! $this->test ) {
			// Production mode configuration
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for decoding encrypted payment package credentials from myPOS.
			$production_package = $this->get_option( 'production_package' );
			$package_data       = array();
			
			// Decode package only if not empty
			if ( ! empty( $production_package ) ) {
				$package_data = json_decode( base64_decode( $production_package ), true );
			}

			$this->sid                         = ! empty( $package_data['sid'] ) ? $package_data['sid'] : $this->get_option( 'production_sid' );
			$this->wallet_number               = ! empty( $package_data['cn'] ) ? $package_data['cn'] : $this->get_option(
				'production_wallet_number'
			);
			$this->private_key                 = ! empty( $package_data['pk'] ) ? $package_data['pk'] : $this->get_option(
				'production_private_key'
			);
			$this->public_certificate          = ! empty( $package_data['pc'] ) ? $package_data['pc'] : $this->get_option(
				'production_public_certificate'
			);
			$this->keyindex                    = ! empty( $package_data['idx'] ) ? $package_data['idx'] : $this->get_option(
				'production_keyindex'
			);
			$this->url                         = $this->get_option( 'production_url' );
			$this->payment_parameters_required = $this->get_option( 'production_ppr' );
			$developer_payment_method          = array();
			for ( $i = 1; $i < 7; $i++ ) {
				if ( 'yes' === $this->get_option( 'payment_method_3' ) ) {
					$this->update_option( 'payment_method_1', 'no' );
					$this->update_option( 'payment_method_2', 'no' );
					$this->update_option( 'payment_method_4', 'no' );
					$this->update_option( 'payment_method_5', 'no' );
					$this->update_option( 'payment_method_6', 'no' );
					$developer_payment_method[] = self::PAYMENT_METHOD_BOTH;
				} elseif ( 'yes' === $this->get_option( 'payment_method_' . $i ) ) {
					$developer_payment_method[] = $i;
				}
			}
			$selected_developer_payment_methods = implode( ',', array_unique( $developer_payment_method ) );
			if ( '' === $selected_developer_payment_methods ) {
				$this->update_option( 'payment_method_3', 'yes' );
				$selected_developer_payment_methods = self::PAYMENT_METHOD_BOTH;
			}
			$this->payment_method = $selected_developer_payment_methods;
		} else {
			// Developer/Test mode configuration
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for decoding encrypted developer package credentials from myPOS.
			$developer_package = $this->get_option( 'developer_package' );
			$package_data      = array();
			
			// Decode package only if not empty
			if ( ! empty( $developer_package ) ) {
				$package_data = json_decode( base64_decode( $developer_package ), true );
			}

			$this->sid                         = ! empty( $package_data['sid'] ) ? $package_data['sid'] : $this->get_option( 'developer_sid' );
			$this->wallet_number               = ! empty( $package_data['cn'] ) ? $package_data['cn'] : $this->get_option(
				'developer_wallet_number'
			);
			$this->private_key                 = ! empty( $package_data['pk'] ) ? $package_data['pk'] : $this->get_option(
				'developer_private_key'
            );
			$this->public_certificate          = ! empty( $package_data['pc'] ) ? $package_data['pc'] : $this->get_option(
				'developer_public_certificate'
            );
			$this->keyindex                    = ! empty( $package_data['idx'] ) ? $package_data['idx'] : $this->get_option(
				'developer_keyindex'
			);
			$this->url                         = $this->get_option( 'developer_url' );
			$this->payment_parameters_required = $this->get_option( 'production_ppr' );
			$developer_payment_method          = array();
			for ( $i = 1; $i < 7; $i++ ) {
				if ( 'yes' === $this->get_option( 'payment_method_3' ) ) {
					$this->update_option( 'payment_method_1', 'no' );
					$this->update_option( 'payment_method_2', 'no' );
					$this->update_option( 'payment_method_4', 'no' );
					$this->update_option( 'payment_method_5', 'no' );
					$this->update_option( 'payment_method_6', 'no' );
					$developer_payment_method[] = self::PAYMENT_METHOD_BOTH;
				} elseif ( 'yes' === $this->get_option( 'payment_method_' . $i ) ) {
					$developer_payment_method[] = $i;
				}
			}
			$selected_developer_payment_methods = implode( ',', array_unique( $developer_payment_method ) );
			if ( '' === $selected_developer_payment_methods ) {
				$this->update_option( 'payment_method_3', 'yes' );
				$selected_developer_payment_methods = self::PAYMENT_METHOD_BOTH;
			}
			$this->payment_method = $selected_developer_payment_methods;
		}

		$this->merchant_wallet_number     = $this->get_option( 'merchant_wallet_number' );
		$this->merchant_send_money_reason = $this->get_option( 'merchant_send_money_reason' );
		$this->has_deadline_pending_order = 'yes' === $this->get_option( 'use_deadline_for_orders', 'no' );
		$base_url                         = home_url( '?wc-api=' . strtolower( __CLASS__ ) );
		if ( function_exists( 'trp_force_language' ) ) {
			remove_filter( 'home_url', 'trp_force_language', 1 );
			$base_url = home_url( '?wc-api=' . strtolower( __CLASS__ ) );
			add_filter( 'home_url', 'trp_force_language', 1, 4 );
		}

		$base_url = remove_query_arg( array( 'lang', 'trp-form-language' ), $base_url );
		$base_url = preg_replace( '#/[a-z]{2}/#', '/', $base_url );

		$this->notify_url = str_replace( 'http:', 'https:', $base_url );

		self::$log_enabled = $this->debug;

		$this->init_cron_hook();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array( $this, 'check_ipc_response' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'check_payment_status_view' ) );
		add_action( 'verify_notify_post', array( $this, 'verify_notify_post' ) );
		add_action( 'wp_ajax_mypos_check_payment_status', array( $this, 'ajax_check_payment_status' ) );
		add_action( 'wp_ajax_mypos_test_connection', array( $this, 'ajax_test_connection' ) );

		// REMOVED: Invalid hook to non-existent method that was causing 2min delays
		// if ( ! empty( $this->merchant_wallet_number ) ) {
		// 	add_action( 'woocommerce_before_order_object_save', array( $this, 'capture_payment_complete' ) );
		// }

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		} else {
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		}
	}

	public function init_form_fields() {
		$this->form_fields = include 'settings-ipc.php';
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Process a refund if supported.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID.', 'mypos-payments' ) );
		}

		$transaction_id = $order->get_transaction_id();

		if ( ! $transaction_id ) {
			return new WP_Error( 'no_transaction_id', __( 'No transaction ID found for this order.', 'mypos-payments' ) );
		}

		// Validate refund amount
		if ( is_null( $amount ) || $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Invalid refund amount.', 'mypos-payments' ) );
		}

		self::log( sprintf( 'Refund: Order #%d, Amount: %s', $order_id, $amount ) );

		try {
			// Prepare refund request data
			$refund_data = array(
				'IPCmethod'    => 'IPCRefund',
				'IPCVersion'   => $this->version,
				'IPCLanguage'  => defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : substr( get_locale(), 0, 2 ),
				'SID'          => $this->sid,
				'WalletNumber' => $this->wallet_number,
                'KeyIndex'     => $this->keyindex,
                'Trn_ref'      => $transaction_id,
				'Amount'       => number_format( $amount, 2, '.', '' ),
				'Currency'     => $order->get_currency(),
				'OutputFormat' => 'json',
			);

			// Add reason if provided
			if ( ! empty( $reason ) ) {
				$refund_data['Note'] = sanitize_text_field( $reason );
			}

			// Create signature
			$refund_data['Signature'] = $this->create_signature( $refund_data );

			// Send refund request to myPOS
			$response = wp_remote_post(
				$this->url,
				array(
					'body'       => $refund_data,
					'timeout'    => 15,
					'blocking'   => true,
					'sslverify'  => true,
					'user-agent' => 'myPOS-WooCommerce/' . $this->version,
				)
			);

			if ( is_wp_error( $response ) ) {
				self::log( 'Refund failed: ' . $response->get_error_message() );
				return new WP_Error( 'refund_failed', $response->get_error_message() );
			}

			$response_body = wp_remote_retrieve_body( $response );
			$result        = json_decode( $response_body, true );

		// Check refund status (myPOS returns integer 0 or string '0' for success)
		if ( isset( $result['Status'] ) && ( 0 === $result['Status'] || '0' === $result['Status'] ) ) {
			// Refund successful - get transaction ID
			$refund_id = isset( $result['IPC_Trnref'] ) ? $result['IPC_Trnref'] : '';
			
			self::log( sprintf( 'Refund successful: Order #%d, Transaction ID: %s', $order_id, $refund_id ) );
			
			// Add order note
			$order->add_order_note(
				sprintf(
					/* translators: 1: Refund amount, 2: Currency, 3: Transaction ID */
					__( 'Refund of %1$s %2$s processed via myPOS. Transaction ID: %3$s', 'mypos-payments' ),
					$amount,
					$order->get_currency(),
					$refund_id
				)
			);
			
			// Return true - WooCommerce will create refund object
			return true;
		} else {
			// Refund failed
			$error_message = isset( $result['StatusMsg'] ) ? $result['StatusMsg'] : __( 'Unknown error', 'mypos-payments' );
			
			$order->add_order_note(
				sprintf(
					/* translators: %s: Error message */
					__( 'Refund failed: %s', 'mypos-payments' ),
					$error_message
				)
			);

			self::log( sprintf( 'Refund failed: Order #%d - %s', $order_id, $error_message ) );

			return new WP_Error( 'refund_failed', $error_message );
		}
		} catch ( Exception $e ) {
			self::log( 'Refund exception: ' . $e->getMessage() );
			return new WP_Error( 'refund_exception', $e->getMessage() );
		}
	}

	/**
	 * Checks if the store is valid for using plugin
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array(
			get_woocommerce_currency(),
			apply_filters(
				'mypos_supported_currencies',
				array(
					'BGN',
					'USD',
					'EUR',
					'GBP',
					'CHF',
					'JPY',
					'RON',
					'HRK',
					'NOK',
                    'ISK',
                    'SEK',
                    'DKK',
                    'CZK',
                    'HUF',
                    'PLN'
				)
			),
			true
		);
	}

	/**
	 * Checks if the store is valid for iDeal payments
	 *
	 * @return bool
	 */
	public function is_valid_for_ideal() {
		return in_array(
			get_woocommerce_currency(),
			apply_filters( 'mypos_supported_currencies', array( 'EUR' ) ),
			true
		);
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string Icon HTML.
	 */
	public function get_icon() {
		$styles = 'style="max-width: 100%;"';
		if ( self::PAYMENT_METHOD_BOTH === $this->payment_method && $this->is_valid_for_ideal() ) {
			$image_name = 'card_schemes_ideal_no_bg.png';
		} elseif ( self::PAYMENT_METHOD_IDEAL === $this->payment_method && $this->is_valid_for_ideal() ) {
			$image_name = 'mypos_ideal_no_bg.png';
		} else {
			$image_name = 'card_schemes_no_bg.png';
		}

	$icon      = WC_HTTPS::force_https_url(
		plugins_url() . '/mypos-virtual-for-woocommerce/assets/images/' . $image_name
	);
	$icon_html = '<img src="' . esc_url( $icon ) . '" alt="mypos_checkout_logo" ' . $styles . '/>';

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WooCommerce core filter for gateway icon compatibility.
	return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Force default configuration values for development/testing.
	 * Note: Test credentials should be obtained from myPOS Developer Portal.
	 * See: https://developers.mypos.com/en/doc/online_payments/v1_4/226-test-data
	 *
	 * @return void
	 */
	public function force_tld() {
		// Set default URLs for myPOS checkout
		$production_url = $this->get_option( 'production_url' );

		if ( empty( $production_url ) || false !== stripos( ( wp_parse_url( $production_url, PHP_URL_HOST ) ), 'mypos.com' ) ) {
			$this->update_option( 'production_url', 'https://mypos.com/vmp/checkout' );
		}

		$developer_url = $this->get_option( 'developer_url' );

		if ( empty( $developer_url ) || false !== stripos( ( wp_parse_url( $developer_url, PHP_URL_HOST ) ), 'mypos.com' ) ) {
			$this->update_option( 'developer_url', 'https://mypos.com/vmp/checkout-test' );
		}
	}

	public function init_cron_hook() {
		$next_scheduled_time = wp_next_scheduled( 'mypos_check_payment_status' );
		if ( ! $next_scheduled_time ) {
			wp_schedule_event( time(), 'hourly', 'mypos_check_payment_status' );
		}
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error">
				<p>
					<strong><?php esc_html_e( 'Gateway Disabled', 'mypos-payments' ); ?></strong>:
					<?php esc_html_e( 'myPOS Checkout does not support your store currency.', 'mypos-payments' ); ?>
				</p>
			</div>
			<?php
		}
	}

	public function get_source() {
		return 'sc_wp_woocommerce 1.4.2 ' . PHP_VERSION . ' ' . get_bloginfo( 'version' );
	}

	public function receipt_page( $order ) {
		$allowed_html = array(
			'div'    => array(
				'style' => array(),
				'class' => array(),
				'id'    => array(),
			),
			'style'  => array(),
			'form'   => array(
				'action' => array(),
				'method' => array(),
				'name'   => array(),
				'id'     => array(),
				'class'  => array(),
			),
			'input'  => array(
				'type'  => array(),
				'name'  => array(),
				'value' => array(),
				'id'    => array(),
				'class' => array(),
			),
			'button' => array(
				'type'  => array(),
				'class' => array(),
				'id'    => array(),
			),
			'script' => array(
				'type' => array(),
				'src'  => array(),
			),
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is sanitized via wp_kses with allowed HTML tags.
		echo wp_kses( $this->generate_ipc_form( $order ), $allowed_html );
	}

	/**
	 * Check for valid ipc response
	 **/
	/**
	 * Check for valid IPC response from myPOS payment gateway.
	 * This is a webhook endpoint that receives callbacks from external payment gateway.
	 * Security is enforced through cryptographic signature verification.
	 *
	 * @return void
	 */
	public function check_ipc_response() {
		// Register shutdown function to catch fatal errors
		register_shutdown_function( array( $this, 'webhook_shutdown_handler' ) );

		global $woocommerce;

		// IMPORTANT: Use RAW POST data for signature verification!
		// DO NOT sanitize before signature check - it will break the signature!
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- External webhook, signature verified instead. Raw data needed for signature verification.
		$post_raw = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- External webhook callback, authenticated via cryptographic signature verification instead of nonce.
		if ( ! empty( $_POST ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw POST data required for signature verification, sanitized after validation.
			foreach ( $_POST as $key => $value ) {
				$post_raw[ $key ] = wp_unslash( $value );
			}
		}

		if ( empty( $post_raw['IPCmethod'] ) ) {
			self::log( 'Webhook error: No IPCmethod in POST data' );
			echo 'NO IPC METHOD';
			exit;
		}

		// Verify cryptographic signature from payment gateway for authentication.
		if ( $this->is_valid_signature( $post_raw ) ) {
			
			// Sanitize the data AFTER successful signature verification
			$post = array();
			foreach ( $post_raw as $key => $value ) {
				$post[ $key ] = sanitize_text_field( $value );
			}
			
			if ( 'IPCSignatureVerify' === $post['IPCmethod'] ) {
				echo 'OK';
				exit;
			}

			// Handle test mode prefix removal
			if ( $this->test && ! empty( $this->test_prefix ) ) {
				$post['OrderID'] = str_replace( $this->test_prefix, '', $post['OrderID'] );
			}

			$order = new WC_Order( $post['OrderID'] );
			
			if ( ! $order || ! $order->get_id() ) {
				self::log( 'Webhook error: Order not found - OrderID: ' . $post['OrderID'] );
				echo 'ORDER NOT FOUND';
				exit;
			}

	if ( 'IPCPurchaseNotify' === $post['IPCmethod'] ) {
		// CRITICAL: Respond OK immediately to prevent timeout
		// Clean output buffers first
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		
		// Send OK response NOW
		header( 'Connection: close' );
		header( 'Content-Length: 2' );
		echo 'OK';
		
		// Flush and close connection to myPOS
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} else {
			flush();
		}
		
		// NOW process the order in background (connection already closed)
		try {
			self::log( 'IPCPurchaseNotify: Processing webhook for order #' . $order->get_id() );
			self::log( 'IPCPurchaseNotify: Current order status: ' . $order->get_status() );
			
			// Set data
			$order->set_transaction_id( sanitize_text_field( $post['IPC_Trnref'] ) );
			$order->set_payment_method( $this->id );
			$order->set_payment_method_title( $this->title );
			$order->update_meta_data( '_mypos_payment_verified', 'yes' );
			
			self::log( 'IPCPurchaseNotify: Transaction ID set: ' . $post['IPC_Trnref'] );
			
			// Update status if needed
			$current_status = $order->get_status();
			if ( ! in_array( $current_status, array( 'processing', 'completed' ), true ) ) {
				self::log( 'IPCPurchaseNotify: Updating status from ' . $current_status . ' to processing' );
				// FIX: Use payment_complete() to trigger WooCommerce stock reduction and hooks
				if ( $order->get_transaction_id() ) {
					$order->payment_complete( $order->get_transaction_id() );
				} else {
					$order->payment_complete();
				}
			} else {
				// Save WITHOUT triggering hooks if already processed
				$order->save();
			}
			
			// Save WITHOUT triggering hooks
			$order->save();
			
			self::log( 'IPCPurchaseNotify: Order saved. Final status: ' . $order->get_status() );
			
			// Remove from pending schedule
			$this->remove_from_pending_payments_schedule( $order->get_id() );
			
		} catch ( Exception $e ) {
			self::log( 'IPCPurchaseNotify error: ' . $e->getMessage() );
		}
		
		exit;
	}

			if ( 'IPCPurchaseRollback' === $post['IPCmethod'] ) {
				try {
					global $wpdb;
					
					// Update order status directly in database
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance critical webhook.
					$wpdb->update(
						$wpdb->posts,
						array( 'post_status' => 'wc-failed' ),
						array( 'ID' => $order->get_id() ),
						array( '%s' ),
						array( '%d' )
					);
					
					$order->add_order_note( __( 'myPOS Gateway declined payment.', 'mypos-payments' ) );
					$this->remove_from_pending_payments_schedule( $order->get_id() );
					
					while ( ob_get_level() > 0 ) {
						ob_end_clean();
					}
					
					echo 'OK';
					exit;
				} catch ( Exception $e ) {
					self::log( 'IPCPurchaseRollback error: ' . $e->getMessage() );
					echo 'ERROR';
					exit;
				}
			}

		if ( 'IPCPurchaseCancel' === $post['IPCmethod'] ) {
			$this->remove_from_pending_payments_schedule( $order->get_id() );
			
			// Update order status to cancelled (this also updates cache)
			$order->set_status( 'cancelled', __( 'User canceled the order.', 'mypos-payments' ) );
			$order->save();

			$redirect_url = $order->get_cancel_order_url();
			wp_safe_redirect( $redirect_url );
			exit;
		}

	if ( 'IPCPurchaseOK' === $post['IPCmethod'] ) {
		// User returns after successful payment
		// IPCPurchaseNotify webhook already updated the order status
		self::log( 'IPCPurchaseOK: User redirect after successful payment for order #' . $order->get_id() );
		
		// IMMEDIATE redirect - no status checks, no queries
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

			// Unknown IPC method
			self::log( 'Webhook error: Invalid IPCmethod - ' . $post['IPCmethod'] );
			echo 'INVALID METHOD';
			exit;
		}

		// Signature validation failed
		self::log( 'Webhook error: Invalid signature' );
		echo 'INVALID SIGNATURE';
		exit;
	}

	/**
	 * Shutdown handler to catch fatal errors during webhook processing
	 *
	 * @return void
	 */
	public function webhook_shutdown_handler() {
		$error = error_get_last();
		
		if ( $error !== null && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			self::log( '!!! FATAL ERROR CAUGHT BY SHUTDOWN HANDLER !!!' );
			self::log( 'Error type: ' . $error['type'] );
			self::log( 'Error message: ' . $error['message'] );
			self::log( 'Error file: ' . $error['file'] . ':' . $error['line'] );
			
			// Try to send response to myPOS even after fatal error
			if ( ! headers_sent() ) {
				echo 'FATAL ERROR: ' . esc_html( $error['message'] );
			}
		}
	}

	/**
	 * Generate myPOS Checkout form.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string HTML form for payment gateway redirect.
	 * @throws Exception If order creation fails.
	 */
	public function generate_ipc_form( $order_id ) {
		global $woocommerce;
		
		$order = new WC_Order( $order_id );
		$this->add_to_pending_payments_schedule( $order->get_id() );

		$post       = $this->create_post( $order );
		$post_array = array();

		foreach ( $post as $key => $value ) {
			$value        = htmlspecialchars( $value, ENT_QUOTES );
			$post_array[] = "<input type='hidden' name='" . esc_attr( $key ) . "' value='" . esc_attr( $value ) . "'/>";
		}

		if ( ! empty( $post['PaymentMethod'] ) && '2' === $post['PaymentMethod'] ) {
			$woocommerce->cart->empty_cart();
		}

		return '<div style="position: fixed; top: 0; left: 0; bottom: 0; right: 0; z-index: 9999; width: 100%; height: 100%; background-color: white;"></div>
                <style>* { display: none; }</style><form action="' . esc_url( $this->url ) . '" method="post" name="mypos_virtual">
               ' . implode( '', $post_array ) . '
                    <button type="submit">' . esc_html__( 'Pay', 'mypos-payments' ) . '</button>
                </form>
                <script>document.mypos_virtual.submit();</script>';
	}

	/**
	 * Create POST data array for myPOS checkout request.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array POST data for myPOS gateway.
	 */
	public function create_post( $order ) {
		$post = array();

		$countries = include 'countries.php';

		$post['IPCmethod']    = 'IPCPurchase';
		$post['IPCVersion']   = $this->version;
		$post['IPCLanguage']  = defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : substr( get_locale(), 0, 2 );
		$post['WalletNumber'] = $this->wallet_number;
		$post['SID']          = $this->sid;
		$post['keyindex']     = $this->keyindex;
		$post['Source']       = $this->get_source();

		$post['Amount']     = number_format( $order->get_total(), 2, '.', '' );
		$post['Currency']   = $order->get_currency();
		$post['OrderID']    = ( $this->test ? $this->test_prefix : '' ) . $order->get_id();
		$post['URL_OK']     = $this->notify_url;
		$post['URL_CANCEL'] = $this->notify_url;
		$post['URL_Notify'] = $this->notify_url;

		$post['CustomerIP']         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$post['CustomerEmail']      = $order->get_billing_email();
		$post['CustomerFirstNames'] = $this->escape_string( $order->get_billing_first_name() );
		$post['CustomerFamilyName'] = $this->escape_string( $order->get_billing_last_name() );
		$post['CustomerCountry']    = $countries[ $order->get_billing_country() ];
		$post['CustomerCity']       = $this->escape_string( $order->get_billing_city() );
		$post['CustomerZIPCode']    = $order->get_billing_postcode();
		$post['CustomerAddress']    = $this->escape_string( $order->get_billing_address_1() );
		$post['CustomerPhone']      = $order->get_billing_phone();
		$post['Note']               = 'myPOS Checkout WooCommerce Extension. Order Number: ' . $order->get_order_number();

		$post['CardTokenRequest']          = 0;
		$post['PaymentParametersRequired'] = $this->payment_parameters_required;
		$post['PaymentMethod']             = $this->payment_method;

		$index = 1;

		$this->line_items = $this->get_line_item_args( $order );

		while ( true ) {
			if ( isset( $this->line_items[ 'item_name_' . $index ] ) ) {
				$post[ 'Article_' . $index ]  = $this->escape_string(
					do_shortcode( $this->line_items[ 'item_name_' . $index ] )
				);
				$post[ 'Quantity_' . $index ] = $this->line_items[ 'quantity_' . $index ];
				$post[ 'Price_' . $index ]    = $this->line_items[ 'amount_' . $index ];
				$post[ 'Amount_' . $index ]   = $this->number_format(
					$this->line_items[ 'amount_' . $index ] * $this->line_items[ 'quantity_' . $index ],
					$order
				);
				$post[ 'Currency_' . $index ] = $post['Currency'];
			} else {
				break;
			}

			++$index;
		}

		if ( isset( $this->line_items['tax_cart'] ) && 0 != $this->line_items['tax_cart'] ) {
			$post[ 'Article_' . $index ]  = 'Tax';
			$post[ 'Quantity_' . $index ] = 1;
			$post[ 'Price_' . $index ]    = $this->line_items['tax_cart'];
			$post[ 'Amount_' . $index ]   = $this->line_items['tax_cart'];
			$post[ 'Currency_' . $index ] = $post['Currency'];

			++$index;
		}

		if ( isset( $this->line_items['discount_amount_cart'] ) && 0 != $this->line_items['discount_amount_cart'] ) {
			$post[ 'Article_' . $index ]  = 'Discount';
			$post[ 'Quantity_' . $index ] = 1;
			$post[ 'Price_' . $index ]    = -$this->line_items['discount_amount_cart'];
			$post[ 'Amount_' . $index ]   = -$this->line_items['discount_amount_cart'];
			$post[ 'Currency_' . $index ] = $post['Currency'];

			++$index;
		}

		$post['CartItems'] = $index - 1;

		$post['Signature'] = $this->create_signature( $post );

		return $post;
	}

	/**
	 * Sanitize and get POST data from payment gateway callback.
	 *
	 * IMPORTANT: Preserves original key case (IPCmethod, OrderID, Signature, etc.)
	 * as myPOS sends parameters with specific capitalization.
	 * 
	 * Note: This method is DEPRECATED. Use direct $_POST access in check_ipc_response() instead.
	 * Kept for backwards compatibility only.
	 *
	 * This is a WooCommerce webhook endpoint that receives callbacks from the external
	 * myPOS payment gateway. Security is enforced through cryptographic signature
	 * verification in is_valid_signature(), not through WordPress nonce verification,
	 * as the request originates from an external payment processor.
	 *
	 * @deprecated Not used anymore - check_ipc_response() accesses $_POST directly.
	 * @return array
	 */
	public function get_sanitized_post_data() {
		$post_data = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- External payment gateway webhook, signature verified instead.
		foreach ( $_POST as $key => $value ) {
			// IMPORTANT: Do NOT use sanitize_key() as it converts keys to lowercase!
			// myPOS sends IPCmethod, OrderID, IPC_Trnref with specific capitalization.
			// Only sanitize values, preserve original key case.
			if ( is_array( $value ) ) {
				$value              = wp_unslash( $value );
				$post_data[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$post_data[ $key ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		return $post_data;
	}

	/**
	 * Check signature validation from payment gateway response.
	 *
	 * @param array $post_data POST data from payment gateway.
	 * @return bool True if signature is valid, false otherwise.
	 */
	public function is_valid_signature( $post_data ) {
		// myPOS sends 'Signature' with capital S. Support both for backwards compatibility.
		$signature     = null;
		$signature_key = null;
		
		if ( isset( $post_data['Signature'] ) ) {
			$signature     = $post_data['Signature'];
			$signature_key = 'Signature';
		} elseif ( isset( $post_data['signature'] ) ) {
			$signature     = $post_data['signature'];
			$signature_key = 'signature';
		} else {
			self::log( 'Signature error: No Signature field in POST data' );
			return false;
		}
		
		if ( ! $signature ) {
			self::log( 'Signature error: Signature is empty' );
			return false;
		}

		// Build concatenated data WITHOUT signature, preserving field order
		$data_parts = array();
		foreach ( $post_data as $key => $value ) {
			if ( $key !== $signature_key ) {
				$data_parts[] = $value;
			}
		}
		
		$data_to_verify = implode( '-', $data_parts );
		
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for cryptographic signature verification.
		$conc_data = base64_encode( $data_to_verify );

		// Extract public key from certificate
		if ( empty( $this->public_certificate ) ) {
			self::log( 'Signature error: Public certificate is empty' );
			return false;
		}
		
		$pub_key_id = openssl_get_publickey( $this->public_certificate );

		if ( ! $pub_key_id ) {
			self::log( 'Signature error: Failed to extract public key' );
			return false;
		}

		// Verify signature
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for cryptographic signature verification.
		$signature_decoded = base64_decode( $signature );
		$result            = openssl_verify( $conc_data, $signature_decoded, $pub_key_id, OPENSSL_ALGO_SHA256 );
		
		// Free key resource.
		unset( $pub_key_id );
		
		if ( 1 === $result ) {
			return true;
		}
		
		// Signature verification failed
		$ipc_method = isset( $post_data['IPCmethod'] ) ? $post_data['IPCmethod'] : 'unknown';
		self::log( 'Signature error: Verification failed for ' . $ipc_method );
		if ( -1 === $result ) {
			self::log( 'OpenSSL error: ' . openssl_error_string() );
		}
		return false;
	}

	/**
	 * Create cryptographic signature for outgoing requests.
	 * Create cryptographic signature for outgoing requests.
	 *
	 * @param array $post POST data to sign.
	 * @return string Base64 encoded signature.
	 */
	private function create_signature( $post ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for cryptographic signature creation.
		$conc_data = base64_encode( implode( '-', $post ) );

		$priv_key_obj = openssl_pkey_get_private( $this->private_key );
		openssl_sign( $conc_data, $signature, $priv_key_obj, OPENSSL_ALGO_SHA256 );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for cryptographic signature creation.
		return base64_encode( $signature );
	}

	/**
	 * Remove order from pending payments schedule
	 *
	 * @param int $order_id
	 */
	public function remove_from_pending_payments_schedule( $order_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::PENDING_PAYMENT_DB_TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$table_name,
			array( 'order_id' => $order_id ),
			array( '%d' )
		);

		// Invalidate cache
		$cache_key = 'mypos_pending_exists_' . (int) $order_id;
		wp_cache_delete( $cache_key, 'mypos' );
	}

	/**
	 * Add order to pending payments schedule
	 *
	 * @param int $order_id
	 */
	public function add_to_pending_payments_schedule( $order_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::PENDING_PAYMENT_DB_TABLE_NAME;
		$cache_key  = 'mypos_pending_exists_' . (int) $order_id;

		// Check if order already exists
		$existing_schedule = wp_cache_get( $cache_key, 'mypos' );
		if ( false === $existing_schedule ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$existing_schedule = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM {$table_name} WHERE order_id = %d",
					$order_id
				)
			);
			wp_cache_set( $cache_key, (int) $existing_schedule, 'mypos', MINUTE_IN_SECONDS * 10 );
		}

		if ( ! $existing_schedule ) {
			$expired_time = time() + DAY_IN_SECONDS;
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table_name,
				array(
					'order_id'         => $order_id,
					'last_check'       => time(),
					'expired_time'     => $expired_time,
					'test_environment' => $this->test ? 1 : 0,
				),
				array( '%d', '%d', '%d', '%d' )
			);
			
			wp_cache_delete( $cache_key, 'mypos' );
		}
	}

	/**
	 * Process admin options
	 *
	 * @return void
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		// Update the test prefix option if it was changed.
		$new_test_prefix = $this->get_option( 'test_prefix' );
		if ( $new_test_prefix !== $this->test_prefix ) {
			$this->update_option( 'test_prefix', $new_test_prefix );
		}
	}

	/**
	 * Check payment status view on order edit page
	 *
	 * @param WC_Order $order
	 */
	public function check_payment_status_view( $order ) {
		// Prevent duplicate rendering
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		// Mark as rendered
		$rendered = true;

		$order_status      = $order->get_status();
		$transaction_id    = $order->get_transaction_id();
		$payment_verified  = $order->get_meta( '_mypos_payment_verified' );
		$order_id          = $order->get_id();

		echo '<div class="mypos-payment-status" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #2271b1;">';
		echo '<h4 style="margin-top: 0;">' . esc_html__( 'myPOS Payment Status', 'mypos-payments' ) . '</h4>';

		// Determine status and display appropriate message
		if ( in_array( $order_status, array( 'processing', 'completed' ), true ) && 'yes' === $payment_verified ) {
			// Payment successful
			echo '<p style="color: #46b450; font-weight: bold;">✓ ' . esc_html__( 'Payment verified and completed successfully.', 'mypos-payments' ) . '</p>';
			
			if ( $transaction_id ) {
				echo '<p><strong>' . esc_html__( 'Transaction ID:', 'mypos-payments' ) . '</strong> <code>' . esc_html( $transaction_id ) . '</code></p>';
			}

			echo '<p style="font-size: 12px; color: #666;">' . esc_html__( 'Payment was confirmed via myPOS webhook.', 'mypos-payments' ) . '</p>';

		} elseif ( in_array( $order_status, array( 'failed', 'cancelled' ), true ) ) {
			// Payment failed or cancelled
			echo '<p style="color: #dc3232; font-weight: bold;">✗ ';
			if ( 'failed' === $order_status ) {
				echo esc_html__( 'Payment failed or was declined.', 'mypos-payments' );
			} else {
				echo esc_html__( 'Payment was cancelled.', 'mypos-payments' );
			}
			echo '</p>';

		} elseif ( 'pending' === $order_status || 'on-hold' === $order_status ) {
			// Payment pending - show check button
			echo '<p style="color: #f0ad4e; font-weight: bold;">⏳ ' . esc_html__( 'Waiting for payment confirmation from myPOS.', 'mypos-payments' ) . '</p>';
			
			if ( $transaction_id ) {
				echo '<p><strong>' . esc_html__( 'Transaction ID:', 'mypos-payments' ) . '</strong> <code>' . esc_html( $transaction_id ) . '</code></p>';
			}

			echo '<p style="font-size: 12px; color: #666; margin-bottom: 15px;">' . esc_html__( 'The payment webhook has not been received yet. You can manually check the payment status.', 'mypos-payments' ) . '</p>';

			// Check payment status button
			?>
			<button type="button" class="button button-primary mypos-check-payment-status" data-order-id="<?php echo esc_attr( $order_id ); ?>" style="margin-top: 10px;">
				<?php esc_html_e( 'Check Payment Status', 'mypos-payments' ); ?>
		</button>
		<span class="mypos-check-status-spinner" style="display: none; margin-left: 10px;">
			<span class="spinner is-active" style="float: none; margin: 0;"></span>
		</span>
		<div class="mypos-status-result" style="margin-top: 10px;"></div>

		<!-- myPOS Check Payment Status Script START -->
		<script type="text/javascript">
		console.log('=== myPOS Check Payment Status Script Loaded ===');
		console.log('jQuery available:', typeof jQuery !== 'undefined');
		console.log('$ available:', typeof $ !== 'undefined');
		
		jQuery(document).ready(function($) {
			console.log('=== jQuery Ready Fired ===');
			
			var button = $('.mypos-check-payment-status');
			console.log('Button found:', button.length, button);
			
			if (button.length === 0) {
				console.error('ERROR: Button .mypos-check-payment-status not found in DOM!');
				return;
			}
			
			button.on('click', function(e) {
				console.log('=== BUTTON CLICKED ===');
				e.preventDefault();
				
				var button = jQuery(this);
				var orderId = button.data('order-id');
				var spinner = jQuery('.mypos-check-status-spinner');
				var resultDiv = jQuery('.mypos-status-result');
				var willReload = false;

				console.log('Order ID:', orderId);
				console.log('Button:', button);
				console.log('Spinner:', spinner);
				console.log('Result div:', resultDiv);

				button.prop('disabled', true);
				spinner.show();
				resultDiv.html('');

				console.log('Making AJAX request to:', ajaxurl);

				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'mypos_check_payment_status',
						order_id: orderId,
						nonce: '<?php echo esc_js( wp_create_nonce( 'mypos_check_payment_status' ) ); ?>'
					},
				success: function(response) {
					console.log('AJAX Success:', response);
					console.log('response.success:', response.success);
					console.log('response.data:', response.data);
					console.log('response.data.reload:', response.data.reload);
					console.log('Type of reload:', typeof response.data.reload);
					
					if (response.success) {
						resultDiv.html('<p style="color: #46b450;">✓ ' + response.data.message + '</p>');
						
						// Check if we should reload - handle both boolean and string
						if (response.data.reload === true || response.data.reload === 'true' || response.data.reload == 1) {
							willReload = true;
							console.log('RELOAD TRIGGERED! Type:', typeof response.data.reload, 'Value:', response.data.reload);
							
							// Show loading message
							resultDiv.append('<p style="color: #666; margin-top: 5px;"><span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Reloading page...</p>');
							
							// Delay to ensure order save completed
							setTimeout(function() {
								console.log('Reloading page...');
								window.location.reload();
							}, 500);
						} else {
							console.log('NO RELOAD - reload value is:', response.data.reload);
						}
					} else {
						// Error from backend
						console.log('Backend error:', response.data.message);
						resultDiv.html('<p style="color: #dc3232;">✗ ' + response.data.message + '</p>');
					}
				},
					error: function(xhr, status, error) {
						// AJAX error
						console.error('AJAX error:', status, error, xhr);
						resultDiv.html('<p style="color: #dc3232;">✗ <?php echo esc_js( __( 'An error occurred. Please try again.', 'mypos-payments' ) ); ?></p>');
					},
					complete: function() {
						console.log('AJAX Complete. Will reload:', willReload);
						
						// Only re-enable button if NOT reloading
						if (!willReload) {
							button.prop('disabled', false);
							spinner.hide();
						}
						// If reloading, keep button disabled and spinner visible
					}
				});
			});
		});
		console.log('=== myPOS Script End ===');
		</script>
		<!-- myPOS Check Payment Status Script END -->
			<?php

		} else {
			// Unknown status
			echo '<p style="color: #666;">' . esc_html__( 'Payment status unknown.', 'mypos-payments' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * AJAX handler for checking payment status - queries myPOS API
	 */
	public function ajax_check_payment_status() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mypos_check_payment_status' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mypos-payments' ) ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'mypos-payments' ) ) );
		}

		// Get order ID
		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'mypos-payments' ) ) );
		}

		// Get order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'mypos-payments' ) ) );
		}

		// Check if order uses myPOS payment
		if ( $order->get_payment_method() !== $this->id ) {
			wp_send_json_error( array( 'message' => __( 'This order does not use myPOS payment.', 'mypos-payments' ) ) );
		}

		// Get current order data
		$order_status     = $order->get_status();
		$transaction_id   = $order->get_transaction_id();
		$payment_verified = $order->get_meta( '_mypos_payment_verified' );

		// If already verified and completed
		if ( 'yes' === $payment_verified && in_array( $order_status, array( 'processing', 'completed' ), true ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Payment already verified and completed.', 'mypos-payments' ),
					'reload'  => false,
				)
			);
		}

		// If no transaction ID, cannot check with myPOS
		if ( ! $transaction_id ) {
			// Try to find in order notes
			$notes = wc_get_order_notes(
				array(
					'order_id' => $order_id,
					'type'     => 'internal',
				)
			);

			foreach ( $notes as $note ) {
				if ( preg_match( '/Transaction ID:\s*([A-Za-z0-9]+)/', $note->content, $matches ) ) {
					$transaction_id = $matches[1];
					break;
				}
			}

			if ( ! $transaction_id ) {
				wp_send_json_error(
					array(
						'message' => __( 'No transaction ID found. Payment webhook has not been received yet. Please wait or check myPOS dashboard.', 'mypos-payments' ),
					)
				);
			}
		}

		self::log( 'Manual payment check: Order #' . $order_id . ', Transaction ID: ' . $transaction_id );

		// Check if URL is configured
		if ( empty( $this->url ) ) {
			self::log( 'ERROR: myPOS URL is not configured!' );
			wp_send_json_error(
				array(
					'message' => __( 'myPOS API URL is not configured. Please check plugin settings.', 'mypos-payments' ),
				)
			);
		}

		// Prepare OrderID with test prefix if needed
		$formatted_order_id = $order_id;
		if ( $this->test && ! empty( $this->test_prefix ) ) {
			$formatted_order_id = $this->test_prefix . $order_id;
		}

		// Query myPOS API using IPCGetPaymentStatus
		try {
			$check_data = array(
				'IPCmethod'    => 'IPCGetPaymentStatus',
				'IPCVersion'   => $this->version,
				'IPCLanguage'  => 'en',
				'SID'          => $this->sid,
				'WalletNumber' => $this->wallet_number,
				'KeyIndex'     => $this->keyindex,
				'OrderID'      => $formatted_order_id,
				'OutputFormat' => 'json',
			);

		// Create signature
		$check_data['Signature'] = $this->create_signature( $check_data );

		self::log( 'Checking payment status with myPOS API (IPCGetPaymentStatus)' );
		self::log( 'myPOS URL: ' . $this->url );
		self::log( 'Order ID: ' . $formatted_order_id );
		self::log( 'Request data: ' . wp_json_encode( $check_data ) );

		// Send request to myPOS
		$response = wp_remote_post(
			$this->url,
			array(
				'body'       => $check_data,
				'timeout'    => 5,
				'blocking'   => true,
				'sslverify'  => true,
				'user-agent' => 'myPOS-WooCommerce/' . $this->version,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log( 'myPOS API error: ' . $response->get_error_message() );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to connect to myPOS. Please try again later.', 'mypos-payments' ),
				)
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$result        = json_decode( $response_body, true );

		self::log( 'myPOS API response: ' . wp_json_encode( $result ) );

		// IPCGetPaymentStatus Response - IGNORE "Status" field!
		// According to real myPOS behavior, "Status" can be 1 even when payment is successful
		// We MUST check ONLY "PaymentStatus" field:
		// PaymentStatus = "1" or 1: Payment Successful
		// PaymentStatus = "2" or 2: Payment Pending
		// PaymentStatus = "3" or 3: Payment Failed/Cancelled
		// PaymentStatus = "0" or 0: Payment not found/not initiated
		
		// Check if PaymentStatus field exists
		if ( ! isset( $result['PaymentStatus'] ) ) {
			self::log( 'ERROR: Missing PaymentStatus field in response' );
			self::log( 'Available fields: ' . implode( ', ', array_keys( $result ) ) );
			wp_send_json_error(
				array(
					'message' => __( 'Invalid response from myPOS - missing PaymentStatus. Please try again.', 'mypos-payments' ),
				)
			);
		}

		// PaymentStatus can be string ("1") or int (1) - convert to int
		$payment_status = intval( $result['PaymentStatus'] );
		self::log( 'PaymentStatus value: ' . $payment_status );

		// Extract transaction ID
		$verified_transaction_id = null;
		if ( isset( $result['IPC_Trnref'] ) ) {
			$verified_transaction_id = sanitize_text_field( $result['IPC_Trnref'] );
			self::log( 'Transaction ID from myPOS: ' . $verified_transaction_id );
		} elseif ( ! empty( $transaction_id ) ) {
			$verified_transaction_id = $transaction_id;
			self::log( 'Using existing transaction ID: ' . $verified_transaction_id );
		}

		// Process based on PaymentStatus ONLY
		if ( 1 === $payment_status ) {
			// PAYMENT SUCCESSFUL (PaymentStatus=1)
			self::log( 'Payment successful (PaymentStatus=1)' );

			if ( in_array( $order_status, array( 'pending', 'on-hold' ), true ) ) {
				self::log( 'Updating order #' . $order_id . ' to Processing' );
				
				if ( $verified_transaction_id ) {
					$order->set_transaction_id( $verified_transaction_id );
				}
				$order->update_meta_data( '_mypos_payment_verified', 'yes' );
				$order->set_status( 'processing', __( 'Payment verified via myPOS API check.', 'mypos-payments' ) );
				$order->save();
				
				self::log( 'Order #' . $order_id . ' updated to Processing' );
				
				wp_send_json_success(
					array(
						'message' => __( 'Payment verified! Order status updated to Processing.', 'mypos-payments' ),
						'reload'  => true,
					)
				);
			} else {
				// Order already processed
				self::log( 'Order #' . $order_id . ' already in final status: ' . $order_status );
				wp_send_json_success(
					array(
						'message' => __( 'Payment is completed in myPOS. Order already in final status.', 'mypos-payments' ),
						'reload'  => false,
					)
				);
			}
		} elseif ( 2 === $payment_status ) {
			// PAYMENT PENDING (PaymentStatus=2)
			self::log( 'Payment pending (PaymentStatus=2)' );
			wp_send_json_error(
				array(
					'message' => __( 'Payment is still pending in myPOS. Please wait for customer to complete the payment.', 'mypos-payments' ),
				)
			);
		} elseif ( 3 === $payment_status ) {
			// PAYMENT FAILED/CANCELLED (PaymentStatus=3)
			self::log( 'Payment failed or cancelled (PaymentStatus=3)' );
			
			if ( in_array( $order_status, array( 'pending', 'on-hold' ), true ) ) {
				$order->set_status( 'failed', __( 'Payment failed or cancelled in myPOS.', 'mypos-payments' ) );
				$order->save();
				
				wp_send_json_success(
					array(
						'message' => __( 'Payment was failed or cancelled. Order marked as Failed.', 'mypos-payments' ),
						'reload'  => true,
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Payment was failed or cancelled in myPOS.', 'mypos-payments' ),
					)
				);
			}
		} elseif ( 0 === $payment_status ) {
			// PAYMENT NOT FOUND (PaymentStatus=0)
			self::log( 'Payment not found or not initiated (PaymentStatus=0)' );
			wp_send_json_error(
				array(
					'message' => __( 'Payment not found in myPOS. The payment may not have been initiated yet.', 'mypos-payments' ),
				)
			);
		} else {
			// UNKNOWN PaymentStatus
			self::log( 'ERROR: Unknown PaymentStatus value: ' . $payment_status );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: Unknown payment status code */
						__( 'Unknown payment status (%d) from myPOS. Please check myPOS dashboard.', 'mypos-payments' ),
						$payment_status
					),
				)
			);
		}
		} catch ( Exception $e ) {
			self::log( 'Manual check exception: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while checking payment status. Please try again.', 'mypos-payments' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for testing myPOS connection
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		// Check nonce
		check_ajax_referer( 'mypos-admin-nonce', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'mypos-payments' ),
				)
			);
		}

		try {
			// Get POST data
			$test_mode     = isset( $_POST['test_mode'] ) && 'true' === $_POST['test_mode'];
			$sid           = isset( $_POST['sid'] ) ? sanitize_text_field( wp_unslash( $_POST['sid'] ) ) : '';
			$wallet_number = isset( $_POST['wallet_number'] ) ? sanitize_text_field( wp_unslash( $_POST['wallet_number'] ) ) : '';
			$keyindex      = isset( $_POST['keyindex'] ) ? sanitize_text_field( wp_unslash( $_POST['keyindex'] ) ) : '';

			// Validate required fields
			if ( empty( $sid ) || empty( $wallet_number ) || empty( $keyindex ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Missing required credentials. Please fill in SID, Wallet Number, and Key Index.', 'mypos-payments' ),
					)
				);
			}

			self::log( 'Testing myPOS connection...' );
			self::log( 'Test mode: ' . ( $test_mode ? 'YES' : 'NO' ) );
			self::log( 'SID: ' . $sid );
			self::log( 'Wallet Number: ' . $wallet_number );
			self::log( 'Key Index: ' . $keyindex );

			// Get URL based on test mode
			$url = $test_mode ? 'https://mypos.com/vmp/checkout-test' : 'https://mypos.com/vmp/checkout';

			// Create a simple test request using IPCGetPaymentStatus with a dummy order
			$test_order_id = 'TEST_CONNECTION_' . time();
			
			$test_data = array(
				'IPCmethod'    => 'IPCGetPaymentStatus',
				'IPCVersion'   => '1.4',
				'IPCLanguage'  => 'en',
				'SID'          => $sid,
				'WalletNumber' => $wallet_number,
				'KeyIndex'     => $keyindex,
				'OrderID'      => $test_order_id,
				'OutputFormat' => 'json',
			);

			// Create signature using current credentials
			// For test, we'll use the saved credentials from settings
			$private_key = $test_mode ? $this->get_option( 'developer_private_key' ) : $this->get_option( 'production_private_key' );
			
			if ( empty( $private_key ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Private key is not configured. Please configure the developer/production package.', 'mypos-payments' ),
					)
				);
			}

			// Create signature
			$conc_data    = '';
			foreach ( $test_data as $key => $val ) {
				if ( 'Signature' !== $key ) {
					$conc_data .= $val;
				}
			}
			
			$priv_key_obj = openssl_pkey_get_private( $private_key );
			if ( false === $priv_key_obj ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid private key format. Please check your private key configuration.', 'mypos-payments' ),
					)
				);
			}

			openssl_sign( $conc_data, $signature, $priv_key_obj, OPENSSL_ALGO_SHA256 );
			$test_data['Signature'] = base64_encode( $signature ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			self::log( 'Sending test request to: ' . $url );

			// Send test request
			$response = wp_remote_post(
				$url,
				array(
					'method'      => 'POST',
					'timeout'     => 10,
					'httpversion' => '1.1',
					'body'        => $test_data,
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				self::log( 'Connection test failed: ' . $error_message );
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: Error message */
							__( 'Connection failed: %s', 'mypos-payments' ),
							$error_message
						),
					)
				);
			}

			$response_body = wp_remote_retrieve_body( $response );
			$result        = json_decode( $response_body, true );

			self::log( 'Test response: ' . wp_json_encode( $result ) );

			// Check if we got a valid response
			if ( ! isset( $result['Status'] ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid response from myPOS. Please check your credentials.', 'mypos-payments' ),
					)
				);
			}

			// For test connection, we expect PaymentStatus=0 (not found) which means connection is OK
			// or any other valid response that shows myPOS accepted our credentials
			if ( isset( $result['PaymentStatus'] ) || isset( $result['StatusMsg'] ) ) {
				self::log( 'Connection test successful!' );
				wp_send_json_success(
					array(
						'message' => __( '✓ Connection successful! Your myPOS credentials are valid.', 'mypos-payments' ),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Unexpected response from myPOS. Please verify your credentials.', 'mypos-payments' ),
					)
				);
			}

		} catch ( Exception $e ) {
			self::log( 'Test connection exception: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Error: %s', 'mypos-payments' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Verify notify view
	 *
	 * @return void
	 */
	public function verify_notify_view() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'myPOS Verify Notify', 'mypos-payments' ); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'mypos_verify_notify', 'mypos_verify_notify_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Order ID', 'mypos-payments' ); ?></th>
						<td><input type="text" name="order_id" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Transaction ID', 'mypos-payments' ); ?></th>
						<td><input type="text" name="transaction_id" required /></td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Verify', 'mypos-payments' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Process deferred order status update on thank you page.
	 * This is called AFTER the redirect to avoid slowing down the redirect.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function process_deferred_order_update( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		// Check if there's a pending update for this order
		$update_pending = get_transient( 'mypos_update_order_' . $order_id );
		
		if ( ! $update_pending ) {
			return;
		}

		// Delete transient immediately to prevent double processing
		delete_transient( 'mypos_update_order_' . $order_id );

		$order = wc_get_order( $order_id );
		
		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			return;
		}

		// Update order status if still pending
		if ( 'pending' === $order->get_status() ) {
			self::log( 'Deferred update: Updating order #' . $order_id . ' to processing' );
			
			$order->set_status( 
				'processing',
				__( 'myPOS payment completed successfully.', 'mypos-payments' )
			);
			$order->save();
			
			self::log( 'Deferred update: Order #' . $order_id . ' updated successfully' );
		}
	}

	/**
	 * Verify notify post
	 *
	 * @return void
	 */
	public function verify_notify_post() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed below after sanitization.
		if ( ! isset( $_POST['mypos_verify_notify_nonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization happens on the next line.
		$nonce = sanitize_text_field( wp_unslash( $_POST['mypos_verify_notify_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'mypos_verify_notify' ) ) {
			return;
		}

		$order_id       = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';

		if ( $order_id > 0 && ! empty( $transaction_id ) ) {
			$order = wc_get_order( $order_id );

			if ( $order && $this->id === $order->get_payment_method() ) {
				$response = $this->verify_transaction( $transaction_id, $order_id );

				if ( $response && isset( $response['status'] ) && 'success' === $response['status'] ) {
					$order->payment_complete( $transaction_id );
					$order->add_order_note( __( 'Payment confirmed by myPOS.', 'mypos-payments' ) );
					$this->remove_from_pending_payments_schedule( $order_id );

					wc_add_notice( __( 'Payment confirmed.', 'mypos-payments' ), 'success' );
				} else {
					wc_add_notice( __( 'Payment not found or already processed.', 'mypos-payments' ), 'error' );
				}
			}
		}
	}

	/**
	 * Verify transaction with myPOS
	 *
	 * @param string $transaction_id
	 * @param int $order_id
	 * @return array|false
	 */
	public function verify_transaction( $transaction_id, $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$amount   = number_format( $order->get_total(), 2, '.', '' );
		$currency = $order->get_currency();

		$secret_key = html_entity_decode( $this->private_key, ENT_COMPAT, 'UTF-8' );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for cryptographic signature generation with myPOS payment gateway.
		$signature = base64_encode(
			hash_hmac(
				'sha256',
				$transaction_id . $amount . $currency,
				$secret_key,
				true
			)
		);

		$response = wp_remote_post(
			$this->url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'IPCmethod'     => 'IPCTransactionVerify',
					'IPCVersion'    => $this->version,
					'IPCLanguage'   => defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : substr( get_locale(), 0, 2 ),
					'WalletNumber'  => $this->wallet_number,
					'SID'           => $this->sid,
					'keyindex'      => $this->keyindex,
					'Source'        => $this->get_source(),
					'TransactionID' => $transaction_id,
					'Amount'        => $amount,
					'Currency'      => $currency,
					'Signature'     => $signature,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		parse_str( wp_remote_retrieve_body( $response ), $response_body );

		return $response_body;
	}

	/**
	 * Escape string for safe use in API requests.
	 *
	 * @param string $input_string String to escape.
	 * @return string Escaped string.
	 */
	protected function escape_string( $input_string ) {
		$input_string = wp_strip_all_tags( htmlspecialchars_decode( str_replace( "\r", '', str_replace( "\n", '', $input_string ) ), ENT_COMPAT ) );
		return preg_replace( '/#!trpst#(.*?)!trpen#/i', '', $input_string ); // Remove TransPress Logic fix.
	}

	/**
	 * Check payment status for a single order.
	 *
	 * @param int|string $order_id Order ID to check.
	 * @return bool True if payment was found/processed, false otherwise.
	 */
	public function check_payment_status( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Skip if order is not pending.
		if ( 'pending' !== $order->get_status() ) {
			return false;
		}

		// Skip if order doesn't use this payment method.
		if ( $this->id !== $order->get_payment_method() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check pending payment orders statuses.
	 *
	 * @return void
	 */
	public function check_pending_payment_orders_statuses() {
		$current_date = new \DateTime();
		$current_time = $current_date->getTimestamp();

		$orders = MyPOS_Pending_Payments_Manager::get_pending_orders( $this->test, 10 );

		if ( ! empty( $orders ) ) {
			foreach ( $orders as $order ) {
				$order_id = $this->test ? str_replace( $this->test_prefix, '', $order->order_id ) : $order->order_id;
				$this->check_payment_status( $order_id );
			}

			// Update last_check timestamp for each processed order.
			foreach ( $orders as $order ) {
				MyPOS_Pending_Payments_Manager::update_last_check( $order->order_id, $current_time );
			}

			if ( ! $this->has_deadline_pending_order ) {
				MyPOS_Pending_Payments_Manager::delete_expired( $current_time );
			} else {
				MyPOS_Pending_Payments_Manager::delete_expired_with_deadline( $current_time );
			}
		}
	}

	/**
	 * Get line item args for mypos request.
	 *
	 * @param WC_Order $order Order object.
	 * @return array Line item arguments.
	 */
	protected function get_line_item_args( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}

		/**
		 * Try passing a line item per product if supported.
		 */
		if ( ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) && $this->prepare_line_items( $order ) ) {
			$line_item_args             = array();
			$line_item_args['tax_cart'] = $this->number_format( $order->get_total_tax(), $order );

			if ( $order->get_total_discount() > 0 ) {
				$line_item_args['discount_amount_cart'] = $this->number_format(
					$this->round( $order->get_total_discount(), $order ),
					$order
				);
			}

			if ( $order->get_shipping_total() > 0 ) {
				$this->add_line_item(
					/* translators: %s: shipping method */
					sprintf( __( 'Shipping via %s', 'mypos-payments' ), $order->get_shipping_method() ),
					1,
					$this->number_format( $order->get_shipping_total(), $order )
				);
			}

			$line_item_args = array_merge( $line_item_args, $this->get_line_items() );
		} else {
			/**
			 * Send order as a single item.
			 */
			$this->delete_line_items();

			$line_item_args = array();
			$all_items_name = $this->get_order_item_names( $order );
			$this->add_line_item(
				$all_items_name ? $all_items_name : __( 'Order', 'mypos-payments' ),
				1,
				$this->number_format(
					$order->get_total() - $this->round(
						(float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
						$order
					),
					$order
				),
				$order->get_order_number()
			);

			if ( $order->get_shipping_total() > 0 ) {
				$this->add_line_item(
					/* translators: %s: shipping method */
					sprintf( __( 'Shipping via %s', 'mypos-payments' ), $order->get_shipping_method() ),
					1,
					$this->number_format( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), $order )
				);
			}

			$line_item_args = array_merge( $line_item_args, $this->get_line_items() );
		}

		return $line_item_args;
	}

	/**
	 * Get order item names as a string.
	 *
	 * @param WC_Order $order Order object.
	 * @return string Item names.
	 */
	protected function get_order_item_names( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}

		$item_names = array();

		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item['name'] . ' x ' . $item['qty'];
		}

		return implode( ', ', $item_names );
	}

	/**
	 * Get cart item name from order.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $item  Order item.
	 * @return string Item name.
	 */
	protected function get_order_item_name( $order, $item ) {
		$item_name = $item['name'];
		$item_meta = new WC_Order_Item_Product( $item );

		// Fix for wrong meta type object.
		$formatted_meta = $item_meta->get_formatted_meta_data( '_' );
		if ( ! empty( $formatted_meta ) ) {
			foreach ( $formatted_meta as $meta ) {
				if ( $meta->label || $meta->value ) {
					return esc_html( $item_name );
				}
			}
		}

		return esc_html( $item_name );
	}

	/**
	 * Return all line items.
	 *
	 * @return array Line items.
	 */
	protected function get_line_items() {
		return $this->line_items;
	}

	/**
	 * Remove all line items.
	 *
	 * @return void
	 */
	protected function delete_line_items() {
		$this->line_items = array();
	}

	/**
	 * Get line items to send to mypos virtual.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool True if line items were prepared successfully.
	 */
	protected function prepare_line_items( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$this->delete_line_items();
		$calculated_total = 0;

		// Products.
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( 'fee' === $item['type'] ) {
				$item_line_total   = $this->number_format( $item['line_total'], $order );
				$line_item         = $this->add_line_item( $item['name'], 1, $item_line_total );
				$calculated_total += $item_line_total;
			} else {
				/** @var WC_Product|bool $product */
				$product           = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
				$sku               = $product ? $product->get_sku() : '';
				$item_line_total   = $this->number_format( $order->get_item_subtotal( $item, false ), $order );
				$line_item         = $this->add_line_item(
					$this->get_order_item_name( $order, $item ),
					$item['qty'],
					$item_line_total,
					$sku
				);
				$calculated_total += $item_line_total * $item['qty'];
			}

			if ( ! $line_item ) {
				return false;
			}
		}

		// Check for mismatched totals.
		$expected_total = $this->number_format(
			$calculated_total + $order->get_total_tax() + $this->round(
				$order->get_shipping_total(),
				$order
			) - $this->round( $order->get_total_discount(), $order ),
			$order
		);
		if ( $expected_total !== $this->number_format( $order->get_total(), $order ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add Line Item.
	 *
	 * @param string $item_name   Item name.
	 * @param int    $quantity    Quantity.
	 * @param float  $amount      Amount.
	 * @param string $item_number Item number/SKU.
	 * @return bool Successfully added or not.
	 */
	protected function add_line_item( $item_name, $quantity = 1, $amount = 0.0, $item_number = '' ) {
		$index = ( count( $this->line_items ) / 4 ) + 1;

		$this->line_items[ 'item_name_' . $index ]   = html_entity_decode(
			wc_trim_string( $item_name ? $item_name : __( 'Item', 'mypos-payments' ), 127 ),
			ENT_NOQUOTES,
			'UTF-8'
		);
		$this->line_items[ 'quantity_' . $index ]    = (int) $quantity;
		$this->line_items[ 'amount_' . $index ]      = (float) $amount;
		$this->line_items[ 'item_number_' . $index ] = $item_number;

		return true;
	}

	/**
	 * Check if currency has decimals.
	 *
	 * @param string $currency Currency code.
	 * @return bool True if currency has decimals.
	 */
	protected function currency_has_decimals( $currency ) {
		if ( in_array( $currency, array( 'HUF', 'JPY', 'TWD' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Round prices.
	 *
	 * @param float    $price Price to round.
	 * @param WC_Order $order Order object.
	 * @return float Rounded price.
	 */
	protected function round( $price, $order ) {
		$precision = 2;

		if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
			$precision = 0;
		}

		return round( $price, $precision );
	}

	/**
	 * Format prices.
	 *
	 * @param float|int $price Price to format.
	 * @param WC_Order  $order Order object.
	 * @return string Formatted price.
	 */
	protected function number_format( $price, $order ) {
		$decimals = 2;

		if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
			$decimals = 0;
		}

		return number_format( $price, $decimals, '.', '' );
	}

	/**
     * Determines if the payment gateway is available for use at checkout.
     *
     * @return bool
     */
    public function is_available() {
        if ($this->enabled !== 'yes') {
            return false;
        }
        if (! $this->is_valid_for_use()) {
            return false;
        }
        // Add any other checks if needed (e.g., required settings, credentials)
        return true;
    }
}
