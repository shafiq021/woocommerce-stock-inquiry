<?php
/**
 * Optional [wsi_button] shortcode.
 *
 * The plugin works without it: the button is placed automatically through WooCommerce hooks.
 * Use the shortcode only to print the button somewhere the hooks do not reach, for example a
 * custom Elementor template. It prints the button only when the product's stock rule says an
 * inquiry is needed, so it is safe to leave in a template.
 *
 *   [wsi_button]                      the current product
 *   [wsi_button product_id="123"]     a specific product
 *   [wsi_button text="Ask us"]        custom button text
 *   [wsi_button method="whatsapp"]    use another (configured) inquiry method
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * The optional [wsi_button] shortcode.
 */
class WSI_Shortcode {

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'text'       => '',
				'method'     => '',
			),
			$atts,
			'wsi_button'
		);

		$product = self::get_product( absint( $atts['product_id'] ) );
		if ( ! $product || ! WSI_Stock_Checker::needs_inquiry( $product ) ) {
			return '';
		}

		$config = WSI_Resolver::resolve( $product );

		// A different method than the product's own, only if it exists.
		$method_id = sanitize_key( (string) $atts['method'] );
		if ( '' !== $method_id && array_key_exists( $method_id, WSI_Plugin::get_methods() ) ) {
			$config['method']      = $method_id;
			$config['button_text'] = '';
		}

		$method = WSI_Plugin::get_method( $config['method'] );
		if ( ! $method ) {
			return '';
		}
		$link = $method->get_link( $product );
		if ( empty( $link['url'] ) ) {
			return '';
		}

		$text = mb_substr( trim( sanitize_text_field( (string) $atts['text'] ) ), 0, 60 );

		return WSI_Button::render( $product, $link, 'shortcode', $config, array( 'text' => $text ) );
	}

	/**
	 * Product from the attribute, or the current product. Only published products are used.
	 *
	 * @param int $product_id Product ID from the shortcode.
	 * @return WC_Product|null
	 */
	private static function get_product( $product_id ) {
		if ( ! $product_id ) {
			global $product;
			$current = ( $product instanceof WC_Product ) ? $product : wc_get_product( get_the_ID() );
		} else {
			$current = wc_get_product( $product_id );
		}

		if ( ! $current instanceof WC_Product || 'publish' !== get_post_status( $current->get_id() ) ) {
			return null;
		}
		return $current;
	}
}
