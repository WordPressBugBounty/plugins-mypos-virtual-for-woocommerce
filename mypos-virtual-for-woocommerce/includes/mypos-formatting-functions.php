<?php
defined( 'ABSPATH' ) || exit;

/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 * Non-scalar values are ignored.
 *
 * @param string|array $data Data to sanitize.
 * @return string|array
 */
function mypos_clean( $data ) {
	if ( is_array( $data ) ) {
		return array_map( 'mypos_clean', $data );
	}

	return is_scalar( $data ) ? sanitize_text_field( $data ) : $data;
}
