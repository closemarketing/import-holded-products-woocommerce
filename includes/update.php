<?php
/**
 * Method updates
 *
 * Methods afer updating the plugin
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert old settings for new array
 *
 * @return void
 */
function convert_settings_fields() {
	$settings_keys = array(
		'wcpimh_api',
		'wcpimh_stock',
		'wcpimh_prodst',
		'wcpimh_virtual',
		'wcpimh_backorders',
		'wcpimh_catsep',
		'wcpimh_filter',
		'wcpimh_rates',
		'wcpimh_catnp'
	);

	if ( false === get_option( 'wcpimh_api' ) ) {
		return;
	}

	foreach ( $settings_keys as $setting_key ) {
		$imhsettings[ $setting_key ] = get_option( $setting_key );
		delete_option( $setting_key );
	}
	update_option( 'imhset', $imhsettings );
}
