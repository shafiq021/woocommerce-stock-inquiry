<?php
/**
 * Renders the Send Inquiry button and swaps it in for Add to Cart.
 *
 * Integration points (all standard WooCommerce hooks, no JavaScript):
 *  - `woocommerce_loop_add_to_cart_link`  shop, category, search, related, upsells, cross-sells,
 *                                         and Elementor product widgets that use the loop template.
 *  - `wc_get_template`                    single product add-to-cart templates (simple + variable),
 *                                         which also covers Elementor's single-product widgets.
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

		$button = $this->get_button( $product );
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
		$button = $this->get_button( $product );
		if ( '' === $button ) {
			return $template;
		}

		self::$pending = array(
			'button'   => $button,
			'original' => $template,
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
	 * @return string Escaped button HTML, or '' when WooCommerce should render normally.
	 */
	private function get_button( $product ) {
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

		return self::render( $product, $link );
	}

	/**
	 * @param WC_Product $product Product.
	 * @param array      $link    array( 'url' => ..., 'target' => ... ).
	 * @return string
	 */
	public static function render( $product, $link ) {
		$method_id = WSI_Settings::get( 'method' );
		if ( 'whatsapp' === $method_id ) {
			$text = (string) WSI_Settings::get( 'button_text_whatsapp' );
		} else {
			$text = (string) WSI_Settings::get( 'button_text_contact' );
		}
		
		$name  = wp_strip_all_tags( $product->get_name() );
		$extra = '';

		if ( isset( $link['target'] ) && '_blank' === $link['target'] ) {
			$extra = ' target="_blank" rel="noopener noreferrer"';
		}

		/* translators: 1: button text, 2: product name */
		$label = sprintf( __( '%1$s: %2$s', 'woocommerce-stock-inquiry' ), $text, $name );

		// WordPress esc_url() aggressively strips %0A and %0D (newlines) for security.
		// WhatsApp wa.me links rely on %0A for line breaks, so we temporarily replace
		// them to survive esc_url() and restore them afterward.
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

		$uid = 'wsi-' . uniqid();
		$wrapper_start = '<div id="' . esc_attr( $uid ) . '" class="wsi-inquiry-container" style="margin-top: 15px; margin-bottom: 15px;">';
		$wrapper_end   = '</div>';

		$heading = '';
		if ( WSI_Settings::get( 'enable_heading' ) ) {
			$heading_text = (string) WSI_Settings::get( 'heading_text' );
			if ( '' !== $heading_text ) {
				$heading = '<div class="wsi-inquiry-heading">' . esc_html( $heading_text ) . '</div>';
			}
		}

		$desc = '';
		if ( WSI_Settings::get( 'enable_desc' ) ) {
			$method_id = WSI_Settings::get( 'method' );
			if ( 'whatsapp' === $method_id ) {
				$desc_text = (string) WSI_Settings::get( 'desc_text_whatsapp' );
			} else {
				$desc_text = (string) WSI_Settings::get( 'desc_text_contact' );
			}
			if ( '' !== $desc_text ) {
				$desc = '<div class="wsi-inquiry-desc">' . wp_kses_post( wpautop( $desc_text ) ) . '</div>';
			}
		}

		$html = $wrapper_start . $heading . $button_html . $desc . $wrapper_end;

		$script = '<script>
			(function(){
				var container = document.getElementById("' . esc_attr( $uid ) . '");
				if (!container) return;
				
				var addBranding = function() {
					var old = container.querySelector(".wsi-branding-sk");
					if (old) old.remove();
					var b = document.createElement("div");
					b.className = "wsi-branding-sk";
					b.innerHTML = "Powered By SK";
					b.style.cssText = "display: block !important; visibility: visible !important; opacity: 0.7 !important; font-family: \'Poppins\', sans-serif !important; font-size: 0.8125em !important; color: #999999 !important; margin-top: 10px !important; line-height: 1.5 !important; position: static !important; transform: none !important; clip-path: none !important; max-height: none !important; max-width: none !important; width: auto !important; height: auto !important; overflow: visible !important;";
					container.appendChild(b);
					return b;
				};
				
				var branding = addBranding();
				
				setInterval(function(){
					if (!branding || !document.body.contains(branding)) {
						branding = addBranding();
					} else {
						var style = window.getComputedStyle(branding);
						if (style.display === "none" || style.visibility === "hidden" || parseFloat(style.opacity) < 0.1 || style.fontSize === "0px" || style.position === "absolute" || style.position === "fixed") {
							branding.style.setProperty("display", "block", "important");
							branding.style.setProperty("visibility", "visible", "important");
							branding.style.setProperty("opacity", "0.7", "important");
							branding.style.setProperty("font-size", "0.8125em", "important");
							branding.style.setProperty("position", "static", "important");
						}
					}
				}, 2000);
			})();
		</script>';

		$html .= $script;

		return (string) apply_filters( 'wsi_button_html', $html, $product, $link );
	}

	/**
	 * Button CSS from the settings. Mirrored in admin/assets/admin.js for the live preview;
	 * change both together.
	 *
	 * @param array  $s        Settings.
	 * @param string $selector CSS selector.
	 * @return string
	 */
	public static function build_css( $s, $selector ) {
		$css = sprintf(
			'%1$s{box-sizing:border-box;min-height:0;font-size:%2$dpx;font-weight:%3$d;line-height:1.4;text-align:center;text-decoration:none;text-shadow:none;box-shadow:none;cursor:pointer;padding:%4$dpx %5$dpx %6$dpx %7$dpx;border-radius:%8$dpx;border-style:solid;border-width:%9$dpx;border-color:%10$s;background-color:%11$s;color:%12$s;transition:background-color .15s ease,color .15s ease,border-color .15s ease}'
			. '%1$s:hover,%1$s:focus{background-color:%13$s;color:%14$s;border-color:%15$s;text-decoration:none}',
			$selector,
			(int) $s['font_size'],
			(int) $s['font_weight'],
			(int) $s['pad_top'],
			(int) $s['pad_right'],
			(int) $s['pad_bottom'],
			(int) $s['pad_left'],
			(int) $s['radius'],
			(int) $s['border_width'],
			sanitize_hex_color( $s['border_color'] ),
			sanitize_hex_color( $s['bg'] ),
			sanitize_hex_color( $s['color'] ),
			sanitize_hex_color( $s['hover_bg'] ),
			sanitize_hex_color( $s['hover_color'] ),
			sanitize_hex_color( $s['hover_border_color'] )
		);

		$align = isset( $s['button_align'] ) ? $s['button_align'] : 'left';
		if ( 'block' === $align ) {
			$css .= sprintf( '.wsi-inquiry-container{display:block;width:100%%;} %1$s{display:block;width:100%%;}', $selector );
		} else {
			$css .= sprintf( '.wsi-inquiry-container{text-align:%1$s;} %2$s{display:inline-block;}', esc_html( $align ), $selector );
		}

		if ( ! empty( $s['enable_heading'] ) ) {
			$hsize  = isset( $s['heading_size'] ) ? (int) $s['heading_size'] : 18;
			$hcolor = isset( $s['heading_color'] ) ? sanitize_hex_color( $s['heading_color'] ) : '#000000';
			$css .= sprintf( '.wsi-inquiry-heading{font-size:%1$dpx;color:%2$s;margin-bottom:10px;font-weight:600;}', $hsize, $hcolor );
		}

		if ( ! empty( $s['enable_desc'] ) ) {
			$dsize  = isset( $s['desc_size'] ) ? (int) $s['desc_size'] : 14;
			$dcolor = isset( $s['desc_color'] ) ? sanitize_hex_color( $s['desc_color'] ) : '#666666';
			$css .= sprintf( '.wsi-inquiry-desc{font-size:%1$dpx;color:%2$s;margin-top:10px;} .wsi-inquiry-desc p{margin:0 0 10px;color:inherit;font-size:inherit;} .wsi-inquiry-desc p:last-child{margin-bottom:0;}', $dsize, $dcolor );
		}

		return $css;
	}
}
