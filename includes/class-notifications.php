<?php
/**
 * Email notifications for stored inquiries (one system for every email the plugin sends).
 *
 *  - Admin notification   : a new inquiry was saved.
 *  - Customer confirmation: "we received your inquiry".
 *  - Status change        : the inquiry status changed (optional, off by default).
 *
 * Subjects and messages are editable templates with {placeholders}. Emails go through wp_mail(), so
 * an SMTP plugin on the site works unchanged. A failed email never affects the stored inquiry, and
 * internal notes are never used here, so they can never reach a customer.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends inquiry emails.
 */
class WSI_Notifications {

	public static function init() {
		add_action( 'wsi_inquiry_created', array( __CLASS__, 'on_created' ), 10, 1 );
		add_action( 'wsi_inquiry_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 3 );
	}

	/* ---------- Helpers ---------- */

	/**
	 * Address that receives admin notifications (and customer replies).
	 *
	 * @return string
	 */
	public static function recipient() {
		$email = sanitize_email( (string) WSI_Settings::get( 'notify_email' ) );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * Admin link to an inquiry.
	 *
	 * @param int $id Inquiry ID.
	 * @return string
	 */
	public static function admin_link( $id ) {
		return admin_url( 'admin.php?page=wsi-inquiries&view=single&id=' . absint( $id ) );
	}

	/**
	 * Placeholders available in templates (tag => description).
	 * {admin_link} only works in the admin email; in customer emails it is always empty.
	 *
	 * @return array
	 */
	public static function placeholders() {
		return array(
			'{inquiry_id}'      => __( 'Inquiry number', 'woocommerce-stock-inquiry' ),
			'{product_name}'    => __( 'Product name', 'woocommerce-stock-inquiry' ),
			'{customer_name}'   => __( 'Customer name', 'woocommerce-stock-inquiry' ),
			'{customer_email}'  => __( 'Customer email', 'woocommerce-stock-inquiry' ),
			'{customer_phone}'  => __( 'Customer phone', 'woocommerce-stock-inquiry' ),
			'{inquiry_message}' => __( 'Customer message', 'woocommerce-stock-inquiry' ),
			'{inquiry_status}'  => __( 'Current status', 'woocommerce-stock-inquiry' ),
			'{site_name}'       => __( 'Site name', 'woocommerce-stock-inquiry' ),
			'{admin_link}'      => __( 'Link to the inquiry in WordPress (admin email only)', 'woocommerce-stock-inquiry' ),
		);
	}

	/**
	 * Plain-text values for the placeholders of one inquiry.
	 *
	 * @param object $inquiry   Inquiry row.
	 * @param bool   $for_admin Whether {admin_link} may be filled in.
	 * @return array tag => value.
	 */
	private static function values( $inquiry, $for_admin ) {
		$statuses = WSI_Inquiry_Repository::statuses();
		$product  = '' !== (string) $inquiry->product_name ? $inquiry->product_name : __( '(general inquiry)', 'woocommerce-stock-inquiry' );
		return array(
			'{inquiry_id}'      => (string) (int) $inquiry->id,
			'{product_name}'    => (string) $product,
			'{customer_name}'   => (string) $inquiry->customer_name,
			'{customer_email}'  => (string) $inquiry->customer_email,
			'{customer_phone}'  => '' !== (string) $inquiry->customer_phone ? (string) $inquiry->customer_phone : '-',
			'{inquiry_message}' => (string) $inquiry->message,
			'{inquiry_status}'  => isset( $statuses[ $inquiry->status ] ) ? $statuses[ $inquiry->status ] : (string) $inquiry->status,
			'{site_name}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{admin_link}'      => $for_admin ? self::admin_link( $inquiry->id ) : '',
		);
	}

	/**
	 * Fill a template. Values are inserted as plain text and are never re-scanned for placeholders.
	 *
	 * @param string $template  Template text.
	 * @param object $inquiry   Inquiry row.
	 * @param bool   $for_admin Whether {admin_link} may be filled in.
	 * @return string
	 */
	public static function render( $template, $inquiry, $for_admin = false ) {
		return strtr( (string) $template, self::values( $inquiry, $for_admin ) );
	}

	/**
	 * Fill a subject template: always a single line, so customer text cannot inject headers.
	 *
	 * @param string $template  Template text.
	 * @param object $inquiry   Inquiry row.
	 * @param bool   $for_admin Whether {admin_link} may be filled in.
	 * @return string
	 */
	public static function render_subject( $template, $inquiry, $for_admin = false ) {
		$subject = self::render( $template, $inquiry, $for_admin );
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( $subject ) ) );
	}

	/**
	 * Send through wp_mail(). Never throws.
	 *
	 * @param string $type    'admin', 'customer', 'status' or 'test'.
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $message Plain-text body.
	 * @param array  $headers Extra headers.
	 * @param int    $inquiry_id Inquiry ID for the failure log (0 for a test).
	 * @return bool Whether wp_mail() accepted the message.
	 */
	private static function deliver( $type, $to, $subject, $message, array $headers, $inquiry_id ) {
		/**
		 * Filter an inquiry email before it is sent. Return an empty `to` to cancel it.
		 *
		 * @param array $mail array( to, subject, message, headers, type, inquiry_id ).
		 */
		$mail = apply_filters(
			'wsi_notification_email',
			array(
				'to'         => $to,
				'subject'    => $subject,
				'message'    => $message,
				'headers'    => array_merge( array( 'Content-Type: text/plain; charset=UTF-8' ), $headers ),
				'type'       => $type,
				'inquiry_id' => (int) $inquiry_id,
			)
		);
		if ( empty( $mail['to'] ) || ! is_email( $mail['to'] ) ) {
			return false;
		}

		try {
			$sent = (bool) wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
		} catch ( \Throwable $e ) {
			$sent = false;
		}
		if ( ! $sent ) {
			self::log_failure( $type, $inquiry_id );
		}
		return $sent;
	}

