=== FG PrestaShop to WooCommerce Premium ===
Contributors: Frédéric GILLES
Plugin Uri: http://www.fredericgilles.net/fg-prestashop-to-woocommerce/
Tags: prestashop, woocommerce, wordpress, convert prestashop to woocommerce, migrate prestashop to woocommerce, prestashop to woocommerce migration, migrator, converter, import
Requires at least: 4.0
Tested up to: WP 4.1.0
Stable tag: 1.7.0
License: GPLv2

A plugin to migrate PrestaShop e-commerce solution to WooCommerce

== Description ==

This plugin migrates products, categories, tags, images, CMS, employees, customers and orders from PrestaShop to WooCommerce/WordPress.

It has been tested with **PrestaShop version 1.4, 1.5 and 1.6** and **Wordpress 4.1**. It is compatible with multisite installations.

Major features include:

* migrates PrestaShop products
* migrates PrestaShop product images
* migrates PrestaShop product categories
* migrates PrestaShop product tags
* migrates PrestaShop product features
* migrates PrestaShop product combinations
* migrates PrestaShop CMS (as posts or pages)
* migrates PrestaShop employees
* migrates PrestaShop customers
* migrates PrestaShop orders
* migrates PrestaShop ratings and reviews
* migrates PrestaShop discounts/vouchers (cart rules)
* SEO: Redirect the PrestaShop URLs to the new WordPress URLs
* SEO: Import meta data (browser title, description, keywords, robots) to WordPress SEO
* the employees and customers can authenticate to WordPress using their PrestaShop passwords
* ability to do a partial import

No need to subscribe to an external web site.

== Installation ==

= Requirements =
WooCommerce must be installed and activated before running the migration.

= Installation =
1.  Install the plugin in the Admin => Plugins menu => Add New => Upload => Select the zip file => Install Now
2.  Activate the plugin in the Admin => Plugins menu
3.  Run the importer in Tools > Import > PrestaShop
4.  Configure the plugin settings. You can find the PrestaShop database parameters in the PrestaShop file settings.inc.php (PrestaShop 1.5+) or in the PrestaShop Preferences > Database tab (PrestaShop 1.4)
5.  Test the database connection
6.  Click on the import button

== Frequently Asked Questions ==

= The import is not complete =

* You can run the migration again and it will continue where it left off.
* You can add: `define('WP_MEMORY_LIMIT', '512M');` in your wp-config.php file to increase the memory allowed by WordPress
* You can also increase the memory limit in php.ini if you have write access to this file (ie: memory_limit = 1G).

= The images aren't being imported =

* Please check the URL field. It must contain the URL of the PrestaShop home page
* Check that the maintenance mode is disabled in PrestaShop
* Use http instead of https in the URL field

= Are the product combinations/attributes imported? =

* This is a Premium feature available on: http://www.fredericgilles.net/fg-prestashop-to-woocommerce/

== Screenshots ==

1. Parameters screen

== Translations ==
* English (default)
* French (fr_FR)
* other can be translated

== Changelog ==

= 1.7.0 =
* New: Import the quantities at variations level
* Tested with WordPress 4.1

= 1.6.0 =
* New: SEO: Redirect to the product or category even if the ID is not in the URL
* Tweak: Don't display the timeout field if the medias are skipped

= 1.5.1 =
* Fixed: Fatal error: Cannot use object of type WP_Error as array
* FAQ updated
* Tested with WordPress 4.0.1

= 1.5.0 =
* New: Import the discounts/vouchers (cart rules)

= 1.4.0 =
* New: Import the product ratings & reviews
* Fixed: WordPress database error: [Duplicate entry 'xxx-yyy' for key 'PRIMARY']

= 1.3.1 =
* Fixed: Some images were not imported on PrestaShop 1.4

= 1.3.0 =
* Fixed: Set the products with a null quantity as "Out of stock"
* New: Import the product features
* New: Import the product supplier reference as SKU if the product reference is empty
* New: Import the product attribute supplier reference as SKU if the product attribute reference is empty

= 1.2.0 =
* New: Import the product combinations (attributes and variations)
* Fixed: Some images were not imported

= 1.1.2 =
* Fixed: URLs were not redirected when using FastCGI (http://php.net/manual/fr/function.filter-input.php#77307)

= 1.1.1 =
* Fixed: The order statuses were always pending (PrestaShop 1.4)

= 1.1.0 =
* Compatible with WooCommerce 2.2
* New: Import PrestaShop employees, customers and orders
* New: The employees and customers can authenticate to WordPress using their PrestaShop passwords
* New: SEO: Redirect the PrestaShop URLs
* New: SEO: Import meta data to WordPress SEO
* New: Ability to do a partial import

= 1.0.0 =
* Initial version: Import PrestaShop products, categories, tags, images and CMS
