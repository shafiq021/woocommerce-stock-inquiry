<?php
/**
 * Phone inquiry method (tel: link).
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Phone (tel:) inquiry method.
 */
class WSI_Phone implements WSI_Inquiry_Method {

	public function get_id() {
		return 'phone';
	}

	public function get_label() {
		return __( 'Phone', 'woocommerce-stock-inquiry' );
	}

	public function is_configured() {
		return '' !== WSI_Settings::clean_tel( WSI_Settings::get( 'phone_number' ) );
	}

	public function get_link( $product ) {
		$number = WSI_Settings::clean_tel( WSI_Settings::get( 'phone_number' ) );
		if ( '' === $number ) {
			return false;
		}

		return array(
			'url'    => 'tel:' . $number,
			'target' => '_self',
		);
	}
}
