<?php
/**
 * Plugin Name:       WooCommerce Stock Inquiry
 * Description:       Keeps out-of-stock WooCommerce products visible and replaces Add to Cart with a customizable Send Inquiry button (Contact Page or WhatsApp). Restocked products get Add to Cart back automatically.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Shafiqur Rehman
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-stock-inquiry
 * Domain Path:       /languages
 * WC requires at least: 7.0
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

define( 'WSI_VERSION', '1.0.0' );
define( 'WSI_FILE', __FILE__ );
define( 'WSI_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSI_URL', plugin_dir_url( __FILE__ ) );

require_once WSI_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WSI_Plugin', 'activate' ) );

// Declare compatibility with WooCommerce HPOS and Cart/Checkout blocks (this plugin does not touch either).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WSI_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WSI_FILE, true );
		}
	}
);

// Priority 20 so WooCommerce (and its class) is loaded first.
add_action( 'plugins_loaded', array( 'WSI_Plugin', 'boot' ), 20 );
