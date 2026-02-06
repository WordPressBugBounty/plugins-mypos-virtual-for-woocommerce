<?php
/**
 * MyPOS Pending Payments Manager
 *
 * Handles all database operations for pending payment tracking
 *
 * @package myPOS
 */

defined( 'ABSPATH' ) || exit;

class MyPOS_Pending_Payments_Manager {

	const TABLE_NAME = 'mypos_pending_payments_schedule';

	/**
	 * Get the full table name with prefix
	 *
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Check if the pending payments table exists
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table_name = self::get_table_name();

		// Check cache first
		$cache_key = 'mypos_table_exists_' . md5( $table_name );
		$cached    = wp_cache_get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table check, result is cached
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		$result = $exists === $table_name;

		// Cache the result for 12 hours
		wp_cache_set( $cache_key, $result, '', 12 * HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Check if an order exists in pending payments
	 *
	 * @param string $order_id Order ID to check
	 * @return bool
	 */
	public static function order_exists( $order_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return false;
		}

		// Direct DB query necessary for custom table
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for order existence check
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT EXISTS(SELECT * FROM ' . esc_sql( $table_name ) . ' WHERE order_id = %s)',
				$order_id
			)
		);

		return '1' === $exists;
	}

	/**
	 * Add an order to pending payments schedule
	 *
	 * @param string $order_id Order ID
	 * @param int    $expired_time Expiration timestamp
	 * @param bool   $test_environment Whether this is a test order
	 * @return bool Success
	 */
	public static function add_order( $order_id, $expired_time, $test_environment = false ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( ! self::table_exists() || self::order_exists( $order_id ) ) {
			return false;
		}

		// Direct DB query necessary for custom table INSERT.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_name,
			array(
				'order_id'         => $order_id,
				'expired_time'     => $expired_time,
				'test_environment' => $test_environment ? 1 : 0,
				'last_check'       => time(),
			),
			array( '%s', '%d', '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Remove an order from pending payments schedule
	 *
	 * @param string $order_id Order ID
	 * @return bool Success
	 */
	public static function remove_order( $order_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return false;
		}

		// Direct DB query necessary for custom table DELETE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table_name,
			array( 'order_id' => $order_id ),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get pending orders for checking
	 *
	 * @param bool $test_environment Whether to get test orders
	 * @param int  $limit Limit number of orders
	 * @return array
	 */
	public static function get_pending_orders( $test_environment = false, $limit = 10 ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return array();
		}

		// Direct DB query necessary for custom table SELECT.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT order_id, expired_time FROM ' . esc_sql( $table_name ) . ' WHERE test_environment = %d ORDER BY last_check ASC LIMIT 0, %d',
				$test_environment ? 1 : 0,
				$limit
			)
		);

		return $orders ? $orders : array();
	}

	/**
	 * Update last check time for an order
	 *
	 * @param string $order_id Order ID
	 * @param int    $time Timestamp
	 * @return bool Success
	 */
	public static function update_last_check( $order_id, $time = null ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return false;
		}

		if ( null === $time ) {
			$time = time();
		}

		// Direct DB query necessary for custom table UPDATE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array( 'last_check' => $time ),
			array( 'order_id' => $order_id ),
			array( '%d' ),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Delete expired orders
	 *
	 * @param int $current_time Current timestamp
	 * @return int Number of deleted rows
	 */
	public static function delete_expired( $current_time ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return 0;
		}

		// Direct DB query necessary for custom table DELETE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table cleanup operation
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . esc_sql( $table_name ) . ' WHERE expired_time < %d',
				$current_time
			)
		);

		return $result ? $result : 0;
	}

	/**
	 * Delete expired orders with deadline
	 *
	 * @param int $current_time Current timestamp
	 * @return int Number of deleted rows
	 */
	public static function delete_expired_with_deadline( $current_time ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return 0;
		}

		// Direct DB query necessary for custom table DELETE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table cleanup operation, not a WP core table
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . esc_sql( $table_name ) . ' WHERE expired_time + 86400 < %d',
				$current_time
			)
		);

		return $result ? $result : 0;
	}
}
