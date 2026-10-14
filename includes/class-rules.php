<?php
/**
 * Advanced inquiry rules: a different inquiry method (and WhatsApp destination) per category.
 *
 * A rule can also set WHEN the button shows (for example "Always" for a wholesale category that is
 * sold by quotation) and its button text (for example "Request a Quote"). That makes the plugin
 * useful for B2B catalogues, not only for products that are out of stock.
 *
 * Priority, from strongest to weakest:
 *   1. The product's own "Custom settings" (Product data > Stock Inquiry).
 *   2. The category rule that matches the product.
 *   3. The global plugin settings.
 *
 * When a product belongs to several categories with rules, the most specific one wins: a rule on
 * the product's own category beats a rule on its parent category. If two rules are equally
 * specific, the one listed first in the Rules tab wins. A rule whose method is not usable (not
 * registered, or not fully configured) is skipped, so the product safely falls back to the next
 * level. Rules only choose HOW to inquire; stock detection and exclusions are untouched.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category rules.
 */
class WSI_Rules {

	const MAX_RULES = 50;

	/** @var array<int,array|null> Per-request memo keyed by product ID. */
	private static $memo = array();

	/**
	 * Forget memoized results.
	 */
	public static function reset() {
		self::$memo = array();
	}

	/**
	 * Saved rules in priority order (first = strongest when equally specific).
	 *
	 * @return array[] Each: category (int), method (string), destination (string), trigger (string), button_text (string).
	 */
	public static function all() {
		$rules = WSI_Settings::get( 'category_rules', array() );
		return is_array( $rules ) ? array_values( $rules ) : array();
	}

	/**
	 * Clean rules submitted from the settings form. Invalid rows are dropped; a category can only
	 * have one rule (the first one wins).
	 *
	 * @param mixed $input Raw rows.
	 * @return array[]
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$methods = WSI_Plugin::get_methods();
		$out     = array();
		$seen    = array();

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::MAX_RULES ) {
				continue;
			}
			$category = isset( $row['category'] ) ? absint( $row['category'] ) : 0;
			$method   = isset( $row['method'] ) ? sanitize_key( (string) $row['method'] ) : '';
			if ( ! $category || isset( $seen[ $category ] ) || ! isset( $methods[ $method ] ) ) {
				continue;
			}
			if ( ! term_exists( $category, 'product_cat' ) ) {
				continue;
			}
			$destination = ( 'whatsapp' === $method && isset( $row['destination'] ) ) ? sanitize_key( (string) $row['destination'] ) : '';

			$trigger = isset( $row['trigger'] ) ? sanitize_key( (string) $row['trigger'] ) : '';
			if ( ! array_key_exists( $trigger, WSI_Settings::trigger_modes() ) ) {
				$trigger = ''; // Empty = use the global trigger.
			}
			$text = isset( $row['button_text'] ) ? trim( sanitize_text_field( (string) $row['button_text'] ) ) : '';
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 60 ) : substr( $text, 0, 60 );

			$seen[ $category ] = true;
			$out[]             = array(
				'category'    => $category,
				'method'      => $method,
				'destination' => $destination,
				'trigger'     => $trigger,
				'button_text' => $text,
			);
		}
		return $out;
	}

	/**
	 * Whether a method can actually be used right now.
	 *
	 * @param string $method_id Method ID.
	 * @return bool
	 */
	private static function usable( $method_id ) {
		$methods = WSI_Plugin::get_methods();
		return isset( $methods[ $method_id ] ) && $methods[ $method_id ]->is_configured();
	}

	/**
	 * The rule that applies to a product through its categories, or null.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return array|null Rule (category, method, destination) or null.
	 */
	public static function for_product( $product ) {
		$rules = self::all();
		if ( ! $rules ) {
			return null; // No rules: no category lookups at all.
		}

		$id = WSI_Resolver::owner_id( $product );
		if ( array_key_exists( $id, self::$memo ) ) {
			return self::$memo[ $id ];
		}

		$by_category = array();
		foreach ( $rules as $position => $rule ) {
			$by_category[ (int) $rule['category'] ] = array( $position, $rule );
		}

		$direct = $id ? wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) ) : array();
		$best   = null;

		if ( is_array( $direct ) ) {
			foreach ( $direct as $term_id ) {
				$term_id   = (int) $term_id;
				$chain     = array_merge( array( $term_id ), array_map( 'intval', get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) );
				foreach ( $chain as $distance => $candidate ) {
					if ( ! isset( $by_category[ $candidate ] ) || ! self::usable( $by_category[ $candidate ][1]['method'] ) ) {
						continue;
					}
					// Specificity = distance from one of the product's own categories, then list order.
					$score = array( $distance, $by_category[ $candidate ][0] );
					if ( null === $best || $score < $best['score'] ) {
						$best = array(
							'score' => $score,
							'rule'  => $by_category[ $candidate ][1],
						);
					}
					break; // Nearer ancestors were checked first; the rest of this chain is weaker.
				}
			}
		}

		self::$memo[ $id ] = $best ? $best['rule'] : null;
		return self::$memo[ $id ];
	}
}
