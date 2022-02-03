<?php
/**
 * Plugin Name: Connect WooCommerce Holded
 * Plugin URI: https://close.technology/wordpress-plugins/integra-tienda-online-woocommerce-holded/
 * Description: Syncs Products and data from Holded to WooCommerce or Easy Digital Downloads.
 * Author: closemarketing
 * Author URI: https://close.technology/
 * Version: 2.0
 * WC requires at least: 5.0
 * WC tested up to: 6.1
 *
 * @package WordPress
 * Text Domain: import-holded-products-woocommerce
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */
defined( 'ABSPATH' ) || exit;
define( 'WCPIMH_VERSION', '1.4' );
define( 'WCPIMH_ECOMMERCE', array( 'woocommerce', 'edd' ) );
define( 'WCPIMH_TABLE_SYNC', 'wcpimh_product_sync' );
define( 'WCPIMH_API', 'Holded' );
define( 'WCPIMH_PURCHASE_URL', 'https://close.technology/connect-woocommerce-holded/?utm_source=WordPress' );

// Loads translation.
add_action( 'init', 'wcpimh_load_textdomain' );

/**
 * Load plugin textdomain.
 */
function wcpimh_load_textdomain() {
	load_plugin_textdomain( 'import-holded-products-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

// Includes files.
require_once dirname( __FILE__ ) . '/includes/class-api-erp.php';
require_once dirname( __FILE__ ) . '/includes/helpers-functions.php';
require_once dirname( __FILE__ ) . '/includes/class-public.php';
require_once dirname( __FILE__ ) . '/includes/class-wcpimh-admin.php';
require_once dirname( __FILE__ ) . '/includes/class-wcpimh-import.php';
require_once dirname( __FILE__ ) . '/includes/update.php';

add_action( 'plugins_loaded', 'imhwc_update_db_check' );
/**
 * Check method updates
 *
 * @return void
 */
function imhwc_update_db_check() {
	$old_method = get_option( 'wcpimh_api' );
	if ( false !== get_option( 'wcpimh_api' ) ) {
		convert_settings_fields();
	}
	update_option( 'wcpimh_version', WCPIMH_VERSION );
}

add_action( 'admin_notices', 'wcpimh_general_admin_notice' );
/**
 * General Admin Notice.
 *
 * @return void
 */
function wcpimh_general_admin_notice() {
	$user_id = get_current_user_id();

	if ( ! get_user_meta( $user_id, 'wcpimh_notice_orders_dismissed' ) ) {
		echo  '<div class="notice notice-warning is-dismissible">' ;
		echo  '<p>' . esc_html__( 'Sync Ecommerce Holded Premium now in version 1.3 brings you to sync Orders after they are completed.', 'import-holded-products-woocommerce' ) . '</p>' ;
		$purchase_url = wp_nonce_url( 'admin.php?page=import_holded-account&wcpimh-notice-orders-dismissed', 'wcpimh_sync_orders' );
		echo  '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $purchase_url, __( 'Purchase Premium Version', 'import-holded-products-woocommerce' ) ) . '</p>' ;
		// phpcs:ignore -- no need to scape
		echo  '</div>' ;
	}

}

add_action( 'admin_init', 'wcpimh_general_admin_notice_dismissed' );
/**
 * Add user meta.
 *
 * @return void
 */
function wcpimh_general_admin_notice_dismissed() {
	$user_id = get_current_user_id();
	if ( isset( $_GET['wcpimh-notice-orders-dismissed'] ) ) {
		add_user_meta(
			$user_id,
			'wcpimh_notice_orders_dismissed',
			'true',
			true
		);
	}
}
