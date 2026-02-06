<?php
/**
 * REST API Upsell controller
 *
 * Handles requests to the /upsells endpoint.
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API Upsell controller class.
 */
class MyPOS_REST_Upsell_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'wc/v3';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected string $rest_base = 'upsells';

	/**
	 * Register the routes for upsells.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'mypos-payments' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check if a given request has access to get items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view upsells.', 'mypos-payments' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Check if a given request has access to get a specific item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view this upsell.', 'mypos-payments' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Check if a given request has access to create items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to create upsells.', 'mypos-payments' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Check if a given request has access to update a specific item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to update the item, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to update this upsell.', 'mypos-payments' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Check if a given request has access to delete a specific item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to delete this upsell.', 'mypos-payments' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	public function get_items() {
		//: WP_Error|WP_REST_Response|WP_HTTP_Response
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, not a WordPress post/meta. Caching handled by REST API layer.
		$upsells = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells ORDER BY date_created DESC' );

		foreach ( $upsells as $upsell ) {
			$upsell->base_products        = maybe_unserialize( $upsell->base_products );
			$upsell->recommended_products = maybe_unserialize( $upsell->recommended_products );
		}

		return rest_ensure_response( $upsells );
	}

	public function get_item( WP_REST_Request $request ) {
		//: WP_Error|WP_REST_Response|WP_HTTP_Response
		global $wpdb;

		if ( empty( $request->get_param( 'id' ) ) ) {
			return new WP_Error( 'missing_id', __( 'Missing ID.', 'mypos-payments' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, not a WordPress post/meta. Single item query, caching not beneficial.
		$upsell = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells WHERE id = %d',
				absint( $request->get_param( 'id' ) )
			)
		);

		if ( ! $upsell ) {
			return new WP_Error( 'doesnt_exist', __( 'Upsell does not exist.', 'mypos-payments' ) );
		}

		$upsell->base_products        = maybe_unserialize( $upsell->base_products );
		$upsell->recommended_products = maybe_unserialize( $upsell->recommended_products );

		return rest_ensure_response( $upsell );
	}

	public function create_item( WP_REST_Request $request ) {
		//: WP_Error|WP_REST_Response|WP_HTTP_Response
		global $wpdb;

		$data = $this->validate_data( $request->get_params() );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Check if upsell with this name already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, not a WordPress post/meta. Validation query, not frequently accessed.
		$existing_upsell = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells WHERE name = %s',
				$data['name']
			)
		);

		if ( $existing_upsell ) {
			return new WP_Error(
				'already_exists',
				sprintf(
					/* translators: %s: The name of the upsell that already exists */
					__( 'Upsell with name (%s) already exists.', 'mypos-payments' ),
					esc_html( $data['name'] )
				)
			);
		}

		$data['date_created'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table INSERT operation, caching not applicable.
		$result = $wpdb->insert( $wpdb->prefix . 'mypos_upsells', $data );

		if ( $result ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetching newly created item, not cached yet.
			$upsell = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells WHERE id = %d',
					$wpdb->insert_id
				)
			);

			$upsell->base_products        = maybe_unserialize( $upsell->base_products );
			$upsell->recommended_products = maybe_unserialize( $upsell->recommended_products );

			return rest_ensure_response( $upsell );
		} else {
			return new WP_Error( 'cannot_create', __( 'The resource cannot be created.', 'mypos-payments' ) );
		}
	}

	/**
	 * Update an upsell item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|WP_REST_Response|WP_HTTP_Response Response object.
	 */
	public function update_item( $request ) {
		global $wpdb;

		if ( empty( $request->get_param( 'id' ) ) ) {
			return new WP_Error( 'missing_id', __( 'Missing ID.', 'mypos-payments' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single item lookup for update operation.
		$upsell = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells WHERE id = %d',
				absint( $request->get_param( 'id' ) )
			)
		);

		if ( ! $upsell ) {
			return new WP_Error( 'doesnt_exist', __( 'Upsell does not exist.', 'mypos-payments' ) );
		}

		$data = $this->validate_data( $request->get_params() );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Check if another upsell with the same name exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, validation query for update.
		$upsell_duplicate = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells WHERE name = %s AND id != %d',
				$data['name'],
				(int) $upsell->id
			)
		);

		if ( $upsell_duplicate ) {
			return new WP_Error(
				'already_exists',
				sprintf(
					/* translators: %s: The name of the upsell that already exists */
					__( 'Another upsell with name (%s) already exists.', 'mypos-payments' ),
					esc_html( $data['name'] )
				)
			);
		}

		$data['date_updated'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table UPDATE operation, caching not applicable.
		$result = $wpdb->update( $wpdb->prefix . 'mypos_upsells', $data, array( 'id' => (int) $upsell->id ) );

		if ( false !== $result ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetching updated item to return to client.
			$upsell = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells WHERE id = %d',
					(int) $upsell->id
				)
			);

			$upsell->base_products        = maybe_unserialize( $upsell->base_products );
			$upsell->recommended_products = maybe_unserialize( $upsell->recommended_products );

			return rest_ensure_response( $upsell );
		} else {
			return new WP_Error( 'cannot_update', __( 'The resource cannot be updated.', 'mypos-payments' ) );
		}
	}

	public function delete_item( WP_REST_Request $request ) {
		global $wpdb;

		if ( empty( $request->get_param( 'id' ) ) ) {
			return new WP_Error( 'missing_id', __( 'Missing ID.', 'mypos-payments' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single item lookup for delete operation.
		$upsell = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'mypos_upsells WHERE id = %d',
				absint( $request->get_param( 'id' ) )
			)
		);

		if ( ! $upsell ) {
			return new WP_Error( 'doesnt_exist', __( 'Upsell does not exist.', 'mypos-payments' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table DELETE operation, caching not applicable.
		$result = $wpdb->delete( $wpdb->prefix . 'mypos_upsells', array( 'id' => (int) $upsell->id ), array( '%d' ) );

		if ( $result ) {
			$upsell->base_products        = maybe_unserialize( $upsell->base_products );
			$upsell->recommended_products = maybe_unserialize( $upsell->recommended_products );

			return rest_ensure_response( $upsell );
		} else {
			return new WP_Error( 'cannot_delete', __( 'The resource cannot be deleted.', 'mypos-payments' ) );
		}
	}

	/**
	 * Validate request data.
	 *
	 * @param array $data Request data.
	 * @return array|WP_Error Validated data or error.
	 */
	public function validate_data( $data ) {
		$required = array( 'name', 'base_products', 'recommended_products' );

		foreach ( $required as $value ) {
			if ( ! array_key_exists( $value, $data ) || empty( $data[ $value ] ) ) {
				return new WP_Error(
					'missing_data',
					sprintf(
						/* translators: %s: The name of the required field that is missing */
						__( 'Missing %s.', 'mypos-payments' ),
						esc_html( $value )
					)
				);
			}

			if ( is_array( $data[ $value ] ) ) {
				$validated[ $value ] = maybe_serialize( array_map( 'intval', $data[ $value ] ) );
			} else {
				$validated[ $value ] = $data[ $value ];
			}
		}

		return $validated;
	}
}
