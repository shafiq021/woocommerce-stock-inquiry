<?php
/**
 * Contract for inquiry methods (Contact Page, WhatsApp, and future ones such as a popup).
 *
 * To add a method: implement this interface and register it through the
 * `wsi_inquiry_methods` filter. Admin fields for it can be printed on the
 * `wsi_admin_method_fields` action.
 *
 * Button text is read from the `button_text_<id>` setting and the description from
 * `desc_text_<id>`, so a new method gets text, styling, placement and preview for free.
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
	 * Link for a product (a simple product, a variable product or a single variation).
	 *
	 * Required keys: 'url' (an https, mailto, tel or '#' URL) and 'target' ('_self' or '_blank').
	 * Optional key: 'attrs', an array of extra attributes for the link. Only `data-*`, `aria-*`
	 * and `role` names are printed. Use it for methods that need JavaScript (the popup method).
	 *
	 * @param WC_Product $product Product.
	 * @return array|false array( 'url' => string, 'target' => string, 'attrs' => array ), or false.
	 */
	public function get_link( $product );
}
