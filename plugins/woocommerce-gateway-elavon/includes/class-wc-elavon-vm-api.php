<?php
/**
 * WooCommerce Elavon VM
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Elavon VM to newer
 * versions in the future. If you wish to customize WooCommerce Elavon VM for your
 * needs please refer to http://docs.woothemes.com/document/elavon-vm-payment-gateway/
 *
 * @package     WC-Elavon/API
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2015, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Elavon VM Payment Gateway API Class
 *
 * The Elavon VM Payment Gateway API class manages the communication between the
 * WooCommerce and Elavon payment servers
 */
class Elavon_VM_API {

	/** @var string transaction endpoint URL */
	var $endpoint_url;

	/** @var string the account merchant id */
	var $ssl_merchant_id;

	/** @var string the account user id */
	var $ssl_user_id;

	/** @var string the account PIN */
	var $ssl_pin;

	/** @var boolean whether request logging is enabled */
	var $log_enabled;


	/**
	 * Initialize and construct the API
	 *
	 * @param string $endpoint_url Elavon VM endpoint url
	 * @param string $ssl_merchant_id
	 * @param string $ssl_user_id
	 * @param string $ssl_pin
	 */
	public function __construct( $endpoint_url, $ssl_merchant_id, $ssl_user_id, $ssl_pin, $log_enabled ) {
		$this->endpoint_url    = $endpoint_url;
		$this->ssl_merchant_id = $ssl_merchant_id;
		$this->ssl_user_id     = $ssl_user_id;
		$this->ssl_pin         = $ssl_pin;
		$this->log_enabled     = $log_enabled;
	}


