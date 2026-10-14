<?php
/**
 * WhatsApp inquiry method (https://wa.me click-to-chat link).
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp inquiry method.
 */
class WSI_WhatsApp implements WSI_Inquiry_Method {

	public function get_id() {
		return 'whatsapp';
	}

	public function get_label() {
		return __( 'WhatsApp', 'woocommerce-stock-inquiry' );
	}

	public function is_configured() {
		// The default number or any extra destination is enough.
		return WSI_Destinations::has_number();
	}

	public function get_link( $product ) {
		// The product's destination (own setting, category rule), falling back to the default number.
		$config = WSI_Resolver::resolve( $product );
		$number = WSI_Destinations::number_for( $config['destination'] );
		if ( '' === $number ) {
			return false;
		}

		return array(
			// rawurlencode() turns each "\n" into %0A, which WhatsApp shows as a line break.
			'url'    => 'https://wa.me/' . $number . '?text=' . rawurlencode( $this->build_message( $product ) ),
			'target' => WSI_Settings::get( 'whatsapp_target' ),
		);
	}

	/**
	 * Build the chat message. Placeholders are always replaced, never shown raw.
	 * "Automatically add" options only append a line when the template does not
	 * already contain the matching placeholder, so nothing is duplicated.
	 *
	 * Template priority: the product's own message, then the destination's message, then the global one.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function build_message( $product ) {
		$config   = WSI_Resolver::resolve( $product );
		$template = $config['message'];
		if ( '' === $template ) {
			$template = WSI_Destinations::message_for( $config['destination'] );
		}
		if ( '' === $template ) {
			$template = (string) WSI_Settings::get( 'whatsapp_message' );
		}
		$template = WSI_Placeholders::normalize_newlines( $template );

		$has_name = WSI_Placeholders::has( $template, '{product_name}' );
		$has_url  = WSI_Placeholders::has( $template, '{product_url}' );

		$message = WSI_Placeholders::replace( $template, $product );
		$values  = WSI_Placeholders::values( $product );

		$extra = array();
		if ( WSI_Settings::get( 'whatsapp_auto_name' ) && ! $has_name ) {
			/* translators: %s: product name */
			$extra[] = sprintf( __( 'Product: %s', 'woocommerce-stock-inquiry' ), $values['{product_name}'] );
		}
		if ( WSI_Settings::get( 'whatsapp_auto_url' ) && ! $has_url ) {
			/* translators: %s: product URL */
			$extra[] = sprintf( __( 'Product URL: %s', 'woocommerce-stock-inquiry' ), $values['{product_url}'] );
		}
		if ( $extra ) {
			$message = rtrim( $message ) . "\n\n" . implode( "\n", $extra );
		}

		return trim( $message );
	}
}
