<?php

/**
 * Plugin Name: Import Holded for WooCommerce or Easy Digital Downloads
 * Plugin URI: https://www.closemarketing.es
 * Description: Imports Products and data from Holded to WooCommerce or Easy Digital Downloads.
 * Author: closemarketing
 * Author URI: https://www.closemarketing.es/
 * Version: 1.2
 *
 * @package WordPress
 * Text Domain: import-holded-products-woocommerce
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
defined( 'ABSPATH' ) || exit;
define( 'WCPIMH_VERSION', '1.2' );
define( 'WCPIMH_ECOMMERCE', array( 'woocommerce', 'edd' ) );
define( 'WCPIMH_TABLE_SYNC', 'wcpimh_product_sync' );
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

add_filter( 'cron_schedules', 'wcpimh_add_cron_recurrence_interval' );
/**
 * Adds a cron Schedule
 *
 * @param array $schedules Array of Schedules.
 * @return array $schedules
 */
function wcpimh_add_cron_recurrence_interval( $schedules )
{
    $schedules['every_five_minutes'] = array(
        'interval' => 450,
        'display'  => __( 'Every 5 Minutes', 'import-holded-products-woocommerce' ),
    );
    $schedules['every_fifteen_minutes'] = array(
        'interval' => 900,
        'display'  => __( 'Every 15 minutes', 'import-holded-products-woocommerce' ),
    );
    $schedules['every_thirty_minutes'] = array(
        'interval' => 1800,
        'display'  => __( 'Every 30 Minutes', 'import-holded-products-woocommerce' ),
    );
    $schedules['every_one_hour'] = array(
        'interval' => 3600,
        'display'  => __( 'Every 1 Hour', 'import-holded-products-woocommerce' ),
    );
    $schedules['every_three_hours'] = array(
        'interval' => 10800,
        'display'  => __( 'Every 3 Hours', 'import-holded-products-woocommerce' ),
    );
    $schedules['every_six_hours'] = array(
        'interval' => 21600,
        'display'  => __( 'Every 6 Hours', 'import-holded-products-woocommerce' ),
    );
    $schedules['every_twelve_hours'] = array(
        'interval' => 43200,
        'display'  => __( 'Every 12 Hours', 'import-holded-products-woocommerce' ),
    );
    return $schedules;
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
