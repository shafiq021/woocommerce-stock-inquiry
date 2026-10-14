<?php
/**
 * Settings storage, defaults and sanitization.
 *
 * All global settings live in one option (`wsi_settings`). The only per-product data is the
 * optional override meta written by WSI_Product_Meta (`_wsi_*`).
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage, defaults and sanitization.
 */
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

	/**
	 * Use the given (already sanitized) settings for the rest of the request without saving them.
	 * Used by the admin "Test" actions so unsaved form values can be tried safely.
	 * Call WSI_Settings::flush() to go back to the stored values.
	 *
	 * @param array $settings Sanitized settings.
	 */
	public static function use_temporarily( array $settings ) {
		self::$cache = wp_parse_args( $settings, self::defaults() );
	}

	/**
	 * Trigger modes (value => label).
	 *
	 * @return array
	 */
	public static function trigger_modes() {
		return array(
			'out_only'     => __( 'Out of stock only', 'woocommerce-stock-inquiry' ),
			'below'        => __( 'Below stock threshold', 'woocommerce-stock-inquiry' ),
			'out_or_below' => __( 'Out of stock OR below threshold', 'woocommerce-stock-inquiry' ),
			'always'       => __( 'Always show inquiry', 'woocommerce-stock-inquiry' ),
			'disabled'     => __( 'Disabled', 'woocommerce-stock-inquiry' ),
		);
	}

	/**
	 * Product listing locations that can be switched on or off (settings key => label).
	 *
	 * @return array
	 */
	public static function locations() {
		return array(
			'show_loc_shop'        => __( 'Shop page', 'woocommerce-stock-inquiry' ),
			'show_loc_category'    => __( 'Product category / tag archives', 'woocommerce-stock-inquiry' ),
			'show_loc_search'      => __( 'Search results', 'woocommerce-stock-inquiry' ),
			'show_loc_related'     => __( 'Related products', 'woocommerce-stock-inquiry' ),
			'show_loc_upsells'     => __( 'Upsells', 'woocommerce-stock-inquiry' ),
			'show_loc_cross_sells' => __( 'Cross-sells', 'woocommerce-stock-inquiry' ),
			'show_loc_other'       => __( 'Other product grids (Elementor widgets, shortcodes, home page)', 'woocommerce-stock-inquiry' ),
		);
	}

	public static function default_email_message() {
		return __( "Hello,\n\nI am interested in {product_name}.\n\nProduct URL:\n{product_url}\n\nPlease provide availability information.", 'woocommerce-stock-inquiry' );
	}

	public static function default_message() {
		return __( "Hi, I am interested in {product_name}.\n\nPlease let me know when this product is available.", 'woocommerce-stock-inquiry' );
	}

	/**
	 * Font families offered in the admin (value => label). Allow-list for sanitizing.
	 * Web fonts must be loaded by the theme; the front end only sets the font-family stack.
	 *
	 * @return array
	 */
	public static function font_families() {
		$families = array( 'inherit' => __( 'Theme default', 'woocommerce-stock-inquiry' ) );
		foreach ( array( 'Poppins', 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Raleway', 'Nunito', 'Playfair Display', 'Georgia', 'serif', 'sans-serif', 'monospace' ) as $name ) {
			$families[ $name ] = $name;
		}
		return $families;
	}

	/**
	 * CSS font-family value for an allow-listed font. Mirrored in admin/assets/admin.js.
	 *
	 * @param string $name Font name.
	 * @return string
	 */
	public static function font_stack( $name ) {
		$name = (string) $name;
		if ( '' === $name || 'inherit' === $name ) {
			return 'inherit';
		}
		if ( in_array( $name, array( 'serif', 'sans-serif', 'monospace' ), true ) ) {
			return $name;
		}
		$fallback = in_array( $name, array( 'Georgia', 'Playfair Display' ), true ) ? 'serif' : 'sans-serif';
		return "'" . str_replace( "'", '', $name ) . "', " . $fallback;
	}

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'              => 1,
			'enable_loops'         => 1,
			'enable_single'        => 1,
			// Trigger.
			'trigger_mode'         => 'out_or_below',
			'threshold_compare'    => 'lt',
			// Display (product listing locations; `enable_loops` stays the master switch).
			'show_loc_shop'        => 1,
			'show_loc_category'    => 1,
			'show_loc_search'      => 1,
			'show_loc_related'     => 1,
			'show_loc_upsells'     => 1,
			'show_loc_cross_sells' => 1,
			'show_loc_other'       => 1,
			// Inquiry method.
			'method'               => 'contact',
			'button_text_contact'  => __( 'Send Inquiry', 'woocommerce-stock-inquiry' ),
			'button_text_whatsapp' => __( 'Send WhatsApp', 'woocommerce-stock-inquiry' ),
			'contact_page'         => 0,
			'contact_target'       => '_self',
			'whatsapp_number'      => '',
			'whatsapp_message'     => self::default_message(),
			'whatsapp_auto_name'   => 1,
			'whatsapp_auto_url'    => 1,
			'whatsapp_target'      => '_blank',
			'button_text_email'    => __( 'Send Email', 'woocommerce-stock-inquiry' ),
			'email_address'        => '',
			'email_subject'        => __( 'Product Inquiry: {product_name}', 'woocommerce-stock-inquiry' ),
			'email_message'        => self::default_email_message(),
			'button_text_phone'    => __( 'Call Us', 'woocommerce-stock-inquiry' ),
			'phone_number'         => '',
			'button_text_popup'    => __( 'Send Inquiry', 'woocommerce-stock-inquiry' ),
			'popup_provider'       => 'elementor',
			'popup_id'             => 0,
			// Built-in inquiry form (modal) and stored inquiries.
			'button_text_form'     => __( 'Send Inquiry', 'woocommerce-stock-inquiry' ),
			'form_title'           => __( 'Send us an inquiry', 'woocommerce-stock-inquiry' ),
			'form_button_text'     => __( 'Send Inquiry', 'woocommerce-stock-inquiry' ),
			'form_success'         => __( 'Thank you! Your inquiry has been received. We will get back to you soon.', 'woocommerce-stock-inquiry' ),
			'form_show_phone'      => 1,
			'form_require_phone'   => 0,
			'form_followup'        => 'none',
			'notify_admin'         => 1,
			'notify_email'         => '',
			'notify_customer'      => 1,
			'notify_status'        => 0,
			'admin_subject'        => __( '[{site_name}] New inquiry #{inquiry_id}: {product_name}', 'woocommerce-stock-inquiry' ),
			'admin_message'        => __( "A new inquiry was received.\n\nInquiry ID: {inquiry_id}\nProduct: {product_name}\nName: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\n\nMessage:\n{inquiry_message}\n\nView this inquiry:\n{admin_link}", 'woocommerce-stock-inquiry' ),
			'customer_subject'     => __( 'We received your inquiry - {site_name}', 'woocommerce-stock-inquiry' ),
			'customer_message'     => __( "Hello {customer_name},\n\nThank you for your inquiry about {product_name}. We have received it and will get back to you soon.\n\nYour message:\n{inquiry_message}\n\nReference: #{inquiry_id}\n\n{site_name}", 'woocommerce-stock-inquiry' ),
			'status_subject'       => __( 'Update on your inquiry #{inquiry_id} - {site_name}', 'woocommerce-stock-inquiry' ),
			'status_message'       => __( "Hello {customer_name},\n\nThe status of your inquiry about {product_name} is now: {inquiry_status}.\n\n{site_name}", 'woocommerce-stock-inquiry' ),
			'delete_data_on_uninstall' => 0,
			// Button.
			'btn_font_family'      => 'inherit',
			'font_size'            => 14,
			'font_weight'          => 600,
			'pad_top'              => 12,
			'pad_right'            => 20,
			'pad_bottom'           => 12,
			'pad_left'             => 20,
			'radius'               => 4,
			'border_width'         => 1,
			'bg'                   => '#2271b1',
			'color'                => '#ffffff',
			'hover_bg'             => '#135e96',
			'hover_color'          => '#ffffff',
			'border_color'         => '#2271b1',
			'hover_border_color'   => '#135e96',
			'position'             => 'replace',
			'button_align'         => 'left',
			// Heading.
			'enable_heading'       => 0,
			'heading_show_single'  => 1,
			'heading_show_loops'   => 0,
			'heading_text'         => __( 'Product Inquiry', 'woocommerce-stock-inquiry' ),
			'heading_font_family'  => 'inherit',
			'heading_size'         => 18,
			'heading_weight'       => 600,
			'heading_color'        => '#000000',
			// Description.
			'enable_desc'          => 0,
			'desc_show_single'     => 1,
			'desc_show_loops'      => 0,
			'desc_text_whatsapp'   => __( 'Click the button below to ask us about this product on WhatsApp.', 'woocommerce-stock-inquiry' ),
			'desc_text_contact'    => __( 'Click the button below to send us a message about this product.', 'woocommerce-stock-inquiry' ),
			'desc_text_email'      => __( 'Click the button below to email us about this product.', 'woocommerce-stock-inquiry' ),
			'desc_text_phone'      => __( 'Click the button below to call us about this product.', 'woocommerce-stock-inquiry' ),
			'desc_text_popup'      => __( 'Click the button below to send us an inquiry about this product.', 'woocommerce-stock-inquiry' ),
			'desc_text_form'       => __( 'Click the button below to send us an inquiry about this product.', 'woocommerce-stock-inquiry' ),
			'desc_font_family'     => 'inherit',
			'desc_size'            => 14,
			'desc_color'           => '#666666',
			// Product rules.
			'stock_threshold'      => 5,
			'excluded_products'    => array(),
			'excluded_categories'  => array(),
			// Advanced rules: array of array( category, method, destination ).
			'category_rules'       => array(),
			// Extra WhatsApp numbers: array of array( id, label, number, message ). The default number is `whatsapp_number`.
			'whatsapp_destinations' => array(),
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
	 * @param mixed  $fallback Fallback when the key does not exist.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
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
		$checkboxes = array(
			'enabled',
			'enable_loops',
			'enable_single',
			'whatsapp_auto_name',
			'whatsapp_auto_url',
			'form_show_phone',
			'form_require_phone',
			'notify_admin',
			'notify_customer',
			'notify_status',
			'delete_data_on_uninstall',
			'enable_heading',
			'heading_show_single',
			'heading_show_loops',
			'enable_desc',
			'desc_show_single',
			'desc_show_loops',
		);
		$checkboxes = array_merge( $checkboxes, array_keys( self::locations() ) );
		foreach ( $checkboxes as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		// Method: only registered, selectable methods are accepted.
		$method        = isset( $input['method'] ) ? sanitize_key( $input['method'] ) : $defaults['method'];
		$out['method'] = array_key_exists( $method, WSI_Plugin::get_methods() ) ? $method : $defaults['method'];

		// Trigger.
		$out['trigger_mode']      = self::choice( $input, 'trigger_mode', array_keys( self::trigger_modes() ), $defaults );
		$out['threshold_compare'] = self::choice( $input, 'threshold_compare', array( 'lt', 'lte' ), $defaults );

		// Button text.
		$text_c                     = isset( $input['button_text_contact'] ) ? sanitize_text_field( $input['button_text_contact'] ) : ( isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '' );
		$out['button_text_contact'] = '' !== $text_c ? self::limit( $text_c, 60 ) : $defaults['button_text_contact'];

		$text_w                      = isset( $input['button_text_whatsapp'] ) ? sanitize_text_field( $input['button_text_whatsapp'] ) : ( isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '' );
		$out['button_text_whatsapp'] = '' !== $text_w ? self::limit( $text_w, 60 ) : $defaults['button_text_whatsapp'];

		foreach ( array( 'email', 'phone', 'popup', 'form' ) as $method_key ) {
			$field_key         = 'button_text_' . $method_key;
			$text              = isset( $input[ $field_key ] ) ? sanitize_text_field( $input[ $field_key ] ) : '';
			$out[ $field_key ] = '' !== $text ? self::limit( $text, 60 ) : $defaults[ $field_key ];
		}

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

		$message                 = isset( $input['whatsapp_message'] ) ? trim( sanitize_textarea_field( $input['whatsapp_message'] ) ) : '';
		$out['whatsapp_message'] = '' !== $message ? self::limit( $message, 1000 ) : $defaults['whatsapp_message'];
		$out['whatsapp_target']  = self::choice( $input, 'whatsapp_target', array( '_self', '_blank' ), $defaults );
		$out['whatsapp_destinations'] = WSI_Destinations::sanitize( isset( $input['whatsapp_destinations'] ) ? $input['whatsapp_destinations'] : array() );

		// Email.
		$raw_email = isset( $input['email_address'] ) ? trim( (string) $input['email_address'] ) : '';
		$email     = sanitize_email( $raw_email );
		if ( '' !== $raw_email && ! is_email( $email ) ) {
			self::report(
				'wsi_bad_email',
				__( 'The email address was not saved. Please enter a valid email address.', 'woocommerce-stock-inquiry' )
			);
			$email = '';
		}
		$out['email_address'] = $email;

		$subject              = isset( $input['email_subject'] ) ? trim( sanitize_text_field( $input['email_subject'] ) ) : '';
		$out['email_subject'] = '' !== $subject ? self::limit( $subject, 200 ) : $defaults['email_subject'];

		$email_message        = isset( $input['email_message'] ) ? trim( sanitize_textarea_field( $input['email_message'] ) ) : '';
		$out['email_message'] = '' !== $email_message ? self::limit( $email_message, 2000 ) : $defaults['email_message'];

		// Phone.
		$raw_tel = isset( $input['phone_number'] ) ? trim( (string) $input['phone_number'] ) : '';
		$tel     = self::clean_tel( $raw_tel );
		if ( '' !== $raw_tel && '' === $tel ) {
			self::report(
				'wsi_bad_phone',
				__( 'The phone number was not saved. Use 5 to 15 digits; start with + and the country code for an international number.', 'woocommerce-stock-inquiry' )
			);
		}
		$out['phone_number'] = $tel;

		// Popup.
		$out['popup_provider'] = self::choice( $input, 'popup_provider', array( 'elementor' ), $defaults );
		$out['popup_id']       = isset( $input['popup_id'] ) ? absint( $input['popup_id'] ) : 0;

		// Inquiry form and notifications.
		$form_title            = isset( $input['form_title'] ) ? sanitize_text_field( $input['form_title'] ) : '';
		$out['form_title']     = '' !== $form_title ? self::limit( $form_title, 100 ) : $defaults['form_title'];
		$form_button           = isset( $input['form_button_text'] ) ? sanitize_text_field( $input['form_button_text'] ) : '';
		$out['form_button_text'] = '' !== $form_button ? self::limit( $form_button, 60 ) : $defaults['form_button_text'];
		$form_success          = isset( $input['form_success'] ) ? trim( sanitize_textarea_field( $input['form_success'] ) ) : '';
		$out['form_success']   = '' !== $form_success ? self::limit( $form_success, 300 ) : $defaults['form_success'];
		$out['form_followup']  = self::choice( $input, 'form_followup', array( 'none', 'whatsapp' ), $defaults );

		$raw_notify = isset( $input['notify_email'] ) ? trim( (string) $input['notify_email'] ) : '';
		$notify     = sanitize_email( $raw_notify );
		if ( '' !== $raw_notify && ! is_email( $notify ) ) {
			self::report(
				'wsi_bad_notify_email',
				__( 'The notification email address was not saved. Please enter a valid email address, or leave it empty to use the site admin email.', 'woocommerce-stock-inquiry' )
			);
			$notify = '';
		}
		$out['notify_email'] = $notify;

		// Email templates: subjects are single-line text, messages are plain text. Empty falls back to the default.
		foreach ( array( 'admin', 'customer', 'status' ) as $mail_key ) {
			$subject = isset( $input[ $mail_key . '_subject' ] ) ? sanitize_text_field( $input[ $mail_key . '_subject' ] ) : '';
			$body    = isset( $input[ $mail_key . '_message' ] ) ? trim( sanitize_textarea_field( $input[ $mail_key . '_message' ] ) ) : '';
			$out[ $mail_key . '_subject' ] = '' !== $subject ? self::limit( $subject, 200 ) : $defaults[ $mail_key . '_subject' ];
			$out[ $mail_key . '_message' ] = '' !== $body ? self::limit( $body, 3000 ) : $defaults[ $mail_key . '_message' ];
		}

		// Heading.
		$htext                      = isset( $input['heading_text'] ) ? sanitize_text_field( $input['heading_text'] ) : '';
		$out['heading_text']        = '' !== $htext ? self::limit( $htext, 100 ) : $defaults['heading_text'];
		$out['heading_size']        = self::int( $input, 'heading_size', 8, 48, $defaults );
		$out['heading_weight']      = self::weight( $input, 'heading_weight', $defaults );
		$out['heading_font_family'] = self::font( $input, 'heading_font_family', $defaults );

		// Description.
		$dtext_wa                  = isset( $input['desc_text_whatsapp'] ) ? sanitize_textarea_field( $input['desc_text_whatsapp'] ) : '';
		$out['desc_text_whatsapp'] = '' !== $dtext_wa ? self::limit( $dtext_wa, 300 ) : $defaults['desc_text_whatsapp'];

		$dtext_contact            = isset( $input['desc_text_contact'] ) ? sanitize_textarea_field( $input['desc_text_contact'] ) : '';
		$out['desc_text_contact'] = '' !== $dtext_contact ? self::limit( $dtext_contact, 300 ) : $defaults['desc_text_contact'];

		foreach ( array( 'email', 'phone', 'popup', 'form' ) as $method_key ) {
			$field_key         = 'desc_text_' . $method_key;
			$text              = isset( $input[ $field_key ] ) ? sanitize_textarea_field( $input[ $field_key ] ) : '';
			$out[ $field_key ] = '' !== $text ? self::limit( $text, 300 ) : $defaults[ $field_key ];
		}

		$out['desc_size']        = self::int( $input, 'desc_size', 8, 48, $defaults );
		$out['desc_font_family'] = self::font( $input, 'desc_font_family', $defaults );

		// Button.
		$out['btn_font_family'] = self::font( $input, 'btn_font_family', $defaults );
		$out['button_align']    = self::choice( $input, 'button_align', array( 'left', 'center', 'right', 'block' ), $defaults );
		$out['font_size']       = self::int( $input, 'font_size', 8, 48, $defaults );
		$out['pad_top']         = self::int( $input, 'pad_top', 0, 80, $defaults );
		$out['pad_right']       = self::int( $input, 'pad_right', 0, 80, $defaults );
		$out['pad_bottom']      = self::int( $input, 'pad_bottom', 0, 80, $defaults );
		$out['pad_left']        = self::int( $input, 'pad_left', 0, 80, $defaults );
		$out['radius']          = self::int( $input, 'radius', 0, 100, $defaults );
		$out['border_width']    = self::int( $input, 'border_width', 0, 20, $defaults );
		$out['font_weight']     = self::weight( $input, 'font_weight', $defaults );

		foreach ( array( 'bg', 'color', 'hover_bg', 'hover_color', 'border_color', 'hover_border_color', 'heading_color', 'desc_color' ) as $key ) {
			$hex         = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $hex ? $hex : $defaults[ $key ];
		}

		$out['position'] = self::choice( $input, 'position', array( 'replace', 'before', 'after' ), $defaults );

		// Product rules.
		$out['stock_threshold']     = self::int( $input, 'stock_threshold', 0, 100000, $defaults );
		$out['excluded_products']   = self::ids( $input, 'excluded_products' );
		$out['excluded_categories'] = self::ids( $input, 'excluded_categories' );

		// Advanced rules (category => method).
		$out['category_rules'] = WSI_Rules::sanitize( isset( $input['category_rules'] ) ? $input['category_rules'] : array() );

		// Tell the administrator about anything that would stop the selected method from working.
		// Settings are still saved so nothing typed is lost; the button simply stays off until fixed.
		if ( class_exists( 'WSI_Validator' ) ) {
			foreach ( WSI_Validator::check( wp_parse_args( $out, $defaults ) ) as $issue ) {
				if ( 'info' !== $issue['severity'] ) {
					self::report( 'wsi_' . $issue['code'], $issue['message'], 'error' === $issue['severity'] ? 'error' : 'warning' );
				}
			}
		}

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

	/**
	 * Normalize a number for a tel: link. International numbers keep their leading "+";
	 * a leading "00" becomes "+". Local numbers (no country code) are allowed.
	 *
	 * @param string $raw Phone number as typed.
	 * @return string Digits with an optional leading +, or '' when invalid (5-15 digits).
	 */
	public static function clean_tel( $raw ) {
		$raw  = trim( (string) $raw );
		$plus = '';
		if ( 0 === strpos( $raw, '+' ) ) {
			$plus = '+';
		} elseif ( 0 === strpos( $raw, '00' ) ) {
			$plus = '+';
			$raw  = substr( $raw, 2 );
		}
		$digits = preg_replace( '/\D+/', '', $raw );
		$length = strlen( $digits );
		if ( $length < 5 || $length > 15 ) {
			return '';
		}
		return $plus . $digits;
	}

	private static function limit( $text, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
	}

	private static function int( $input, $key, $min, $max, $defaults ) {
		$value = ( isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) ? (int) $input[ $key ] : (int) $defaults[ $key ];
		return max( $min, min( $max, $value ) );
	}

	private static function choice( $input, $key, $allowed, $defaults ) {
		$value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		return in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
	}

	private static function weight( $input, $key, $defaults ) {
		$weight = isset( $input[ $key ] ) ? (int) $input[ $key ] : (int) $defaults[ $key ];
		return in_array( $weight, array( 400, 500, 600, 700, 800 ), true ) ? $weight : (int) $defaults[ $key ];
	}

	private static function font( $input, $key, $defaults ) {
		$value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		return array_key_exists( $value, self::font_families() ) ? $value : $defaults[ $key ];
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
	public static function report( $code, $message, $type = 'error' ) {
		if ( isset( self::$reported[ $code ] ) ) {
			return;
		}
		self::$reported[ $code ] = true;
		add_settings_error( self::OPTION, $code, $message, $type );
	}
}
