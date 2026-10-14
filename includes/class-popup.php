<?php
/**
 * Popup inquiry method (Elementor Pro popup).
 *
 * This is an integration, not a form builder: the store owner builds the popup, and puts their
 * own form inside it, with Elementor. The button opens that popup and hands it the product details.
 *
 * Elementor Pro is only needed for this method. When it is missing the method is "not configured"
 * and WooCommerce keeps its normal buttons.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor Pro popup inquiry method.
 */
class WSI_Popup implements WSI_Inquiry_Method {

	public function get_id() {
		return 'popup';
	}

	public function get_label() {
		return __( 'Popup', 'woocommerce-stock-inquiry' );
	}

	public function is_configured() {
		return WSI_Elementor::is_pro_active() && WSI_Elementor::popup_exists( WSI_Settings::get( 'popup_id' ) );
	}

	public function get_link( $product ) {
		if ( ! $this->is_configured() ) {
			return false;
		}

		$popup_id = absint( WSI_Settings::get( 'popup_id' ) );
		WSI_Elementor::request_popup( $popup_id );

		return array(
			'url'    => '#',
			'target' => '_self',
			'attrs'  => array(
				'role'             => 'button',
				'data-wsi-popup'   => (string) $popup_id,
				'data-wsi-product' => wp_json_encode( self::product_data( $product ) ),
			),
		);
	}

	/**
	 * Product details handed to the popup (all plain text).
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function product_data( $product ) {
		$values = WSI_Placeholders::values( $product );
		$config = WSI_Resolver::resolve( $product );

		return array(
			'id'      => $values['{product_id}'],
			'name'    => $values['{product_name}'],
			'url'     => $values['{product_url}'],
			'sku'     => $values['{sku}'],
			'price'   => $values['{price}'],
			'message' => '' !== $config['message'] ? trim( WSI_Placeholders::replace( $config['message'], $product ) ) : '',
		);
	}
}
