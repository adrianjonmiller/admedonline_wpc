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
 * @package     WC-Elavon/Gateway
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2015, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Gateway class
 */
class WC_Gateway_Elavon_VM extends WC_Payment_Gateway {


	/** @var string the endpoint URL for the demo environment */
	private $demo_endpoint_url = "https://demo.myvirtualmerchant.com/VirtualMerchantDemo/processxml.do";

	/** @var string the endpoint URL for the live environment */
	private $live_endpoint_url = "https://www.myvirtualmerchant.com/VirtualMerchant/processxml.do";

	/** @var string account environment, one of 'demo' or 'production', defaults to 'production' */
	private $account;

	/** @var string indicates test mode is enabled for the production account, one of 'yes' or 'no', defaults to 'no' */
	private $testmode;

	/** @var string 4 options for debug mode - off, checkout, log, both */
	private $debug_mode;

	/** @var string submit all transactions for settlement immediately, one of 'yes' or 'no' and defaults to 'yes' */
	private $settlement;

	/** @var string whether the card verification code is required for checkout, one of 'yes' or 'no', defaults to 'no' */
	private $cvv;

	/** @var array array of accepted card types, ie 'VISA', 'MC', etc */
	private $cardtypes;

	/** @var string the production account merchant id */
	private $sslmerchant_id;

	/** @var string the production account user id */
	private $ssluser_id;

	/** @var string the production account pin */
	private $sslpin;

	/** @var string the demo account merchant id */
	private $demo_ssl_merchant_id;

	/** @var string the demo account user id */
	private $demo_ssl_user_id;

	/** @var string the demo account pin */
	private $demo_ssl_pin;


