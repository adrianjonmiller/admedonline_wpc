<?php
/**
 * Plugin Name: FG PrestaShop to WooCommerce Premium
 * Plugin Uri:  http://www.fredericgilles.net/fg-prestashop-to-woocommerce/
 * Description: A plugin to migrate PrestaShop e-commerce solution to WooCommerce
 * Version:     1.7.0
 * Author:      Frédéric GILLES
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

require_once 'fgp2wc-users-authenticate.php';
require_once 'fgp2wc-url-rewriting.php';

register_activation_hook( __FILE__, 'fgp2wcp_activate' );

function fgp2wcp_activate() {
	flush_rewrite_rules();
}

if ( !defined('WP_LOAD_IMPORTERS') ) return;

require_once 'fg-prestashop-to-woocommerce.php';

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		require_once $class_wp_importer;
	}
}

if ( !function_exists( 'fgp2wcp_load' ) ) {
	remove_action( 'plugins_loaded', 'fgp2wc_load', 20 );
	add_action( 'plugins_loaded', 'fgp2wcp_load', 21 );
	
	function fgp2wcp_load() {
		new fgp2wcp();
		define('FGP2WCP_LOADED', 1);
	}
}

if ( !class_exists('fgp2wcp', false) ) {
	class fgp2wcp extends fgp2wc {
		
		public $premium_options = array();
		private $users_count = 0;
		private $orders_count = 0;
		private $reviews_count = 0;
		private $vouchers_count = 0;
		private $attribute_values = array();
		private $feature_values = array();
		
		/**
		 * Sets up the plugin
		 *
		 */
		public function __construct() {
			parent::__construct();
			
			add_filter('fgp2wc_pre_display_admin_page', array ($this, 'process_admin_page'), 10, 1);
			add_action('fgp2wc_post_empty_database', array ($this, 'reset_counters'), 10, 1);
			add_action('fgp2wc_post_empty_database', array ($this, 'delete_users'), 10, 1);
			add_action('fgp2wc_post_empty_database', array ($this, 'set_truncate_option'), 10, 1);
			add_action('fgp2wc_post_save_plugin_options', array ($this, 'save_premium_options') );
			add_action('fgp2wc_post_insert_post', array ($this, 'set_meta_seo'), 10, 2);
			add_action('fgp2wc_post_import', array ($this, 'import_premium'));
			add_action('fgp2wc_import_notices', array ($this, 'display_users_count'));
			add_action('fgp2wc_import_notices', array ($this, 'display_orders_count'));
			add_action('fgp2wc_import_notices', array ($this, 'display_reviews_count'));
			add_action('fgp2wc_import_notices', array ($this, 'display_vouchers_count'));
			
			// Default options values
			$this->premium_options = array(
				'cookie_key'				=> '',
				'import_meta_seo'			=> false,
				'url_redirect'				=> false,
				'skip_cms'					=> false,
				'skip_products'				=> false,
				'skip_users'				=> false,
				'skip_orders'				=> false,
				'skip_reviews'				=> false,
				'skip_vouchers'				=> false,
			);
			$options = get_option('fgp2wcp_options');
			if ( is_array($options) ) {
				$this->premium_options = array_merge($this->premium_options, $options);
			}
		}
		
		/**
		 * Initialize the plugin
		 */
		public function init() {
			parent::init();
			
			load_plugin_textdomain( __CLASS__, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			
			register_importer('fgp2wc', __('PrestaShop', 'fgp2wcp'), __('Import PrestaShop e-commerce solution to WooCommerce', 'fgp2wcp'), array ($this, 'dispatch'));
		}
		
		/**
		 * Add information to the admin page
		 * 
		 * @param array $data
		 * @return array
		 */
		public function process_admin_page($data) {
			$data['title'] = __('Import PrestaShop Premium', __CLASS__);
			$data['description'] = __('This plugin will import products, categories, tags, images, CMS, employees, customers and orders from PrestaShop to WooCommerce/WordPress.', __CLASS__);
			$data['description'] .= "<br />\n" . __('For any issue, please read the <a href="http://www.fredericgilles.net/fg-prestashop-to-woocommerce" target="_blank">FAQ</a> first.', __CLASS__);

			// Users
			$count_users = count_users();
			$data['users_count'] = $count_users['total_users'];
			$data['database_info'][] = sprintf(_n('%d user', '%d users', $data['users_count'], __CLASS__), $data['users_count']);

			// Orders
			$data['orders_count'] = $this->count_posts('shop_order');
			$data['database_info'][] = sprintf(_n('%d order', '%d orders', $data['orders_count'], __CLASS__), $data['orders_count']);
			
			// Premium options
			foreach ( $this->premium_options as $key => $value ) {
				$data[$key] = $value;
			}
			
			return $data;
		}
		
		/**
		 * Save the Premium options
		 *
		 */
		public function save_premium_options() {
			$this->premium_options = array_merge($this->premium_options, $this->validate_form_premium_info());
			update_option('fgp2wcp_options', $this->premium_options);
		}
		
		/**
		 * Validate POST info
		 *
		 * @return array Form parameters
		 */
		private function validate_form_premium_info() {
			return array(
				'cookie_key'				=> filter_input(INPUT_POST, 'cookie_key', FILTER_SANITIZE_STRING),
				'import_meta_seo'			=> filter_input(INPUT_POST, 'import_meta_seo', FILTER_VALIDATE_BOOLEAN),
				'url_redirect'				=> filter_input(INPUT_POST, 'url_redirect', FILTER_VALIDATE_BOOLEAN),
				'skip_cms'					=> filter_input(INPUT_POST, 'skip_cms', FILTER_VALIDATE_BOOLEAN),
				'skip_products'				=> filter_input(INPUT_POST, 'skip_products', FILTER_VALIDATE_BOOLEAN),
				'skip_users'				=> filter_input(INPUT_POST, 'skip_users', FILTER_VALIDATE_BOOLEAN),
				'skip_orders'				=> filter_input(INPUT_POST, 'skip_orders', FILTER_VALIDATE_BOOLEAN),
				'skip_reviews'				=> filter_input(INPUT_POST, 'skip_reviews', FILTER_VALIDATE_BOOLEAN),
				'skip_vouchers'				=> filter_input(INPUT_POST, 'skip_vouchers', FILTER_VALIDATE_BOOLEAN),
			);
		}
		
		/**
		 * Set the truncate option in order to keep use the "keep PrestaShop ID" feature
		 * 
		 * @param string $action	newposts = removes only new imported posts
		 * 							all = removes all
		 */
		public function set_truncate_option($action) {
			if ( $action == 'all' ) {
				update_option('fgp2wc_truncate_posts_table', 1);
			} else {
				delete_option('fgp2wc_truncate_posts_table');
			}
		}
		
		/**
		 * Reset the plugin counters
		 * 
		 * @param string $action	newposts = removes only new imported posts
		 * 							all = removes all
		 */
		public function reset_counters($action) {
			update_option('fgp2wc_last_prestashop_order_id', 0);
			update_option('fgp2wc_last_prestashop_review_id', 0);
			update_option('fgp2wc_last_prestashop_cart_rule_id', 0);
		}
		
		/**
		 * Delete all users except the current user
		 *
		 */
		public function delete_users($action) {
			global $wpdb;
			
			if ( $action != 'all' ) {
				return;
			}
			
			$wpdb->show_errors();
			
			$current_user = get_current_user_id();
			if ( is_multisite() ) {
				$blogusers = get_users(array('exclude' => $current_user));
				foreach ( $blogusers as $user ) {
					wp_delete_user($user->ID);
				}
			} else { // monosite (quicker)
				$sql_queries = array();
				$sql_queries[] = <<<SQL
-- Delete User meta
DELETE FROM $wpdb->usermeta
WHERE user_id != '$current_user'
SQL;

				$sql_queries[] = <<<SQL
-- Delete Users
DELETE FROM $wpdb->users
WHERE ID != '$current_user'
SQL;
				// Execute SQL queries
				if ( count($sql_queries) > 0 ) {
					foreach ( $sql_queries as $sql ) {
						$wpdb->query($sql);
					}
				}
			}
			wp_cache_flush();
			
			$wpdb->hide_errors();
			
			$this->display_admin_notice(__('Users deleted', 'fgp2wcp'));
		}
		
		/**
		 * Import Premium data
		 *
		 */
		public function import_premium() {
			if ( !isset($this->premium_options['skip_products']) || !$this->premium_options['skip_products'] ) {
				$this->import_features();
				$this->import_attributes();
				$this->import_products_features();
				$this->import_products_attributes();
				$this->import_products_variations();
			}
			if ( !isset($this->premium_options['skip_users']) || !$this->premium_options['skip_users'] ) {
				$this->import_users();
			}
			if ( !isset($this->premium_options['skip_orders']) || !$this->premium_options['skip_orders'] ) {
				$this->import_orders();
			}
			if ( !isset($this->premium_options['skip_reviews']) || !$this->premium_options['skip_reviews'] ) {
				$this->import_reviews();
			}
			if ( !isset($this->premium_options['skip_vouchers']) || !$this->premium_options['skip_vouchers'] ) {
				$this->import_vouchers();
			}
			$this->remove_transients(); // The transients may cause bugs in the front attributes drop-down lists.
		}
		
		/**
		 * Import users
		 *
		 */
		private function import_users() {
			$this->import_employees();
			$this->import_customers();
		}
		
		/**
		 * Import employees
		 *
		 */
		private function import_employees() {
			$employees = $this->get_employees();
			foreach ( $employees as $employee ) {
				$user_id = $this->add_user($employee['firstname'], $employee['lastname'], $employee['email'], $employee['passwd'], '', 'editor');
				if ( !is_wp_error($user_id) ) {
					// Link between the PrestaShop ID and the WordPress user ID
					add_user_meta($user_id, 'prestashop_employee_id', $employee['id_employee'], true);
				}
			}
		}
		
		/**
		 * Import customers
		 *
		 */
		private function import_customers() {
			$customers = $this->get_customers();
			foreach ( $customers as $customer ) {
				$user_id = $this->add_user($customer['firstname'], $customer['lastname'], $customer['email'], $customer['passwd'], '', 'customer');
				if ( !is_wp_error($user_id) ) {
					// Add the customer web site
					wp_update_user(array('ID' => $user_id, 'user_url' => $customer['website']));
					// Link between the PrestaShop ID and the WordPress user ID
					add_user_meta($user_id, 'prestashop_customer_id', $customer['id_customer'], true);
					// Add the address fields
					$address = $this->get_customer_address($customer['id_customer']);
					if ( !empty($address) ) {
						update_user_meta($user_id, 'billing_company', $address['company']);
						update_user_meta($user_id, 'billing_last_name', $address['lastname']);
						update_user_meta($user_id, 'billing_first_name', $address['firstname']);
						update_user_meta($user_id, 'billing_phone', $address['phone']);
						update_user_meta($user_id, 'billing_address_1', $address['address1']);
						update_user_meta($user_id, 'billing_address_2', $address['address2']);
						update_user_meta($user_id, 'billing_city', $address['city']);
						update_user_meta($user_id, 'billing_state', $address['state']);
						update_user_meta($user_id, 'billing_country', $address['country']);
						update_user_meta($user_id, 'billing_postcode', $address['postcode']);
						update_user_meta($user_id, 'billing_email', $customer['email']);
						update_user_meta($user_id, 'shipping_company', $address['company']);
						update_user_meta($user_id, 'shipping_last_name', $address['lastname']);
						update_user_meta($user_id, 'shipping_first_name', $address['firstname']);
						update_user_meta($user_id, 'shipping_address_1', $address['address1']);
						update_user_meta($user_id, 'shipping_address_2', $address['address2']);
						update_user_meta($user_id, 'shipping_city', $address['city']);
						update_user_meta($user_id, 'shipping_state', $address['state']);
						update_user_meta($user_id, 'shipping_country', $address['country']);
						update_user_meta($user_id, 'shipping_postcode', $address['postcode']);
					}
				}
			}
		}
		
		/**
		 * Import the PrestaShop orders
		 *
		 */
		private function import_orders() {
			global $wpdb;
			$step = 1000; // to limit the results
			
			$customers = $this->get_imported_customers();
			$products_ids = $this->get_woocommerce_products();
			$shipment_methods = $this->get_shipments_methods();
			
			do {
				$orders = $this->get_orders($step);
				foreach ( $orders as $order ) {
					
					// Order status
					if ( $order['current_state'] == 0 ) {
						// Look into the history table if the current_state is 0 (PrestaShop 1.4)
						$last_history = $this->get_order_history($order['id_order']);
						if ( !empty($last_history) ) {
							$current_state = $last_history['id_order_state'];
						} else {
							$current_state = 0;
						}
					} else {
						$current_state = $order['current_state'];
					}
					$order_status = $this->map_order_status($current_state);
					
					// Insert the post
					$new_post = array(
						'post_date'			=> $order['date_add'],
						'post_title'		=> 'Order &ndash;' . $order['date_add'],
						'post_excerpt'		=> '',
						'post_status'		=> $order_status,
						'ping_status'		=> 'closed',
						'post_type'			=> 'shop_order',
						'post_password'		=> $order['secure_key'],
					);
					
					$new_post_id = wp_insert_post($new_post);
					
					if ( $new_post_id ) {
						$this->orders_count++;

						$user_id = isset($customers[$order['id_customer']])? $customers[$order['id_customer']] : 0;
						$customer = get_user_by('id', $user_id);
						
						// Billing address
						$billing_address = $this->get_address($order['id_address_invoice']);
						add_post_meta($new_post_id, '_billing_country', $billing_address['country'], true);
						add_post_meta($new_post_id, '_billing_first_name', $billing_address['firstname'], true);
						add_post_meta($new_post_id, '_billing_last_name', $billing_address['lastname'], true);
						add_post_meta($new_post_id, '_billing_company', $billing_address['company'], true);
						add_post_meta($new_post_id, '_billing_address_1', $billing_address['address1'], true);
						add_post_meta($new_post_id, '_billing_address_2', $billing_address['address2'], true);
						add_post_meta($new_post_id, '_billing_postcode', $billing_address['postcode'], true);
						add_post_meta($new_post_id, '_billing_city', $billing_address['city'], true);
						add_post_meta($new_post_id, '_billing_state', $billing_address['state'], true);
						add_post_meta($new_post_id, '_billing_email', $customer->user_email, true);
						add_post_meta($new_post_id, '_billing_phone', $billing_address['phone'], true);
						
						// Shipping address
						$shipping_address = $this->get_address($order['id_address_delivery']);
						add_post_meta($new_post_id, '_shipping_country', $shipping_address['country'], true);
						add_post_meta($new_post_id, '_shipping_first_name', $shipping_address['firstname'], true);
						add_post_meta($new_post_id, '_shipping_last_name', $shipping_address['lastname'], true);
						add_post_meta($new_post_id, '_shipping_company', $shipping_address['company'], true);
						add_post_meta($new_post_id, '_shipping_address_1', $shipping_address['address1'], true);
						add_post_meta($new_post_id, '_shipping_address_2', $shipping_address['address2'], true);
						add_post_meta($new_post_id, '_shipping_postcode', $shipping_address['postcode'], true);
						add_post_meta($new_post_id, '_shipping_city', $shipping_address['city'], true);
						add_post_meta($new_post_id, '_shipping_state', $shipping_address['state'], true);
						
						add_post_meta($new_post_id, '_payment_method', $order['payment'], true);
						add_post_meta($new_post_id, '_payment_method_title', $order['payment'], true);
						add_post_meta($new_post_id, '_order_shipping', $order['total_shipping'], true);
						add_post_meta($new_post_id, '_order_discount', $order['total_discounts'], true);
						add_post_meta($new_post_id, '_cart_discount', 0, true);
						add_post_meta($new_post_id, '_order_tax', $order['total_tax'], true);
						add_post_meta($new_post_id, '_order_shipping_tax', $order['total_shipping_tax'], true);
						add_post_meta($new_post_id, '_order_total', $order['total_paid'], true);
						add_post_meta($new_post_id, '_order_key', $order['reference'], true);
						add_post_meta($new_post_id, '_customer_user', $user_id, true);
						add_post_meta($new_post_id, '_order_currency', $order['currency'], true);
						add_post_meta($new_post_id, '_prices_include_tax', 'no', true);
						add_post_meta($new_post_id, '_customer_ip_address', '', true);
						add_post_meta($new_post_id, '_customer_user_agent', '', true);
						add_post_meta($new_post_id, '_recorded_sales', 'yes', true);
						add_post_meta($new_post_id, '_recorded_coupon_usage_counts', 'yes', true);
						
						// Order items
						$order_items = $this->get_order_items($order['id_order']);
						foreach ( $order_items as $order_item ) {
							
							if ( $wpdb->insert($wpdb->prefix . 'woocommerce_order_items', array(
								'order_item_name'	=> $order_item['product_name'],
								'order_item_type'	=> 'line_item',
								'order_id'			=> $new_post_id,
							)) ) {
								$wc_order_item_id = $wpdb->insert_id;
								$product_id = isset($products_ids[$order_item['product_id']])? $products_ids[$order_item['product_id']]: 0;
								$this->add_wc_order_itemmeta($wc_order_item_id, '_qty', $order_item['product_quantity']);
								$this->add_wc_order_itemmeta($wc_order_item_id, '_tax_class', $order_item['tax_name']);
								$this->add_wc_order_itemmeta($wc_order_item_id, '_product_id', $product_id);
								$this->add_wc_order_itemmeta($wc_order_item_id, '_variation_id', 0);
								$this->add_wc_order_itemmeta($wc_order_item_id, '_line_subtotal', $order_item['product_price']);
								$this->add_wc_order_itemmeta($wc_order_item_id, '_line_total', $order_item['product_price']);
								$this->add_wc_order_itemmeta($wc_order_item_id, '_line_tax', $order_item['product_tax']);
								$this->add_wc_order_itemmeta($wc_order_item_id, '_line_subtotal_tax', $order_item['product_tax']);

//								// Product attributes
//								$product_attributes = $this->get_order_item_attributes($order_item['product_attribute_id']);
//								foreach ( $product_attributes as $key => $value ) {
//									$this->add_wc_order_itemmeta($wc_order_item_id, $key, $value);
//								}
							}
						}
						
						// Shipping
						$shipment_method = isset($shipment_methods[$order['id_carrier']])? $shipment_methods[$order['id_carrier']] : '';
						if ( $wpdb->insert($wpdb->prefix . 'woocommerce_order_items', array(
							'order_item_name'	=> $shipment_method,
							'order_item_type'	=> 'shipping',
							'order_id'			=> $new_post_id,
						)) ) {
							$wc_order_item_id = $wpdb->insert_id;
							$this->add_wc_order_itemmeta($wc_order_item_id, 'method_id', 0);
							$this->add_wc_order_itemmeta($wc_order_item_id, 'cost', $order['total_shipping']);
						}
						
						// Taxes
						if ( $wpdb->insert($wpdb->prefix . 'woocommerce_order_items', array(
							'order_item_name'	=> 'Tax',
							'order_item_type'	=> 'tax',
							'order_id'			=> $new_post_id,
						)) ) {
							$wc_order_item_id = $wpdb->insert_id;
							$this->add_wc_order_itemmeta($wc_order_item_id, 'rate_id', 0);
							$this->add_wc_order_itemmeta($wc_order_item_id, 'label', 'Tax');
							$this->add_wc_order_itemmeta($wc_order_item_id, 'compound', 0);
							$this->add_wc_order_itemmeta($wc_order_item_id, 'tax_amount', $order['total_tax']);
							$this->add_wc_order_itemmeta($wc_order_item_id, 'shipping_tax_amount', $order['total_shipping_tax']);
						}
					}
					
					// Increment the PrestaShop last imported order ID
					update_option('fgp2wc_last_prestashop_order_id', $order['id_order']);
				}
			} while ( ($orders != null) && (count($orders) > 0) );
		}
		
		/**
		 * Mapping between PrestaShop and WooCommerce status
		 *
		 * @param string $state PrestaShop order status
		 * @return string WooCommerce order status
		 */
		private function map_order_status($state) {
			switch ( $state ) {
				case '1': // waiting cheque
				case '10': // waiting bank wire
				case '11': // waiting Paypal
					$status = 'wc-pending'; break;
				case '2': // payment accepted
				case '3': // ongoing preparation
				case '12': // remote payment accepted
					$status = 'wc-processing'; break;
				case '9': // out of stock
					$status = 'wc-on-hold'; break;
				case '6': // cancelled
					$status = 'wc-cancelled'; break;
				case '8': // payment error
					$status = 'wc-failed'; break;
				case '7': // refund
					$status = 'wc-refunded'; break;
				case '4': // ongoing shipping
				case '5': // shipped
					$status = 'wc-completed'; break;
				default:
					default: $status = 'wc-pending'; break;
			}
			return $status;
		}
		
		/**
		 * Add an order item meta
		 *
		 * @param int $order_item_id Order item ID
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return int order item meta ID
		 */
		private function add_wc_order_itemmeta($order_item_id, $meta_key, $meta_value) {
			global $wpdb;
			if ( $wpdb->insert($wpdb->prefix . 'woocommerce_order_itemmeta', array(
				'order_item_id'	=> $order_item_id,
				'meta_key'		=> $meta_key,
				'meta_value'	=> $meta_value,
			)) ) {
				return $wpdb->insert_id;
			} else {
				return 0;
			}
		}
		
		/**
		 * Import the PrestaShop features
		 *
		 */
		private function import_features() {
			$this->feature_values = array();
			
			$features = $this->get_features();
			foreach ( $features as $feature ) {
				
				// Create the feature
				$taxonomy = $this->create_woocommerce_attribute($feature['name'], 'select');
				
				// Create the feature values
				$feature_values = $this->get_feature_values($feature['id_feature']);
				$terms = array();
				foreach ( $feature_values as $feature_value ) {
					$attribute_values_terms = $this->create_woocommerce_attribute_value($taxonomy, $feature_value['value'], 0);
					foreach ( $attribute_values_terms as $term ) {
						$terms[] = $term['term_id'];
						$this->feature_values[$feature_value['id_feature_value']] = $term;
					}
				}

				// Update cache
				if ( !empty($terms) ) {
					clean_term_cache($terms, $taxonomy);
				}
			}
			
			// Empty attribute taxonomies cache
			delete_transient('wc_attribute_taxonomies');
		}
		
		/**
		 * Import the PrestaShop attributes
		 *
		 */
		private function import_attributes() {
			$this->attribute_values = array();
			
			$attribute_groups = $this->get_attribute_groups();
			foreach ( $attribute_groups as $attribute_group ) {
				
				// Create the attribute
				$taxonomy = $this->create_woocommerce_attribute($attribute_group['name'], 'select');
				
				// Create the attributes values
				$attributes = $this->get_attributes($attribute_group['id_attribute_group']);
				$terms = array();
				foreach ( $attributes as $attribute ) {
					$attribute_values_terms = $this->create_woocommerce_attribute_value($taxonomy, $attribute['name'], $attribute['position']);
					foreach ( $attribute_values_terms as $term ) {
						$terms[] = $term['term_id'];
						$this->attribute_values[$attribute['id_attribute']] = $term;
					}
				}

				// Update cache
				if ( !empty($terms) ) {
					clean_term_cache($terms, $taxonomy);
				}
			}
			
			// Empty attribute taxonomies cache
			delete_transient('wc_attribute_taxonomies');
		}
		
		/**
		 * Create a product attribute
		 *
		 * @param string $attribute_label Attribute label
		 * @param string $attribute_type select | text
		 * @return string Taxonomy
		 */
		private function create_woocommerce_attribute($attribute_label, $attribute_type) {
			global $wpdb;
			global $wc_product_attributes;
			
			$attribute_name = sanitize_title($attribute_label);
			$taxonomy = 'pa_' . $attribute_name;
			
			if ( !array_key_exists($taxonomy, $wc_product_attributes) ) {
				// Create the taxonomy
				$attribute_taxonomy = array(
					'attribute_name'	=> $attribute_name,
					'attribute_label'	=> $attribute_label,
					'attribute_type'	=> $attribute_type,
					'attribute_orderby'	=> 'menu_order',
				);
				$wpdb->insert($wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute_taxonomy);

				// Register the taxonomy
				register_taxonomy($taxonomy,
					apply_filters('woocommerce_taxonomy_objects_' . $taxonomy, array('product')),
					apply_filters('woocommerce_taxonomy_args_' . $taxonomy, array(
						'hierarchical' => true,
						'show_ui' => false,
						'query_var' => true,
						'rewrite' => false,
					))
				);
				$wc_product_attributes[$taxonomy] = $attribute_taxonomy; // useful for wc_set_term_order()
			}
			return $taxonomy;
		}
		
		/**
		 * Create an attribute value
		 *
		 * @param string $taxonomy Taxonomy
		 * @param string $attribute_value Attribute value
		 * @param int $attribute_value_ordering Attribute value ordering
		 * @return array Terms created
		 */
		private function create_woocommerce_attribute_value($taxonomy, $attribute_value, $attribute_value_ordering = 0) {
			$terms = array();
			
			// Create one term by custom value
			$attribute_value = substr($attribute_value, 0, 200); // term name is limited to 200 characters
			$term = get_term_by('name', $attribute_value, $taxonomy, ARRAY_A);
			if ( $term !== false ) {
				$terms[] = $term;
			} elseif ( !empty($attribute_value) ) {
				$newterm = wp_insert_term($attribute_value, $taxonomy);
				if ( !is_wp_error($newterm) ) {
					$term = get_term_by('id', $newterm['term_id'], $taxonomy, ARRAY_A);
					$terms[] = $term;
					// Category ordering
					if ( function_exists('wc_set_term_order') ) {
						wc_set_term_order($term['term_id'], $attribute_value_ordering, $taxonomy);
					}
				}
			}
			return $terms;
		}
		
		/**
		 * Import the PrestaShop product features
		 *
		 */
		private function import_products_features() {
			$products_ids = $this->get_woocommerce_products();
			foreach ( $products_ids as $ps_product_id => $wc_product_id ) {
				$product_features = $this->get_product_features($ps_product_id);
				foreach ( $product_features as $product_feature ) {
					$attribute_name = sanitize_title($product_feature['name']);
					$taxonomy = 'pa_' . $attribute_name;
					$is_variation = 0;
					$this->create_woocommerce_product_attribute($wc_product_id, $taxonomy, $is_variation, $product_feature['position']);
					if ( isset($this->feature_values[$product_feature['id_feature_value']]) ) {
						$this->set_object_terms($wc_product_id, $this->feature_values[$product_feature['id_feature_value']]['term_taxonomy_id'], 0);
					}
				}
			}
		}
		
		/**
		 * Import the PrestaShop products attributes
		 *
		 */
		private function import_products_attributes() {
			$products_ids = $this->get_woocommerce_products();
			foreach ( $products_ids as $ps_product_id => $wc_product_id ) {
				
				// Assign the attribute group to the product
				$product_attribute_groups = $this->get_attribute_groups_from_product($ps_product_id);
				foreach ( $product_attribute_groups as $product_attribute_group ) {
					$attribute_name = sanitize_title($product_attribute_group['name']);
					$taxonomy = 'pa_' . $attribute_name;
					$is_variation = 1;
					$this->create_woocommerce_product_attribute($wc_product_id, $taxonomy, $is_variation, $product_attribute_group['position']);
				}
				
				// Set the relationship between the product and the attribute values
				$product_attributes = $this->get_attributes_from_product($ps_product_id);
				$i = 0;
				foreach ( $product_attributes as $attribute_value ) {
					if ( isset($this->attribute_values[$attribute_value]) ) {
						$this->set_object_terms($wc_product_id, $this->attribute_values[$attribute_value]['term_taxonomy_id'], $i++);
					}
				}
			}
		}
		
		/**
		 * Create a product attribute value
		 *
		 * @param string $product_id Product ID
		 * @param string $taxonomy Taxonomy
		 * @param int $is_variation Is it a variation?
		 * @param int $attribute_ordering Attribute ordering
		 */
		private function create_woocommerce_product_attribute($product_id, $taxonomy, $is_variation, $attribute_ordering = 0) {
			// Assign the attribute to the product
			$product_attributes = get_post_meta($product_id, '_product_attributes', true);
			if ( empty($product_attributes) ) {
				$product_attributes = array();
			}
			if ( !array_key_exists($taxonomy, $product_attributes) ) {
				$product_attributes = array_merge($product_attributes, array(
					$taxonomy => array(
						'name'			=> $taxonomy,
						'value'			=> '',
						'position'		=> $attribute_ordering,
						'is_visible'	=> 1,
						'is_variation'	=> $is_variation,
						'is_taxonomy'	=> 1,
					)
				));
				update_post_meta($product_id, '_product_attributes', $product_attributes);
			}
		}
		
		/**
		 * Same function as wp_set_object_terms but with the term_order parameter
		 *
		 * @param int $object_id Object ID
		 * @param int $term_taxonomy_id Term taxonomy ID
		 * @param int $term_order Term order
		 */
		private function set_object_terms($object_id, $term_taxonomy_id, $term_order) {
			global $wpdb;
			
			$wpdb->hide_errors(); // to prevent the display of an error if the term relashionship already exists
			$wpdb->insert($wpdb->prefix . 'term_relationships', array(
				'object_id'			=> $object_id,
				'term_taxonomy_id'	=> $term_taxonomy_id,
				'term_order'		=> $term_order,
			));
			$wpdb->show_errors();
		}
		
		/**
		 * Import the PrestaShop products variations
		 *
		 */
		private function import_products_variations() {
			$products_ids = $this->get_woocommerce_products();
			foreach ( $products_ids as $ps_product_id => $wc_product_id ) {
				$product_attributes = $this->get_product_attributes($ps_product_id);
				
				// Create the variations (posts)
				$i = 0;
				$default_attributes = array();
				foreach ( $product_attributes as $product_attribute ) {
					$i++;
					$product_reference = !empty($product_attribute['reference'])? $product_attribute['reference'] : $product_attribute['name'];
					$new_post = array(
						'post_title'	=> "Variation # of $product_reference",
						'post_name'		=> "product-$wc_product_id-variation" . (($i == 1)? '': "-$i"),
						'post_parent'	=> $wc_product_id,
						'menu_order'	=> 0,
						'post_type'		=> 'product_variation',
						'post_status'	=> 'publish',
					);
					$new_post_id = wp_insert_post($new_post);

					if ( $new_post_id ) {
						$post_title = "Variation #$new_post_id of $product_reference";
						wp_update_post(array(
							'ID'			=> $new_post_id,
							'post_title'	=> $post_title,
						));
						$attributes = $this->get_attributes_from_product_attribute($product_attribute['id_product_attribute']);
						foreach ( $attributes as $attribute_group => $attribute_id ) {
							$attribute_name = sanitize_title($attribute_group);
							$taxonomy = 'pa_' . $attribute_name;
							$attribute_value = $this->attribute_values[$attribute_id]['slug'];
							add_post_meta($new_post_id, 'attribute_' . $taxonomy, strtolower($attribute_value), true);
							
							// Set the default attributes
							if ( $product_attribute['default_on'] ) {
								$default_attributes[$taxonomy] = $attribute_value;
							}
						}
						// Price
						$price = round($product_attribute['product_price'] + $product_attribute['price'], 2);
						
						// SKU = Stock Keeping Unit
						$sku = $product_attribute['reference'];
						if ( empty($sku) ) {
							$sku = $product_attribute['supplier_reference'];
							if ( empty($sku) ) {
								$sku = $this->get_product_attribute_supplier_reference($product_attribute['id_product_attribute']);
							}
						}
						
						add_post_meta($new_post_id, '_regular_price', $price, true);
						add_post_meta($new_post_id, '_price', $price, true);
						add_post_meta($new_post_id, '_sku', $sku, true);
						if ( $product_attribute['quantity'] != 0 ) {
							add_post_meta($new_post_id, '_manage_stock', 'yes', true);
							add_post_meta($new_post_id, '_stock', $product_attribute['quantity'], true);
						}
					}
					
					// Set the product type as "variable"
					wp_set_object_terms($wc_product_id, $this->product_types['variable'], 'product_type', false);
					
				}
				
				// Store the default attributes
				if ( !empty($default_attributes) ) {
					add_post_meta($wc_product_id, '_default_attributes', $default_attributes);
				}
			}
		}
		
		/**
		 * Import the PrestaShop product reviews
		 *
		 */
		public function import_reviews() {
			if ( !$this->table_exists('product_comment') ) {
				return;
			}
			$customers = $this->get_imported_customers();
			$products_ids = $this->get_woocommerce_products();
			$reviews = $this->get_reviews();
			foreach ( $reviews as $review ) {
				$product_id = array_key_exists($review['id_product'], $products_ids)? $products_ids[$review['id_product']]: 0;
				if ( $product_id != 0 ) {
					$user_id = array_key_exists($review['id_customer'], $customers)? $customers[$review['id_customer']]: 0;
					$content = '<h3>' . $review['title'] . '</h3>' . $review['content'];
					$comment = array(
						'comment_post_ID'		=> $product_id,
						'comment_author'		=> $review['customer_name'],
						'comment_author_email'	=> '',
						'comment_content'		=> $content,
						'user_id'				=> $user_id,
						'comment_author_IP'		=> '',
						'comment_date'			=> $review['date_add'],
						'comment_approved'		=> $review['validate'],
					);
					$comment_id = wp_insert_comment($comment);
					if ( !empty($comment_id) ) {
						$this->reviews_count++;
						add_comment_meta($comment_id, 'rating', $review['grade'], true);
					}
				}
				// Increment the PrestaShop last imported review ID
				update_option('fgp2wc_last_prestashop_review_id', $review['id_product_comment']);
			}
		}
		
		/**
		 * Import the PrestaShop vouchers
		 *
		 */
		public function import_vouchers() {
			$products_ids = $this->get_woocommerce_products();
			$vouchers = $this->get_vouchers();
			foreach ( $vouchers as $voucher ) {
				if ( $voucher['date_from'] > date('Y-m-d H:i:s') ) {
					$post_status = 'future';
				} else {
					$post_status = 'publish';
				}
				$data = array(
					'post_type'			=> 'shop_coupon',
					'post_date'			=> $voucher['date_from'],
					'post_title'		=> $voucher['code'],
					'post_excerpt'		=> $voucher['description'],
					'post_status'		=> ($voucher['active'] == 1)? $post_status: 'draft',
					'comment_status'	=> 'closed',
					'ping_status'		=> 'closed',
				);
				$voucher_id = wp_insert_post($data);
				if ( !empty($voucher_id) ) {
					$this->vouchers_count++;
					
					//  Percent or amount
					if ( version_compare($this->prestashop_version, '1.5', '<') ) {
						// PrestaShop 1.4
						$coupon_amount = $voucher['value'];
						$free_shipping = 'no';
						switch ( $voucher['id_discount_type'] ) {
							case 1: // percent
								$discount_type = 'percent';
								break;
							case 3: // free shipping
								$discount_type = 'fixed_cart';
								$coupon_amount = 0.0;
								$free_shipping = 'yes';
								break;
							case 2: // fixed amount
							default: 
								$discount_type = 'fixed_cart';
								break;
						}
					} else {
						// PrestaShop 1.5+
						if ($voucher['reduction_percent'] != 0.0) {
							$discount_type = 'percent';
							$coupon_amount = $voucher['reduction_percent'];
						} else {
							$discount_type = 'fixed_cart';
							$coupon_amount = $voucher['reduction_amount'];
						}
						$free_shipping = !empty($voucher['free_shipping'])? 'yes': 'no';
					}
					
					// Not cumulable with other discounts
					if ( isset($voucher['cumulable']) && ($voucher['cumulable'] == 0) ) {
						add_post_meta($voucher_id, 'individual_use', 'yes', true);
					}
					
					// Not cumulable with other reductions
					if ( isset($voucher['cumulable_reduction']) && ($voucher['cumulable_reduction'] == 0) ) {
						add_post_meta($voucher_id, 'exclude_sale_items', 'yes', true);
					}
					
					// Start date
					if ( $voucher['date_to'] == '0000-00-00 00:00:00' ) {
						$expiry_date = '';
					} else {
						$expiry_date = substr($voucher['date_to'], 0, 10); // Remove the hour
					}
					
					// Customer email
					if ( !empty($voucher['email']) ) {
						$customer_email = array($voucher['email']);
					} else {
						$customer_email = array();
					}
					add_post_meta($voucher_id, 'discount_type', $discount_type, true);
					add_post_meta($voucher_id, 'coupon_amount', $coupon_amount, true);
					add_post_meta($voucher_id, 'usage_limit', $voucher['quantity'], true);
					add_post_meta($voucher_id, 'usage_limit_per_user', $voucher['quantity_per_user'], true);
					add_post_meta($voucher_id, 'expiry_date', $expiry_date, true);
					add_post_meta($voucher_id, 'free_shipping', $free_shipping, true);
					add_post_meta($voucher_id, 'minimum_amount', $voucher['minimum_amount'], true);
					add_post_meta($voucher_id, 'customer_email', $customer_email, true);
					
					// Products restrictions
					if ( version_compare($this->prestashop_version, '1.5', '>=') ) { // PrestaShop 1.5+ only
						$cart_rule_products = $this->get_cart_rule_products($voucher['id_cart_rule']);
						$products = array();
						foreach ( $cart_rule_products as $cart_rule_product ) {
							if ( array_key_exists($cart_rule_product, $products_ids) ) {
								$products[] = $products_ids[$cart_rule_product];
							}
						}
						if ( !empty($products) ) {
							add_post_meta($voucher_id, 'product_ids', implode(',', $products), true);
						}
					}
				}
				// Increment the last imported cart rule ID
				update_option('fgp2wc_last_prestashop_cart_rule_id', $voucher['id_cart_rule']);
			}
		}
		
		/**
		 * Add a user if it does not exists
		 *
		 * @param string $firstname User's first name
		 * @param string $lastname User's last name
		 * @param string $email User's email
		 * @param string $password User's password in PrestaShop
		 * @param string $register_date Registration date
		 * @param string $role User's role - default: subscriber
		 * @return int User ID
		 */
		private function add_user($firstname, $lastname, $email='', $password='', $register_date='', $role='subscriber') {
			$email = sanitize_email($email);
//			$login = $this->generate_login($firstname, $lastname);
			$login = $email; // Use the email as login
			$display_name = $firstname . ' ' . $lastname;
			
			$user = get_user_by('email', $email);
			if ( !$user ) {
				// Create a new user
				$userdata = array(
					'user_login'		=> $login,
					'user_pass'			=> wp_generate_password( 12, false ),
					'user_nicename'		=> $login,
					'user_email'		=> $email,
					'display_name'		=> $display_name,
					'first_name'		=> $firstname,
					'last_name'			=> $lastname,
					'user_registered'	=> $register_date,
					'role'				=> $role,
				);
				$user_id = wp_insert_user( $userdata );
				if ( is_wp_error($user_id) ) {
//					$this->display_admin_error(sprintf(__('Creating user %s: %s', 'fgp2wcp'), $login, $user_id->get_error_message()));
				} else {
					$this->users_count++;
					if ( !empty($password) ) {
						// PrestaShop password to authenticate the users
						add_user_meta($user_id, 'prestashop_pass', $password, true);
					}
//					$this->display_admin_notice(sprintf(__('User %s created', 'fgp2wcp'), $login));
				}
			}
			else {
				$user_id = $user->ID;
				global $blog_id;
				if ( is_multisite() && $blog_id && !is_user_member_of_blog($user_id) ) {
					// Add user to the current blog (in multisite)
					add_user_to_blog($blog_id, $user_id, $role);
					$this->users_count++;
				}
			}
			return $user_id;
		}
		
		/**
		 * Generate a login identifier from the first and last names
		 * 
		 * @param string $firstname User's first name
		 * @param string $lastname User's last name
		 * @return string Login
		 */
		private function generate_login($firstname, $lastname) {
			$login = $firstname . $lastname;
			$login = str_replace(' ', '', $login); // Remove spaces
			$login = $this->convert_to_latin(remove_accents($login)); // Remove accents
			$login = strtolower($login); // Lower case
			$login = sanitize_user($login, true);
			return $login;
		}
		
		/**
		 * Convert string to latin
		 */
		private function convert_to_latin($string) {
			$string = self::greek_to_latin($string); // For Greek characters
			$string = self::cyrillic_to_latin($string); // For Cyrillic characters
			return $string;
		}
		
		/**
		 * Convert Greek characters to latin
		 */
		static private function greek_to_latin($string) {
			static $from = array('Α','Β','Γ','Δ','Ε','Ζ','Η','Θ','Ι','Κ','Λ','Μ','Ν','Ξ','Ο','Π','Ρ','Σ','Τ','Υ','Φ','Χ','Ψ','Ω','α','β','γ','δ','ε','ζ','η','θ','ι','κ','λ','μ','ν','ξ','ο','π','ρ','ς','σ','τ','υ','φ','χ','ψ','ω','ϑ','ϒ','ϖ');
			static $to = array('A','V','G','D','E','Z','I','TH','I','K','L','M','N','X','O','P','R','S','T','Y','F','CH','PS','O','a','v','g','d','e','z','i','th','i','k','l','m','n','x','o','p','r','s','s','t','y','f','ch','ps','o','th','y','p');
			return str_replace($from, $to, $string);
		}
		
		/**
		 * Convert Cyrillic (Russian) characters to latin
		 */
		static private function cyrillic_to_latin($string) {
			static $from = array('ж',  'ч',  'щ',   'ш',  'ю',  'а', 'б', 'в', 'г', 'д', 'е', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ъ', 'ь', 'я', 'Ж',  'Ч',  'Щ',   'Ш',  'Ю',  'А', 'Б', 'В', 'Г', 'Д', 'Е', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ъ', 'Ь', 'Я');
			static $to = array('zh', 'ch', 'sht', 'sh', 'yu', 'a', 'b', 'v', 'g', 'd', 'e', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'y', 'x', 'q', 'Zh', 'Ch', 'Sht', 'Sh', 'Yu', 'A', 'B', 'V', 'G', 'D', 'E', 'Z', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'c', 'Y', 'X', 'Q');
			return str_replace($from, $to, $string);
		}
		
		/**
		 * Get the PrestaShop employees
		 * 
		 * @return array of employees
		 */
		private function get_employees() {
			global $prestashop_db;
			$employees = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT id_employee, lastname, firstname, email, passwd
					FROM ${prefix}employee
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$employees[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $employees;
		}
		
		/**
		 * Get the PrestaShop customers
		 * 
		 * @return array of customers
		 */
		private function get_customers() {
			global $prestashop_db;
			$customers = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				if ( $this->column_exists('customer', 'website') ) {
					$website_field = 'c.website';
				} else {
					$website_field = "'' AS website";
				}
				$sql = "
					SELECT c.id_customer, c.firstname, c.lastname, c.email, c.passwd, $website_field, c.date_add
					FROM ${prefix}customer c
					WHERE c.active = 1
					AND c.deleted = 0
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$customers[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $customers;
		}
		
		/**
		 * Get a PrestaShop customer address
		 * 
		 * @param int $customer_id Customer ID
		 * @return array Address fields
		 */
		private function get_customer_address($customer_id) {
			global $prestashop_db;
			$address = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT a.company, a.lastname, a.firstname, a.address1, a.address2, a.postcode, a.city, a.phone,
					c.iso_code AS country, s.name AS state
					FROM ${prefix}address a
					LEFT JOIN ${prefix}country c ON c.id_country = a.id_country
					LEFT JOIN ${prefix}state s ON s.id_state = a.id_state
					WHERE a.id_customer = '$customer_id'
					AND a.active = 1
					AND a.deleted = 0
					LIMIT 1
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$address = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $address;
		}
		
		/**
		 * Get a PrestaShop address
		 * 
		 * @param int $address_id PrestaShop address ID
		 * @return array Address fields
		 */
		private function get_address($address_id) {
			global $prestashop_db;
			$address = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT a.company, a.lastname, a.firstname, a.address1, a.address2, a.postcode, a.city, a.phone,
					c.iso_code AS country, s.name AS state
					FROM ${prefix}address a
					LEFT JOIN ${prefix}country c ON c.id_country = a.id_country
					LEFT JOIN ${prefix}state s ON s.id_state = a.id_state
					WHERE a.id_address = '$address_id'
					LIMIT 1
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$address = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $address;
		}
		
		/**
		 * Get PrestaShop shipment methods
		 * 
		 * @return array Shipment methods
		 */
		private function get_shipments_methods() {
			global $prestashop_db;
			$shipment_methods = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT c.id_carrier, c.name
					FROM ${prefix}carrier c
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$shipment_methods[$row['id_carrier']] = $row['name'];
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $shipment_methods;
		}
		
		/**
		 * Get PrestaShop orders
		 * 
		 * @param int limit Number of articles max
		 * @return array Orders
		 */
		private function get_orders($limit=1000) {
			global $prestashop_db;
			$orders = array();

			$last_prestashop_order_id = (int)get_option('fgp2wc_last_prestashop_order_id'); // to restore the import where it left

			try {
				$prefix = $this->plugin_options['prefix'];
				
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$sql = "
						SELECT o.id_order, '' AS reference, o.id_carrier, o.id_customer, o.id_address_delivery, o.id_address_invoice, 0 AS current_state, o.payment, o.total_paid, o.total_products_wt - o.total_products AS total_tax, o.total_products, o.total_shipping, 0 AS total_shipping_tax, o.total_wrapping, o.total_discounts, o.secure_key, o.date_add,
						c.iso_code AS currency
						FROM ${prefix}orders o
						LEFT JOIN ${prefix}currency c ON c.id_currency = o.id_currency
						WHERE o.id_order > '$last_prestashop_order_id'
						ORDER BY o.id_order
						LIMIT $limit
					";
				} else {
					// PrestaShop 1.5+
					$sql = "
						SELECT o.id_order, o.reference, o.id_carrier, o.id_customer, o.id_address_delivery, o.id_address_invoice, o.current_state, o.payment, o.total_paid, o.total_paid_tax_incl - o.total_paid_tax_excl AS total_tax, o.total_products, o.total_shipping, o.total_shipping_tax_incl - o.total_shipping_tax_excl AS total_shipping_tax, o.total_wrapping, o.total_discounts, o.secure_key, o.date_add,
						c.iso_code AS currency
						FROM ${prefix}orders o
						LEFT JOIN ${prefix}currency c ON c.id_currency = o.id_currency
						WHERE o.id_order > '$last_prestashop_order_id'
						ORDER BY o.id_order
						LIMIT $limit
					";
				}
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$orders[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $orders;
		}
		
		/**
		 * Get PrestaShop order items
		 * 
		 * @param int $order_id Order ID
		 * @return array Order items
		 */
		private function get_order_items($order_id) {
			global $prestashop_db;
			$order_items = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$sql = "
						SELECT d.id_order_detail, d.id_order, d.product_id, d.product_attribute_id, d.product_name, d.product_quantity, d.tax_name, d.product_price * d.tax_rate / 100 AS product_tax, d.product_price
						FROM ${prefix}order_detail d
						WHERE d.id_order = '$order_id'
						ORDER BY id_order_detail
					";
				} else {
					// PrestaShop 1.5+
					$sql = "
						SELECT d.id_order_detail, d.id_order, d.product_id, d.product_attribute_id, d.product_name, d.product_quantity, d.tax_name, d.total_price_tax_incl - d.total_price_tax_excl AS product_tax, d.total_price_tax_excl AS product_price
						FROM ${prefix}order_detail d
						WHERE d.id_order = '$order_id'
						ORDER BY id_order_detail
					";
				}
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$order_items[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $order_items;
		}
		
		/**
		 * Get PrestaShop order history
		 * 
		 * @param int $order_id Order ID
		 * @return array Order history
		 */
		private function get_order_history($order_id) {
			global $prestashop_db;
			$order_history = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT h.id_order_state
					FROM ${prefix}order_history h
					WHERE h.id_order = '$order_id'
					ORDER BY h.date_add DESC
					LIMIT 1
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$order_history = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $order_history;
		}
		
		/**
		 * Get the PrestaShop attribute groups
		 *
		 * @return array of attribute groups
		 */
		private function get_attribute_groups() {
			global $prestashop_db;
			$attribute_groups = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$position = '0 AS position';
				} else {
					// PrestaShop 1.5+
					$position = 'g.position';
				}
				$sql = "
					SELECT g.id_attribute_group, $position, gl.name, gl.public_name
					FROM ${prefix}attribute_group g
					INNER JOIN ${prefix}attribute_group_lang gl ON gl.id_attribute_group = g.id_attribute_group AND gl.id_lang = '$lang'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$attribute_groups[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $attribute_groups;
		}
		
		/**
		 * Get the PrestaShop attributes
		 *
		 * @param int $attribute_group_id Attribute group ID
		 * @return array of attributes
		 */
		private function get_attributes($attribute_group_id) {
			global $prestashop_db;
			$attributes = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$position = 'a.id_attribute AS position';
				} else {
					// PrestaShop 1.5+
					$position = 'a.position';
				}
				$sql = "
					SELECT a.id_attribute, $position, al.name
					FROM ${prefix}attribute a
					INNER JOIN ${prefix}attribute_lang al ON al.id_attribute = a.id_attribute AND al.id_lang = '$lang'
					WHERE a.id_attribute_group = '$attribute_group_id'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$attributes[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $attributes;
		}
		
		/**
		 * Get the PrestaShop product attributes
		 *
		 * @param int $product_id Product ID
		 * @return array of product attributes
		 */
		private function get_product_attributes($product_id) {
			global $prestashop_db;
			$product_attributes = array();
			
			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$sql = "
						SELECT pa.id_product_attribute, pa.reference, pa.supplier_reference, pa.price, pa.unit_price_impact, pa.quantity, pa.default_on, p.price AS product_price, pl.name
						FROM ${prefix}product_attribute pa
						INNER JOIN ${prefix}product AS p ON p.id_product = pa.id_product
						INNER JOIN ${prefix}product_lang AS pl ON pl.id_product = pa.id_product AND pl.id_lang = '$lang'
						WHERE pa.id_product = '$product_id'
					";
				} else {
					// PrestaShop 1.5+
					$sql = "
						SELECT pa.id_product_attribute, pa.reference, pa.supplier_reference, pa.price, pa.unit_price_impact, s.quantity, pa.default_on, p.price AS product_price, pl.name
						FROM ${prefix}product_attribute pa
						INNER JOIN ${prefix}product AS p ON p.id_product = pa.id_product
						INNER JOIN ${prefix}product_lang AS pl ON pl.id_product = pa.id_product AND pl.id_lang = '$lang'
						LEFT JOIN ${prefix}stock_available AS s ON s.id_product_attribute = pa.id_product_attribute
						WHERE pa.id_product = '$product_id'
					";
				}
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$product_attributes[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $product_attributes;
		}
		
		/**
		 * Get the PrestaShop attributes from a product
		 *
		 * @param int $product_id Product ID
		 * @return array of product attributes
		 */
		private function get_attributes_from_product($product_id) {
			global $prestashop_db;
			$product_attributes = array();
			
			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT DISTINCT pac.id_attribute
					FROM ${prefix}product_attribute pa
					INNER JOIN ${prefix}product_attribute_combination pac ON pac.id_product_attribute = pa.id_product_attribute
					WHERE pa.id_product = '$product_id'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$product_attributes[] = $row['id_attribute'];
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $product_attributes;
		}
		
		/**
		 * Get the PrestaShop product attributes from a product attribute ID
		 *
		 * @param int $product_attribute_id Product attribute ID
		 * @return array of product attributes
		 */
		private function get_attributes_from_product_attribute($product_attribute_id) {
			global $prestashop_db;
			$product_attributes = array();
			
			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				$sql = "
					SELECT pac.id_attribute, al.name, agl.name AS group_name
					FROM ${prefix}product_attribute_combination pac
					INNER JOIN ${prefix}attribute a ON a.id_attribute = pac.id_attribute
					INNER JOIN ${prefix}attribute_lang al ON al.id_attribute = a.id_attribute AND al.id_lang = '$lang'
					INNER JOIN ${prefix}attribute_group_lang agl ON agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = '$lang'
					WHERE pac.id_product_attribute = '$product_attribute_id'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$product_attributes[$row['group_name']] = $row['id_attribute'];
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $product_attributes;
		}
		
		/**
		 * Get the PrestaShop product attribute groups
		 *
		 * @param int $product_id Product ID
		 * @return array of product attribute groups
		 */
		private function get_attribute_groups_from_product($product_id) {
			global $prestashop_db;
			$product_attribute_groups = array();
			
			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$position = 'ag.id_attribute_group AS position';
				} else {
					// PrestaShop 1.5+
					$position = 'ag.position';
				}
				$sql = "
					SELECT DISTINCT agl.name, $position
					FROM ${prefix}product_attribute pa
					INNER JOIN ${prefix}product_attribute_combination pac ON pac.id_product_attribute = pa.id_product_attribute
					INNER JOIN ${prefix}attribute a ON a.id_attribute = pac.id_attribute
					INNER JOIN ${prefix}attribute_group ag ON ag.id_attribute_group = a.id_attribute_group
					INNER JOIN ${prefix}attribute_group_lang agl ON agl.id_attribute_group = ag.id_attribute_group AND agl.id_lang = '$lang'
					WHERE pa.id_product = '$product_id'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$product_attribute_groups[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $product_attribute_groups;
		}
		
		/**
		 * Get the product attribute supplier reference (PrestaShop 1.5+)
		 *
		 * @param int $product_attribute_id PrestaShop product attribute ID
		 * @return string Supplier reference
		 */
		private function get_product_attribute_supplier_reference($product_attribute_id) {
			global $prestashop_db;
			$supplier_reference = '';

			if ( version_compare($this->prestashop_version, '1.5', '>=') ) {
				// PrestaShop 1.5+
				try {
					$prefix = $this->plugin_options['prefix'];
					$sql = "
						SELECT ps.product_supplier_reference
						FROM ${prefix}product_supplier ps
						WHERE ps.id_product_attribute = '$product_attribute_id'
						LIMIT 1
					";
					$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
					if ( is_object($query) ) {
						foreach ( $query as $row ) {
							$supplier_reference = $row['product_supplier_reference'];
							break;
						}
					}
				} catch ( PDOException $e ) {
					$this->display_admin_error(__('Error:', 'fgp2wc') . $e->getMessage());
				}
			}
			return $supplier_reference;
		}
		
		/**
		 * Get the PrestaShop features
		 *
		 * @return array of features
		 */
		private function get_features() {
			global $prestashop_db;
			$features = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$position = '0 AS position';
				} else {
					// PrestaShop 1.5+
					$position = 'f.position';
				}
				$sql = "
					SELECT f.id_feature, $position, fl.name
					FROM ${prefix}feature f
					INNER JOIN ${prefix}feature_lang fl ON fl.id_feature = f.id_feature AND fl.id_lang = '$lang'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$features[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $features;
		}
		
		/**
		 * Get the PrestaShop feature values
		 *
		 * @param int $feature_id Attribute group ID
		 * @return array of feature values
		 */
		private function get_feature_values($feature_id) {
			global $prestashop_db;
			$feature_values = array();

			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				$sql = "
					SELECT fvl.id_feature_value, fvl.value
					FROM ${prefix}feature_value_lang fvl
					INNER JOIN ${prefix}feature_value fv ON fv.id_feature_value = fvl.id_feature_value
					WHERE fv.id_feature = '$feature_id'
					AND fvl.id_lang = '$lang'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$feature_values[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $feature_values;
		}
		
		/**
		 * Get the PrestaShop product features
		 *
		 * @param int $product_id Product ID
		 * @return array of product features
		 */
		private function get_product_features($product_id) {
			global $prestashop_db;
			$product_features = array();
			
			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$position = '0 AS position';
					$order = '';
				} else {
					// PrestaShop 1.5+
					$position = 'f.position';
					$order = 'ORDER BY f.position';
				}
				$sql = "
					SELECT fp.id_feature_value, fl.name, $position
					FROM ${prefix}feature_product fp
					INNER JOIN ${prefix}feature f ON f.id_feature = fp.id_feature
					INNER JOIN ${prefix}feature_lang fl ON fl.id_feature = fp.id_feature AND fl.id_lang = '$lang'
					WHERE fp.id_product = '$product_id'
					$order
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$product_features[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $product_features;
		}
		
		/**
		 * Get the PrestaShop product reviews
		 *
		 * @return array of product reviews
		 */
		private function get_reviews() {
			global $prestashop_db;
			$product_reviews = array();
			
			$last_prestashop_review_id = (int)get_option('fgp2wc_last_prestashop_review_id'); // to restore the import where it left

			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT id_product_comment, id_product, id_customer, title, content, customer_name, grade, validate, date_add
					FROM ${prefix}product_comment
					WHERE deleted = 0
					AND id_product_comment > '$last_prestashop_review_id'
					ORDER BY id_product_comment
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$product_reviews[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $product_reviews;
		}
		
		/**
		 * Get the PrestaShop vouchers
		 *
		 * @return array of vouchers
		 */
		private function get_vouchers() {
			global $prestashop_db;
			$vouchers = array();
			
			$last_prestashop_voucher_id = (int)get_option('fgp2wc_last_prestashop_cart_rule_id'); // to restore the import where it left

			try {
				$prefix = $this->plugin_options['prefix'];
				$lang = $this->default_language;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$sql = "
						SELECT d.id_discount AS id_cart_rule, d.id_discount_type, d.id_customer, d.name AS code, d.value, d.quantity, d.quantity_per_user, d.cumulable, d.cumulable_reduction, d.date_from, d.date_to, d.minimal AS minimum_amount, d.active,
						dl.description,
						cu.email
						FROM ${prefix}discount d
						LEFT JOIN ${prefix}discount_lang dl ON dl.id_discount = d.id_discount AND dl.id_lang = '$lang'
						LEFT JOIN ${prefix}customer cu ON cu.id_customer = d.id_customer
						WHERE d.id_discount > '$last_prestashop_voucher_id'
						ORDER BY d.id_discount
					";
				} else {
					// PrestaShop 1.5+
					$sql = "
						SELECT c.id_cart_rule, c.id_customer, c.date_from, c.date_to, c.description, c.quantity, c.quantity_per_user, c.code, c.minimum_amount, c.product_restriction, c.free_shipping, c.reduction_percent, c.reduction_amount, c.reduction_product, c.active,
						cu.email
						FROM ${prefix}cart_rule c
						LEFT JOIN ${prefix}customer cu ON cu.id_customer = c.id_customer
						WHERE c.id_cart_rule > '$last_prestashop_voucher_id'
						ORDER BY c.id_cart_rule
					";
				}
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$vouchers[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $vouchers;
		}
		
		/**
		 * Get the PrestaShop products included in a voucher
		 *
		 * @param int $id_cart_rule Cart rule ID
		 * @return array of product IDs
		 */
		private function get_cart_rule_products($id_cart_rule) {
			global $prestashop_db;
			$products = array();
			
			try {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT cpv.id_item AS id_product
					FROM ${prefix}cart_rule_product_rule_value cpv
					INNER JOIN ${prefix}cart_rule_product_rule cp ON cp.id_product_rule = cpv.id_product_rule AND cp.type = 'products'
					INNER JOIN ${prefix}cart_rule_product_rule_group cg ON cg.id_product_rule_group = cp.id_product_rule_group
					WHERE cg.id_cart_rule = '$id_cart_rule'
				";
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$products[] = $row['id_product'];
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fgp2wcp') . $e->getMessage());
			}
			return $products;
		}
		
		/**
		 * Get the WooCommerce products
		 *
		 * @return array of products mapped with the PrestaShop products ids
		 */
		private function get_woocommerce_products() {
			global $wpdb;
			$products = array();
			
			try {
				$sql = "
					SELECT post_id, meta_value
					FROM $wpdb->postmeta
					WHERE meta_key = '_fgp2wc_old_ps_product_id'
				";
				$rows = $wpdb->get_results($sql);
				foreach ( $rows as $row ) {
					$products[$row->meta_value] = $row->post_id;
				}
			} catch ( PDOException $e ) {
				$this->plugin->display_admin_error(__('Error:', get_class($this->plugin)) . $e->getMessage());
			}
			return $products;
		}
		
		/**
		 * Sets the meta fields used by the SEO by Yoast plugin
		 * 
		 * @param int $new_post_id WordPress ID
		 * @param array $post PrestaShop Post
		 */
		public function set_meta_seo($new_post_id, $post) {
			if ( $this->premium_options['import_meta_seo'] ) {
				if ( array_key_exists('meta_title', $post) && !empty($post['meta_title']) ) {
					update_post_meta($new_post_id, '_yoast_wpseo_title', $post['meta_title']);
				}
				if ( array_key_exists('meta_description', $post) && !empty($post['meta_description']) ) {
					update_post_meta($new_post_id, '_yoast_wpseo_metadesc', $post['meta_description']);
				}
				if ( array_key_exists('meta_keywords', $post) && !empty($post['meta_keywords']) ) {
					update_post_meta($new_post_id, '_yoast_wpseo_metakeywords', $post['meta_keywords']);
				}
				if ( array_key_exists('indexation', $post) && ($post['indexation'] == 0) ) {
					update_post_meta($new_post_id, '_yoast_wpseo_meta-robots-noindex', 1);
				}
			}
		}
		
		/**
		 * Get the imported customers mapped with their PrestaShop IDs
		 * 
		 * @return array [PrestaShop customer ID => WP user ID]
		 */
		public function get_imported_customers() {
			global $wpdb;
			$tab_customers = array();
			$sql = "
				SELECT user_id, meta_value
				FROM $wpdb->usermeta
				WHERE meta_key = 'prestashop_customer_id'
			";
			foreach ( $wpdb->get_results($sql) as $usermeta ) {
				$tab_customers[$usermeta->meta_value] = $usermeta->user_id;
			}
			return $tab_customers;
		}
		
		/**
		 * Remove the WordPress transients
		 */
		protected function remove_transients() {
			global $wpdb;
			$sql = "
				DELETE FROM $wpdb->options
				WHERE option_name LIKE '_transient%'
			";
			$wpdb->query($sql);
		}
		
		/**
		 * Display the number of imported users
		 * 
		 */
		public function display_users_count() {
			$this->display_admin_notice(sprintf(_n('%d user imported', '%d users imported', $this->users_count, 'fgp2wcp'), $this->users_count));
		}
		
		/**
		 * Display the number of imported orders
		 * 
		 */
		public function display_orders_count() {
			$this->display_admin_notice(sprintf(_n('%d order imported', '%d orders imported', $this->orders_count, __CLASS__), $this->orders_count));
		}

		/**
		 * Display the number of imported reviews
		 * 
		 */
		public function display_reviews_count() {
			$this->display_admin_notice(sprintf(_n('%d review imported', '%d reviews imported', $this->reviews_count, __CLASS__), $this->reviews_count));
		}

		/**
		 * Display the number of imported vouchers
		 * 
		 */
		public function display_vouchers_count() {
			$this->display_admin_notice(sprintf(_n('%d voucher imported', '%d vouchers imported', $this->vouchers_count, __CLASS__), $this->vouchers_count));
		}

	}
}
?>
