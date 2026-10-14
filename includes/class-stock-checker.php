<?php
/**
 * Decides, per product and per request, whether the inquiry button replaces Add to Cart.
 *
 * WooCommerce stays the source of truth: the decision is computed from the live product
 * object each time it renders. Nothing is stored, synced or scheduled, so restocking a
 * product brings Add to Cart back immediately.
 *
 * Variable products are judged variation by variation:
 *  - 'none'  no visible variation needs the inquiry  -> normal WooCommerce form.
 *  - 'all'   every visible variation needs it         -> the form is replaced by the button.
 *  - 'mixed' some do, some do not                     -> normal form; the front-end script shows
 *                                                        the button when a variation that needs it
 *                                                        is selected.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a product needs the inquiry button.
 */
class WSI_Stock_Checker {

	/** @var array<int,bool> Per-request memo so a product is evaluated once per page. */
	private static $memo = array();

	/** @var array<int,string> Per-request memo for variable product states. */
	private static $states = array();

	/**
	 * Forget memoized results.
	 */
	public static function reset() {
		self::$memo   = array();
		self::$states = array();
		WSI_Resolver::reset();
	}

	/**
	 * @param WC_Product $product Simple product, variable product (all variations) or variation.
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
	 * State of a variable product: 'none', 'all' or 'mixed'. Other product types are 'none'.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function variable_state( $product ) {
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return 'none';
		}
		$id = $product->get_id();
		if ( ! isset( self::$states[ $id ] ) ) {
			self::$states[ $id ] = self::compute_variable_state( $product );
		}
		return self::$states[ $id ];
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array|null Resolved configuration, or null when the plugin must stay out of the way.
	 */
	private static function config_for( $product ) {
		if ( ! WSI_Settings::get( 'enabled' ) ) {
			return null;
		}

		$types = (array) apply_filters( 'wsi_supported_product_types', array( 'simple', 'variable' ) );
		$type  = $product->is_type( 'variation' ) ? 'variable' : null;
		if ( ! $product->is_type( $types ) && ( null === $type || ! in_array( $type, $types, true ) ) ) {
			return null;
		}

		$config = WSI_Resolver::resolve( $product );

		// Exclusions apply unless the product has its own custom settings, which are more specific.
		if ( ! $config['has_override'] && WSI_Product_Rules::is_excluded( $product ) ) {
			return null;
		}

		return $config;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private static function evaluate( $product ) {
		$config = self::config_for( $product );
		if ( null === $config ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			return 'all' === self::variable_state( $product );
		}

		return self::unit_matches( $product, $config );
	}

	/**
	 * @param WC_Product $product Variable product.
	 * @return string
	 */
	private static function compute_variable_state( $product ) {
		$config = self::config_for( $product );
		if ( null === $config || 'disabled' === $config['trigger'] ) {
			return 'none';
		}
		if ( 'always' === $config['trigger'] ) {
			return 'all';
		}

		// WooCommerce marks the parent out of stock when every variation is, which settles the
		// "out of stock" modes without loading each variation.
		if ( ! $product->is_in_stock() && in_array( $config['trigger'], array( 'out_only', 'out_or_below' ), true ) ) {
			return 'all';
		}

		$total = 0;
		$match = 0;
		foreach ( (array) $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation instanceof WC_Product ) {
				continue;
			}
			if ( method_exists( $variation, 'variation_is_visible' ) && ! $variation->variation_is_visible() ) {
				continue;
			}
			++$total;
			if ( self::unit_matches( $variation, $config ) ) {
				++$match;
			}
		}

		if ( 0 === $total || 0 === $match ) {
			return 'none';
		}
		return ( $match === $total ) ? 'all' : 'mixed';
	}

	/**
	 * Stock rules for one purchasable unit (simple product or variation).
	 *
	 *  1. Trigger "disabled"                                  -> normal WooCommerce.
	 *  2. Trigger "always"                                    -> inquiry.
	 *  3. WooCommerce says out of stock (modes with "out")    -> inquiry.
	 *  4. On backorder / backorders allowed                   -> normal WooCommerce.
	 *  5. Stock managed and quantity compared to the threshold (modes with "below"):
	 *     "lt"  quantity <  threshold (default, same as version 1)
	 *     "lte" quantity <= threshold
	 *     Threshold 0 never matches. Stock that is not managed has no quantity, so only rule 3 applies.
	 *
	 * @param WC_Product $product Simple product or variation.
	 * @param array      $config  Resolved configuration.
	 * @return bool
	 */
	public static function unit_matches( $product, array $config ) {
		$trigger = $config['trigger'];

		if ( 'disabled' === $trigger ) {
			return false;
		}
		if ( 'always' === $trigger ) {
			return true;
		}

		$use_out   = in_array( $trigger, array( 'out_only', 'out_or_below' ), true );
		$use_below = in_array( $trigger, array( 'below', 'out_or_below' ), true );

		if ( $use_out && ! $product->is_in_stock() ) {
			return true;
		}

		if ( ! $use_below ) {
			return false;
		}

		if ( 'onbackorder' === $product->get_stock_status() || $product->backorders_allowed() ) {
			return false;
		}

		$threshold = (int) $config['threshold'];
		if ( $threshold > 0 && $product->managing_stock() ) {
			$quantity = $product->get_stock_quantity();
			if ( null !== $quantity ) {
				return ( 'lte' === $config['compare'] ) ? (int) $quantity <= $threshold : (int) $quantity < $threshold;
			}
		}

		return false;
	}
}
