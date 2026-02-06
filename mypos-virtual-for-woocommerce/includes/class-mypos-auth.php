<?php

defined( 'ABSPATH' ) || exit;

/**
 * Auth class.
 */
class MyPOS_Auth {

	/**
	 * Setup class.
	 */
	public function __construct() {
		// Add query vars.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Register auth endpoint.
		add_action( 'init', array( $this, 'add_endpoint' ) );

		// Handle auth requests.
		add_action( 'parse_request', array( $this, 'handle_auth_requests' ) );

		// Refresh permalinks to set our rewrite rule on activation/deactivation
		register_activation_hook( __FILE__, 'flush_rewrite_rules_on_activation' );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules_on_deactivation' );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query variables.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ) {
		$vars[] = 'mp-auth-route';
		return $vars;
	}

	/**
	 * Add auth endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_rule( '^mp-auth/(.*)?', 'index.php?mp-auth-route=$matches[1]', 'top' );
	}

	/**
	 * Flush rewrite rules when plugin is activated
	 */
	public function flush_rewrite_rules_on_activation() {
		$this->add_endpoint();
		flush_rewrite_rules();
	}


	/**
	 * Flush rewrite rules when plugin is deactivated
	 */
	public function flush_rewrite_rules_on_deactivation() {
		flush_rewrite_rules();
	}

	/**
	 * Handle auth requests.
	 *
	 * @throws Exception When auth_endpoint validation fails.
	 */
	public function handle_auth_requests() {
		global $wp;

		$current_route = add_query_arg( array(), $wp->request );
		$path          = explode( '/', $current_route );

		if ( 'mp-path' === $path[0] ) {
			$wp->query_vars['mp-auth-route'] = is_scalar( wp_unslash( $path[1] ) ? sanitize_text_field( wp_unslash( $path[1] ) ) : wp_unslash( $path[1] ) );
		}

		// mp-auth endpoint requests.
		if ( ! empty( $wp->query_vars['mp-auth-route'] ) ) {
			$this->auth_endpoint( $wp->query_vars['mp-auth-route'] );
		}
	}

	/**
	 * Build auth urls.
	 *
	 * @param array  $data     Data to build URL.
	 * @param string $endpoint Endpoint.
	 * @return string
	 */
	protected function build_url( array $data, string $endpoint ) {
		$url = mypos_get_endpoint_url( 'mp-auth', $endpoint, home_url( '/' ) );

		return add_query_arg(
			array(
				'return_url'        => rawurlencode( $this->get_formatted_url( $data['return_url'] ) ),
				'success_url'       => rawurlencode( $this->get_formatted_url( $data['success_url'] ) ),
				'store_name'        => mypos_clean( $data['store_name'] ),
				'developer_package' => mypos_clean( $data['developer_package'] ),
			),
			$url
		);
	}

