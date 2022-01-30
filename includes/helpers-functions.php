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
 * Check if is active ecommerce plugin
 *
 * @param sring $plugin Plugin to check.
 * @return boolean
 */
function imhwc_is_active_ecommerce( $plugin ) {
	if ( 'woocommerce' === $plugin && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return true;
	}
	if ( 'edd' === $plugin && in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return true;
	}
	return false;
}

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
function connwoo_is_premium() {
	return apply_filters(
		'connwoo_is_premium',
		false
	);
}

function connwoo_loads_api() {

	$api_name = apply_filters(
		'connwoo_api_name',
		WCPIMH_API
	);
	include_once 'class-api-' . strtolower( $api_name ) . '.php';
	$crmclassname = 'CONNAPI_' . $api_name;
	if ( class_exists( $crmclassname ) ) {
		$connapi = new $crmclassname();
	}
}
