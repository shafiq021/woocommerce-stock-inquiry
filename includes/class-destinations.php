<?php
/**
 * WhatsApp destinations: extra WhatsApp numbers (for example one per department) next to the
 * default number. Numbers are only ever used to build a wa.me click-to-chat link; nothing is sent
 * automatically and no WhatsApp Business API is used.
 *
 * The default number is the existing `whatsapp_number` setting, so stores that only use one number
 * keep working with no change. A destination is { id, label, number, message }; `message` is an
 * optional template that replaces the global WhatsApp message for that destination.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp destinations.
 */
class WSI_Destinations {

	const MAX = 20;

	/**
	 * Saved destinations.
	 *
	 * @return array[] Each: id, label, number (digits), message.
	 */
	public static function all() {
		$list = WSI_Settings::get( 'whatsapp_destinations', array() );
		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * One destination by ID, or null.
	 *
	 * @param string $id Destination ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		$id = sanitize_key( (string) $id );
		if ( '' === $id ) {
			return null;
		}
		foreach ( self::all() as $destination ) {
			if ( $destination['id'] === $id ) {
				return $destination;
			}
		}
		return null;
	}

	/**
	 * Choices for a dropdown (id => label). '' is the default number.
	 *
	 * @param string $default_label Label for the empty choice.
	 * @return array
	 */
	public static function choices( $default_label = '' ) {
		$choices = array( '' => '' !== $default_label ? $default_label : __( 'Default WhatsApp number', 'woocommerce-stock-inquiry' ) );
		foreach ( self::all() as $destination ) {
			$choices[ $destination['id'] ] = $destination['label'];
		}
		return $choices;
	}

	/**
	 * Whether any WhatsApp number (default or extra) is configured.
	 *
	 * @return bool
	 */
	public static function has_number() {
		return '' !== self::number_for( '' );
	}

	/**
	 * The number to use for a destination. A missing, deleted or invalid destination falls back to
	 * the default number; if there is no default number, to the first valid extra number.
	 *
	 * @param string $id Destination ID ('' = default).
	 * @return string Digits for wa.me, or '' when nothing is configured.
	 */
	public static function number_for( $id ) {
		$destination = self::find( $id );
		if ( $destination && '' !== WSI_Settings::clean_phone( $destination['number'] ) ) {
			return WSI_Settings::clean_phone( $destination['number'] );
		}
		$default = WSI_Settings::clean_phone( WSI_Settings::get( 'whatsapp_number' ) );
		if ( '' !== $default ) {
			return $default;
		}
		foreach ( self::all() as $candidate ) {
			$number = WSI_Settings::clean_phone( $candidate['number'] );
			if ( '' !== $number ) {
				return $number;
			}
		}
		return '';
	}

	/**
	 * Message template of a destination ('' when it has none or the destination is unknown).
	 *
	 * @param string $id Destination ID.
	 * @return string
	 */
	public static function message_for( $id ) {
		$destination = self::find( $id );
		return $destination ? (string) $destination['message'] : '';
	}

	/**
	 * Clean the rows submitted from the settings form. Invalid numbers are reported and dropped, IDs
	 * are kept for existing rows and generated for new ones.
	 *
	 * @param mixed $input Raw rows.
	 * @return array[]
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$out  = array();
		$used = array();

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::MAX ) {
				continue;
			}
			$label  = isset( $row['label'] ) ? trim( sanitize_text_field( (string) $row['label'] ) ) : '';
			$raw    = isset( $row['number'] ) ? trim( (string) $row['number'] ) : '';
			if ( '' === $label && '' === $raw ) {
				continue; // An empty row.
			}
			$number = WSI_Settings::clean_phone( $raw );
			if ( '' === $number ) {
				WSI_Settings::report(
					'wsi_bad_destination_number',
					__( 'A WhatsApp destination was not saved because its number is not valid. Use international format with the country code, for example +1 555 123 4567.', 'woocommerce-stock-inquiry' )
				);
				continue;
			}
			if ( '' === $label ) {
				$label = '+' . $number;
			}
			$label = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 60 ) : substr( $label, 0, 60 );

			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			if ( '' === $id || isset( $used[ $id ] ) ) {
				$base = sanitize_title( $label );
				$base = '' !== $base ? $base : 'destination';
				$id   = $base;
				$n    = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n;
					++$n;
				}
			}
			$used[ $id ] = true;

			$message = isset( $row['message'] ) ? trim( sanitize_textarea_field( (string) $row['message'] ) ) : '';
			$message = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 1000 ) : substr( $message, 0, 1000 );

			$out[] = array(
				'id'      => $id,
				'label'   => $label,
				'number'  => $number,
				'message' => $message,
			);
		}
		return $out;
	}
}
