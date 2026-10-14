<?php
/**
 * Placeholder replacement shared by every inquiry method (WhatsApp, Email, Popup, Contact).
 *
 * All values are plain text (no HTML, no entities) so each method can encode them for its own
 * destination (URL, mailto, form field). Values that are not available for a product are an
 * empty string, never a guess.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central placeholder replacement for every inquiry method.
 */
class WSI_Placeholders {

	/**
	 * Supported placeholders (tag => description) for the admin reference table.
	 *
	 * @return array
	 */
	public static function tags() {
		return array(
			'{product_id}'     => __( 'Product ID (the variation ID when a variation is selected)', 'woocommerce-stock-inquiry' ),
			'{product_name}'   => __( 'Product name (includes the variation attributes for a variation)', 'woocommerce-stock-inquiry' ),
			'{product_url}'    => __( 'Product link (a variation link pre-selects the variation)', 'woocommerce-stock-inquiry' ),
			'{sku}'            => __( 'SKU (empty when the product has none)', 'woocommerce-stock-inquiry' ),
			'{price}'          => __( 'Current price (a range for variable products, empty when there is no price)', 'woocommerce-stock-inquiry' ),
			'{regular_price}'  => __( 'Regular price (empty for variable products)', 'woocommerce-stock-inquiry' ),
			'{sale_price}'     => __( 'Sale price (empty when not on sale)', 'woocommerce-stock-inquiry' ),
			'{stock_quantity}' => __( 'Stock quantity (empty when stock is not managed)', 'woocommerce-stock-inquiry' ),
			'{stock_status}'   => __( 'Stock status: In stock, Out of stock or On backorder', 'woocommerce-stock-inquiry' ),
			'{category}'       => __( 'Product categories, comma separated', 'woocommerce-stock-inquiry' ),
			'{site_name}'      => __( 'Your site name', 'woocommerce-stock-inquiry' ),
			'{site_url}'       => __( 'Your site address', 'woocommerce-stock-inquiry' ),
		);
	}

	/**
	 * Example values for the admin live preview (tag => value).
	 *
	 * @return array
	 */
	public static function sample_values() {
		return array(
			'{product_id}'     => '123',
			'{product_name}'   => __( 'Example Product', 'woocommerce-stock-inquiry' ),
			'{product_url}'    => 'https://example.com/product/example',
			'{sku}'            => 'SKU-001',
			'{price}'          => '$25.00',
			'{regular_price}'  => '$30.00',
			'{sale_price}'     => '$25.00',
			'{stock_quantity}' => '2',
			'{stock_status}'   => __( 'In stock', 'woocommerce-stock-inquiry' ),
			'{category}'       => __( 'Example Category', 'woocommerce-stock-inquiry' ),
			'{site_name}'      => __( 'Your Store', 'woocommerce-stock-inquiry' ),
			'{site_url}'       => 'https://example.com/',
		);
	}

	/**
	 * Real values for a product (tag => plain text).
	 *
	 * @param WC_Product $product Simple product, variable product or variation.
	 * @return array
	 */
	public static function values( $product ) {
		$is_variation = $product->is_type( 'variation' );
		$parent_id    = $is_variation ? $product->get_parent_id() : $product->get_id();

		$values = array(
			'{product_id}'     => (string) $product->get_id(),
			'{product_name}'   => self::text( $product->get_name() ),
			'{product_url}'    => self::product_url( $product, $parent_id ),
			'{sku}'            => self::text( $product->get_sku() ),
			'{price}'          => self::price( $product ),
			'{regular_price}'  => $product->is_type( 'variable' ) ? '' : self::money( $product->get_regular_price() ),
			'{sale_price}'     => ( $product->is_type( 'variable' ) || ! $product->is_on_sale() ) ? '' : self::money( $product->get_sale_price() ),
			'{stock_quantity}' => self::stock_quantity( $product ),
			'{stock_status}'   => self::stock_status( $product ),
			'{category}'       => self::categories( $parent_id ),
			'{site_name}'      => self::text( get_bloginfo( 'name' ) ),
			'{site_url}'       => esc_url_raw( home_url( '/' ) ),
		);

		/**
		 * Filter the placeholder values.
		 *
		 * @param array      $values  tag => plain text value.
		 * @param WC_Product $product Product.
		 */
		return (array) apply_filters( 'wsi_placeholder_values', $values, $product );
	}

