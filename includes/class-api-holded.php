<?php
/**
 * Class Holded Connector
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LoadsAPI.
 *
 * API Holded.
 *
 * @since 1.0
 */
class CONNAPI_Holded {

	/**
	 * Construct of Class
	 */
	public function __construct() {
	}

	/**
	 * # Functions
	 * ---------------------------------------------------------------------------------------------------- */
		
	/**
	 * Gets information from Holded CRM
	 *
	 * @return array
	 */
	public function get_rates() {
		$imh_settings = get_option( 'imhset' );
		if ( ! isset( $imh_settings['wcpimh_api'] ) ) {
			return false;
		}
		$apikey = $imh_settings['wcpimh_api'];
		$args = array(
			'headers' => array(
			'key' => $apikey,
		),
			'timeout' => 10,
		);
		$response = wp_remote_get( 'https://api.holded.com/api/invoicing/v1/rates/', $args );
		$body = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $body, true );
		
		if ( isset( $body_response['errors'] ) ) {
			error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call Rates: /' );
			return false;
		}
		
		$array_options = array(
			'default' => __( 'Default price', 'import-holded-products-woocommerce' ),
		);
		foreach ( $body_response as $rate ) {
			$array_options[$rate['id']] = $rate['name'];
		}
		return $array_options;
	}
}

new CONNAPI_Holded();
