<?php
/**
 * Configuration checks. One list of problems, shared by the settings page, the save notices
 * and the Plugins screen notice, so an invalid setup never fails silently.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Configuration checks shared by the settings page and save notices.
 */
class WSI_Validator {

	/**
	 * Problems with the given settings.
	 *
	 * Each issue is array( 'code' => string, 'severity' => 'error'|'warning'|'info',
	 * 'message' => string, 'tab' => string ). "error" means the inquiry button cannot be shown
	 * until it is fixed (WooCommerce keeps its normal buttons meanwhile).
	 *
	 * @param array|null $s Settings, or null for the stored ones.
	 * @return array[]
	 */
	public static function check( $s = null ) {
		$s      = is_array( $s ) ? $s : WSI_Settings::all();
		$issues = array();

		if ( empty( $s['enabled'] ) ) {
			return $issues;
		}

		$issue = static function ( $code, $severity, $message, $tab ) {
			return array(
				'code'     => $code,
				'severity' => $severity,
				'message'  => $message,
				'tab'      => $tab,
			);
		};

		switch ( $s['method'] ) {
			case 'whatsapp':
				$has_extra = false;
				foreach ( (array) ( isset( $s['whatsapp_destinations'] ) ? $s['whatsapp_destinations'] : array() ) as $destination ) {
					if ( is_array( $destination ) && isset( $destination['number'] ) && '' !== WSI_Settings::clean_phone( $destination['number'] ) ) {
						$has_extra = true;
						break;
					}
				}
				if ( '' === WSI_Settings::clean_phone( $s['whatsapp_number'] ) && ! $has_extra ) {
					$issues[] = $issue( 'whatsapp_number', 'error', __( 'WhatsApp number is required.', 'woocommerce-stock-inquiry' ), 'method' );
				}
				break;

			case 'contact':
				$page_id = absint( $s['contact_page'] );
				if ( ! $page_id ) {
					$issues[] = $issue( 'contact_page', 'error', __( 'Please select a contact page.', 'woocommerce-stock-inquiry' ), 'method' );
				} elseif ( 'publish' !== get_post_status( $page_id ) ) {
					$issues[] = $issue( 'contact_page_unpublished', 'error', __( 'The selected contact page is not published.', 'woocommerce-stock-inquiry' ), 'method' );
				}
				break;

			case 'email':
				if ( ! is_email( sanitize_email( (string) $s['email_address'] ) ) ) {
					$issues[] = $issue( 'email_address', 'error', __( 'A valid email address is required.', 'woocommerce-stock-inquiry' ), 'method' );
				}
				break;

			case 'phone':
				if ( '' === WSI_Settings::clean_tel( $s['phone_number'] ) ) {
					$issues[] = $issue( 'phone_number', 'error', __( 'Phone number is required.', 'woocommerce-stock-inquiry' ), 'method' );
				}
				break;

			case 'popup':
				if ( ! WSI_Elementor::is_active() ) {
					$issues[] = $issue( 'popup_elementor', 'error', __( 'Elementor is required for Elementor Popup integration.', 'woocommerce-stock-inquiry' ), 'method' );
				} elseif ( ! WSI_Elementor::is_pro_active() ) {
					$issues[] = $issue( 'popup_pro', 'error', __( 'Elementor Pro is required for Elementor Popup integration. The Popup Builder is part of Elementor Pro.', 'woocommerce-stock-inquiry' ), 'method' );
				} elseif ( ! absint( $s['popup_id'] ) ) {
					$issues[] = $issue( 'popup_select', 'error', __( 'Please select an Elementor Popup.', 'woocommerce-stock-inquiry' ), 'method' );
				} elseif ( ! WSI_Elementor::popup_exists( $s['popup_id'] ) ) {
					$issues[] = $issue( 'popup_missing', 'error', __( 'The selected Elementor Popup was not found or is not published. Please select another one.', 'woocommerce-stock-inquiry' ), 'method' );
				}
				break;
		}

		if ( 'below' === $s['trigger_mode'] && (int) $s['stock_threshold'] < 1 ) {
			$issues[] = $issue( 'threshold_zero', 'warning', __( 'The trigger is "Below stock threshold" but the threshold is 0, so the inquiry button will never appear. Enter a threshold of 1 or more.', 'woocommerce-stock-inquiry' ), 'trigger' );
		}

		if ( 'disabled' === $s['trigger_mode'] ) {
			$issues[] = $issue( 'trigger_disabled', 'info', __( 'The global trigger is "Disabled", so the inquiry button only appears on products that have their own custom settings.', 'woocommerce-stock-inquiry' ), 'trigger' );
		}

		if ( empty( $s['enable_single'] ) && empty( $s['enable_loops'] ) ) {
			$issues[] = $issue( 'no_location', 'warning', __( 'The inquiry button is switched off on both single product pages and product listings.', 'woocommerce-stock-inquiry' ), 'display' );
		}

		return $issues;
	}

	/**
	 * @param array|null $s Settings, or null for the stored ones.
	 * @return bool True when the selected global method has a blocking problem.
	 */
	public static function has_errors( $s = null ) {
		foreach ( self::check( $s ) as $issue ) {
			if ( 'error' === $issue['severity'] ) {
				return true;
			}
		}
		return false;
	}
}
