<?php
/*
Plugin Name: WooCommerce Cart-Based Shipping
Plugin URI: http://www.bolderelements.net/woocommerce-demo/woocommerce-cart-based-shipping/
Description: WooCommerce custom plugin used to calculate shipping price based on items in the shopping cart
Author: Erica Dion
Author URI: http://www.bolderelements.net/
Version: 2.5.1

	Copyright: © 2012 Bolder Elements (email : erica@bolderelements.net)
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_cart_based_shipping_init', 0);

function woocommerce_cart_based_shipping_init() {

/**
 * Check if WooCommerce is active
 */
if ( class_exists( 'woocommerce' ) || class_exists( 'WooCommerce' ) ) {
	
	if (!class_exists('WC_Shipping_Method')) return;

	if ( !class_exists( 'WC_Cart_Based_Shipping' ) ) {
		
		if( WOOCOMMERCE_VERSION >= 2.1 && !isset( $woocommerce ) ) 
			$woocommerce = WC();

		class WC_Cart_Based_Shipping extends WC_Shipping_Method {

			/**
			 * __construct function.
			 *
			 * @access public
			 * @return void
			 */
			function __construct() {
	        	$this->id = 'cart_based_rate';
		 		$this->method_title = __( 'Cart Based', 'woo-cart-based' );
				$this->admin_page_heading = __('Cart Based Rates', 'woo-cart-based' );
				$this->admin_page_description = __( 'Cart based rates let you define a standard rate to be assigned based on the total price of the customer\'s cart.', 'woo-cart-based' );
				$this->cart_rate_sub_option = 'woocommerce_cart_rates_subtotal';
				$this->cart_rate_count_option = 'woocommerce_cart_rates_itemcount';
				$this->cart_rate_weight_option = 'woocommerce_cart_rates_weighttotal';
				$this->class_exclusions_options = 'woocommerce_cart_rates_exclusions';
				// $this->cart_rate_option = 'woocommerce_cart_rates';

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_cart_rates' ) );

				$this->init();
			}


			/**
			* init function.
			* initialize variables to be used
			*
			* @access public
			* @return void
			*/
			function init() {
				// Load the form fields.
				$this->init_form_fields();

				// Load the settings.
				$this->init_settings();

				// Define user set variables
				$this->enabled = $this->settings['enabled'];
				$this->title = $this->settings['title'];
				$this->availability = $this->settings['availability'];
				$this->countries = $this->settings['countries'];
				$this->fee = $this->settings['fee'];
				$this->method = $this->settings['method'];
				$this->includetax = $this->settings['includetax'];
				$this->excludediscount = $this->settings['excludediscount'];
				$this->minprice = $this->settings['minprice'];
				$this->maxprice = $this->settings['maxprice'];
				$this->minship = $this->settings['minship'];
				$this->maxship = $this->settings['maxship'];

				// Load Cart rates
				$this->get_cart_rates();

				// Load Excluded Classes
				$this->get_class_exclusions();
			}


			/**
			 * Initialise Gateway Settings Form Fields
			 *
			 * @access public
			 * @return void
			 */
			function init_form_fields() {
				global $woocommerce;

				$this->form_fields = array(
					'enabled' => array(
						'title' => __( 'Enable/Disable', 'woo-cart-based' ),
						'type' 	=> 'checkbox',
						'label' => __( 'Enable this shipping method', 'woo-cart-based' ),
						'default' => 'no',
						),
				'title' => array(
						'title' => __( 'Method Title', 'woo-cart-based' ),
						'type' => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'woo-cart-based' ),
						'default' => __( 'Shipping', 'woo-cart-based' ),
						),
				'availability' => array(
						'title' => __( 'Method availability', 'woo-cart-based' ),
						'type' 	=> 'select',
						'default' => 'all',
						'class'	=> 'availability',
						'options' => array(
							'all' => __( 'All allowed countries', 'woo-cart-based' ),
							'specific' => __( 'Specific countries', 'woo-cart-based' ),
							'excluding' => __( 'Countries excluding', 'woo-cart-based' ),
							),
						),
				'countries' => array(
						'title' => __( 'Specific Countries', 'woo-cart-based' ),
						'type' => 'multiselect',
						'class' => 'chosen_select',
						'css' => 'width: 450px;',
						'default' => '',
						'options' => $woocommerce->countries->countries,
						),
				'fee' => array(
						'title' 		=> __( 'Handling Fee', 'woo-cart-based' ),
						'type' 			=> 'text',
						'description'	=> __( 'Fee excluding tax. Enter an amount, e.g. 2.50, or a percentage, e.g. 5%. Leave blank to disable.', 'woo-cart-based' ),
						'default'		=> '',
						'desc_tip'      => true,
						'placeholder'	=> '0.00'
						),
				'method' => array(
						'title' => __( 'Calculation Method', 'woo-cart-based' ),
						'type' 	=> 'select',
						'default' => 'subtotal',
						'options' => array(
							'subtotal' => __( 'Cart Subtotal Price ', 'woo-cart-based' ),
							'itemcount' => __( 'Number of Items ', 'woo-cart-based' ),
							'weighttotal' => __( 'Total Weight ', 'woo-cart-based' ),
							)
						),
				'includetax' => array(
						'title' => __( 'Include Tax', 'woo-cart-based' ),
						'type' 	=> 'checkbox',
						'description' => __( 'Calculate shipping based on prices AFTER tax', 'woo-cart-based' ),
						'default' => 'no',
						),
				'excludediscount' => array(
						'title' => __( 'Exclude Discount', 'woo-cart-based' ),
						'type' 	=> 'checkbox',
						'description' => __( 'Calculate shipping based on prices BEFORE any discounts are applied', 'woo-cart-based' ),
						'default' => 'no',
						),
				'minprice' => array(
						'title' => __( 'Minimum Shipping Price', 'woo-cart-based' ),
						'type' => 'text',
						'description' => __( 'The minimum shipping price a customer pays', 'woo-cart-based' ),
						'default' => __( '', 'woo-cart-based' ),
						),
				'maxprice' => array(
						'title' => __( 'Maximum Shipping Price', 'woo-cart-based' ),
						'type' => 'text',
						'description' => __( 'The amount the shipping price will not exceed', 'woo-cart-based' ),
						'default' => __( '', 'woo-cart-based' ),
						),
				'minship' => array(
						'title' => __( 'Minimum to Ship', 'woo-cart-based' ),
						'type' => 'text',
						'description' => __( 'The minimum cart subtotal, item count, or weight that can be shipped (all orders below this will be denied)', 'woo-cart-based' ),
						'default' => __( '', 'woo-cart-based' ),
						),
				'maxship' => array(
						'title' => __( 'Maximum to Ship', 'woo-cart-based' ),
						'type' => 'text',
						'description' => __( 'The maximum cart subtotal, item count, or weight that can be shipped (all orders above this will be denied)', 'woo-cart-based' ),
						'default' => __( '', 'woo-cart-based' ),
						)
				);

			}


			/**
			 * calculate_shipping function.
			 *
			 * @access public
			 * @param array $package (default: array())
			 * @return void
			 */
			function calculate_shipping( $package = array() ) {
				global $woocommerce;

				$cart_subtotal = ($this->includetax == 'yes') ? 0 : $package['contents_cost'];
				if( $this->excludediscount == 'yes' ) $cart_subtotal += $woocommerce->cart->get_cart_discount_total();

				$this->rates = array();
				$excludedClasses = array();
				$SHIP_ITEM = TRUE;

				// get excluded shipping classes
				foreach ($this->class_exclusions as $key => $value) {
					if($value['excluded'] == 'on')
						$excludedClasses[$value['term_id']] = $value;
				}

		    	// Find prices for products in the cart, sum to get subtotal
		    	if ( sizeof( $package['contents'] ) > 0 ) {
					foreach ( $package['contents'] as $item_id => $values ) {
						if($values['data']->needs_shipping()) {
							$item_class_id = $values['data']->get_shipping_class_id();

							if(array_key_exists($item_class_id, $excludedClasses) ) {
								if($this->includetax == 'no') {
									$cart_subtotal -= ($values['data']->get_price() * $values['quantity']);
								}
							}elseif($this->includetax == 'yes') {
								$cart_subtotal += $values['data']->get_price_including_tax() * $values['quantity'];
							}
						}
		    		}
		    	}

				if($this->method == 'subtotal') {
					$shipping_options = $this->cart_rates_subtotal;

					// For each cart scenario, check if subtotal is less than minimum total
					foreach ( $shipping_options as $option ) {
						if( ( $this->minship != '' && $cart_subtotal < $this->minship ) || ( $this->maxship != '' && $cart_subtotal > $this->maxship ) ) {
							$SHIP_ITEM = false;
						} else {
							if( $cart_subtotal >= $option['min'] )  {
								if($option['shiptype'] == '%') {
									$shipping_total = $cart_subtotal * ($option['cost'] / 100);
								} else {
									$shipping_total = $option['cost'];
								}
							}
						}
					}
				} else if($this->method == 'itemcount') {
					$shipping_options = $this->cart_rates_itemcount;

					$cart_item_count = 0;
			    	// Find prices for products in the cart, sum to get subtotal
			    	if ( sizeof( $package['contents'] ) > 0 ) {
						foreach ( $package['contents'] as $item_id => $values ) {
							if($values['data']->needs_shipping()) {
								$item_class_id = $values['data']->get_shipping_class_id();
								if(!array_key_exists($item_class_id, $excludedClasses))
									$cart_item_count += $values['quantity'];
				    		}
				    	}
			    	}

					// For each cart scenario, check if item count is more than minimum items
					foreach ( $shipping_options as $option ) {
						if( ( $this->minship != '' && $cart_item_count < $this->minship ) || ( $this->maxship != '' && $cart_item_count > $this->maxship ) ) {
							$SHIP_ITEM = false;
						} else {
							if( $cart_item_count >= $option['min'] ) {
								if($option['shiptype'] == '%') {
									$shipping_total = $cart_subtotal * ($option['cost'] / 100);
								} else {
									$shipping_total = $option['cost'];
								}
							}
						}
					}
				} else if($this->method == 'weighttotal') {
					$shipping_options = $this->cart_rates_weighttotal;

			    	// Find weight for all products in the cart
					$cart_weight_total = 0;
			    	if ( sizeof( $package['contents'] ) > 0 ) {
						foreach ( $package['contents'] as $item_id => $values ) {
							if($values['data']->needs_shipping()) {
								$item_class_id = $values['data']->get_shipping_class_id();
								if(!array_key_exists($item_class_id, $excludedClasses))
									$cart_weight_total += $values['data']->weight * $values['quantity'];
			    			}
			    		}
			    	}

					// For each cart scenario, check if weight is more than specified minimum
					foreach ( $shipping_options as $option ) {
						if( ( $this->minship != '' && $cart_weight_total < $this->minship ) || ( $this->maxship != '' && $cart_weight_total > $this->maxship ) ) {
							$SHIP_ITEM = false;
						} else {
							if( $cart_weight_total >= $option['min'] ) {
								if($option['shiptype'] == '%') {
									$shipping_total = $cart_subtotal * ($option['cost'] / 100);
								} else {
									$shipping_total = $option['cost'];
								}
							}
						}
					}
				}
				if($SHIP_ITEM) {
					if($this->fee != 0) {
						if(strstr($this->fee, '%')) {
							$shipping_total += $cart_subtotal * ((float) $this->fee / 100);
						} else {
							$shipping_total += (float) $this->fee;
						}
					}

					// Apply Min / Max rules
					if( !empty( $this->minprice ) && $shipping_total < $this->minprice )
						$shipping_total = $this->minprice;
					
					if( !empty( $this->maxprice ) && $shipping_total > $this->maxprice )
						$shipping_total = $this->maxprice;

					$rate = array(
						'id' 	=> $this->id,
						'label' => $this->title,
						'cost' 	=> $shipping_total,
						);

					// Register the rate
					$this->add_rate( $rate );
				}
			}


			/**
			 * Admin Panel Options
			 * - Options for the cart based portion
			 *
			 * @since 1.0.0
			 * @access public
			 * @return void
			 */
			public function admin_options() {
				global $woocommerce;

				$cur_symbol = get_woocommerce_currency_symbol();
?>
		<style>.check-column input{margin-left:8px;} .check-column {margin: 0;padding: 0;}</style>
    	<h3><?php echo $this->admin_page_heading; ?></h3>
    	<p><?php echo $this->admin_page_description; ?></p>
    	<table class="form-table">
    	<?php
    		// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?>
	    	<tr valign="top" id="row_subtotal_based" style="display:none;">
	            <th scope="row" class="titledesc"><?php _e( 'Subtotal Based Rates', 'woo-cart-based' ); ?>:</th>
	            <td class="forminp" id="<?php echo $this->id; ?>_cart_rates_subtotal">
	            	<table class="shippingrows widefat" cellspacing="0">
	            		<thead>
	            			<tr>
	            				<th class="check-column"><input type="checkbox"></th>
	            				<th class="shipping_class"><?php _e( 'Orders Equal to and Above', 'woo-cart-based' ); ?></th>
	        	            	<th><?php _e( 'Shipping Cost', 'woo-cart-based' ); ?></th>
	        	            	<th><?php _e( '', 'woo-cart-based' ); ?></th>
	            			</tr>
	            		</thead>
	            		<tfoot>
	            			<tr>
	            				<th colspan="2"><a href="#" class="add button"><?php _e( 'Add Cart Rate', 'woo-cart-based' ); ?></a></th>
	            				<th colspan="2"><small><?php _e( 'Add subtotal based rates for shipping here', 'woo-cart-based' ); ?></small> <a href="#" class="remove button"><?php _e( 'Delete selected rates', 'woo-cart-based' ); ?></a></th>
	            			</tr>
	            		</tfoot>
	            		<tbody class="cart_rates">
	                	<?php
	                	$i = -1;
	                	if ( $this->cart_rates_subtotal ) {
	                		foreach ( $this->cart_rates_subtotal as $class => $rate ) {
		                		$i++;
								$selType = "<select name=\"". $this->id ."_sub_shiptype[" . $i . "]\" class=\"shiptype\">
									<option>".$cur_symbol."</option>
									<option";
									if($rate['shiptype'] == "%") $selType .= " selected=\"selected\"";
									$selType .= ">%</option></select>";

		                		echo '<tr class="cart_rate">
		                		    <td class="check-column"><input type="checkbox" name="select" /></td>
		                		    <td>' . $cur_symbol . ' <input type="text" value="' . $rate['min'] . '" name="'. $this->id .'_sub_min[' . $i . ']" placeholder="'.__( '0.00', 'woo-cart-based' ).'" size="4" /></td>
				                    <td>' . $selType . ' <input type="text" value="' . $rate['cost'] . '" name="'. $this->id .'_sub_cost[' . $i . ']" placeholder="'.__( '0.00', 'woo-cart-based' ).'" size="4" /></td>
				                    <td></td>
			                    </tr>';
	                		}
	                	}
	                	?>
	                	</tbody>
	                </table>
	            </td>
	        </tr>
	    	<tr valign="top" id="row_itemcount_based" style="display:none;">
	            <th scope="row" class="titledesc"><?php _e( 'Item Count Based Rates', 'woo-cart-based' ); ?>:</th>
	            <td class="forminp" id="<?php echo $this->id; ?>_cart_rates_itemcount">
	            	<table class="shippingrows widefat" cellspacing="0">
	            		<thead>
	            			<tr>
	            				<th class="check-column"><input type="checkbox"></th>
	            				<th class="shipping_class"><?php _e( 'Number of Items Equal to and Above', 'woo-cart-based' ); ?></th>
	        	            	<th><?php _e( 'Shipping Cost', 'woo-cart-based' ); ?></th>
	        	            	<th><?php _e( '', 'woo-cart-based' ); ?></th>
	            			</tr>
	            		</thead>
	            		<tfoot>
	            			<tr>
	            				<th colspan="2"><a href="#" class="add button"><?php _e( 'Add Cart Rate', 'woo-cart-based' ); ?></a></th>
	            				<th colspan="2"><small><?php _e( 'Add item count based rates for shipping here', 'woo-cart-based' ); ?></small> <a href="#" class="remove button"><?php _e( 'Delete selected rates', 'woo-cart-based' ); ?></a></th>
	            			</tr>
	            		</tfoot>
	            		<tbody class="cart_rates">
	                	<?php
	                	$i = -1;
	                	if ( $this->cart_rates_itemcount ) {
	                		foreach ( $this->cart_rates_itemcount as $class => $rate ) {
		                		$i++;
								$selType = "<select name=\"". $this->id ."_count_shiptype[" . $i . "]\" class=\"shiptype\">
									<option>".$cur_symbol."</option>
									<option";
									if($rate['shiptype'] == "%") $selType .= " selected=\"selected\"";
									$selType .= ">%</option></select>";

		                		echo '<tr class="cart_rate">
		                		    <td class="check-column"><input type="checkbox" name="select" /></td>
		                		    <td><input type="text" value="' . $rate['min'] . '" name="'. $this->id .'_count_min[' . $i . ']" placeholder="'.__( '0', 'woo-cart-based' ).'" size="4" /></td>
				                    <td>' . $selType . ' <input type="text" value="' . $rate['cost'] . '" name="'. $this->id .'_count_cost[' . $i . ']" placeholder="'.__( '0.00', 'woo-cart-based' ).'" size="4" /></td>
				                    <td></td>
			                    </tr>';
	                		}
	                	}
	                	?>
	                	</tbody>
	                </table>
	            </td>
	        </tr>
	    	<tr valign="top" id="row_weighttotal_based" style="display:none;">
	            <th scope="row" class="titledesc"><?php _e( 'Weight Based Rates', 'woo-cart-based' ); ?>:</th>
	            <td class="forminp" id="<?php echo $this->id; ?>_cart_rates_weighttotal">
	            	<table class="shippingrows widefat" cellspacing="0">
	            		<thead>
	            			<tr>
	            				<th class="check-column"><input type="checkbox"></th>
	            				<th class="shipping_class"><?php _e( 'Total Weight Equal to and Above', 'woo-cart-based' ); ?></th>
	        	            	<th><?php _e( 'Shipping Cost', 'woo-cart-based' ); ?></th>
	        	            	<th><?php _e( '', 'woo-cart-based' ); ?></th>
	            			</tr>
	            		</thead>
	            		<tfoot>
	            			<tr>
	            				<th colspan="2"><a href="#" class="add button"><?php _e( 'Add Cart Rate', 'woo-cart-based' ); ?></a></th>
	            				<th colspan="2"><small><?php _e( 'Add weight based rates for shipping here', 'woo-cart-based' ); ?></small> <a href="#" class="remove button"><?php _e( 'Delete selected rates', 'woo-cart-based' ); ?></a></th>
	            			</tr>
	            		</tfoot>
	            		<tbody class="cart_rates">
	                	<?php
	                	$i = -1;
	                	if ( $this->cart_rates_weighttotal ) {
	                		foreach ( $this->cart_rates_weighttotal as $class => $rate ) {
		                		$i++;
								$selType = "<select name=\"". $this->id ."_weight_shiptype[" . $i . "]\" class=\"shiptype\">
									<option>".$cur_symbol."</option>
									<option";
									if($rate['shiptype'] == "%") $selType .= " selected=\"selected\"";
									$selType .= ">%</option></select>";

		                		echo '<tr class="cart_rate">
		                		    <td class="check-column"><input type="checkbox" name="select" /></td>
		                		    <td><input type="text" value="' . $rate['min'] . '" name="'. $this->id .'_weight_min[' . $i . ']" placeholder="'.__( '0', 'woo-cart-based' ).'" size="4" /></td>
				                    <td>' . $selType . ' <input type="text" value="' . $rate['cost'] . '" name="'. $this->id .'_weight_cost[' . $i . ']" placeholder="'.__( '0.00', 'woo-cart-based' ).'" size="4" /></td>
				                    <td></td>
			                    </tr>';
	                		}
	                	}
	                	?>
	                	</tbody>
	                </table>
	            </td>
	        </tr>
		</table><!--/.form-table-->


    	<table class="form-table">
	    	<tr valign="top" id="shipping_class_exclusions">
	            <th scope="row" class="titledesc"><?php _e( 'Shipping Class Exclusions', 'woo-cart-based' ); ?>:</th>
	            <td class="forminp" id="<?php echo $this->id; ?>_class_exclusions">
	            	<table class="shippingrows widefat" cellspacing="0">
	            		<thead>
	            			<tr>
	        	            	<th class="shipping_class"><?php _e( 'Shipping Class', 'woo-cart-based' ); ?></th>
	        	            	<th><?php _e( 'Exclude', 'woo-cart-based' ); ?> <a class="tips" data-tip="If shipping is free for items with this class, check the box to exclude these cart items">[?]</a></th>
	            			</tr>
	            		</thead>
	            		<tfoot>
	            			<tr>
	            				<th colspan="2"></th>
	            			</tr>
	            		</tfoot>
	            		<tbody class="class_exclusions">
	                	<?php
	                	$class_exclusions_array = array();
						$shippingClasses = $woocommerce->shipping->get_shipping_classes();
		                //$class_exclusions = (!is_array($this->class_exclusions_options)) ? array() : $this->class_exclusions_options;
	                	if(count($shippingClasses) > 0) {
		                	foreach ($shippingClasses as $key => $val) {
		                		$class_exclusions_array[$val->term_id] = array("term_id" => $val->term_id, "name" => $val->name, "exclude" => '0');
		                	}
		                }
	                	if(count($this->class_exclusions) > 0) {
		                	foreach ($this->class_exclusions as $key => $val) {
		                		if(!array_key_exists($val['term_id'], $class_exclusions_array)) unset($this->class_exclusions[$val['term_id']]);
		                	}
		                }
	                	$class_exclusions_array =  $this->class_exclusions + $class_exclusions_array;

						// Sort Array by Priority
						if(count($class_exclusions_array) > 0) {
							foreach ($class_exclusions_array as $key => $row) {
		    					$name[$key]  = $row['name'];
							}
							array_multisort($name, SORT_ASC, $class_exclusions_array);
						}

	                	$i = -1;
	                	if(count($class_exclusions_array) > 0) {
	                		foreach ( $class_exclusions_array as $id => $arr ) {
	                			$i++;
	                			$checked = ( isset( $arr['excluded'] ) && $arr['excluded'] == 'on' ) ? ' checked="checked"' : '';
		                		echo '<tr class="shipping_class">
		                			<input type="hidden" name="'. $this->id .'_scpid[' . $i . ']" value="' . $arr['term_id'] . '" />
		                			<input type="hidden" name="'. $this->id .'_scp[' . $i . ']" value="' . $id . '" />
		                			<input type="hidden" name="'. $this->id .'_sname[' . $i . ']" value="' . $arr['name'] . '" />
		                			<td>'.$arr['name'].'</td>
		                		    <td><input type="checkbox" ' . $checked . '" name="'. $this->id .'_excluded[' . $i . ']" size="5" /></td></tr>';
	                		}
	                	} else echo '<tr><td colspan="2">You have no shipping classes available</td></tr>'
	                	?>
	                	</tbody>
	                </table>
	            </td>
	        </tr>
		</table><!--/.form-table-->

		<script type="text/javascript">
			jQuery(function() {
				// Load default frame
				jQuery(window).load(function () {

				var e = document.getElementById("woocommerce_<?php echo $this->id; ?>_method");
				var method_sel = e.options[e.selectedIndex].value;
				if(method_sel=='subtotal') document.getElementById('row_subtotal_based').style.display='table-row';
				if(method_sel=='itemcount') document.getElementById('row_itemcount_based').style.display='table-row';
				if(method_sel=='weighttotal') document.getElementById('row_weighttotal_based').style.display='table-row';

				return false;
				});

				// Event Handler for Change of Shipping Method
				jQuery('#woocommerce_<?php echo $this->id; ?>_method').change(function(){

				document.getElementById('row_subtotal_based').style.display='none';
				document.getElementById('row_itemcount_based').style.display='none';
				document.getElementById('row_weighttotal_based').style.display='none';

				var e = document.getElementById("woocommerce_<?php echo $this->id; ?>_method");
				var method_sel = e.options[e.selectedIndex].value;
				if(method_sel=='subtotal') document.getElementById('row_subtotal_based').style.display='table-row';
				if(method_sel=='itemcount') document.getElementById('row_itemcount_based').style.display='table-row';
				if(method_sel=='weighttotal') document.getElementById('row_weighttotal_based').style.display='table-row';

				return false;
				});

				jQuery('#<?php echo $this->id; ?>_cart_rates_subtotal a.add').live('click', function(){

				var size = jQuery('#<?php echo $this->id; ?>_cart_rates_subtotal tbody .cart_rate').size();

				jQuery('<tr class="cart_rate">\
				    <td class="check-column"><input type="checkbox" name="select" /></td>\
            			    <td><?php echo $cur_symbol; ?> <input type="text" name="<?php echo $this->id; ?>_sub_min[' + size + ']" placeholder="0.00" size="4" /></td>\
		                    <td><select name="<?php echo $this->id; ?>_shiptype[' + size + ']" class="shiptype"><option><?php echo $cur_symbol; ?></option><option>%</option></select>\
		                    <input type="text" name="<?php echo $this->id; ?>_sub_cost[' + size + ']" placeholder="0.00" size="4" /></td>\
		                    <td></td>\
				    </tr>').appendTo('#<?php echo $this->id; ?>_cart_rates_subtotal table tbody');

				return false;
				});

				jQuery('#<?php echo $this->id; ?>_cart_rates_itemcount a.add').live('click', function(){

				var size = jQuery('#<?php echo $this->id; ?>_cart_rates_itemcount tbody .cart_rate').size();

				jQuery('<tr class="cart_rate">\
				    <td class="check-column"><input type="checkbox" name="select" /></td>\
            			    <td><input type="text" name="<?php echo $this->id; ?>_count_min[' + size + ']" placeholder="0" size="4" /></td>\
		                    <td><select name="<?php echo $this->id; ?>_shiptype[' + size + ']" class="shiptype"><option><?php echo $cur_symbol; ?></option><option>%</option></select>\
		                    <input type="text" name="<?php echo $this->id; ?>_count_cost[' + size + ']" placeholder="0.00" size="4" /></td>\
		                    <td></td>\
				    </tr>').appendTo('#<?php echo $this->id; ?>_cart_rates_itemcount table tbody');

				return false;
				});

				jQuery('#<?php echo $this->id; ?>_cart_rates_weighttotal a.add').live('click', function(){

				var size = jQuery('#<?php echo $this->id; ?>_cart_rates_weighttotal tbody .cart_rate').size();

				jQuery('<tr class="cart_rate">\
				    <td class="check-column"><input type="checkbox" name="select" /></td>\
            			    <td><input type="text" name="<?php echo $this->id; ?>_weight_min[' + size + ']" placeholder="0" size="4" /></td>\
		                    <td><select name="<?php echo $this->id; ?>_shiptype[' + size + ']" class="shiptype"><option><?php echo $cur_symbol; ?></option><option>%</option></select>\
		                    <input type="text" name="<?php echo $this->id; ?>_weight_cost[' + size + ']" placeholder="0.00" size="4" /></td>\
		                    <td></td>\
				    </tr>').appendTo('#<?php echo $this->id; ?>_cart_rates_weighttotal table tbody');

				return false;
				});

				// Remove row
				jQuery('#<?php echo $this->id; ?>_cart_rates_subtotal a.remove').live('click', function(){
					var answer = confirm("<?php _e('Delete the selected rates?', 'woo-cart-based'); ?>")
					if (answer) {
						jQuery('#<?php echo $this->id; ?>_cart_rates_subtotal table tbody tr td.check-column input:checked').each(function(i, el){
							jQuery(el).closest('tr').remove();
						});
					}
					return false;
				});

				jQuery('#<?php echo $this->id; ?>_cart_rates_itemcount a.remove').live('click', function(){
					var answer = confirm("<?php _e('Delete the selected rates?', 'woo-cart-based'); ?>")
					if (answer) {
						jQuery('#<?php echo $this->id; ?>_cart_rates_itemcount table tbody tr td.check-column input:checked').each(function(i, el){
							jQuery(el).closest('tr').remove();
						});
					}
					return false;
				});

				jQuery('#<?php echo $this->id; ?>_cart_rates_weighttotal a.remove').live('click', function(){
					var answer = confirm("<?php _e('Delete the selected rates?', 'woo-cart-based'); ?>")
					if (answer) {
						jQuery('#<?php echo $this->id; ?>_cart_rates_weighttotal table tbody tr td.check-column input:checked').each(function(i, el){
							jQuery(el).closest('tr').remove();
						});
					}
					return false;
				});
			});
		</script>
<?php
			} // End admin_options()


			/**
			 * process_cart_rates function.
			 *
			 * @access public
			 * @return void
			 */
			function process_cart_rates() {
				// Save the rates
				$cart_rate_max = array();
				$cart_rate_shiptype = array();
				$cart_rate_cost = array();
				$cart_rates = array();
				$cart_rates_exclusions = array();
				$cart_rates_scpid = array();
				$cart_rates_scp = array();
				$cart_rates_sname = array();
				$class_exclusions = array();
				$class_excluded = array();
				$method_desired = $_POST[ 'woocommerce_' . $this->id . '_method' ];

				if($method_desired == 'subtotal')
					$type = "sub";
				else if($method_desired == 'itemcount')
					$type = "count";
				else if($method_desired == 'weighttotal')
					$type = "weight";

				if ( isset( $_POST[ $this->id . '_'.$type.'_min'] ) )  $cart_rate_min = array_map( 'woocommerce_clean', $_POST[ $this->id . '_'.$type.'_min'] );
				if ( isset( $_POST[ $this->id . '_'.$type.'_shiptype'] ) )   $cart_rate_shiptype = array_map( 'woocommerce_clean', $_POST[ $this->id . '_'.$type.'_shiptype'] );
				if ( isset( $_POST[ $this->id . '_'.$type.'_cost'] ) )   $cart_rate_cost = array_map( 'woocommerce_clean', $_POST[ $this->id . '_'.$type.'_cost'] );

				if ( isset( $_POST[ $this->id . '_scpid'] ) )   $cart_rates_scpid = array_map( 'woocommerce_clean', $_POST[ $this->id . '_scpid'] );
				if ( isset( $_POST[ $this->id . '_scp'] ) )   $cart_rates_scp = array_map( 'woocommerce_clean', $_POST[ $this->id . '_scp'] );
				if ( isset( $_POST[ $this->id . '_sname'] ) )   $cart_rates_sname = array_map( 'woocommerce_clean', $_POST[ $this->id . '_sname'] );
				if ( isset( $_POST[ $this->id . '_excluded'] ) )   $cart_rates_excluded = array_map( 'woocommerce_clean', $_POST[ $this->id . '_excluded'] );

				// Get max key
				$values = $cart_rate_min;
				ksort( $values );
				$value = end( $values );
				$key = key( $values );

				for ( $i = 0; $i <= $key; $i++ ) {
					if ( isset( $cart_rate_min[$i] ) && isset( $cart_rate_cost[$i] ) ) {

						$cart_rate_cost[$i] = number_format($cart_rate_cost[$i], 2,  '.', '');
						$cart_rate_min[$i] = ($method_desired == 'subtotal' || $method_desired == 'weighttotal') ? number_format($cart_rate_min[$i], 2,  '.', '') : number_format($cart_rate_min[$i], 0,  '', '');

						// Add to cart rates array
						$cart_rates[$i] = array(
						    'min' => $cart_rate_min[ $i ],
						    'cost'  => $cart_rate_cost[ $i ],
						    'shiptype' => ( isset( $cart_rate_shiptype[ $i ] ) ) ? $cart_rate_shiptype[ $i ] : 0
						);
					}
				}

				$cart_rates = $this->m_array_sort($cart_rates, 'min');

				// Get max key
				$values = $cart_rates_scp;
				ksort( $values );
				$value = end( $values );
				$key = key( $values );

				for ( $i = 0; $i <= $key; $i++ ) {
					if(isset($cart_rates_scp[$i])) {

						// Add exclusions to class exclusions array
						$class_exclusions[$cart_rates_scpid[$i]] = array(
							"term_id" => $cart_rates_scpid[$i],
							"name" => $cart_rates_sname[$i],
							'excluded' => ( isset( $cart_rates_excluded[$i] ) ) ? $cart_rates_excluded[$i] : false,
						);
					}
				}

				if($method_desired == 'subtotal')
					update_option( $this->cart_rate_sub_option, $cart_rates );
				else if($method_desired == 'itemcount')
					update_option( $this->cart_rate_count_option, $cart_rates );
				else if($method_desired == 'weighttotal')
					update_option( $this->cart_rate_weight_option, $cart_rates );

				update_option( $this->class_exclusions_options, $class_exclusions );

				$this->get_cart_rates();
				$this->get_class_exclusions();
			}


			/**
			 * m_array_sort function.
			 * sorts a multi-dimensional array by secondary value
			 *
			 * @access public
			 * @return array
			 */
			function m_array_sort($array, $key) {
			    if (is_array($array) && count($array) > 0) {
				        if (!empty($key)) {
			            $mapping = array();
			            foreach ($array as $k => $v) {
			                $sort_key = $v[$key];
			                $mapping[$k] = $sort_key;
			            }
			            asort($mapping);
			            $sorted = array();
			            foreach ($mapping as $k => $v) {
			                $sorted[] = $array[$k];
			            }
			            return $sorted;
			        }
			    }
			    return $array;
			}


			/**
			 * get_cart_rates function.
			 *
			 * @access public
			 * @return void
			 */
			function get_cart_rates() {
				$this->cart_rates_subtotal = array_filter( (array) get_option( $this->cart_rate_sub_option ) );
				$this->cart_rates_itemcount = array_filter( (array) get_option( $this->cart_rate_count_option ) );
				$this->cart_rates_weighttotal = array_filter( (array) get_option( $this->cart_rate_weight_option ) );
			}


			/**
			 * get_class_exclusions function.
			 *
			 * @access public
			 * @return void
			 */
			function get_class_exclusions() {
				$this->class_exclusions = array_filter( (array) get_option( $this->class_exclusions_options ) );
			}

    /**
     * is_available function.
     *
     * @param array $package
     * @return bool
     */
    public function is_available( $package ) {
    	if ( $this->enabled == "no" )
    		return false;

		// Country availability
		switch ( $this->availability ) {
			case 'specific' :
			case 'including' :
				$ship_to_countries = array_intersect( $this->countries, array_keys( WC()->countries->get_shipping_countries() ) );
			break;
			case 'excluding' :
				$ship_to_countries = array_diff( array_keys( WC()->countries->get_shipping_countries() ), $this->countries );
			break;
			default :
				$ship_to_countries = array_keys( WC()->countries->get_shipping_countries() );
			break;
		}

		if ( ! in_array( $package['destination']['country'], $ship_to_countries ) )
			return false;

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );
    }

		}


		/**
		 * add_cart_rate_method function.
		 *
		 * @package		WooCommerce/Classes/Shipping
		 * @access public
		 * @param array $methods
		 * @return array
		 */
		function add_cart_rate_method( $methods ) {
			$methods[] = 'WC_Cart_Based_Shipping';
			return $methods;
		}

		add_filter( 'woocommerce_shipping_methods', 'add_cart_rate_method' );

	}
}

}