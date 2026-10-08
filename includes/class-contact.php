<?php
/**
 * Contact Page inquiry method.
 *
 * Sends the customer to the selected page and passes the product in the query string:
 * /contact-us/?wsi_product=123&wsi_product_name=...&wsi_product_url=...
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

class WSI_Contact implements WSI_Inquiry_Method {

	public function get_id() {
		return 'contact';
	}

	public function get_label() {
		return __( 'Contact Page', 'woocommerce-stock-inquiry' );
	}

	public function is_configured() {
		return (bool) $this->get_page_url();
	}

	/**
	 * @return string Permalink of the selected page, or '' when missing/unpublished.
	 */
	private function get_page_url() {
		$page_id = absint( WSI_Settings::get( 'contact_page' ) );
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}
		$url = get_permalink( $page_id );
		return $url ? $url : '';
	}

	public function get_link( $product ) {
		$url = $this->get_page_url();
		if ( '' === $url ) {
			return false;
		}

		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		// add_query_arg() does not encode values, so encode them explicitly.
		$url = add_query_arg(
			array(
				'wsi_product'      => absint( $product_id ),
				'wsi_product_name' => rawurlencode( wp_strip_all_tags( $product->get_name() ) ),
				'wsi_product_url'  => rawurlencode( (string) get_permalink( $product_id ) ),
			),
			$url
		);

		return array(
			'url'    => $url,
			'target' => WSI_Settings::get( 'contact_target' ),
		);
	}

	/**
	 * Shortcode for the contact page, handy for pre-filling a form field:
	 * [wsi_product field="name"] (default), field="id" or field="url".
	 *
	 * The product is looked up by ID, so the query string cannot inject text into the page.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'field' => 'name' ), $atts, 'wsi_product' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only lookup, no state change.
		$product_id = isset( $_GET['wsi_product'] ) ? absint( wp_unslash( $_GET['wsi_product'] ) ) : 0;
		if ( ! $product_id || 'publish' !== get_post_status( $product_id ) ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		switch ( $atts['field'] ) {
			case 'id':
				return (string) $product_id;
			case 'url':
				return esc_url( get_permalink( $product_id ) );
			default:
				return esc_html( wp_strip_all_tags( $product->get_name() ) );
		}
	}
}
