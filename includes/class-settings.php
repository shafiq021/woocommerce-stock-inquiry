<?php
/**
 * Settings storage, defaults and sanitization.
 *
 * All settings live in one option (`wsi_settings`). Nothing is stored per product.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

class WSI_Settings {

	const OPTION = 'wsi_settings';
	const GROUP  = 'wsi_settings_group';

	/** @var array|null */
	private static $cache = null;

	/** @var array */
	private static $reported = array();

	/**
	 * Keep the in-request cache fresh when the option changes.
	 */
	public static function init() {
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'delete_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	public static function flush() {
		self::$cache = null;
	}

	public static function default_message() {
		return __( "Hi, I am interested in {product_name}.\n\nPlease let me know when this product is available.", 'woocommerce-stock-inquiry' );
	}

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'             => 1,
			'enable_loops'        => 1,
			'enable_single'       => 1,
			// Inquiry method.
			'method'              => 'contact',
			'button_text_contact' => __( 'Send Inquiry', 'woocommerce-stock-inquiry' ),
			'button_text_whatsapp'=> __( 'Send WhatsApp', 'woocommerce-stock-inquiry' ),
			'contact_page'        => 0,
			'contact_target'      => '_self',
			'whatsapp_number'     => '',
			'whatsapp_message'    => self::default_message(),
			'whatsapp_auto_name'  => 1,
			'whatsapp_auto_url'   => 1,
			'whatsapp_target'     => '_blank',
			// Button design.
			'font_size'           => 14,
			'font_weight'         => 600,
			'pad_top'             => 12,
			'pad_right'           => 20,
			'pad_bottom'          => 12,
			'pad_left'            => 20,
			'radius'              => 4,
			'border_width'        => 1,
			'bg'                  => '#2271b1',
			'color'               => '#ffffff',
			'hover_bg'            => '#135e96',
			'hover_color'         => '#ffffff',
			'border_color'        => '#2271b1',
			'hover_border_color'  => '#135e96',
			'position'            => 'replace',
			// Extra display elements.
			'enable_heading'      => 0,
			'heading_text'        => __( 'Product Inquiry', 'woocommerce-stock-inquiry' ),
			'heading_color'       => '#000000',
			'heading_size'        => 18,
			'enable_desc'         => 0,
			'desc_text_whatsapp'  => __( 'Click the button below to ask us about this product on WhatsApp.', 'woocommerce-stock-inquiry' ),
			'desc_text_contact'   => __( 'Click the button below to send us a message about this product.', 'woocommerce-stock-inquiry' ),
			'desc_color'          => '#666666',
			'desc_size'           => 14,
			'button_align'        => 'left',
			// Product rules.
			'stock_threshold'     => 5,
			'excluded_products'   => array(),
			'excluded_categories' => array(),
		);
	}

	/**
	 * All settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key does not exist.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Find a published page that looks like a contact page, for first-run convenience.
	 *
	 * @return int Page ID or 0.
	 */
	public static function detect_contact_page() {
		foreach ( array( 'contact-us', 'contact', 'contactus', 'get-in-touch' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				return (int) $page->ID;
			}
		}
		return 0;
	}

	/**
	 * Settings API sanitize callback. Every value is validated against an allow-list or clamped.
	 *
	 * @param mixed $input Raw (unslashed) form input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = array();

		// Checkboxes: an unchecked box is simply absent from the submission.
		foreach ( array( 'enabled', 'enable_loops', 'enable_single', 'whatsapp_auto_name', 'whatsapp_auto_url', 'enable_heading', 'enable_desc' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		// Method: only registered, selectable methods are accepted ("popup" is not one in V1).
		$method        = isset( $input['method'] ) ? sanitize_key( $input['method'] ) : $defaults['method'];
		$out['method'] = array_key_exists( $method, WSI_Plugin::get_methods() ) ? $method : $defaults['method'];

		// Text.
		$text_c = isset( $input['button_text_contact'] ) ? sanitize_text_field( $input['button_text_contact'] ) : (isset($input['button_text']) ? sanitize_text_field($input['button_text']) : '');
		$out['button_text_contact'] = '' !== $text_c ? self::limit( $text_c, 60 ) : $defaults['button_text_contact'];

		$text_w = isset( $input['button_text_whatsapp'] ) ? sanitize_text_field( $input['button_text_whatsapp'] ) : (isset($input['button_text']) ? sanitize_text_field($input['button_text']) : '');
		$out['button_text_whatsapp'] = '' !== $text_w ? self::limit( $text_w, 60 ) : $defaults['button_text_whatsapp'];

		// Contact page.
		$page_id = isset( $input['contact_page'] ) ? absint( $input['contact_page'] ) : 0;
		if ( $page_id && 'page' !== get_post_type( $page_id ) ) {
			$page_id = 0;
		}
		$out['contact_page']   = $page_id;
		$out['contact_target'] = self::choice( $input, 'contact_target', array( '_self', '_blank' ), $defaults );

		// WhatsApp.
		$raw    = isset( $input['whatsapp_number'] ) ? trim( (string) $input['whatsapp_number'] ) : '';
		$number = self::clean_phone( $raw );
		if ( '' !== $raw && '' === $number ) {
			self::report(
				'wsi_bad_number',
				__( 'The WhatsApp number was not saved. Enter it in international format with the country code, for example +1 555 123 4567.', 'woocommerce-stock-inquiry' )
			);
		}
		$out['whatsapp_number'] = $number;

		$message = isset( $input['whatsapp_message'] ) ? trim( sanitize_textarea_field( $input['whatsapp_message'] ) ) : '';
		$out['whatsapp_message'] = '' !== $message ? self::limit( $message, 1000 ) : $defaults['whatsapp_message'];
		$out['whatsapp_target']  = self::choice( $input, 'whatsapp_target', array( '_self', '_blank' ), $defaults );

		// Extra display elements.
		$htext = isset( $input['heading_text'] ) ? sanitize_text_field( $input['heading_text'] ) : '';
		$out['heading_text'] = '' !== $htext ? self::limit( $htext, 100 ) : $defaults['heading_text'];
		
		$dtext_wa = isset( $input['desc_text_whatsapp'] ) ? sanitize_textarea_field( $input['desc_text_whatsapp'] ) : '';
		$out['desc_text_whatsapp'] = '' !== $dtext_wa ? self::limit( $dtext_wa, 300 ) : $defaults['desc_text_whatsapp'];

		$dtext_contact = isset( $input['desc_text_contact'] ) ? sanitize_textarea_field( $input['desc_text_contact'] ) : '';
		$out['desc_text_contact'] = '' !== $dtext_contact ? self::limit( $dtext_contact, 300 ) : $defaults['desc_text_contact'];

		$out['heading_size'] = self::int( $input, 'heading_size', 8, 48, $defaults );
		$out['desc_size']    = self::int( $input, 'desc_size', 8, 48, $defaults );
		
		$out['button_align'] = self::choice( $input, 'button_align', array( 'left', 'center', 'right', 'block' ), $defaults );

		// Button design.
		$out['font_size']    = self::int( $input, 'font_size', 8, 48, $defaults );
		$out['pad_top']      = self::int( $input, 'pad_top', 0, 80, $defaults );
		$out['pad_right']    = self::int( $input, 'pad_right', 0, 80, $defaults );
		$out['pad_bottom']   = self::int( $input, 'pad_bottom', 0, 80, $defaults );
		$out['pad_left']     = self::int( $input, 'pad_left', 0, 80, $defaults );
		$out['radius']       = self::int( $input, 'radius', 0, 100, $defaults );
		$out['border_width'] = self::int( $input, 'border_width', 0, 20, $defaults );

		$weight             = isset( $input['font_weight'] ) ? (int) $input['font_weight'] : $defaults['font_weight'];
		$out['font_weight'] = in_array( $weight, array( 400, 500, 600, 700, 800 ), true ) ? $weight : $defaults['font_weight'];

		foreach ( array( 'bg', 'color', 'hover_bg', 'hover_color', 'border_color', 'hover_border_color', 'heading_color', 'desc_color' ) as $key ) {
			$hex         = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $hex ? $hex : $defaults[ $key ];
		}

		$out['position'] = self::choice( $input, 'position', array( 'replace', 'before', 'after' ), $defaults );

		// Product rules.
		$out['stock_threshold']     = self::int( $input, 'stock_threshold', 0, 100000, $defaults );
		$out['excluded_products']   = self::ids( $input, 'excluded_products' );
		$out['excluded_categories'] = self::ids( $input, 'excluded_categories' );

		return $out;
	}

	/**
	 * Reduce a phone number to digits for wa.me. Requires an international number:
	 * 8-15 digits, no leading zero (a leading single 0 means a local number without country code).
	 *
	 * @param string $raw Phone number as typed.
	 * @return string Digits only, or '' when invalid.
	 */
	public static function clean_phone( $raw ) {
		$raw = trim( (string) $raw );
		if ( 0 === strpos( $raw, '00' ) ) {
			$raw = substr( $raw, 2 );
		}
		$digits = preg_replace( '/\D+/', '', $raw );
		$length = strlen( $digits );
		if ( $length < 8 || $length > 15 || '0' === $digits[0] ) {
			return '';
		}
		return $digits;
	}

	private static function limit( $string, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $string, 0, $length ) : substr( $string, 0, $length );
	}

	private static function int( $input, $key, $min, $max, $defaults ) {
		$value = ( isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) ? (int) $input[ $key ] : (int) $defaults[ $key ];
		return max( $min, min( $max, $value ) );
	}

	private static function choice( $input, $key, $allowed, $defaults ) {
		$value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		return in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
	}

	private static function ids( $input, $key ) {
		if ( empty( $input[ $key ] ) || ! is_array( $input[ $key ] ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $input[ $key ] ) ) ) );
	}

	/**
	 * add_settings_error once per request (the Settings API can run the callback twice on first save).
	 */
	private static function report( $code, $message, $type = 'error' ) {
		if ( isset( self::$reported[ $code ] ) ) {
			return;
		}
		self::$reported[ $code ] = true;
		add_settings_error( self::OPTION, $code, $message, $type );
	}
}