	/**
	 * Perform the transaction request
	 *
	 * @param object $request request object
	 * @return SimpleXMLElement response, or false on error
	 */
	public function transaction_request( $request ) {

		// build the simplexml object
		$request_xml = simplexml_load_string( "<txn />" );

		$request_xml->addChild( 'ssl_merchant_id', $this->ssl_merchant_id );
		$request_xml->addChild( 'ssl_user_id',     $this->ssl_user_id );
		$request_xml->addChild( 'ssl_pin',         $this->ssl_pin );

		$request_xml->addChild( 'ssl_test_mode',        $request->ssl_test_mode );
		$request_xml->addChild( 'ssl_transaction_type', $request->ssl_transaction_type );
		$request_xml->addChild( 'ssl_invoice_number',   $this->stripspecialchars( $request->ssl_invoice_number ) );

		$request_xml->addChild( 'ssl_card_number',   $request->ssl_card_number );
		$request_xml->addChild( 'ssl_exp_date',      $request->ssl_exp_date );
		$request_xml->addChild( 'ssl_amount',        $request->ssl_amount );
		$request_xml->addChild( 'ssl_salestax',      $request->ssl_salestax );
		$request_xml->addChild( 'ssl_customer_code', $request->ssl_customer_code );

		$request_xml->addChild( 'ssl_cvv2cvc2_indicator', $request->ssl_cvv2cvc2_indicator );
		if ( isset( $request->ssl_cvv2cvc2 ) ) $request_xml->addChild( 'ssl_cvv2cvc2', $request->ssl_cvv2cvc2 );

		$request_xml->addChild( 'ssl_first_name',  $this->stripspecialchars( $request->ssl_first_name ) );
		$request_xml->addChild( 'ssl_last_name',   $this->stripspecialchars( $request->ssl_last_name ) );
		$request_xml->addChild( 'ssl_company',     $this->stripspecialchars( $request->ssl_company ) );
		$request_xml->addChild( 'ssl_avs_address', $this->stripspecialchars( $request->ssl_avs_address ) );
		$request_xml->addChild( 'ssl_address2',    $this->stripspecialchars( $request->ssl_address2 ) );
		$request_xml->addChild( 'ssl_city',        $this->stripspecialchars( $request->ssl_city ) );
		$request_xml->addChild( 'ssl_state',       $this->stripspecialchars( $request->ssl_state ) );
		$request_xml->addChild( 'ssl_avs_zip',     $this->stripspecialchars( $request->ssl_avs_zip ) );
		$request_xml->addChild( 'ssl_country',     $this->stripspecialchars( $request->ssl_country ) );
		$request_xml->addChild( 'ssl_email',       $this->stripspecialchars( $request->ssl_email ) );
		$request_xml->addChild( 'ssl_phone',       $this->stripspecialchars( $request->ssl_phone ) );
		$request_xml->addChild( 'ssl_cardholder_ip', $request->ssl_cardholder_ip );

		// allow other actors to modify the request.  Useful for adding custom fields
		$request_xml = apply_filters( 'wc_payment_gateway_elavon_vm_request_xml', $request_xml, $request );

		// According to Elavon's tech support, their "XML" protocol isn't actually
		//  true XML, and will report the request as invalid if it contains the
		//  normal XML header, so strip it out of our requests
		$request = str_replace("<?xml version=\"1.0\"?>\n", '', $request_xml->asXML() );

		$start_time = microtime( true );
		$response = $this->perform_request( $this->endpoint_url, $request );
		$time = round( microtime( true ) - $start_time, 5 );

		// log the request
		if ( $this->log_enabled ) {

			$dom = new DOMDocument();
			$dom->preserveWhiteSpace = FALSE;
			$dom->loadXML( $request );
			$dom->formatOutput = TRUE;
			$request = $dom->saveXml();

			// make the request data safe for display
			// replace merchant authentication
			if ( preg_match( '/<ssl_pin>(.*)<\/ssl_pin>/', $request, $matches ) ) {
				$request = preg_replace( '/<ssl_pin>.*<\/ssl_pin>/', '<ssl_pin>' . str_repeat( '*', strlen( $matches[1] ) ) . '</ssl_pin>', $request );
			}

			// replace real card number
			if ( preg_match( '/<ssl_card_number>(.*)<\/ssl_card_number>/', $request, $matches ) ) {
				$request = preg_replace( '/<ssl_card_number>.*<\/ssl_card_number>/', '<ssl_card_number>' . str_repeat( '*', strlen( $matches[1] ) - 4 ) . substr( $matches[1], -4 ) . '</ssl_card_number>', $request );
			}

			// replace real CSC code
			if ( isset( $request->ssl_cvv2cvc2 ) && preg_match( '/<ssl_cvv2cvc2>(.**)<\/ssl_cvv2cvc2>/', $request, $matches ) ) {
				$request = preg_replace( '/<ssl_cvv2cvc2>.**<\/ssl_cvv2cvc2>/', '<ssl_cvv2cvc2>' . str_repeat( '*', strlen( $matches[1] ) ) . '</ssl_cvv2cvc2>', $request );
			}

			$request = str_replace("<?xml version=\"1.0\"?>\n", '', $request );

			wc_elavon_vm()->log( sprintf( __( "Request Time (s): %s\nRequest Method: %s\nRequest URI: %s\nRequest Body:\n %s", WC_Elavon_VM::TEXT_DOMAIN ), $time, 'POST', $this->endpoint_url, $request ) );
		}

		return simplexml_load_string( $response );
	}


	/**
	 * Strip HTML special characters (&, <, >).  Encoding the special chars as
	 * entities, while technically valid XML, breaks the weirdo Elavon gateway
	 * implementation, so the best we can do is strip the problem characters
	 * out entirely.  The Elavon gateway an't handle CDATA sections either
	 *
	 * @param string $str a string
	 * @return string with &, < and > stripped out
	 */
	private function stripspecialchars( $str ) {
		return str_replace( array( '&', '<', '>' ), '', $str );
	}


	/**
	 * Perform the request
	 *
	 * @param string $url endpoint URL
	 * @param string $request XML request data
	 * @return string XML response
	 */
	private function perform_request( $url, $request ) {

		$post_response = wp_remote_post( $url, array(
			'redirection' => 0,
			'method'     => 'POST',
			'body'       => array( "xmldata" => $request ),
			'timeout'    => 60,
			'sslverify'  => false,
			'user-agent' => "PHP " . PHP_VERSION,
			'headers'    => array(
				'referer' => site_url(),
			),
		) );

		return $post_response['body'];
	}
}
