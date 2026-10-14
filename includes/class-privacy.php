<?php
/**
 * Privacy: policy text and WordPress personal data export / erase support.
 *
 * Internal notes are never part of an export (they are the store's own records).
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the plugin into the WordPress privacy tools.
 */
class WSI_Privacy {

	const PER_PAGE = 50;

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['woocommerce-stock-inquiry'] = array(
			'exporter_friendly_name' => __( 'Product inquiries', 'woocommerce-stock-inquiry' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['woocommerce-stock-inquiry'] = array(
			'eraser_friendly_name' => __( 'Product inquiries', 'woocommerce-stock-inquiry' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * @param string $email Email address of the person.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$rows  = WSI_Inquiry_Repository::get_by_email( sanitize_email( $email ), (int) $page, self::PER_PAGE );
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'group_id'          => 'wsi-inquiries',
				'group_label'       => __( 'Product inquiries', 'woocommerce-stock-inquiry' ),
				'group_description' => __( 'Inquiries submitted through the website.', 'woocommerce-stock-inquiry' ),
				'item_id'           => 'wsi-inquiry-' . (int) $row->id,
				'data'              => array(
					array(
						'name'  => __( 'Product', 'woocommerce-stock-inquiry' ),
						'value' => $row->product_name,
					),
					array(
						'name'  => __( 'Name', 'woocommerce-stock-inquiry' ),
						'value' => $row->customer_name,
					),
					array(
						'name'  => __( 'Email', 'woocommerce-stock-inquiry' ),
						'value' => $row->customer_email,
					),
					array(
						'name'  => __( 'Phone', 'woocommerce-stock-inquiry' ),
						'value' => $row->customer_phone,
					),
					array(
						'name'  => __( 'Message', 'woocommerce-stock-inquiry' ),
						'value' => $row->message,
					),
					array(
						'name'  => __( 'Submitted (UTC)', 'woocommerce-stock-inquiry' ),
						'value' => $row->created_at,
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * @param string $email Email address of the person.
	 * @return array
	 */
	public static function erase( $email ) {
		$removed = WSI_Inquiry_Repository::delete_by_email( sanitize_email( $email ) );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested text for the site's privacy policy page.
	 */
	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<h3>' . esc_html__( 'Product inquiries', 'woocommerce-stock-inquiry' ) . '</h3>'
			. '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'woocommerce-stock-inquiry' ) . '</p>'
			. '<p>' . esc_html__( 'When you send us a product inquiry, we store your name, email address, optional phone number, your message and the product you asked about, so we can reply to you. Inquiries are visible only to our staff and are not shared with third parties. You can ask us to export or erase your inquiries at any time.', 'woocommerce-stock-inquiry' ) . '</p>';
		wp_add_privacy_policy_content( 'WooCommerce Stock Inquiry', wp_kses_post( wpautop( $text, false ) ) );
	}
}
