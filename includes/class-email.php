<?php
/**
 * Email inquiry method.
 *
 * Builds a mailto: link that opens the customer's own email app with the subject and message
 * filled in. The plugin never sends mail itself, so it never claims that a message was delivered.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email (mailto:) inquiry method.
 */
class WSI_Email implements WSI_Inquiry_Method {

	public function get_id() {
		return 'email';
	}

	public function get_label() {
		return __( 'Email', 'woocommerce-stock-inquiry' );
	}

	public function is_configured() {
		return '' !== $this->get_address();
	}

	/**
	 * @return string Valid recipient address, or ''.
	 */
	private function get_address() {
		$address = sanitize_email( (string) WSI_Settings::get( 'email_address' ) );
		return is_email( $address ) ? $address : '';
	}

	public function get_link( $product ) {
		$address = $this->get_address();
		if ( '' === $address ) {
			return false;
		}

		$config = WSI_Resolver::resolve( $product );

		// A subject is a single line. The body uses CRLF line breaks, as mailto: (RFC 6068) expects.
		$subject  = trim( preg_replace( '/\s+/', ' ', WSI_Placeholders::replace( (string) WSI_Settings::get( 'email_subject' ), $product ) ) );
		$template = '' !== $config['message'] ? $config['message'] : (string) WSI_Settings::get( 'email_message' );
		$body     = trim( WSI_Placeholders::replace( $template, $product ) );
		$body     = str_replace( "\n", "\r\n", $body );

		return array(
			'url'    => 'mailto:' . $address . '?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $body ),
			'target' => '_self',
		);
	}
}
