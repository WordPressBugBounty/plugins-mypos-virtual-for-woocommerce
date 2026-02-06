<?php

defined( 'ABSPATH' ) || exit;

/**
 * MyPOS_Upsells class.
 */
class MyPOS_Upsells {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static string $endpoint = 'upsells';

	/**
	 * MyPOS_Upsells constructor.
	 */
	public function __construct() {
		// Register template endpoint.
		add_action( 'init', array( __CLASS__, 'add_endpoint' ), 0 );

		// Add query vars.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		add_action( 'template_redirect', array( $this, 'check_if_checkout_page' ), 0 );

		// Handle request.
		add_filter( 'template_include', array( $this, 'upsell_template' ), 0 );
	}

	/**
	 * Add endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( self::$endpoint, EP_PERMALINK );
		flush_rewrite_rules( false );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query variables.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ) {
		if ( isset( $vars[ self::$endpoint ] ) ) {
			$vars[ self::$endpoint ] = true;
		}

		return $vars;
	}

	/**
	 * Check if page is checkout and referer is not upsells then redirect to upsells page.
	 */
	public function check_if_checkout_page() {
		if ( is_page( get_option( 'woocommerce_checkout_page_id' ) )
			&& false === strpos( wp_get_referer(), 'upsells' )
			&& false === strpos( wp_get_referer(), 'checkout' )
			&& ! empty( $this->get_upsell_product_ids() ) ) {
			wp_safe_redirect( site_url( '/upsells' ) );
			exit;
		}
	}

	/**
	 * Handle request.
	 *
	 * @param string $template Template path.
	 * @return string Modified template path.
	 */
	public function upsell_template( $template ) {
		global $wp;

		if ( self::$endpoint === $wp->query_vars['pagename'] ) {
			mypos_get_template(
				'upsells/upsells-page.php',
				array(
					'products' => implode( ',', $this->get_upsell_product_ids() ),
				)
			);
			exit;
		}

		return $template;
	}

	/**
	 * Retrieve upsell product IDs for current cart products.
	 *
	 * @return array Array of upsell product IDs.
	 */
	public function get_upsell_product_ids() {
		global $woocommerce;
		global $wpdb;

		$items              = $woocommerce->cart->get_cart();
		$upsell_product_ids = array();
		foreach ( $items as $item ) {
			$like = '%i:' . $item['data']->get_id() . ';%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for cart upsells, not cacheable
			$upsells = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM wp_mypos_upsells WHERE base_products LIKE %s', $like ) );
			if ( ! empty( $upsells ) ) {
				foreach ( $upsells as $upsell ) {
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Data is stored serialized by WordPress core.
					foreach ( unserialize( $upsell->recommended_products ) as $product_id ) {
						if ( false !== get_post_status( $product_id ) && ! in_array( $product_id, $upsell_product_ids, true ) ) {
							$upsell_product_ids[] = $product_id;
						}
					}
				}
			}
		}

		return $upsell_product_ids;
	}
}
new MyPOS_Upsells();
