<?php
/**
 * Resolves the effective inquiry configuration for a product: the global settings, replaced by the
 * product's own override when the product is set to "Custom settings".
 *
 * Overrides are stored as post meta on the (parent) product. This is the only place that reads
 * them, so stock logic, button rendering and every inquiry method see the same answer.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the effective configuration of a product (global settings plus product override).
 */
class WSI_Resolver {

	const META_CUSTOM    = '_wsi_custom';
	const META_TRIGGER   = '_wsi_trigger';
	const META_THRESHOLD = '_wsi_threshold';
	const META_METHOD    = '_wsi_method';
	const META_TEXT      = '_wsi_button_text';
	const META_MESSAGE   = '_wsi_message';
	const META_DESTINATION = '_wsi_wa_destination';

	/** @var array<int,array> Per-request memo, keyed by parent product ID. */
	private static $memo = array();

	/**
	 * All meta keys the plugin writes on products (used by uninstall and the product panel).
	 *
	 * @return string[]
	 */
	public static function meta_keys() {
		return array( self::META_CUSTOM, self::META_TRIGGER, self::META_THRESHOLD, self::META_METHOD, self::META_TEXT, self::META_MESSAGE, self::META_DESTINATION );
	}

	/**
	 * Forget memoized results (settings or product meta changed within the request).
	 */
	public static function reset() {
		self::$memo = array();
		WSI_Rules::reset();
	}

	/**
	 * ID of the product that owns the override (the parent for a variation).
	 *
	 * @param WC_Product $product Product.
	 * @return int
	 */
	public static function owner_id( $product ) {
		return $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product->get_id();
	}

	/**
	 * Effective configuration.
	 *
	 * Returned keys:
	 *  - has_override (bool)  The product uses "Custom settings".
	 *  - trigger      (string) One of WSI_Settings::trigger_modes().
	 *  - threshold    (int)
	 *  - compare      (string) 'lt' or 'lte'.
	 *  - method       (string) Inquiry method ID.
	 *  - button_text  (string) '' = use the method's default button text.
	 *  - message      (string) '' = use the method's default message template.
	 *  - source       (string) Where the method came from: 'product', 'category' or 'global'.
	 *  - destination  (string) WhatsApp destination ID ('' = the default number).
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function resolve( $product ) {
		$id = self::owner_id( $product );
		if ( isset( self::$memo[ $id ] ) ) {
			return self::$memo[ $id ];
		}

		$config = array(
			'has_override' => false,
			'trigger'      => (string) WSI_Settings::get( 'trigger_mode' ),
			'threshold'    => (int) WSI_Settings::get( 'stock_threshold' ),
			'compare'      => (string) WSI_Settings::get( 'threshold_compare' ),
			'method'       => (string) WSI_Settings::get( 'method' ),
			'button_text'  => '',
			'message'      => '',
			'source'       => 'global',
			'destination'  => '',
		);

		$product_method  = false;
		$product_trigger = false;
		$product_text    = false;

		if ( $id && 'custom' === get_post_meta( $id, self::META_CUSTOM, true ) ) {
			$config['has_override'] = true;

			$trigger = (string) get_post_meta( $id, self::META_TRIGGER, true );
			if ( array_key_exists( $trigger, WSI_Settings::trigger_modes() ) ) {
				$config['trigger'] = $trigger;
				$product_trigger   = true;
			}

			$threshold = get_post_meta( $id, self::META_THRESHOLD, true );
			if ( '' !== (string) $threshold && is_numeric( $threshold ) ) {
				$config['threshold'] = max( 0, min( 100000, (int) $threshold ) );
			}

			$method = sanitize_key( (string) get_post_meta( $id, self::META_METHOD, true ) );
			if ( '' !== $method && array_key_exists( $method, WSI_Plugin::get_methods() ) ) {
				$config['method']  = $method;
				$config['source']  = 'product';
				$product_method    = true;
			}

			$destination = sanitize_key( (string) get_post_meta( $id, self::META_DESTINATION, true ) );
			if ( '' !== $destination ) {
				$config['destination'] = $destination;
			}

			$config['button_text'] = trim( sanitize_text_field( (string) get_post_meta( $id, self::META_TEXT, true ) ) );
			$product_text          = '' !== $config['button_text'];
			$config['message']     = trim( sanitize_textarea_field( (string) get_post_meta( $id, self::META_MESSAGE, true ) ) );
		}

		// Priority: product's own setting > category rule > global settings (each setting separately).
		$rule = WSI_Rules::for_product( $product );
		if ( $rule ) {
			if ( ! $product_method ) {
				$config['method'] = $rule['method'];
				$config['source'] = 'category';
				if ( '' === $config['destination'] && '' !== (string) $rule['destination'] ) {
					$config['destination'] = $rule['destination'];
				}
			}
			if ( ! $product_trigger && ! empty( $rule['trigger'] ) ) {
				$config['trigger'] = $rule['trigger'];
			}
			if ( ! $product_text && ! empty( $rule['button_text'] ) ) {
				$config['button_text'] = $rule['button_text'];
			}
		}

		/**
		 * Filter the effective inquiry configuration for a product.
		 *
		 * @param array      $config  Configuration.
		 * @param WC_Product $product Product.
		 */
		self::$memo[ $id ] = (array) apply_filters( 'wsi_product_config', $config, $product );
		return self::$memo[ $id ];
	}
}