	/**
	 * Log only the email type and inquiry ID, never customer details.
	 *
	 * @param string $type       Email type.
	 * @param int    $inquiry_id Inquiry ID.
	 */
	private static function log_failure( $type, $inquiry_id ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WooCommerce Stock Inquiry: the ' . sanitize_key( $type ) . ' email for inquiry #' . absint( $inquiry_id ) . ' could not be sent.' );
		}
	}

	/**
	 * Reply-To header for an address, with the display name cleaned of header-breaking characters.
	 *
	 * @param string $email Address.
	 * @param string $name  Optional display name.
	 * @return array Zero or one header.
	 */
	private static function reply_to( $email, $name = '' ) {
		if ( ! is_email( $email ) ) {
			return array();
		}
		$name = trim( preg_replace( '/[\r\n"<>,;]+/', ' ', (string) $name ) );
		return array( 'Reply-To: ' . ( '' !== $name ? $name . ' ' : '' ) . '<' . $email . '>' );
	}

	/* ---------- Senders ---------- */

	/**
	 * Notify the store owner about an inquiry.
	 *
	 * @param object $inquiry Inquiry row.
	 * @param string $prefix  Optional subject prefix (used by the test email).
	 * @return bool
	 */
	public static function send_admin( $inquiry, $prefix = '' ) {
		return self::deliver(
			$prefix ? 'test' : 'admin',
			self::recipient(),
			$prefix . self::render_subject( WSI_Settings::get( 'admin_subject' ), $inquiry, true ),
			self::render( WSI_Settings::get( 'admin_message' ), $inquiry, true ),
			self::reply_to( $inquiry->customer_email, $inquiry->customer_name ),
			(int) $inquiry->id
		);
	}

	/**
	 * Confirm to the customer that the inquiry was received.
	 *
	 * @param object $inquiry Inquiry row.
	 * @return bool
	 */
	public static function send_customer( $inquiry ) {
		return self::deliver(
			'customer',
			$inquiry->customer_email,
			self::render_subject( WSI_Settings::get( 'customer_subject' ), $inquiry ),
			self::render( WSI_Settings::get( 'customer_message' ), $inquiry ),
			self::reply_to( self::recipient() ),
			(int) $inquiry->id
		);
	}

	/**
	 * Tell the customer the status changed.
	 *
	 * @param object $inquiry Inquiry row (with the new status).
	 * @return bool
	 */
	public static function send_status( $inquiry ) {
		return self::deliver(
			'status',
			$inquiry->customer_email,
			self::render_subject( WSI_Settings::get( 'status_subject' ), $inquiry ),
			self::render( WSI_Settings::get( 'status_message' ), $inquiry ),
			self::reply_to( self::recipient() ),
			(int) $inquiry->id
		);
	}

	/**
	 * Send the saved admin template with sample data to the notification address.
	 *
	 * @return bool
	 */
	public static function send_test() {
		$sample = (object) array(
			'id'             => 0,
			'product_name'   => __( 'Sample product', 'woocommerce-stock-inquiry' ),
			'customer_name'  => __( 'Sample Customer', 'woocommerce-stock-inquiry' ),
			'customer_email' => self::recipient(),
			'customer_phone' => '+1 555 0100',
			'message'        => __( 'This is a test message.', 'woocommerce-stock-inquiry' ),
			'status'         => 'new',
		);
		return self::send_admin( $sample, __( '[Test] ', 'woocommerce-stock-inquiry' ) );
	}

	/* ---------- Hooks ---------- */

	/**
	 * A new inquiry was stored: admin notification and customer confirmation.
	 * Each email is isolated, so one failing never prevents the other, or the saved inquiry.
	 *
	 * @param int $inquiry_id New inquiry ID.
	 */
	public static function on_created( $inquiry_id ) {
		$inquiry = WSI_Inquiry_Repository::get( $inquiry_id );
		if ( ! $inquiry ) {
			return;
		}
		if ( WSI_Settings::get( 'notify_admin' ) ) {
			try {
				self::send_admin( $inquiry );
			} catch ( \Throwable $e ) {
				self::log_failure( 'admin', $inquiry_id );
			}
		}
		if ( WSI_Settings::get( 'notify_customer' ) ) {
			try {
				self::send_customer( $inquiry );
			} catch ( \Throwable $e ) {
				self::log_failure( 'customer', $inquiry_id );
			}
		}
	}

	/**
	 * The status changed: optionally tell the customer. Moving an inquiry back to "New" is an
	 * internal correction, so it is never emailed.
	 *
	 * @param int    $inquiry_id Inquiry ID.
	 * @param string $status     New status.
	 * @param string $old_status Previous status.
	 */
	public static function on_status_changed( $inquiry_id, $status, $old_status ) {
		if ( ! WSI_Settings::get( 'notify_status' ) || 'new' === $status ) {
			return;
		}
		$inquiry = WSI_Inquiry_Repository::get( $inquiry_id );
		if ( ! $inquiry ) {
			return;
		}
		try {
			self::send_status( $inquiry );
		} catch ( \Throwable $e ) {
			self::log_failure( 'status', $inquiry_id );
		}
	}
}
