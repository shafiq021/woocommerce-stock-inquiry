<?php
/**
 * WhatsApp inquiry method (https://wa.me click-to-chat link).
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

class WSI_WhatsApp implements WSI_Inquiry_Method {

	public function get_id() {
		return 'whatsapp';
	}

	public function get_label() {
		return __( 'WhatsApp', 'woocommerce-stock-inquiry' );
	}

	public function is_configured() {
		return '' !== WSI_Settings::clean_phone( WSI_Settings::get( 'whatsapp_number' ) );
	}

	public function get_link( $product ) {
		$number = WSI_Settings::clean_phone( WSI_Settings::get( 'whatsapp_number' ) );
		if ( '' === $number ) {
			return false;
		}

		return array(
			'url'    => 'https://wa.me/' . $number . '?text=' . rawurlencode( $this->build_message( $product ) ),
			'target' => WSI_Settings::get( 'whatsapp_target' ),
		);
	}

	/**
	 * Build the chat message. Placeholders are always replaced, never shown raw.
	 * "Automatically add" options only append a line when the template does not
	 * already contain the matching placeholder, so nothing is duplicated.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function build_message( $product ) {
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$name       = html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' );
		$url        = (string) get_permalink( $product_id );

		$template = str_replace( array( "\r\n", "\r" ), "\n", (string) WSI_Settings::get( 'whatsapp_message' ) );
		$has_name = false !== strpos( $template, '{product_name}' );
		$has_url  = false !== strpos( $template, '{product_url}' );

		// strtr() replaces in a single pass, so a product name can never be re-expanded as a placeholder.
		$message = strtr(
			$template,
			array(
				'{product_name}' => $name,
				'{product_url}'  => $url,
			)
		);

		$extra = array();
		if ( WSI_Settings::get( 'whatsapp_auto_name' ) && ! $has_name ) {
			/* translators: %s: product name */
			$extra[] = sprintf( __( 'Product: %s', 'woocommerce-stock-inquiry' ), $name );
		}
		if ( WSI_Settings::get( 'whatsapp_auto_url' ) && ! $has_url ) {
			/* translators: %s: product URL */
			$extra[] = sprintf( __( 'Product URL: %s', 'woocommerce-stock-inquiry' ), $url );
		}
		if ( $extra ) {
			$message = rtrim( $message ) . "\n\n" . implode( "\n", $extra );
		}

		return trim( $message );
	}
}