	/**
	 * Replace every supported placeholder in a template, in a single pass so a product name
	 * that happens to contain "{sku}" can never be expanded again. Line breaks are normalized
	 * to "\n" first so URL encoding is correct.
	 *
	 * @param string          $template Template text.
	 * @param WC_Product|null $product  Product, or null to use the sample values.
	 * @return string
	 */
	public static function replace( $template, $product = null ) {
		$template = self::normalize_newlines( $template );
		$values   = ( $product instanceof WC_Product ) ? self::values( $product ) : self::sample_values();
		return strtr( $template, $values );
	}

	/**
	 * "\r\n" and "\r" become "\n". A literal backslash-n typed into a template field (two characters)
	 * is also treated as a line break, because that is what the writer meant.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize_newlines( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		return str_replace( '\\n', "\n", $text );
	}

	/**
	 * Whether the template already contains a placeholder.
	 *
	 * @param string $template Template.
	 * @param string $tag      Placeholder including braces.
	 * @return bool
	 */
	public static function has( $template, $tag ) {
		return false !== strpos( (string) $template, $tag );
	}

	/**
	 * Plain text from a possibly HTML/entity encoded string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function text( $value ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Product link. A variation link carries its attributes so the product page opens with
	 * that variation already selected.
	 *
	 * @param WC_Product $product   Product.
	 * @param int        $parent_id Parent (or own) product ID.
	 * @return string
	 */
	private static function product_url( $product, $parent_id ) {
		$url = (string) get_permalink( $parent_id );
		if ( '' !== $url && $product->is_type( 'variation' ) && method_exists( $product, 'get_variation_attributes' ) ) {
			$args = array();
			foreach ( (array) $product->get_variation_attributes() as $key => $value ) {
				if ( '' !== (string) $value ) {
					$args[ sanitize_key( $key ) ] = rawurlencode( (string) $value );
				}
			}
			if ( $args ) {
				$url = add_query_arg( $args, $url );
			}
		}
		return $url;
	}

	/**
	 * Formatted price as plain text.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function price( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$min = $product->get_variation_price( 'min', true );
			$max = $product->get_variation_price( 'max', true );
			if ( '' === (string) $min ) {
				return '';
			}
			return ( (string) $min === (string) $max ) ? self::money( $min ) : self::money( $min ) . ' - ' . self::money( $max );
		}
		return self::money( $product->get_price() );
	}

	/**
	 * @param mixed $amount Raw amount.
	 * @return string Formatted amount as plain text, '' when there is no price.
	 */
	private static function money( $amount ) {
		if ( '' === (string) $amount || null === $amount ) {
			return '';
		}
		return self::text( wc_price( $amount ) );
	}

	/**
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function stock_quantity( $product ) {
		if ( ! $product->managing_stock() ) {
			return '';
		}
		$quantity = $product->get_stock_quantity();
		return null === $quantity ? '' : (string) (int) $quantity;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function stock_status( $product ) {
		switch ( $product->get_stock_status() ) {
			case 'outofstock':
				return __( 'Out of stock', 'woocommerce-stock-inquiry' );
			case 'onbackorder':
				return __( 'On backorder', 'woocommerce-stock-inquiry' );
			default:
				return __( 'In stock', 'woocommerce-stock-inquiry' );
		}
	}

	/**
	 * @param int $product_id Parent product ID.
	 * @return string
	 */
	private static function categories( $product_id ) {
		$names = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) || ! is_array( $names ) ) {
			return '';
		}
		return self::text( implode( ', ', $names ) );
	}
}
