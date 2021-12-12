<?php

/**
 * Plugin Name: Sync Ecommerce Holded
 * Plugin URI: https://www.closemarketing.es
 * Description: Syncs Products and data from Holded to WooCommerce or Easy Digital Downloads.
 * Author: closemarketing
 * Author URI: https://www.closemarketing.es/
 * Version: 1.3
 * WC requires at least: 5.0
 * WC tested up to: 5.8.2
 *
 * @package WordPress
 * Text Domain: import-holded-products-woocommerce
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */
defined( 'ABSPATH' ) || exit;
define( 'WCPIMH_VERSION', '1.3' );
define( 'WCPIMH_ECOMMERCE', array( 'woocommerce', 'edd' ) );
define( 'WCPIMH_TABLE_SYNC', 'wcpimh_product_sync' );
define( 'PLUGIN_PREFIX', 'wcpimh_' );
define( 'WCPIMH_EC_NAME', 'Holded' );
define( 'WCPIMH_PURCHASE_URL', 'https://checkout.freemius.com/mode/dialog/plugin/5133/plan/8469/' );
// Loads translation.
add_action( 'init', 'wcpimh_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function wcpimh_load_textdomain()
{
    load_plugin_textdomain( 'import-holded-products-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

// Includes files.
require_once dirname( __FILE__ ) . '/includes/helpers-functions.php';
require_once dirname( __FILE__ ) . '/includes/class-wcpimh-admin.php';
require_once dirname( __FILE__ ) . '/includes/class-wcpimh-import.php';
require_once dirname( __FILE__ ) . '/includes/update.php';
add_action( 'plugins_loaded', 'imhwc_update_db_check' );
/**
 * Check method updates
 *
 * @return void
 */
function imhwc_update_db_check()
{
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
function wcpimh_general_admin_notice()
{
    $user_id = get_current_user_id();
    
    if ( !get_user_meta( $user_id, 'wcpimh_notice_orders_dismissed' ) ) {
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
function wcpimh_general_admin_notice_dismissed()
{
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


if ( function_exists( 'cmk_fs' ) ) {
    cmk_fs()->set_basename( false, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    
    if ( !function_exists( 'cmk_fs' ) ) {
        /**
         * Create a helper function for easy SDK access.
         *
         * @return function Dynamic init.
         */
        function cmk_fs()
        {
            global  $cmk_fs ;
            
            if ( !isset( $cmk_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/vendor/freemius/wordpress-sdk/start.php';
                $cmk_fs = fs_dynamic_init( array(
                    'id'             => '5133',
                    'slug'           => 'import-holded-products-woocommerce',
                    'premium_slug'   => 'import-holded-woocommerce-premium',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_248ed62e4388ec19335accdf1822c',
                    'is_premium'     => false,
                    'premium_suffix' => '',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'trial'          => array(
                    'days'               => 7,
                    'is_require_payment' => false,
                ),
                    'menu'           => array(
                    'slug'       => 'import_holded',
                    'first-path' => 'admin.php?page=import_holded&tab=settings',
                ),
                    'is_live'        => true,
                ) );
            }
            
            return $cmk_fs;
        }
        
        // Init Freemius.
        cmk_fs();
        // Signal that SDK was initiated.
        do_action( 'cmk_fs_loaded' );
    }

}

register_activation_hook( __FILE__, 'wcpimh_create_db' );
/**
 * Creates the database
 *
 * @since  1.0
 * @access private
 * @return void
 */
function wcpimh_create_db()
{
    global  $wpdb ;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . WCPIMH_TABLE_SYNC;
    // DB Tasks.
    $sql = "CREATE TABLE {$table_name} (\n\t    holded_prodid varchar(255) NOT NULL,\n\t    synced boolean,\n          UNIQUE KEY holded_prodid (holded_prodid)\n    \t) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
