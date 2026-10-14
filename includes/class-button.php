<?php
/**
 * Renders the inquiry section (heading, button, description) and swaps it in for Add to Cart.
 *
 * Integration points (all standard WooCommerce hooks; no shortcode needed):
 *  - `woocommerce_loop_add_to_cart_link`  shop, category, search, related, upsells, cross-sells,
 *                                         and Elementor product widgets that use the loop template.
 *  - `wc_get_template`                    single product add-to-cart templates (simple + variable),
 *                                         which also covers Elementor's single-product widgets.
 *  - `woocommerce_available_variation`    per-variation data so a variable product's button follows
 *                                         the selected variation (a small script uses it).
 *
 * The script (public/js/frontend.js) is only loaded on pages that print a variable-product button
 * or a popup button. Everything else is server-rendered HTML plus inline CSS.
 *
 * Heading and description each have their own "show on single / show on listings" switch,
 * so the full section can appear on the product page while listings only get the button.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the inquiry section and swaps it in for Add to Cart.
 */
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
		add_filter( 'woocommerce_available_variation', array( $this, 'filter_variation' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (do not load) the inline stylesheet and the script. They are loaded the first time
	 * a button is printed (see use_assets()); WooCommerce pages load the stylesheet up front so
	 * the button never flashes unstyled.
	 */
	public function register_assets() {
		// Always registered, so the [wsi_inquiry_form] shortcode is styled even while automatic
		// replacement of Add to Cart is switched off. Enqueued automatically only when it is on.
		wp_register_style( 'wsi-button', false, array(), WSI_VERSION );
		wp_add_inline_style( 'wsi-button', self::build_css( WSI_Settings::all(), self::SELECTOR ) );
		wp_register_script( 'wsi-frontend', WSI_URL . 'public/js/frontend.js', array( 'jquery' ), WSI_VERSION, true );

		if ( WSI_Settings::get( 'enabled' ) && function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			wp_enqueue_style( 'wsi-button' );
		}
	}

	/**
	 * Load what a printed button needs.
	 *
	 * @param bool $needs_script Whether the button needs frontend.js.
	 */
	private static function use_assets( $needs_script ) {
		wp_enqueue_style( 'wsi-button' );
		if ( $needs_script ) {
			wp_enqueue_script( 'wsi-frontend' );
		}
	}

	/**
	 * Which product listing the current loop item belongs to.
	 *
	 * @return string shop, category, search, related, upsells, cross_sells or other.
	 */
	public static function loop_location() {
		$name = function_exists( 'wc_get_loop_prop' ) ? (string) wc_get_loop_prop( 'name' ) : '';
		if ( 'related' === $name ) {
			$location = 'related';
		} elseif ( 'up-sells' === $name ) {
			$location = 'upsells';
		} elseif ( 'cross-sells' === $name ) {
			$location = 'cross_sells';
		} elseif ( function_exists( 'is_search' ) && is_search() ) {
			$location = 'search';
		} elseif ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$location = 'category';
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$location = 'shop';
		} else {
			$location = 'other';
		}
		return (string) apply_filters( 'wsi_loop_location', $location );
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
		if ( ! WSI_Settings::get( 'show_loc_' . self::loop_location(), 1 ) ) {
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
	 * Single product page: swap the simple/variable add-to-cart template for our wrapper.
	 *
	 * Modes handed to the wrapper:
	 *  - 'static'  the button replaces (or sits before/after) the normal template.
	 *  - 'dynamic' variable product where only some variations need the inquiry: the normal form
	 *              stays and a hidden button is shown by frontend.js for those variations.
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
		if ( ! $product instanceof WC_Product ) {
			return $template;
		}

		$mode   = 'static';
		$button = $this->get_button( $product, 'single' );
		if ( '' === $button && 'mixed' === WSI_Stock_Checker::variable_state( $product ) ) {
			$mode   = 'dynamic';
			$button = $this->get_button( $product, 'single', true );
		}
		if ( '' === $button ) {
			return $template;
		}

		self::$pending = array(
			'button'    => $button,
			'mode'      => $mode,
			'original'  => $template,
			'is_simple' => 'single-product/add-to-cart/simple.php' === $template_name,
		);

		return WSI_PATH . 'templates/add-to-cart-wrapper.php';
	}

	/**
	 * Adds the inquiry data of each variation to WooCommerce's variation JSON, so the button can
	 * follow the selected variation (including the AJAX-loaded variations of large products).
	 *
	 * @param array                $data      Variation data.
	 * @param WC_Product           $parent_product Variable product.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public function filter_variation( $data, $parent_product = null, $variation = null ) {
		if ( ! is_array( $data ) || ! $parent_product instanceof WC_Product || ! $variation instanceof WC_Product ) {
			return $data;
		}
		if ( ! WSI_Settings::get( 'enable_single' ) || 'mixed' !== WSI_Stock_Checker::variable_state( $parent_product ) ) {
			return $data;
		}

		$data['wsi'] = array( 'inquiry' => false );

		if ( WSI_Stock_Checker::needs_inquiry( $variation ) ) {
			$config = WSI_Resolver::resolve( $variation );
			$method = WSI_Plugin::get_method( $config['method'] );
			$link   = $method ? $method->get_link( $variation ) : false;
			if ( ! empty( $link['url'] ) ) {
				$parts       = self::link_parts( $variation, $link, $config );
				$data['wsi'] = array(
					'inquiry' => true,
					'url'     => $parts['raw_url'],
					'target'  => $parts['target'],
					'text'    => $parts['text'],
					'label'   => $parts['label'],
					'attrs'   => $parts['attrs'],
				);
			}
		}

		return $data;
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
	 * @param bool            $dynamic Build the hidden, script-controlled button for a variable product.
	 * @return string Escaped HTML, or '' when WooCommerce should render normally.
	 */
	private function get_button( $product, $context, $dynamic = false ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		if ( ! $dynamic && ! WSI_Stock_Checker::needs_inquiry( $product ) ) {
			return '';
		}

		$config = WSI_Resolver::resolve( $product );
		$method = WSI_Plugin::get_method( $config['method'] );
		if ( ! $method ) {
			return '';
		}

		$link = $method->get_link( $product );
		if ( empty( $link['url'] ) ) {
			return '';
		}

		return self::render( $product, $link, $context, $config, array( 'dynamic' => $dynamic ) );
	}

	/**
	 * Whether an extra element (heading, desc) is shown in a context.
	 *
	 * @param string $element 'heading' or 'desc'.
	 * @param string $context 'single', 'shortcode' or 'loop'.
	 * @return bool
	 */
	private static function shows( $element, $context ) {
		if ( ! WSI_Settings::get( 'enable_' . $element ) ) {
			return false;
		}
		$suffix = ( 'loop' === $context ) ? 'loops' : 'single';
		return (bool) WSI_Settings::get( $element . '_show_' . $suffix );
	}

	/**
	 * Display URL that survives esc_url(). esc_url() strips %0A and %0D (line breaks), which
	 * wa.me and mailto: links rely on, so they are swapped for markers and restored afterwards.
	 *
	 * @param string $url     URL.
	 * @param bool   $display True for an HTML attribute (&amp; encoded), false for raw use (JSON, JS).
	 * @return string
	 */
	public static function clean_url( $url, $display = true ) {
		$marked  = str_replace( array( '%0A', '%0D', '%0a', '%0d' ), array( '__WSI_0A__', '__WSI_0D__', '__WSI_0A__', '__WSI_0D__' ), (string) $url );
		$cleaned = $display ? esc_url( $marked ) : esc_url_raw( $marked );
		return str_replace( array( '__WSI_0A__', '__WSI_0D__' ), array( '%0A', '%0D' ), $cleaned );
	}

	/**
	 * Printable attributes from a method's 'attrs' (data-*, aria-* and role only).
	 *
	 * @param mixed $attrs Attributes (name => value).
	 * @return array name => value
	 */
	private static function allowed_attrs( $attrs ) {
		$out = array();
		foreach ( (array) $attrs as $name => $value ) {
			$name = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $name ) );
			if ( 'role' === $name || 0 === strpos( $name, 'data-' ) || 0 === strpos( $name, 'aria-' ) ) {
				$out[ $name ] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * Everything needed to print or serialize one button.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $link    Method link.
	 * @param array      $config  Resolved configuration.
	 * @param string     $text    Optional text override (shortcode).
	 * @return array
	 */
	private static function link_parts( $product, array $link, array $config, $text = '' ) {
		$method_id = (string) $config['method'];

		if ( '' === $text ) {
			$text = $config['button_text'];
		}
		if ( '' === $text ) {
			$text = (string) WSI_Settings::get( 'button_text_' . $method_id );
		}
		if ( '' === $text ) {
			$text = __( 'Send Inquiry', 'woocommerce-stock-inquiry' );
		}

		$name = html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' );

		return array(
			'method'  => $method_id,
			'text'    => $text,
			/* translators: 1: button text, 2: product name */
			'label'   => sprintf( __( '%1$s: %2$s', 'woocommerce-stock-inquiry' ), $text, $name ),
			'url'     => self::clean_url( $link['url'], true ),
			'raw_url' => self::clean_url( $link['url'], false ),
			'target'  => ( isset( $link['target'] ) && '_blank' === $link['target'] ) ? '_blank' : '_self',
			'attrs'   => self::allowed_attrs( isset( $link['attrs'] ) ? $link['attrs'] : array() ),
		);
	}

	/**
	 * @param WC_Product $product Product.
	 * @param array      $link    array( 'url' => ..., 'target' => ..., 'attrs' => ... ).
	 * @param string     $context 'single', 'loop' or 'shortcode'.
	 * @param array|null $config  Resolved configuration (resolved here when null).
	 * @param array      $options 'dynamic' (hidden, script-controlled) and 'text' (button text override).
	 * @return string
	 */
	public static function render( $product, $link, $context = 'single', $config = null, $options = array() ) {
		if ( ! is_array( $config ) ) {
			$config = WSI_Resolver::resolve( $product );
		}
		$dynamic = ! empty( $options['dynamic'] );
		$text    = isset( $options['text'] ) ? (string) $options['text'] : '';
		$parts   = self::link_parts( $product, $link, $config, $text );

		$extra = '';
		if ( '_blank' === $parts['target'] ) {
			$extra .= ' target="_blank" rel="noopener noreferrer"';
		}
		foreach ( $parts['attrs'] as $attr_name => $attr_value ) {
			$extra .= ' ' . $attr_name . '="' . esc_attr( $attr_value ) . '"';
		}

		$classes     = 'button wsi-inquiry-button wsi-method-' . sanitize_html_class( $parts['method'] );
		$button_html = sprintf(
			'<a href="%1$s" class="%2$s" aria-label="%3$s"%4$s>%5$s</a>',
			$parts['url'],
			esc_attr( $classes ),
			esc_attr( $parts['label'] ),
			$extra,
			esc_html( $parts['text'] )
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
			$desc_text = (string) WSI_Settings::get( 'desc_text_' . $parts['method'] );
			if ( '' !== $desc_text ) {
				$desc = '<div class="wsi-inquiry-desc">' . wp_kses_post( wpautop( $desc_text ) ) . '</div>';
			}
		}

		$container_attrs = '';
		if ( $dynamic ) {
			$container_attrs = ' data-wsi-dynamic="1" data-wsi-position="' . esc_attr( WSI_Settings::get( 'position' ) ) . '" style="display:none"';
		}

		// Order: heading -> button -> description.
		$html = '<div class="wsi-inquiry-container"' . $container_attrs . '>' . $heading . $button_html . $desc . '</div>';

		if ( isset( $parts['attrs']['data-wsi-form'] ) ) {
			WSI_Form::enqueue();
		}
		self::use_assets( $dynamic || isset( $parts['attrs']['data-wsi-popup'] ) );

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


		return $css;
	}
}
