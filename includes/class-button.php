<?php
/**
 * Renders the inquiry section (heading, button, description, branding) and swaps it in for Add to Cart.
 *
 * Integration points (all standard WooCommerce hooks, no JavaScript):
 *  - `woocommerce_loop_add_to_cart_link`  shop, category, search, related, upsells, cross-sells,
 *                                         and Elementor product widgets that use the loop template.
 *  - `wc_get_template`                    single product add-to-cart templates (simple + variable),
 *                                         which also covers Elementor's single-product widgets.
 *
 * Heading, description and branding each have their own "show on single / show on listings" switch,
 * so the full section can appear on the product page while listings only get the button.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

class WSI_Button {

	/**
	 * CSS selector for the button. Doubled class keeps it above most theme `.button` rules.
	 * The admin live preview uses the exact same selector and CSS template.
	 */
	const SELECTOR = 'a.wsi-inquiry-button.wsi-inquiry-button.button';

	/** @var array|null Data handed from the template filter to the wrapper template. */
	private static $pending = null;

	public function __construct() {
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_loop_link' ), 20, 3 );
		add_filter( 'wc_get_template', array( $this, 'filter_template' ), 20, 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Inline styles only: no extra request, no JavaScript. Skipped entirely when the plugin
	 * is off or the chosen method is not configured.
	 */
	public function enqueue_styles() {
		if ( ! WSI_Plugin::get_active_method() ) {
			return;
		}
		wp_register_style( 'wsi-button', false, array(), WSI_VERSION );
		wp_enqueue_style( 'wsi-button' );
		wp_add_inline_style( 'wsi-button', self::build_css( WSI_Settings::all(), self::SELECTOR ) );
	}

	/**
	 * Product listings.
	 *
	 * @param string          $html    Default add-to-cart link HTML.
	 * @param WC_Product|null $product Product.
	 * @param array           $args    Link args.
	 * @return string
	 */
	public function filter_loop_link( $html, $product = null, $args = array() ) {
		if ( ! WSI_Settings::get( 'enable_loops' ) ) {
			return $html;
		}

		$button = $this->get_button( $product, 'loop' );
		if ( '' === $button ) {
			return $html;
		}

		switch ( WSI_Settings::get( 'position' ) ) {
			case 'before':
				return $button . $html;
			case 'after':
				return $html . $button;
			default:
				return $button;
		}
	}

	/**
	 * Single product page: swap the simple/variable add-to-cart template for our wrapper,
	 * which prints the button and (for before/after) the original template.
	 *
	 * @param string $template      Located template path.
	 * @param string $template_name Template name.
	 * @return string
	 */
	public function filter_template( $template, $template_name ) {
		if ( 'single-product/add-to-cart/simple.php' !== $template_name && 'single-product/add-to-cart/variable.php' !== $template_name ) {
			return $template;
		}
		if ( ! WSI_Settings::get( 'enable_single' ) ) {
			return $template;
		}

		global $product;
		$button = $this->get_button( $product, 'single' );
		if ( '' === $button ) {
			return $template;
		}

		self::$pending = array(
			'button'    => $button,
			'original'  => $template,
			'is_simple' => 'single-product/add-to-cart/simple.php' === $template_name,
		);

		return WSI_PATH . 'templates/add-to-cart-wrapper.php';
	}

	/**
	 * Used by the wrapper template. Returns and clears the pending data.
	 *
	 * @return array|null
	 */
	public static function take_pending() {
		$pending       = self::$pending;
		self::$pending = null;
		return $pending;
	}

	/**
	 * @param WC_Product|null $product Product.
	 * @param string          $context 'single' or 'loop'.
	 * @return string Escaped HTML, or '' when WooCommerce should render normally.
	 */
	private function get_button( $product, $context ) {
		if ( ! $product instanceof WC_Product || ! WSI_Stock_Checker::needs_inquiry( $product ) ) {
			return '';
		}

		$method = WSI_Plugin::get_active_method();
		if ( ! $method ) {
			return '';
		}

		$link = $method->get_link( $product );
		if ( empty( $link['url'] ) ) {
			return '';
		}

		return self::render( $product, $link, $context );
	}

	/**
	 * Whether an extra element (heading, desc, branding) is shown in a context.
	 *
	 * @param string $element 'heading', 'desc' or 'branding'.
	 * @param string $context 'single' or 'loop'.
	 * @return bool
	 */
	private static function shows( $element, $context ) {
		if ( 'branding' !== $element && ! WSI_Settings::get( 'enable_' . $element ) ) {
			return false;
		}
		$suffix = ( 'single' === $context ) ? 'single' : 'loops';
		return (bool) WSI_Settings::get( $element . '_show_' . $suffix );
	}

	/**
	 * @param WC_Product $product Product.
	 * @param array      $link    array( 'url' => ..., 'target' => ... ).
	 * @param string     $context 'single' or 'loop'.
	 * @return string
	 */
	public static function render( $product, $link, $context = 'single' ) {
		$method_id = WSI_Settings::get( 'method' );
		$is_wa     = ( 'whatsapp' === $method_id );
		$text      = (string) WSI_Settings::get( $is_wa ? 'button_text_whatsapp' : 'button_text_contact' );

		$name  = wp_strip_all_tags( $product->get_name() );
		$extra = '';

		if ( isset( $link['target'] ) && '_blank' === $link['target'] ) {
			$extra = ' target="_blank" rel="noopener noreferrer"';
		}

		/* translators: 1: button text, 2: product name */
		$label = sprintf( __( '%1$s: %2$s', 'woocommerce-stock-inquiry' ), $text, $name );

		// esc_url() strips %0A and %0D (newlines). WhatsApp wa.me links rely on them for line breaks,
		// so they are swapped for placeholders that survive esc_url() and restored afterwards.
		$safe_url = str_replace(
			array( '__WSI_0A__', '__WSI_0D__' ),
			array( '%0A', '%0D' ),
			esc_url( str_replace( array( '%0A', '%0D', '%0a', '%0d' ), array( '__WSI_0A__', '__WSI_0D__', '__WSI_0A__', '__WSI_0D__' ), $link['url'] ) )
		);

		$button_html = sprintf(
			'<a href="%1$s" class="button wsi-inquiry-button" aria-label="%2$s"%3$s>%4$s</a>',
			$safe_url,
			esc_attr( $label ),
			$extra,
			esc_html( $text )
		);

		$heading = '';
		if ( self::shows( 'heading', $context ) ) {
			$heading_text = (string) WSI_Settings::get( 'heading_text' );
			if ( '' !== $heading_text ) {
				$heading = '<div class="wsi-inquiry-heading">' . esc_html( $heading_text ) . '</div>';
			}
		}

		$desc = '';
		if ( self::shows( 'desc', $context ) ) {
			$desc_text = (string) WSI_Settings::get( $is_wa ? 'desc_text_whatsapp' : 'desc_text_contact' );
			if ( '' !== $desc_text ) {
				$desc = '<div class="wsi-inquiry-desc">' . wp_kses_post( wpautop( $desc_text ) ) . '</div>';
			}
		}

		$branding = '';
		if ( self::shows( 'branding', $context ) ) {
			$branding = '<div class="wsi-branding-sk">Powered By SK</div>';
		}

		// Order: heading -> button -> description -> branding.
		$html = '<div class="wsi-inquiry-container">' . $heading . $button_html . $desc . $branding . '</div>';

		return (string) apply_filters( 'wsi_button_html', $html, $product, $link, $context );
	}

	/**
	 * Section CSS from the settings. Mirrored in admin/assets/admin.js for the live preview;
	 * change both together.
	 *
	 * @param array  $s        Settings.
	 * @param string $selector Button CSS selector.
	 * @return string
	 */
	public static function build_css( $s, $selector ) {
		$c   = '.wsi-inquiry-container';
		$hex = static function ( $key ) use ( $s ) {
			return (string) sanitize_hex_color( $s[ $key ] );
		};

		// Container + alignment.
		$align = isset( $s['button_align'] ) ? $s['button_align'] : 'left';
		if ( 'block' === $align ) {
			$css = $c . '{display:block;width:100%;margin:15px 0;}' . $selector . '{display:block;width:100%;}';
		} else {
			$css = $c . '{text-align:' . esc_html( $align ) . ';margin:15px 0;}' . $selector . '{display:inline-block;}';
		}

		// Button.
		$css .= $selector . '{box-sizing:border-box;min-height:0;font-family:' . WSI_Settings::font_stack( $s['btn_font_family'] )
			. ';font-size:' . (int) $s['font_size'] . 'px;font-weight:' . (int) $s['font_weight']
			. ';line-height:1.4;text-align:center;text-decoration:none;text-shadow:none;box-shadow:none;cursor:pointer;padding:'
			. (int) $s['pad_top'] . 'px ' . (int) $s['pad_right'] . 'px ' . (int) $s['pad_bottom'] . 'px ' . (int) $s['pad_left']
			. 'px;border-radius:' . (int) $s['radius'] . 'px;border-style:solid;border-width:' . (int) $s['border_width']
			. 'px;border-color:' . $hex( 'border_color' ) . ';background-color:' . $hex( 'bg' ) . ';color:' . $hex( 'color' )
			. ';transition:background-color .15s ease,color .15s ease,border-color .15s ease}'
			. $selector . ':hover,' . $selector . ':focus{background-color:' . $hex( 'hover_bg' ) . ';color:' . $hex( 'hover_color' )
			. ';border-color:' . $hex( 'hover_border_color' ) . ';text-decoration:none}';

		// Heading.
		$css .= $c . ' .wsi-inquiry-heading{margin:0 0 10px;line-height:1.3;font-family:' . WSI_Settings::font_stack( $s['heading_font_family'] )
			. ';font-size:' . (int) $s['heading_size'] . 'px;font-weight:' . (int) $s['heading_weight'] . ';color:' . $hex( 'heading_color' ) . '}';

		// Description.
		$css .= $c . ' .wsi-inquiry-desc{margin:10px 0 0;line-height:1.5;font-family:' . WSI_Settings::font_stack( $s['desc_font_family'] )
			. ';font-size:' . (int) $s['desc_size'] . 'px;color:' . $hex( 'desc_color' ) . '}'
			. $c . ' .wsi-inquiry-desc p{margin:0 0 10px;color:inherit;font-size:inherit;font-family:inherit}'
			. $c . ' .wsi-inquiry-desc p:last-child{margin-bottom:0}';

		// Branding: fixed muted color, only font family, size and placement are configurable.
		$b_align = isset( $s['branding_align'] ) ? $s['branding_align'] : 'right';
		$css    .= $c . ' .wsi-branding-sk{display:block;margin:10px 0 0;opacity:.7;color:#999;line-height:1.5;font-family:' . WSI_Settings::font_stack( $s['branding_font_family'] )
			. ';font-size:' . (int) $s['branding_size'] . 'px;text-align:' . esc_html( $b_align ) . '}';

		return $css;
	}
}
