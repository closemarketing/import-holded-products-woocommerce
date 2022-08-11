<?php
/**
 * Helpers Functions
 *
 * @package    WordPress
 * @author     David Pérez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks if can syncs
 *
 * @return boolean
 */
function sync_ecommerce_check_can_sync() {
	$imh_settings = get_option( 'imhset' );
	if ( ! isset( $imh_settings['wcpimh_api'] ) ) {
		return false;
	}
	return true;
}

/**
 * Returns Version.
 *
 * @return array
 */
if ( ! function_exists( 'connwoo_is_pro' ) ) {
	function connwoo_is_pro() {
		return apply_filters(
			'connwoo_is_pro',
			false
		);
	}
}
