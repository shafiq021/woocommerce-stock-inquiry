<?php
/**
 * Contract for inquiry methods (Contact Page, WhatsApp, and future ones such as a popup).
 *
 * To add a method: implement this interface and register it through the
 * `wsi_inquiry_methods` filter. Admin fields for it can be printed on the
 * `wsi_admin_method_fields` action.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

interface WSI_Inquiry_Method {

	/**
	 * Unique id stored in the `method` setting.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Admin label.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether the method has everything it needs (page selected, number entered...).
	 * When false the plugin leaves WooCommerce untouched instead of showing a broken button.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Link for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return array|false array( 'url' => string, 'target' => '_self'|'_blank' ), or false.
	 */
	public function get_link( $product );
}
