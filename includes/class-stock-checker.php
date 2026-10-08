<?php
/**
 * Decides, per product and per request, whether the inquiry button replaces Add to Cart.
 *
 * WooCommerce stays the source of truth: the decision is computed from the live product
 * object each time it renders. Nothing is stored, synced or scheduled, so restocking a
 * product brings Add to Cart back immediately.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

class WSI_Stock_Checker {

	/** @var array<int,bool> Per-request memo so a product is evaluated once per page. */
	private static $memo = array();

	/**
	 * @param WC_Product $product Product.
	 * @return bool True when the inquiry button should be used for this product.
	 */
	public static function needs_inquiry( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		$id = $product->get_id();
		if ( ! isset( self::$memo[ $id ] ) ) {
			self::$memo[ $id ] = (bool) apply_filters( 'wsi_needs_inquiry', self::evaluate( $product ), $product );
		}
		return self::$memo[ $id ];
	}

	/**
	 * Rules, in order:
	 *  1. Plugin disabled, unsupported product type, or excluded product/category -> normal WooCommerce.
	 *  2. WooCommerce says out of stock (manually or by quantity)                 -> inquiry.
	 *  3. On backorder (backorders allowed, customers can still buy)              -> normal WooCommerce.
	 *  4. Stock is managed and quantity < threshold (threshold 0 = off)           -> inquiry.
	 *  5. Stock is not managed                                                    -> only rule 2 applies.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private static function evaluate( $product ) {
		if ( ! WSI_Settings::get( 'enabled' ) ) {
			return false;
		}

		$types = apply_filters( 'wsi_supported_product_types', array( 'simple', 'variable' ) );
		if ( ! $product->is_type( $types ) ) {
			return false;
		}

		if ( WSI_Product_Rules::is_excluded( $product ) ) {
			return false;
		}

		if ( ! $product->is_in_stock() ) {
			return true;
		}

		if ( 'onbackorder' === $product->get_stock_status() || $product->backorders_allowed() ) {
			return false;
		}

		$threshold = (int) WSI_Settings::get( 'stock_threshold' );
		if ( $threshold > 0 && $product->managing_stock() ) {
			$quantity = $product->get_stock_quantity();
			if ( null !== $quantity ) {
				return $quantity < $threshold;
			}
		}

		return false;
	}
}