	/**
	 * Initialize the gateway
	 */
	public function __construct() {

		$this->id                 = WC_Elavon_VM::PLUGIN_ID;
		$this->method_title       = __( 'Elavon VM', WC_Elavon_VM::TEXT_DOMAIN );
		$this->method_description = __( 'Elavon VM Payment Gateway provides a seamless and secure checkout process for your customers', WC_Elavon_VM::TEXT_DOMAIN );

		$this->supports = array( 'products' );

		$this->has_fields = true;

		$this->icon = apply_filters( 'woocommerce_elavon_vm_icon', '' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		foreach ( $this->settings as $setting_key => $setting ) {
			$this->$setting_key = $setting;
		}

		// add some notices if test/demo mode are enabled
		if ( $this->is_test_mode() ) {
			$this->description .= ' ' . __( 'TEST MODE ENABLED', WC_Elavon_VM::TEXT_DOMAIN );
		}
		if ( $this->is_demo_account() ) {
			$this->description .= ' ' . __( 'ENVIRONMENT: DEMO', WC_Elavon_VM::TEXT_DOMAIN );
		}

		// pay page fallback
		add_action( 'woocommerce_receipt_' . $this->id, create_function( '$order', 'echo "<p>" . __( "Thank you for your order.", WC_Elavon_VM::TEXT_DOMAIN ) . "</p>";' ) );

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	}


	/**
	 * Initialise Settings Form Fields
	 *
	 * Add an array of fields to be displayed
	 * on the gateway's settings screen.
	 *
	 * @see WC_Settings_API::init_form_fields()
	 */
	public function init_form_fields() {

		$this->form_fields = array(

			'enabled' => array(
				'title'       => __( 'Enable', WC_Elavon_VM::TEXT_DOMAIN ),
				'label'       => __( 'Enable Elavon VM', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),

			'title' => array(
				'title'    => __( 'Title', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => __( 'Payment method title that the customer will see on your website.', WC_Elavon_VM::TEXT_DOMAIN ),
				'default'  => __( 'Credit Card', WC_Elavon_VM::TEXT_DOMAIN ),
			),

			'description' => array(
				'title'    => __( 'Description', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'textarea',
				'desc_tip' => __( 'Payment method description that the customer will see on your website.', WC_Elavon_VM::TEXT_DOMAIN ),
				'default'  => __( 'Pay securely using your credit card.', WC_Elavon_VM::TEXT_DOMAIN ),
			),

			'settlement' => array(
				'title'       => __( 'Submit for Settlement', WC_Elavon_VM::TEXT_DOMAIN ),
				'label'       => __( 'Submit all transactions for settlement immediately.', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),

			'cvv' => array(
				'title'       => __( 'Card Verification', WC_Elavon_VM::TEXT_DOMAIN ),
				'label'       => __( 'Require customer to enter credit card verification code', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'default'     => 'no',
			),

			'cardtypes' => array(
				'title'    => __( 'Accepted Card Logos', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select chosen_select',
				'css'      => 'width: 350px;',
				'desc_tip' => __( 'Select which card types you accept to display the logos for on your checkout page.  This is purely cosmetic and optional, and will have no impact on the cards actually accepted by your account.', WC_Elavon_VM::TEXT_DOMAIN ),
				'default'  => array( 'VISA', 'MC', 'AMEX', 'DISC', 'JCB' ),
				//  Additional display names can be associated with a single card type by using the following convention: VISA: Visa, VISA-1: Visa Debit, etc
				'options'  => apply_filters( 'woocommerce_elavon_card_types',
					array(
						'VISA' => 'Visa',
						'MC'   => 'MasterCard',
						'AMEX' => 'American Express',
						'DISC' => 'Discover',
						'JCB'  => 'JCB',
					)
				),
			),

			'account' => array(
				'title'    => __( 'Account', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'select',
				'desc_tip' => __( 'What account do you want your transactions posted to?', WC_Elavon_VM::TEXT_DOMAIN ),
				'default'  => 'production',
				'options'  => array(
					'production' => __( 'Production', WC_Elavon_VM::TEXT_DOMAIN ),
					'demo'       => __( 'Demo',       WC_Elavon_VM::TEXT_DOMAIN ),
				),
			),

			'testmode' => array(
				'title'       => __( 'Test Mode', WC_Elavon_VM::TEXT_DOMAIN ),
				'label'       => __( 'Enable Test Mode', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode for your production account, transactions will not be posted to your account or credit card processor.', WC_Elavon_VM::TEXT_DOMAIN ),
				'default'     => 'no',
			),

			'sslmerchantid' => array(
				'title'    => __( 'Merchant ID', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => __( 'VirtualMerchant ID/Account ID as provided by Elavon.  This will be six digits long, and start with the number 5 or 6.', WC_Elavon_VM::TEXT_DOMAIN ),
				'class'    => 'production-field',
			),

			'ssluserid' => array(
				'title'    => __( 'User ID', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => __( 'Virtual Merchant user ID as configured on Virtual Merchant', WC_Elavon_VM::TEXT_DOMAIN ),
				'class'    => 'production-field',
			),

			'sslpin' => array(
				'title'    => __( 'PIN', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'password',
				'desc_tip' => __( 'VirtualMerchant PIN as generated within VirtualMerchant', WC_Elavon_VM::TEXT_DOMAIN ),
				'class'    => 'production-field',
			),

			'demo_ssl_merchant_id' => array(
				'title'    => __( 'Demo Merchant ID', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => __( 'VirtualMerchant ID/Account ID as provided by Elavon for your demo account.  This will be six digits long, and start with the number 5 or 6.', WC_Elavon_VM::TEXT_DOMAIN ),
				'class'    => 'demo-field',
			),

			'demo_ssl_user_id' => array(
				'title'    => __( 'Demo User ID', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'text',
				'desc_tip' => __( 'Virtual Merchant demo user ID as configured on Virtual Merchant', WC_Elavon_VM::TEXT_DOMAIN ),
				'class'    => 'demo-field',
			),

			'demo_ssl_pin' => array(
				'title'    => __( 'Demo PIN', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'password',
				'desc_tip' => __( 'VirtualMerchant demo PIN as generated within VirtualMerchant', WC_Elavon_VM::TEXT_DOMAIN ),
				'class'    => 'demo-field',
			),

			'debug_mode' => array(
				'title'    => __( 'Debug Mode', WC_Elavon_VM::TEXT_DOMAIN ),
				'type'     => 'select',
				'desc_tip' => __( 'Show Detailed Error Messages and API requests / responses on the checkout page and/or save them to the log for debugging purposes.', WC_Elavon_VM::TEXT_DOMAIN ),
				'default'  => 'off',
				'options'  => array(
					'off'      => __( 'Off', WC_Elavon_VM::TEXT_DOMAIN ),
					'checkout' => __( 'Show on Checkout Page', WC_Elavon_VM::TEXT_DOMAIN ),
					'log'      => __( 'Save to Log', WC_Elavon_VM::TEXT_DOMAIN ),
					'both'     => __( 'Both', WC_Elavon_VM::TEXT_DOMAIN )
				),
			),
		);
	}


	/**
	 * Override the admin options method to add a little javascript to control
	 * how the gateway settings behave
	 *
	 * @see WC_Settings_API::admin_options()
	 */
	public function admin_options() {

		// allow parent to do its thing
		parent::admin_options();

		// 'testmode' only applies to production accounts and hide/show the demo/production field
		ob_start();
		?>
		$( '#woocommerce_elavon_vm_account' ).change(
			function() {
				var testmode_row = $( '#woocommerce_elavon_vm_testmode' ).closest( 'tr' );

				if ( 'production' == $( this ).val() ) {
					testmode_row.show();

					$( '.production-field' ).closest( 'tr' ).show();
					$( '.demo-field' ).closest( 'tr' ).hide();
				} else {
					testmode_row.hide();

					$( '.demo-field' ).closest( 'tr' ).show();
					$( '.production-field' ).closest( 'tr' ).hide();
				}
			} ).change();
		<?php

		wc_enqueue_js( ob_get_clean() );
	}


	/**
	 * get_icon function.
	 *
	 * @see WC_Payment_Gateway::get_icon()
	 * @return string card icons
	 */
	public function get_icon() {
		global  $wc_elavon_vm;

		$icon = '';
		if ( $this->icon ) {
			// default behavior
			$icon = '<img src="' . esc_url( WC_HTTPS::force_https_url( $this->icon ) ) . '" alt="' . esc_attr( $this->title ) . '" />';
		} elseif ( $this->cardtypes ) {
			// display icons for the selected card types
			$icon = '';
			foreach ( $this->cardtypes as $cardtype ) {
				if ( file_exists( wc_elavon_vm()->get_plugin_path() . '/assets/images/card-' . strtolower( $cardtype ) . '.png' ) ) {
					$icon .= '<img src="' . esc_url( WC_HTTPS::force_https_url( wc_elavon_vm()->get_plugin_url() . '/assets/images/card-' . strtolower( $cardtype ) . '.png' ) ) . '" alt="' . esc_attr( strtolower( $cardtype ) ) . '" />';
				}
			}
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}


	/**
	 * Payment fields
	 *
	 * @see WC_Payment_Gateway::payment_fields()
	 */
	public function payment_fields() {

		$card_defaults = array(
			'account-number' => '',
			'exp-month'      => '',
			'exp-year'       => '',
			'cvv'            => '',
		);

		// for the demo environment, display a notice and supply a default test card
		if ( $this->is_demo_account() ) {

			$card_defaults = array(
				'account-number' => '4111111111111111',
				'exp-month'      => '1',
				'exp-year'       => date( 'Y' ) + 1,
				'cvv'            => '123',
			);

		}

		?>
		<style type="text/css">#payment ul.payment_methods li label[for='payment_method_elavon_vm'] img:nth-child(n+2) { margin-left:1px; }</style>
		<fieldset>
			<?php if ( $this->get_description() ) : ?><?php echo wpautop( wptexturize( $this->get_description() ) ); ?><?php endif; ?>

			<p class="form-row form-row-first">
				<label for="elavon_vm_accountNumber"><?php echo __( "Credit Card number", WC_Elavon_VM::TEXT_DOMAIN ) ?> <span class="required">*</span></label>
				<input type="text" class="input-text" id="elavon_vm_accountNumber" name="elavon_vm_accountNumber" maxlength="19" autocomplete="off" value="<?php echo $card_defaults['account-number']; ?>" />
			</p>
			<p class="form-row form-row-last">
				<label for="elavon_vm_expirationMonth"><?php echo __( "Expiration date", WC_Elavon_VM::TEXT_DOMAIN ) ?> <span class="required">*</span></label>
				<select name="elavon_vm_expirationMonth" id="elavon_vm_expirationMonth" class="woocommerce-select woocommerce-cc-month" style="width:auto;">
					<option value=""><?php _e( 'Month', WC_Elavon_VM::TEXT_DOMAIN ) ?></option>
					<?php foreach ( range( 1, 12 ) as $month ) : ?>
						<option value="<?php echo sprintf( '%02d', $month ); ?>" <?php selected( $card_defaults['exp-month'], $month ); ?>><?php echo sprintf( '%02d', $month ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="elavon_vm_expirationYear" id="elavon_vm_expirationYear" class="woocommerce-select woocommerce-cc-year" style="width:auto;">
					<option value=""><?php _e( 'Year', WC_Elavon_VM::TEXT_DOMAIN ) ?></option>
					<?php foreach ( range( date( 'Y' ), date( 'Y' ) + 6 ) as $year ) : ?>
						<option value="<?php echo $year; ?>" <?php selected( $card_defaults['exp-year'], $year ); ?>><?php echo $year; ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<div class="clear"></div>

			<?php if ( $this->cvv_required() ) : ?>

				<p class="form-row form-row-first">
					<label for="elavon_vm_cvNumber"><?php _e( "Card security code", WC_Elavon_VM::TEXT_DOMAIN ) ?> <span class="required">*</span></label>
					<input type="text" class="input-text" id="elavon_vm_cvNumber" name="elavon_vm_cvNumber" maxlength="4" style="width:60px" autocomplete="off" value="<?php echo $card_defaults['cvv']; ?>" />
				</p>
			<?php endif ?>
		</fieldset>
		<?php
	}


	/**
	 * Process the payment and return the result
	 *
	 * @see WC_Payment_Gateway::process_payment()
	 * @param int $order_id the order identifier
	 */
	public function process_payment( $order_id ) {

		$order = SV_WC_Plugin_Compatibility::wc_get_order( $order_id );

		require_once( 'class-wc-elavon-vm-api.php' );

		// create the elavon vm api client
		$elavon_client = new Elavon_VM_API( $this->get_endpoint_url(), $this->get_ssl_merchant_id(), $this->get_ssl_user_id(), $this->get_ssl_pin(), $this->log_enabled() );

		$response = $this->transaction_request( $elavon_client, $order );

		if ( $response ) {

			if ( $this->log_enabled() ) {
				wc_elavon_vm()->log( "Response:\n" . print_r( $response, true ) );
			}

			if ( '0' == $response->ssl_result ) {
				// Successful payment

				// if debug mode load the response into the messages object
				if ( $this->is_debug_mode() ) {
					$this->response_debug_message( $response, 'message', true );
				}

				// update the order record with success

				$order->add_order_note( sprintf( __( 'Credit Card Transaction Approved: %s (%s)', WC_Elavon_VM::TEXT_DOMAIN ),
					$response->ssl_card_number, substr( $response->ssl_exp_date, 0, 2 ) . '/' . substr( $response->ssl_exp_date, 2 ) ) );

				// store the payment reference and card number in the order
				update_post_meta( $order->id, '_elavon_txn_id',      (string) $response->ssl_txn_id );
				update_post_meta( $order->id, '_elavon_card_number', (string) $response->ssl_card_number );
				update_post_meta( $order->id, '_elavon_account',     (string) $this->account );

				$order->payment_complete();

				WC()->cart->empty_cart();

				// Return thank you redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);

			} else {

				if ( $response->ssl_result || $response->ssl_result_message ) {
					$error_note = sprintf( __( 'Elavon VM Credit Card payment failed (Result: [%s] - "%s").', WC_Elavon_VM::TEXT_DOMAIN ), $response->ssl_result, $response->ssl_result_message );
				} else {
					$error_note = sprintf( __( 'Elavon VM Credit Card payment failed (Result: [%s] %s - "%s").', WC_Elavon_VM::TEXT_DOMAIN ), $response->errorCode, $response->errorName, $response->errorMessage );
				}

				if ( 'CALL AUTH CENTER' == $response->ssl_result_message || 'CALL REF:' == substr( $response->ssl_result_message, 0, 9 ) ) {
					// voice authorization required
					$error_note .= __( 'Voice authorization required to complete transaction, please call your merchant account.', WC_Elavon_VM::TEXT_DOMAIN );

					$order->update_status( 'on-hold', $error_note );
				} else {
					// default behavior
					$this->order_failed( $order, $error_note );
				}

				// default customer message
				$message = __( 'An error occurred, please try again or try an alternate form of payment', WC_Elavon_VM::TEXT_DOMAIN );

				// specific customer message
				if ( 'CALL AUTH CENTER' == $response->ssl_result_message || 'CALL REF:' == substr( $response->ssl_result_message, 0, 9 ) ) {
					// voice authorization required
					$message = __( 'This transaction request has not been approved. You may elect to use another form of payment to complete this transaction or contact customer service for additional options.', WC_Elavon_VM::TEXT_DOMAIN );
				}

				wc_add_notice( $message, 'error' );

				// if debug mode load the response into the messages object
				if ( $this->is_debug_mode() ) {
					$this->response_debug_message( $response, 'error' );
				}
			}
		} else {

			if ( $this->log_enabled() ) {
				wc_elavon_vm()->log( "ERROR: No response received" );
			}

			wc_add_notice( __( 'Connection error', WC_Elavon_VM::TEXT_DOMAIN ), 'error' );
		}
	}


	/**
	 * Validate payment form fields
	 *
	 * @see WC_Payment_Gateway::validate_fields()
	 */
	public function validate_fields() {

		$is_valid = parent::validate_fields();

		$account_number   = $this->get_post( 'elavon_vm_accountNumber' );
		$cv_number        = $this->get_post( 'elavon_vm_cvNumber' );
		$expiration_month = $this->get_post( 'elavon_vm_expirationMonth' );
		$expiration_year  = $this->get_post( 'elavon_vm_expirationYear' );

		// Elavon has stringent length limits for name/address fields
		$billing_field_lengths = array(
			'billing_first_name' => 20,
			'billing_last_name'  => 30,
			'billing_company'    => 50,
			'billing_address_1'  => 30,
			'billing_address_2'  => 30,
			'billing_city'       => 30,
			'billing_state'      => 30,
			'billing_postcode'   => 9,
			'billing_country'    => 50,
			'billing_email'      => 100,
			'billing_phone'      => 20,
		);

		// for each of our billing fields with maximum lengths
		foreach ( $billing_field_lengths as $field_name => $length ) {

			// if the supplied length exceeds the maximum
			if ( strlen( $this->get_post( $field_name ) ) > $length ) {

				// is there a checkout field with this name, to grab the label from?  Otherwise, just use the upper-cased version of $field_name
				if ( isset( WC()->checkout()->checkout_fields['billing'][ $field_name ]['label'] ) && WC()->checkout()->checkout_fields['billing'][ $field_name ]['label'] ) {
					$field_label = WC()->checkout()->checkout_fields['billing'][ $field_name ]['label'];
				} else {
					$field_label = ucwords( str_replace( '_', ' ', $field_name ) );
				}

				if ( false === stripos( $field_label, 'billing' ) ) {
					wc_add_notice( sprintf( __( 'The billing %s is too long, %d characters maximum are allowed.  Please fix the %s and try again.', WC_Elavon_VM::TEXT_DOMAIN ), $field_label, $length, $field_label ), 'error' );
					$is_valid = false;
				} else {
					wc_add_notice( sprintf( __( 'The %s is too long, %d characters maximum are allowed.  Please fix the %s and try again.', WC_Elavon_VM::TEXT_DOMAIN ), $field_label, $length, $field_label ), 'error' );
					$is_valid = false;
				}

			}

		}

		if ( $this->cvv_required() ) {
			// check security code
			if ( empty( $cv_number ) ) {
				wc_add_notice( __( 'Card security code is missing', WC_Elavon_VM::TEXT_DOMAIN ), 'error' );
				$is_valid = false;
			}

			if ( ! ctype_digit( $cv_number ) ) {
				wc_add_notice( __( 'Card security code is invalid (only digits are allowed)', WC_Elavon_VM::TEXT_DOMAIN ), 'error' );
				$is_valid = false;
			}

			if ( strlen( $cv_number ) < 3 || strlen( $cv_number ) > 4 ) {
				wc_add_notice( __( 'Card security code is invalid (wrong length)', WC_Elavon_VM::TEXT_DOMAIN ), 'error' );
				$is_valid = false;
			}
		}

		// check expiration data
		$current_year  = date( 'Y' );
		$current_month = date( 'n' );

		if ( ! ctype_digit( $expiration_month ) || ! ctype_digit( $expiration_year ) ||
			$expiration_month > 12 ||
			$expiration_month < 1 ||
			$expiration_year < $current_year ||
			( $expiration_year == $current_year && $expiration_month < $current_month ) ||
			$expiration_year > $current_year + 20
		) {
			wc_add_notice( __( 'Card expiration date is invalid', WC_Elavon_VM::TEXT_DOMAIN ), 'error' );
			$is_valid = false;
		}

		// check card number
		$account_number = str_replace( array( ' ', '-' ), '', $account_number );

		if ( empty( $account_number ) || ! ctype_digit( $account_number ) || ! $this->luhn_check( $account_number ) ) {
			wc_add_notice( __( 'Card number is invalid', WC_Elavon_VM::TEXT_DOMAIN ), 'error' );
			$is_valid = false;
		}

		return $is_valid;
	}


	/**
	 * Checks for proper gateway configuration (required fields populated, etc)
	 * and that there are no missing dependencies
	 *
	 * @see WC_Payment_Gateway::is_available()
	 */
	public function is_available() {

		// proper configuration
		if ( ! $this->get_ssl_merchant_id() || ! $this->get_ssl_user_id() || ! $this->get_ssl_pin() ) {
			return false;
		}

		// all dependencies met
		if ( count( wc_elavon_vm()->get_missing_dependencies() ) > 0 ) {
			return false;
		}

		return parent::is_available();
	}


	/** Communication methods ******************************************************/


	/**
	 * Perform a credit card transaction request
	 *
	 * @param Elavon_VM_API $elavon_client elavon api client
	 * @param WC_Order $order the order
	 *
	 * @return SimpleXMLElement response, or false on error
	 */
	private function transaction_request( $elavon_client, $order ) {

		$request = new stdClass();

		$request->ssl_test_mode        = $this->is_test_mode() ? "true" : "false";
		$request->ssl_transaction_type = $this->auth_settle() ? "ccsale" : "ccauthonly";
		$request->ssl_invoice_number   = ltrim( $order->get_order_number(), _x( '#', 'hash before order number', WC_Elavon_VM::TEXT_DOMAIN ) );

		$request->ssl_card_number = $this->get_post( 'elavon_vm_accountNumber' );
		$request->ssl_exp_date    = $this->get_post( 'elavon_vm_expirationMonth' ) . substr( $this->get_post( 'elavon_vm_expirationYear' ), -2 );
		$request->ssl_amount      = number_format( $order->get_total(), 2, '.', '' );
		$request->ssl_salestax    = number_format( $order->get_total_tax(), 2 );

		// Note:  this is a fix suggested by Elavon that should work "90%" of the time.  We'll go with it for now, until someone really needs a POID field collected on the frontend
		$request->ssl_customer_code = substr( $this->get_post( 'elavon_vm_accountNumber' ), -4 );

		$request->ssl_cvv2cvc2_indicator = $this->cvv_required() ? "1" : "0";
		if ( $this->cvv_required() ) {
			$request->ssl_cvv2cvc2 = $this->get_post( 'elavon_vm_cvNumber' );
		}

		$request->ssl_first_name  = $order->billing_first_name;
		$request->ssl_last_name   = $order->billing_last_name;
		$request->ssl_company     = $order->billing_company;
		$request->ssl_avs_address = $order->billing_address_1;
		$request->ssl_address2    = $order->billing_address_2;
		$request->ssl_city        = $order->billing_city;
		$request->ssl_state       = $order->billing_state;
		$request->ssl_avs_zip     = $order->billing_postcode;
		$request->ssl_country     = $order->billing_country;  // country code
		$request->ssl_email       = $order->billing_email;
		$request->ssl_phone       = preg_replace( '/[^0-9]/', '', $order->billing_phone );
		$request->ssl_cardholder_ip = $_SERVER['REMOTE_ADDR'];

		return $elavon_client->transaction_request( $request );
	}


	/** Helper methods ******************************************************/


	/**
	 * Mark the given order as failed, and set the order note
	 *
	 * @param WC_Order $order the order
	 * @param string $order_note the order note to set
	 */
	private function order_failed( $order, $order_note ) {
		if ( ! SV_WC_Plugin_Compatibility::order_has_status( $order, 'failed' ) ) {
			$order->update_status( 'failed', $order_note );
		} else {
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			$order->add_order_note( $order_note );
		}
	}


	/**
	 * Perform standard luhn check.  Algorithm:
	 *
	 * 1. Double the value of every second digit beginning with the second-last right-hand digit.
	 * 2. Add the individual digits comprising the products obtained in step 1 to each of the other digits in the original number.
	 * 3. Subtract the total obtained in step 2 from the next higher number ending in 0.
	 * 4. This number should be the same as the last digit (the check digit). If the total obtained in step 2 is a number ending in zero (30, 40 etc.), the check digit is 0.
	 *
	 * @param string $account_number the credit card number to check
	 *
	 * @return boolean true if $account_number passes the check, false otherwise
	 */
	private function luhn_check( $account_number ) {
		$sum = 0;
		for ( $i = 0, $ix = strlen( $account_number ); $i < $ix - 1; $i++) {
			$weight = substr( $account_number, $ix - ( $i + 2 ), 1 ) * ( 2 - ( $i % 2 ) );
			$sum += $weight < 10 ? $weight : $weight - 9;
		}

		return substr( $account_number, $ix - 1 ) == ( ( 10 - $sum % 10 ) % 10 );
	}


	/**
	 * Add the XML response to the woocommerce message object
	 *
	 * @param SimpleXMLElement $response response from the Elavon server
	 * @param string $type optional message type, one of 'message' or 'error', defaults to 'message'
	 * @param boolean $set_message optional whether to set the supplied
	 *        message so that it appears on the next page load (ie, a
	 *        message you want displayed on the 'thank you' page
	 *
	 * @return void
	 */
	private function response_debug_message( $response, $type = 'message', $set_message = false ) {

		$dom = dom_import_simplexml( $response )->ownerDocument;
		$dom->formatOutput = true;
		$debug_message = "<pre>" . htmlspecialchars( $dom->saveXML() ) . "</pre>";

		if ( $type == 'message' ) {
			wc_add_notice( $debug_message );
		} else {
			wc_add_notice( $debug_message, 'error' );
		}
	}


	/**
	 * Safely get post data if set
	 *
	 * @param string $name name of post argument to get
	 * @return mixed post data, or null
	 */
	private function get_post( $name ) {
		if ( isset( $_POST[ $name ] ) ) {
			return trim( $_POST[ $name ] );
		}
		return null;
	}


	/** Getter methods ******************************************************/


	/**
	 * Returns true if the demo account is being used
	 *
	 * @since 1.1.1
	 * @return boolean if the demo account is being used
	 */
	private function is_demo_account() {
		return 'demo' == $this->account;
	}


	/**
	 * Returns the SSL merchant id
	 *
	 * @return string SSL merchant id
	 */
	private function get_ssl_merchant_id() {
		return $this->is_demo_account() ? $this->demo_ssl_merchant_id : $this->sslmerchantid;
	}


	/**
	 * Returns the SSL user id
	 *
	 * @return string SSL user id
	 */
	private function get_ssl_user_id() {
		return $this->is_demo_account() ? $this->demo_ssl_user_id : $this->ssluserid;
	}


	/**
	 * Returns the SSL pin
	 *
	 * @return string SSL pin
	 */
	private function get_ssl_pin() {
		return $this->is_demo_account() ? $this->demo_ssl_pin : $this->sslpin;
	}


	/**
	 * Returns the endpoint url
	 *
	 * @return string endpoint URL
	 */
	private function get_endpoint_url() {
		return $this->is_demo_account() ? $this->demo_endpoint_url : $this->live_endpoint_url;
	}


	/**
	 * Is the card security code required?
	 *
	 * @return boolean true if the card security code is required
	 */
	public function cvv_required() {
		return 'yes' == $this->cvv;
	}


	/**
	 * Perform an authorization and settlement (capture funds)?
	 *
	 * @return boolean true if a settlement should be performed, false if authorize-only
	 */
	public function auth_settle() {
		return 'yes' == $this->settlement;
	}


	/**
	 * Is test mode enabled?
	 *
	 * @return boolean true if test mode is enabled
	 */
	public function is_test_mode() {
		// test mode only applies to production accounts
		return 'production' == $this->account && 'yes' == $this->testmode;
	}


	/**
	 * Is debug mode enabled?
	 *
	 * @return boolean true if debug mode is enabled
	 */
	public function is_debug_mode() {
		return 'both' == $this->debug_mode || 'checkout' == $this->debug_mode;
	}


	/**
	 * Should communication be logged?
	 *
	 * @return boolean true if log mode is enabled
	 */
	public function log_enabled() {
		return 'both' == $this->debug_mode || 'log' == $this->debug_mode;
	}


	/**
	 * Returns true if the gateway is enabled.  This has nothing to do with
	 * whether the gateway is properly configured or functional.
	 *
	 * @return boolean true if the gateway is enabled
	 */
	private function is_enabled() {
		return $this->enabled;
	}

} // WC_Gateway_Elavon_VM
