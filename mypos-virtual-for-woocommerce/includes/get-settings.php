<?php
/**
 * Get myPOS settings endpoint
 *
 * @package myPOS
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

header( 'Content-Type: application/json' );
echo wp_json_encode( get_option( 'woocommerce_mypos_virtual_settings', array() ) );
