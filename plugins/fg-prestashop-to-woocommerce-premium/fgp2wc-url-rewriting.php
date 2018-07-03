<?php
/**
 * FG PrestaShop to WooCommerce Premium
 * URL Rewriting module
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'fgp2wcp_url_rewriting', 21 );

if ( !function_exists( 'fgp2wcp_url_rewriting' ) ) {
	function fgp2wcp_url_rewriting() {
		new fgp2wcp_urlrewriting();
	}
}

if ( !class_exists('fgp2wcp_urlrewriting', false) ) {
	class fgp2wcp_urlrewriting {

		private static $rewrite_rules = array(
			array( 'rule' => '^.*/(\d+)-',			'method' => 'id',	'meta_key' => '_fgp2wc_old_ps_product_id'),
			array( 'rule' => '^.*/(.+?)(\.html)?$',	'method' => 'slug'),
		);
		
		/**
		 * Sets up the plugin
		 *
		 */
		public function __construct() {
			$premium_options = get_option('fgp2wcp_options');
			$do_redirect = isset($premium_options['url_redirect']) && !empty($premium_options['url_redirect']);
			$do_redirect = apply_filters('fgp2wcp_do_redirect', $do_redirect);
			if ( $do_redirect ) {
				// Hook on template redirect
				add_action('template_redirect', array($this, 'template_redirect'));
				// for PrestaShop non SEF URLs
				add_filter('query_vars', array($this, 'add_query_vars'));
				add_action('fgp2wcp_pre_404_redirect', array($this, 'pre_404_redirect'));
			}
		}
		
		/**
		 * Add the query vars
		 *
		 * @param array $vars Query vars
		 * @return array $vars Query vars
		 */
		public function add_query_vars($vars) {
			
			$vars[] = 'id_product'; // PrestaShop product ID without URL rewriting
			return $vars;
		}
		
		/**
		 * Redirection to the new URL
		 */
		public function template_redirect() {
			$matches = array();
			do_action('fgp2wcp_pre_404_redirect');
			
			if ( !is_404() ) { // A page is found, don't redirect
				return;
			}
			
			do_action('fgp2wcp_post_404_redirect');

			// Process the rewrite rules
			$rewrite_rules = apply_filters('fgp2wcp_rewrite_rules', self::$rewrite_rules);
			// PrestaShop configured with SEF URLs
			foreach ( $rewrite_rules as $rewrite_rule ) {
				if ( preg_match('#'.$rewrite_rule['rule'].'#', $_SERVER['REQUEST_URI'], $matches) ) {
					switch ( $rewrite_rule['method'] ) {
						case 'id':
							$old_id = $matches[1];
							self::redirect_id($rewrite_rule['meta_key'], $old_id);
							break;
						case 'slug':
							$slug = $matches[1];
							self::redirect_slug($slug);
							break;
					}
				}
			}
		}
		
		/**
		 * Try to redirect the PrestaShop non SEF URLs
		 */
		public function pre_404_redirect() {
			// PrestaShop configured without SEF URLs: id_product=xxx
			$id_product = get_query_var('id_product');
			if ( !empty($id_product) ) {
				self::redirect_id('_fgp2wc_old_ps_product_id', $id_product);
			}
		}
		
		/**
		 * Search a post by its old ID and redirect to it if found
		 *
		 * @param string $meta_key Meta Key to search in the postmeta table
		 * @param int $old_id PrestaShop ID
		 */
		public static function redirect_id($meta_key, $old_id) {
			if ( !empty($old_id) && !empty($meta_key) ) {
				// Try to find a post by its old ID
				query_posts( array(
					'post_type' => 'any',
					'meta_key' => $meta_key,
					'meta_value' => $old_id,
					'ignore_sticky_posts' => 1,
				) );
				if ( have_posts() ) {
					self::redirect_to_post();
				}
				// else continue the normal workflow
			}
		}
		
		/**
		 * Search a post by its slug and redirect to it if found
		 *
		 * @param string $slug Slug to search
		 */
		public static function redirect_slug($slug) {
			if ( !empty($slug) ) {
				// Try to find a post by its slug
				query_posts( array(
					'post_type' => 'any',
					'name' => $slug,
					'ignore_sticky_posts' => 1,
				) );
				if ( have_posts() ) {
					self::redirect_to_post();
					
				} else {
					// Try to find a category by its slug
					$cat = get_term_by('slug', $slug, 'product_cat');
					if ( $cat !== false ) {
						self::redirect_to_category($cat);
					}
				}
				// else continue the normal workflow
			}
		}
		
		/**
		 * Redirect to the new product URL if a post is found
		 */
		protected static function redirect_to_post() {
			the_post();
			$url = get_permalink();
//			die($url);
			wp_redirect($url, 301);
			wp_reset_query();
			exit;
		}
		
		/**
		 * Redirect to the new category URL if a category is found
		 */
		protected static function redirect_to_category($term) {
			$url = get_term_link($term, 'product_cat');
			if ( !is_wp_error($url) ) {
//				die($url);
				wp_redirect($url, 301);
				wp_reset_query();
				exit;
			}
		}
	}
}
?>
