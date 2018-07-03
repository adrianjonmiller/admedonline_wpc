<?php
/**
 * FG PrestaShop to WooCommerce Premium
 * Users authentication module
 * Authenticate the WordPress users using the imported PrestaShop passwords
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

add_filter('authenticate', array('fgp2wcp_users_authenticate', 'auth_signon'), 30, 3);

if ( !class_exists('fgp2wcp_users_authenticate', false) ) {
	class fgp2wcp_users_authenticate {
		
		/**
		 * Authenticate a user using his PrestaShop password
		 *
		 * @param WP_User $user User data
		 * @param string $username User login entered
		 * @param string $password Password entered
		 * @return WP_User User data
		 */
		public static function auth_signon($user, $username, $password) {
			
			if ( is_a($user, 'WP_User') ) {
				// User is already identified
				return $user;
			}
			
			if ( empty($username) || empty($password) ) {
				return $user;
			}
			
			$wp_user = get_user_by('login', $username);
			if ( !is_a($wp_user, 'WP_User') ) {
				// username not found in WP users
				return $user;
			}
			
			// Get the imported prestashop_pass
			$prestashop_pass = get_user_meta($wp_user->ID, 'prestashop_pass', true);
			if ( empty($prestashop_pass) ) {
				return $user;
			}
			
			// Authenticate the user using the PrestaShop password
			if ( self::auth_prestashop($password, $prestashop_pass) ) {
				// Update WP user password
				wp_update_user(array('ID' => $wp_user->ID, 'user_pass' => $password));
				// To prevent the user to log in again with his PrestaShop password once he has successfully logged in. The following times, his password stored in WordPress will be used instead.
				delete_user_meta($wp_user->ID, 'prestashop_pass');
				
				return $wp_user;
			}
			
			return $user;
		}
		
		/**
		 * PrestaShop user authentication
		 *
		 * @param string $password Password entered
		 * @param string $prestashop_pass Password stored in the WP usermeta table
		 */
		private static function auth_prestashop($password, $prestashop_pass) {
			$options = get_option('fgp2wcp_options');
			$salt = isset($options['cookie_key'])? $options['cookie_key'] : '';
			$hash = md5($salt . $password);
			return ($hash == $prestashop_pass);
		}
	}
}
?>
