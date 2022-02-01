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
class CONNAPI_ERP {

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
	/**
	 * Gets information from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	public function get_products( $id = null, $page = null  ) {
		$imh_settings = get_option( 'imhset' );
		if ( ! isset( $imh_settings['wcpimh_api'] ) ) {
			return false;
		}
		$apikey       = $imh_settings['wcpimh_api'];
		$args         = array(
			'headers' => array(
				'key' => $apikey,
			),
			'timeout' => 10,
		);
		$url = '';
		if ( $page > 1 ) {
			$url = '?page=' . $page;
		}

		if ( $id ) {
			$url = '/' . $id;
		}

		$response      = wp_remote_get( 'https://api.holded.com/api/invoicing/v1/products' . $url, $args );
		$body          = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $body, true );

		if ( isset( $body_response['errors'] ) ) {
			error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call: /' );
			return false;
		}

		return $body_response;
	}

}

$connapi_erp = new CONNAPI_ERP();