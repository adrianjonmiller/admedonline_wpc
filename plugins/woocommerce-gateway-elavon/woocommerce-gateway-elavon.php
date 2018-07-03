<?php
/**
 * Plugin Name: WooCommerce Elavon VM Gateway
 * Plugin URI: http://www.woothemes.com/products/elavon-vm-payment-gateway/
 * Description: Adds the Elavon Virtual Merchant Gateway to your WooCommerce website. Requires an SSL certificate.
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com
 * Version: 1.3.0
 * Text Domain: woocommerce-gateway-elavon
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2013-2014 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Elavon
 * @author    SkyVerge
 * @category  Payment-Gateways
 * @copyright Copyright (c) 2012-2015, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '2732aedb77a13149b4db82d484d3bb22', '18722' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '3.1.0', __( 'WooCommerce Elavon VM Gateway', 'woocommerce-gateway-elavon' ), __FILE__, 'init_woocommerce_gateway_elavon', array( 'minimum_wc_version' => '2.1', 'backwards_compatible' => '3.1.0' ) );

function init_woocommerce_gateway_elavon() {

/**
 * The main class for the Elavon VM Payment Gateway.  This class handles all the
 * non-gateway tasks such as verifying dependencies are met, loading the text
 * domain, etc.
 *
 */
class WC_Elavon_VM extends SV_WC_Plugin {


	/** version number */
	const VERSION = '1.3.0';

	/** @var WC_Elavon_VM single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'elavon_vm';

	/** plugin text domain */
	const TEXT_DOMAIN = 'woocommerce-gateway-elavon';

	/** string class name to load as gateway */
	const GATEWAY_CLASS_NAME = 'WC_Gateway_Elavon_VM';


	/**
	 * Initialize the plugin
	 *
	 * @see SV_WC_Plugin::__construct()
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			self::TEXT_DOMAIN,
			array(
				'dependencies' => array( 'simplexml', 'dom' ),
			)
		);

		// Load the gateway
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'load_classes' ) );
	}


	/**
	 * Loads Gateway class once parent class is available
	 */
	public function load_classes() {

		// Elavon gateway
		require_once( 'includes/class-wc-gateway-elavon-vm.php' );

		// Add class to WC Payment Methods
		add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway' ) );
	}


	/**
	 * Adds gateway to the list of available payment gateways
	 *
	 * @param array $gateways array of gateway names or objects
	 * @return array $gateways array of gateway names or objects
	 */
	public function load_gateway( $gateways ) {

		$gateways[] = self::GATEWAY_CLASS_NAME;

		return $gateways;
	}


	/**
	 * Load the translation so that WPML is supported
	 *
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {
		load_plugin_textdomain( 'woocommerce-gateway-elavon', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woothemes.com/document/elavon-vm-payment-gateway/';
	}


	/**
	 * Returns the review page url
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::get_review_url()
	 * @return string review URL, or ''
	 */
	public function get_review_url() {
		return 'http://www.skyverge.com/product/woocommerce-elavon-vm-payment-gateway/#tab-reviews';
	}


	/**
	 * Gets the gateway configuration URL
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::get_settings_url()
	 * @param string $plugin_id the plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {
		return $this->get_payment_gateway_configuration_url( self::GATEWAY_CLASS_NAME );
	}


	/**
	 * Returns true if on the gateway settings page
	 *
	 * @since 1.2
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the admin gateway settings page
	 */
	public function is_plugin_settings() {
		return $this->is_payment_gateway_configuration_page( self::GATEWAY_CLASS_NAME );
	}


