<?php
/**
 * Product and category exclusions.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

class WSI_Product_Rules {

	/**
	 * Whether the product is excluded (directly or through a category) from the inquiry button.
	 * Excluded products keep normal WooCommerce behavior.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function is_excluded( $product ) {
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		$excluded_products = array_map( 'intval', (array) WSI_Settings::get( 'excluded_products', array() ) );
		if ( $excluded_products && in_array( (int) $product_id, $excluded_products, true ) ) {
			return true;
		}

		$excluded_categories = array_map( 'intval', (array) WSI_Settings::get( 'excluded_categories', array() ) );
		if ( $excluded_categories ) {
			// Includes parent categories, so excluding a parent category covers its children.
			$product_cats = function_exists( 'wc_get_product_cat_ids' )
				? wc_get_product_cat_ids( $product_id )
				: wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

			if ( is_array( $product_cats ) && array_intersect( array_map( 'intval', $product_cats ), $excluded_categories ) ) {
				return true;
			}
		}

		return false;
	}
}
