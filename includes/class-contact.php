<?php
/**
 * Contact Page inquiry method.
 *
 * Sends the customer to the selected page and passes the product in the query string:
 * /contact-us/?wsi_product=123&wsi_product_name=...&wsi_product_url=...
 * A selected variation is added as wsi_variation=456.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contact Page inquiry method.
 */
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

		$is_variation = $product->is_type( 'variation' );
		$product_id   = $is_variation ? $product->get_parent_id() : $product->get_id();
		$values       = WSI_Placeholders::values( $product );

		// add_query_arg() does not encode values, so encode them explicitly.
		$args = array(
			'wsi_product'      => absint( $product_id ),
			'wsi_product_name' => rawurlencode( $values['{product_name}'] ),
			'wsi_product_url'  => rawurlencode( $values['{product_url}'] ),
		);
		if ( $is_variation ) {
			$args['wsi_variation'] = absint( $product->get_id() );
		}
		$url = add_query_arg( $args, $url );

		return array(
			'url'    => $url,
			'target' => WSI_Settings::get( 'contact_target' ),
		);
	}

	/**
	 * Shortcode for the contact page, handy for pre-filling a form field:
	 * [wsi_product field="name"] (default), or field="id", "url", "sku", "price".
	 *
	 * The product is looked up by ID, so the query string cannot inject text into the page.
	 * When a variation ID is passed it is only used if it really belongs to that product.
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
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only lookup, no state change.
		$variation_id = isset( $_GET['wsi_variation'] ) ? absint( wp_unslash( $_GET['wsi_variation'] ) ) : 0;
		if ( $variation_id && $product->is_type( 'variable' ) && in_array( $variation_id, array_map( 'intval', $product->get_children() ), true ) ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof WC_Product ) {
				$product = $variation;
			}
		}

		$values = WSI_Placeholders::values( $product );
		switch ( $atts['field'] ) {
			case 'id':
				return esc_html( $values['{product_id}'] );
			case 'url':
				return esc_url( $values['{product_url}'] );
			case 'sku':
				return esc_html( $values['{sku}'] );
			case 'price':
				return esc_html( $values['{price}'] );
			default:
				return esc_html( $values['{product_name}'] );
		}
	}
}