	/**
	 * Returns the admin configuration url for the gateway with class name
	 * $gateway_class_name
	 *
	 * @since 2.2.0-1
	 * @param string $gateway_class_name the gateway class name
	 * @return string admin configuration url for the gateway
	 */
	public function get_payment_gateway_configuration_url( $gateway_class_name ) {

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( $gateway_class_name ) );
	}


	/**
	 * Returns true if the current page is the admin configuration page for the
	 * gateway with class name $gateway_class_name
	 *
	 * @since 2.2.0-1
	 * @param string $gateway_class_name the gateway class name
	 * @return boolean true if the current page is the admin configuration page for the gateway
	 */
	public function is_payment_gateway_configuration_page( $gateway_class_name ) {

		return isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] &&
		isset( $_GET['tab'] ) && 'checkout' == $_GET['tab'] &&
		isset( $_GET['section'] ) && strtolower( $gateway_class_name ) == $_GET['section'];
	}


	/**
	 * Checks if required PHP extensions are loaded and SSL is enabled. Adds an admin notice if either check fails.
	 * Also gateway settings are checked as well.
	 *
	 * @since 1.2.3
	 * @see SV_WC_Plugin::add_delayed_admin_notices()
	 */
	public function add_delayed_admin_notices() {

		parent::add_delayed_admin_notices();

		// show a notice for any settings/configuration issues
		$this->add_ssl_required_admin_notice();
	}


	/**
	 * Render the SSL Required notice, as needed
	 *
	 * @since 1.2.3
	 */
	private function add_ssl_required_admin_notice() {

		// check settings:  gateway active and SSl enabled
		$settings = get_option( 'woocommerce_elavon_settings' );

		if ( isset( $settings['enabled'] ) && 'yes' == $settings['enabled'] && isset( $settings['environment'] ) && 'production' == $settings['environment'] ) {
			// SSL check if gateway enabled/production mode
			if ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$message = sprintf(
					__( "%Elavon Error%s: WooCommerce is not being forced over SSL; your customer's payment data is at risk.", self::TEXT_DOMAIN ),
					'<strong>', '</strong>'
				);
				$this->get_admin_notice_handler()->add_admin_notice( $message, 'ssl-required' );
			}
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Main <Plugin Name> Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.3.0
	 * @see wc_elavon_vm()
	 * @return WC_Elavon_VM
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.2
	 * @see SV_WC_Payment_Gateway::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Elavon', self::TEXT_DOMAIN );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.2
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @see SV_WC_Plugin::install()
	 */
	protected function install() {

		// check for a pre 1.2 version
		$legacy_version = get_option( 'wc_gateway_elavon_vm' );

		if ( false !== $legacy_version ) {

			// upgrade path from previous version, trash old version option
			delete_option( 'wc_gateway_elavon_vm' );

			// upgrade path
			$this->upgrade( $legacy_version );

			// and we're done
			return;
		}
	}


	/**
	 * Run when plugin version number changes
	 *
	 * @see SV_WC_Plugin::upgrade()
	 */
	protected function upgrade( $installed_version ) {
		global $wpdb;

		// if installed version is less than 1.0.4, set the correct account type, if needed
		if ( version_compare( $installed_version, "1.0.4", '<' ) ) {

			// Can't think of a great way of grabbing this from the abstract WC_Settings_API class
			$plugin_id = 'woocommerce_';

			$form_field_settings = (array) get_option( $plugin_id . self::PLUGIN_ID . '_settings' );

			// for existing installs, configured prior to the introduction of the 'account' setting
			if ( $form_field_settings && ! isset( $form_field_settings['account'] ) ) {

				if ( isset( $form_field_settings['testmode'] ) && 'yes' == $form_field_settings['testmode'] ) {
					$form_field_settings['account'] = 'demo';
				} else {
					$form_field_settings['account'] = 'production';
				}

				// set the account type
				update_option( $plugin_id . self::PLUGIN_ID . '_settings', $form_field_settings );
			}
		}

		// standardize debug_mode setting
		if ( version_compare( $installed_version, "1.1.1", '<' ) && ( $settings = get_option( 'woocommerce_' . self::PLUGIN_ID . '_settings' ) ) ) {

			// previous settings
			$log_enabled   = isset( $settings['log'] )   && 'yes' == $settings['log']   ? true : false;
			$debug_enabled = isset( $settings['debug'] ) && 'yes' == $settings['debug'] ? true : false;

			// logger -> debug_mode
			if ( $log_enabled && $debug_enabled ) {
				$settings['debug_mode'] = 'both';
			} elseif ( ! $log_enabled && ! $debug_enabled ) {
				$settings['debug_mode'] = 'off';
			} elseif ( $log_enabled ) {
				$settings['debug_mode'] = 'log';
			} else {
				$settings['debug_mode'] = 'checkout';
			}

			unset( $settings['log'] );
			unset( $settings['debug'] );

			update_option( 'woocommerce_' . self::PLUGIN_ID . '_settings', $settings );
		}

	}


} // end WC_Elavon_VM


/**
 * Returns the One True Instance of Elavon VM
 *
 * @since 1.3.0
 * @return WC_Elavon_VM
 */
function wc_elavon_vm() {
	return WC_Elavon_VM::instance();
}


/**
 * The WC_Elavon_VM global object, exists only for backwards compat
 *
 * @deprecated 1.3.0
 * @name $wc_elavon_vm
 * @global WC_Elavon_VM $GLOBALS['wc_elavon_vm']
 */
$GLOBALS['wc_elavon_vm'] = wc_elavon_vm();


} // init_woocommerce_gateway_elavon()
