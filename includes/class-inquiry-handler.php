<?php
/**
 * Receives inquiry form submissions (admin-ajax, logged in or not), validates them and stores them.
 *
 * Order of checks: nonce, honeypot, rate limit, field validation, product verification,
 * duplicate check, insert. The success response is only sent after the row was really stored.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * AJAX endpoint for the inquiry form.
 */
class WSI_Inquiry_Handler {

	const ACTION     = 'wsi_submit_inquiry';
	const NONCE      = 'wsi_inquiry_form';
	const RATE_LIMIT = 5;   // Submissions per visitor...
	const RATE_WINDOW = 600; // ...per ten minutes.

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_wsi_form_nonce', array( __CLASS__, 'fresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_wsi_form_nonce', array( __CLASS__, 'fresh_nonce' ) );
	}

	/**
	 * A new nonce, for pages served from a cache whose embedded nonce has expired.
	 */
	public static function fresh_nonce() {
		nocache_headers();
		wp_send_json_success( array( 'nonce' => wp_create_nonce( self::NONCE ) ) );
	}

	/**
	 * @param string $key Field name in $_POST.
	 * @return string Unslashed raw value ('' when missing).
	 */
	private static function post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in submit(); each value is sanitized by the caller.
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';
	}

	/**
	 * @param string $text   Text.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function limit( $text, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
	}

	/**
	 * Clean a phone number typed by a visitor.
	 *
	 * @param string $raw Raw input.
	 * @return string Cleaned number, or '' when it is not a plausible phone number.
	 */
	public static function clean_visitor_phone( $raw ) {
		$raw = trim( sanitize_text_field( $raw ) );
		if ( '' === $raw || ! preg_match( '/^[0-9+\-\s().]{5,20}$/', $raw ) ) {
			return '';
		}
		$digits = preg_replace( '/\D+/', '', $raw );
		return ( strlen( $digits ) >= 5 && strlen( $digits ) <= 15 ) ? $raw : '';
	}

	private static function fail( $message, $status = 422, array $fields = array(), $code = 'invalid' ) {
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
				'fields'  => $fields,
			),
			$status
		);
	}

	/**
	 * Handle one submission.
	 */
	public static function submit() {
		nocache_headers();

		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			self::fail( __( 'Your session expired. Please try again.', 'woocommerce-stock-inquiry' ), 403, array(), 'nonce' );
		}

		// Honeypot: real visitors never see or fill this field. Pretend it worked.
		if ( '' !== self::post( 'wsi_website' ) ) {
			wp_send_json_success( array( 'message' => (string) WSI_Settings::get( 'form_success' ) ) );
		}

		// Rate limit per visitor. Only a hash of the address is kept, and only in a short-lived transient.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rate_key = 'wsi_rl_' . md5( $ip . wp_salt() );
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= self::RATE_LIMIT ) {
			self::fail( __( 'Too many inquiries in a short time. Please try again later.', 'woocommerce-stock-inquiry' ), 429, array(), 'rate' );
		}

		// Fields.
		$name    = trim( self::limit( sanitize_text_field( self::post( 'name' ) ), 120 ) );
		$email   = sanitize_email( self::post( 'email' ) );
		$phone   = self::clean_visitor_phone( self::post( 'phone' ) );
		$raw_tel = trim( self::post( 'phone' ) );
		$message = trim( self::limit( sanitize_textarea_field( self::post( 'message' ) ), 1000 ) );

		$errors = array();
		if ( '' === $name ) {
			$errors['name'] = __( 'Please enter your name.', 'woocommerce-stock-inquiry' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'woocommerce-stock-inquiry' );
		}
		if ( WSI_Settings::get( 'form_show_phone' ) ) {
			if ( '' !== $raw_tel && '' === $phone ) {
				$errors['phone'] = __( 'Please enter a valid phone number.', 'woocommerce-stock-inquiry' );
			} elseif ( '' === $phone && WSI_Settings::get( 'form_require_phone' ) ) {
				$errors['phone'] = __( 'Please enter your phone number.', 'woocommerce-stock-inquiry' );
			}
		} else {
			$phone = '';
		}
		if ( '' === $message ) {
			$errors['message'] = __( 'Please enter your message.', 'woocommerce-stock-inquiry' );
		}
		if ( $errors ) {
			self::fail( __( 'Please correct the highlighted fields.', 'woocommerce-stock-inquiry' ), 422, $errors );
		}

		// Product: when one is given it must be a real, published product (and a variation of it).
		$product_id   = absint( self::post( 'product_id' ) );
		$variation_id = absint( self::post( 'variation_id' ) );
		$product_name = '';
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product || $product->is_type( 'variation' ) || 'publish' !== get_post_status( $product_id ) ) {
				self::fail( __( 'This product is no longer available for inquiries.', 'woocommerce-stock-inquiry' ), 422, array(), 'product' );
			}
			$product_name = html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' );
			if ( $variation_id ) {
				$variation = $product->is_type( 'variable' ) && in_array( $variation_id, array_map( 'intval', $product->get_children() ), true ) ? wc_get_product( $variation_id ) : null;
				if ( $variation instanceof WC_Product ) {
					$product_name = html_entity_decode( wp_strip_all_tags( $variation->get_name() ), ENT_QUOTES, 'UTF-8' );
				} else {
					$variation_id = 0;
				}
			}
		} else {
			$variation_id = 0;
		}
		$product_name = self::limit( $product_name, 255 );

		$origin = sanitize_key( self::post( 'origin' ) );
		$origin = array_key_exists( $origin, WSI_Inquiry_Repository::sources() ) ? $origin : 'other';

		// Duplicate: the same person sending the same message for the same product within ten minutes.
		$hash = WSI_Inquiry_Repository::dedupe_hash( $email, $product_id, $variation_id, $message );
		$dupe = WSI_Inquiry_Repository::find_recent_duplicate( $hash, 10 );
		if ( $dupe ) {
			wp_send_json_success( self::success_data( $dupe, $name, $message, $product_id, $product_name, true ) );
		}

		$id = WSI_Inquiry_Repository::insert(
			array(
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'product_name'   => $product_name,
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'message'        => $message,
				'source'         => $origin,
			)
		);
		if ( ! $id ) {
			self::fail( __( 'Your inquiry could not be saved. Please try again in a moment.', 'woocommerce-stock-inquiry' ), 500, array(), 'save' );
		}

		set_transient( $rate_key, $attempts + 1, self::RATE_WINDOW );

		/**
		 * Fires after an inquiry was stored. Emails are sent from here, and a failure there
		 * never changes the stored inquiry.
		 *
		 * @param int $id Inquiry ID.
		 */
		do_action( 'wsi_inquiry_created', $id );

		wp_send_json_success( self::success_data( $id, $name, $message, $product_id, $product_name, false ) );
	}

	/**
	 * Response for a stored (or already stored) inquiry.
	 *
	 * @param int    $id           Inquiry ID.
	 * @param string $name         Customer name.
	 * @param string $message      Customer message.
	 * @param int    $product_id   Product ID.
	 * @param string $product_name Product name.
	 * @param bool   $duplicate    True when this repeated an earlier submission.
	 * @return array
	 */
	private static function success_data( $id, $name, $message, $product_id, $product_name, $duplicate ) {
		$data = array(
			'id'        => (int) $id,
			'duplicate' => (bool) $duplicate,
			'message'   => (string) WSI_Settings::get( 'form_success' ),
		);

		// Optional WhatsApp hand-off. The inquiry is already stored; opening WhatsApp is the
		// customer's own action and is never counted as a sent message.
		$send = sanitize_key( self::post( 'send' ) );
		if ( 'whatsapp' !== $send && 'store' !== $send ) {
			$send = (string) WSI_Settings::get( 'form_followup' );
		}
		if ( 'whatsapp' === $send ) {
			// The destination configured for this product (category rule or product setting), else the default number.
			$destination = '';
			$wc_product  = $product_id ? wc_get_product( $product_id ) : null;
			if ( $wc_product instanceof WC_Product ) {
				$config      = WSI_Resolver::resolve( $wc_product );
				$destination = (string) $config['destination'];
			}
			$number = WSI_Destinations::number_for( $destination );
			if ( '' !== $number ) {
				$text = $message;
				if ( '' !== $product_name ) {
					$text .= "\n\n" . sprintf(
						/* translators: %s: product name */
						__( 'Product: %s', 'woocommerce-stock-inquiry' ),
						$product_name
					);
					$url = $product_id ? get_permalink( $product_id ) : '';
					if ( $url ) {
						$text .= "\n" . $url;
					}
				}
				$data['whatsapp_url'] = 'https://wa.me/' . $number . '?text=' . rawurlencode( $text );
			}
		}
		return $data;
	}
}