	/**
	 * Decode and format a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	protected function get_formatted_url( $url ) {
		$url = urldecode( $url );

		if ( ! str_contains( $url, '://' ) ) {
			$url = 'https://' . $url;
		}

		return $url;
	}

	/**
	 * Make validation.
	 *
	 * @throws Exception When validate fails.
	 */
	protected function make_validation() {
		$data   = array();
		$params = array(
			'return_url',
			'success_url',
			'store_name',
			'developer_package',
		);

		// Check for empty params
		foreach ( $params as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth flow, nonce verified at grant step
			if ( empty( $_REQUEST[ $param ] ) ) {
				throw new RuntimeException(
					sprintf(
						/* translators: %s: parameter name */
						esc_html__( 'Missing parameter %s', 'mypos-payments' ),
						esc_html( $param )
					)
				);
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth flow, nonce verified at grant step
			$data[ $param ] = sanitize_text_field( wp_unslash( $_REQUEST[ $param ] ) );
		}

		// Validate URL addresses
		foreach ( array( 'return_url', 'success_url' ) as $param ) {
			$param = $this->get_formatted_url( $data[ $param ] ); // force param to get URL format

			if ( false === filter_var( $param, FILTER_VALIDATE_URL ) ) {
				throw new RuntimeException(
					sprintf(
						/* translators: %s: URL address */
						esc_html__( 'The %s is not a valid URL', 'mypos-payments' ),
						esc_url( $param )
					)
				);
			}
		}

		return $data;
	}

	/**
	 * Update Store Configuration Options.
	 *
	 * @param string $developer_package Developer package data.
	 * @return bool
	 * @throws Exception When update fails.
	 */
	protected function update_options( $developer_package ) {
		$new_options = '';
		$old_options = get_option( 'woocommerce_mypos_virtual_settings' );

		if ( false !== $old_options ) {
			$new_options = $old_options;

			$new_options['test']               = 'no';
			$new_options['production_package'] = $developer_package;
		}
		if ( get_option( 'woocommerce_mypos_virtual_settings' ) !== $new_options &&
			false === update_option( 'woocommerce_mypos_virtual_settings', $new_options ) ) {
			throw new RuntimeException( esc_html__( 'Could not make an update', 'mypos-payments' ) );
		}

		return true;
	}

	/**
	 * Auth endpoint.
	 *
	 * @param string $route Route.
	 * @throws Exception When validation fails.
	 */
	protected function auth_endpoint( string $route ) {
		ob_start();
		include 'mypos-core-functions.php';
		try {
			$route = strtolower( $route );

			if ( 'checkinstall' !== $route ) {
				$this->make_validation();
			}

			// Sanitize all REQUEST data.
			$data = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth flow, nonce verified at grant step
			foreach ( $_REQUEST as $key => $value ) {
				$data[ sanitize_key( $key ) ] = is_array( $value ) ? array_map( 'sanitize_text_field', wp_unslash( $value ) ) : sanitize_text_field( wp_unslash( $value ) );
			}

			// Login endpoint.
			if ( 'login' === $route && ! is_user_logged_in() ) {
				mypos_get_template(
					'auth/form-login.php',
					array(
						'return_url'   => $this->get_formatted_url( $data['return_url'] ),
						'redirect_url' => $this->build_url( $data, 'authorize' ),
						'store_name'   => mypos_clean( $data['store_name'] ),
					)
				);
				exit;

			} elseif ( 'checkinstall' === $route ) {
				echo 'OK';
				exit;

			} elseif ( 'login' === $route && is_user_logged_in() ) {
				// Redirect with user is logged in.
				wp_safe_redirect( esc_url_raw( $this->build_url( $data, 'authorize' ) ) );
				exit;

			} elseif ( 'authorize' === $route && ! is_user_logged_in() ) {
				// Redirect with user is not logged in and trying to access the authorize endpoint.
				wp_safe_redirect( esc_url_raw( $this->build_url( $data, 'login' ) ) );
				exit;

			} elseif ( 'authorize' === $route && current_user_can( 'manage_woocommerce' ) ) {
				// Authorize endpoint.
				mypos_get_template(
					'auth/form-grant-access.php',
					array(
						'store_name'  => mypos_clean( $data['store_name'] ),
						'return_url'  => $this->get_formatted_url( $data['return_url'] ),
						'granted_url' => wp_nonce_url( $this->build_url( $data, 'access_granted' ), 'mp_auth_grant_access', 'mp_auth_nonce' ),
						'logout_url'  => wp_logout_url( $this->build_url( $data, 'login' ) ),
						'user'        => wp_get_current_user(),
					)
				);
				exit;

			} elseif ( 'access_granted' === $route && current_user_can( 'manage_woocommerce' ) ) {
				// Granted access endpoint.
				if ( ! isset( $_GET['mp_auth_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['mp_auth_nonce'] ) ), 'mp_auth_grant_access' ) ) { // WPCS: input var ok.
					throw new Exception( __( 'Invalid nonce verification', 'mypos-payments' ) );
				}

				if ( $this->update_options( $data['developer_package'] ) ) {
					wp_safe_redirect(
						esc_url_raw(
							$this->get_formatted_url( $data['success_url'] )
						)
					);
					exit;
				}
			} else {
				throw new RuntimeException( esc_html__( 'You do not have permission to access this page', 'mypos-payments' ) );
			}
		} catch ( Exception $e ) {
			/* translators: %s: error message */
			wp_die( sprintf( esc_html__( 'Error: %s.', 'mypos-payments' ), esc_html( $e->getMessage() ) ), esc_html__( 'Access denied', 'mypos-payments' ), array( 'response' => 401 ) );
		}
	}

	public function mypos_handle_post_request( $request ) {
		// $parameters = $request->get_json_params();

		return $this->handle_auth_requests();
	}
}
new MyPOS_Auth();
