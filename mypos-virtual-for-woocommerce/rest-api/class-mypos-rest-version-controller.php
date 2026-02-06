<?php
/**
 * REST API Version controller
 *
 * Handles requests to the /upsells endpoint.
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API Version controller class.
 */
class MyPOS_REST_Version_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'mp';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected string $rest_base = 'version';

	/**
	 * Version.
	 *
	 * @var string
	 */
	protected string $version = 'v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes for version check.
	 */
	public function register_routes() {
		//: void
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_version' ),
				'permission_callback' => array( $this, 'check_version_permissions_check' ),
			)
		);
	}

	/**
	 * Check if a given request has access to get version.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function check_version_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to access version information.', 'mypos-payments' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	public function check_version() {
		//: WP_Error|WP_REST_Response|WP_HTTP_Response
		return rest_ensure_response( $this->version );
	}
}
