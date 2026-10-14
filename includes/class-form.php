<?php
/**
 * Built-in inquiry form: a modal with name, email, phone and message fields.
 *
 * It is both an inquiry method ("Inquiry Form (modal)": the automatic button opens the modal) and
 * the [wsi_inquiry_form] shortcode (paste it anywhere to print a button that opens the same modal).
 * Submissions go to WSI_Inquiry_Handler, which stores them in the database.
 *
 *   [wsi_inquiry_form]                          button for the current product
 *   [wsi_inquiry_form product_id="123"]         a specific product
 *   [wsi_inquiry_form text="Ask us"]            custom button text
 *   [wsi_inquiry_form send="whatsapp"]          after saving, offer a "Continue on WhatsApp" button
 *   [wsi_inquiry_form title="Request a quote"]  custom modal heading
 *   [wsi_inquiry_form only_if_needed="yes"]     print only when the product's stock rule needs an inquiry
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modal inquiry form, its method and its shortcode.
 */
class WSI_Form implements WSI_Inquiry_Method {

	/** @var bool A form button was printed on this page, so the modal must be printed too. */
	private static $used = false;

	/**
	 * Register the shortcode and the page hooks.
	 */
	public static function init() {
		add_shortcode( 'wsi_inquiry_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_modal' ), 20 );
	}

	/* ---------- Inquiry method ---------- */

	public function get_id() {
		return 'form';
	}

	public function get_label() {
		return __( 'Inquiry Form (modal)', 'woocommerce-stock-inquiry' );
	}

	public function is_configured() {
		return true;
	}

	public function get_link( $product ) {
		return array(
			'url'    => '#',
			'target' => '_self',
			'attrs'  => array(
				'role'             => 'button',
				'data-wsi-form'    => '1',
				'data-wsi-origin'  => 'popup',
				'data-wsi-product' => wp_json_encode( self::product_data( $product ) ),
			),
		);
	}

	/**
	 * Identification of a product for the form (plain text).
	 *
	 * @param WC_Product|null $product Product, variable product or variation.
	 * @return array
	 */
	public static function product_data( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return array(
				'id'        => 0,
				'variation' => 0,
				'name'      => '',
			);
		}
		$is_variation = $product->is_type( 'variation' );
		return array(
			'id'        => $is_variation ? (int) $product->get_parent_id() : (int) $product->get_id(),
			'variation' => $is_variation ? (int) $product->get_id() : 0,
			'name'      => html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' ),
		);
	}

	/* ---------- Assets ---------- */

	public static function register_assets() {
		wp_register_style( 'wsi-inquiry-form', WSI_URL . 'public/css/inquiry-form.css', array(), WSI_VERSION );
		wp_add_inline_style( 'wsi-inquiry-form', self::build_css( WSI_Settings::all() ) );
		wp_register_script( 'wsi-inquiry-form', WSI_URL . 'public/js/inquiry-form.js', array(), WSI_VERSION, true );
		wp_localize_script(
			'wsi-inquiry-form',
			'wsiForm',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => WSI_Inquiry_Handler::ACTION,
				'nonce'   => wp_create_nonce( WSI_Inquiry_Handler::NONCE ),
				'i18n'    => array(
					'sending'    => __( 'Sending…', 'woocommerce-stock-inquiry' ),
					'error'      => __( 'Something went wrong. Please try again.', 'woocommerce-stock-inquiry' ),
					'productFor' => __( 'Product:', 'woocommerce-stock-inquiry' ),
					'required'   => __( 'This field is required.', 'woocommerce-stock-inquiry' ),
					'email'      => __( 'Please enter a valid email address.', 'woocommerce-stock-inquiry' ),
				),
			)
		);
	}

	/**
	 * Load the form's CSS and JavaScript (once per page, only when a form button exists).
	 */
	public static function enqueue() {
		self::$used = true;
		wp_enqueue_style( 'wsi-inquiry-form' );
		wp_enqueue_script( 'wsi-inquiry-form' );
	}

	/**
	 * Form CSS from the settings (submit button uses the button colors).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function build_css( $s ) {
		$hex = static function ( $key ) use ( $s ) {
			return (string) sanitize_hex_color( $s[ $key ] );
		};
		return '.wsi-modal .wsi-form__submit{background-color:' . $hex( 'bg' ) . ';color:' . $hex( 'color' ) . ';border-color:' . $hex( 'border_color' )
			. ';border-radius:' . (int) $s['radius'] . 'px;font-weight:' . (int) $s['font_weight'] . '}'
			. '.wsi-modal .wsi-form__submit:hover,.wsi-modal .wsi-form__submit:focus{background-color:' . $hex( 'hover_bg' ) . ';color:' . $hex( 'hover_color' ) . ';border-color:' . $hex( 'hover_border_color' ) . '}';
	}

	/* ---------- Shortcode ---------- */

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id'     => 0,
				'text'           => '',
				'send'           => '',
				'title'          => '',
				'only_if_needed' => 'no',
			),
			$atts,
			'wsi_inquiry_form'
		);

		$product = null;
		$id      = absint( $atts['product_id'] );
		if ( $id ) {
			$candidate = wc_get_product( $id );
			if ( $candidate instanceof WC_Product && 'publish' === get_post_status( $candidate->is_type( 'variation' ) ? $candidate->get_parent_id() : $candidate->get_id() ) ) {
				$product = $candidate;
			}
		} else {
			// The product of the page being printed (single product, loop item or Elementor template).
			$current = ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) ? $GLOBALS['product'] : wc_get_product( get_the_ID() );
			$product = ( $current instanceof WC_Product && 'publish' === get_post_status( $current->is_type( 'variation' ) ? $current->get_parent_id() : $current->get_id() ) ) ? $current : null;
		}

		if ( 'yes' === strtolower( (string) $atts['only_if_needed'] ) && ( ! $product || ! WSI_Stock_Checker::needs_inquiry( $product ) ) ) {
			return '';
		}

		$text = mb_substr( trim( sanitize_text_field( (string) $atts['text'] ) ), 0, 60 );
		if ( '' === $text ) {
			$text = (string) WSI_Settings::get( 'form_button_text' );
		}
		$send  = in_array( sanitize_key( (string) $atts['send'] ), array( 'store', 'whatsapp' ), true ) ? sanitize_key( (string) $atts['send'] ) : '';
		$title = mb_substr( trim( sanitize_text_field( (string) $atts['title'] ) ), 0, 100 );

		wp_enqueue_style( 'wsi-button' );
		self::enqueue();

		return sprintf(
			'<div class="wsi-inquiry-container"><a href="#" class="button wsi-inquiry-button wsi-method-form" role="button" data-wsi-form="1" data-wsi-origin="shortcode" data-wsi-product="%1$s" data-wsi-send="%2$s" data-wsi-title="%3$s">%4$s</a></div>',
			esc_attr( wp_json_encode( self::product_data( $product ) ) ),
			esc_attr( $send ),
			esc_attr( $title ),
			esc_html( $text )
		);
	}

	/* ---------- Modal ---------- */

	/**
	 * Print the modal once, in the footer, when a form button was printed on the page.
	 */
	public static function print_modal() {
		if ( ! self::$used ) {
			return;
		}
		$show_phone    = (bool) WSI_Settings::get( 'form_show_phone' );
		$require_phone = (bool) WSI_Settings::get( 'form_require_phone' );
		$title         = (string) WSI_Settings::get( 'form_title' );
		$submit        = (string) WSI_Settings::get( 'form_button_text' );
		?>
		<div class="wsi-modal" id="wsi-modal" hidden data-default-title="<?php echo esc_attr( $title ); ?>">
			<div class="wsi-modal__overlay" data-wsi-close></div>
			<div class="wsi-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="wsi-modal-title" tabindex="-1">
				<button type="button" class="wsi-modal__close" data-wsi-close aria-label="<?php esc_attr_e( 'Close', 'woocommerce-stock-inquiry' ); ?>">&times;</button>
				<h2 class="wsi-modal__title" id="wsi-modal-title"><?php echo esc_html( $title ); ?></h2>
				<p class="wsi-modal__product" hidden></p>
				<form class="wsi-form" novalidate>
					<div class="wsi-field">
						<label for="wsi-f-name"><?php esc_html_e( 'Name', 'woocommerce-stock-inquiry' ); ?> <span aria-hidden="true">*</span></label>
						<input type="text" id="wsi-f-name" name="name" maxlength="120" autocomplete="name" required />
						<span class="wsi-field__error" data-error-for="name" role="alert"></span>
					</div>
					<div class="wsi-field">
						<label for="wsi-f-email"><?php esc_html_e( 'Email', 'woocommerce-stock-inquiry' ); ?> <span aria-hidden="true">*</span></label>
						<input type="email" id="wsi-f-email" name="email" maxlength="100" autocomplete="email" required />
						<span class="wsi-field__error" data-error-for="email" role="alert"></span>
					</div>
					<?php if ( $show_phone ) : ?>
					<div class="wsi-field">
						<label for="wsi-f-phone"><?php esc_html_e( 'Phone', 'woocommerce-stock-inquiry' ); ?> <?php echo $require_phone ? '<span aria-hidden="true">*</span>' : '<span class="wsi-optional">' . esc_html__( '(optional)', 'woocommerce-stock-inquiry' ) . '</span>'; ?></label>
						<input type="tel" id="wsi-f-phone" name="phone" maxlength="20" autocomplete="tel" <?php echo $require_phone ? 'required' : ''; ?> />
						<span class="wsi-field__error" data-error-for="phone" role="alert"></span>
					</div>
					<?php endif; ?>
					<div class="wsi-field">
						<label for="wsi-f-message"><?php esc_html_e( 'Message', 'woocommerce-stock-inquiry' ); ?> <span aria-hidden="true">*</span></label>
						<textarea id="wsi-f-message" name="message" rows="4" maxlength="1000" required></textarea>
						<span class="wsi-field__error" data-error-for="message" role="alert"></span>
					</div>
					<div class="wsi-hp" aria-hidden="true">
						<label for="wsi-f-website"><?php esc_html_e( 'Leave this field empty', 'woocommerce-stock-inquiry' ); ?></label>
						<input type="text" id="wsi-f-website" name="wsi_website" tabindex="-1" autocomplete="off" />
					</div>
					<p class="wsi-form__status" role="status" aria-live="polite"></p>
					<button type="submit" class="wsi-form__submit"><?php echo esc_html( $submit ); ?></button>
				</form>
				<div class="wsi-success" hidden>
					<p class="wsi-success__message"></p>
					<a class="wsi-success__whatsapp" href="#" target="_blank" rel="noopener noreferrer" hidden><?php esc_html_e( 'Continue on WhatsApp', 'woocommerce-stock-inquiry' ); ?></a>
					<button type="button" class="wsi-success__close" data-wsi-close><?php esc_html_e( 'Close', 'woocommerce-stock-inquiry' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
