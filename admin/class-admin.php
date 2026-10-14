<?php
/**
 * Admin: WooCommerce > Stock Inquiry settings page.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page, AJAX test/preview actions and notices.
 */
class WSI_Admin {

	const PAGE       = 'wsi-stock-inquiry';
	const AJAX_NONCE = 'wsi_admin_ajax';

	/** @var string Page hook suffix. */
	private $hook = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wsi_reset_settings', array( $this, 'handle_reset' ) );
		add_action( 'wp_ajax_wsi_test_action', array( $this, 'ajax_test_action' ) );
		add_action( 'wp_ajax_wsi_preview_product', array( $this, 'ajax_preview_product' ) );
		add_action( 'admin_notices', array( $this, 'unconfigured_notice' ) );
		add_filter( 'woocommerce_screen_ids', array( $this, 'add_screen_id' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WSI_FILE ), array( $this, 'action_links' ) );
		add_filter( 'option_page_capability_' . WSI_Settings::GROUP, array( $this, 'save_capability' ) );
	}

	public function add_menu() {
		$this->hook = add_submenu_page(
			'woocommerce',
			__( 'Stock Inquiry', 'woocommerce-stock-inquiry' ),
			__( 'Stock Inquiry', 'woocommerce-stock-inquiry' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			WSI_Settings::GROUP,
			WSI_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WSI_Settings', 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * options.php checks this capability on save (default would be manage_options).
	 */
	public function save_capability() {
		return 'manage_woocommerce';
	}

	/**
	 * Let WooCommerce load its admin assets (select2 / product search) on our page.
	 *
	 * @param array $ids Screen IDs.
	 * @return array
	 */
	public function add_screen_id( $ids ) {
		$ids[] = 'woocommerce_page_' . self::PAGE;
		return $ids;
	}

	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'woocommerce-stock-inquiry' ) . '</a>' );
		return $links;
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wsi-admin', WSI_URL . 'admin/assets/admin.css', array(), WSI_VERSION );
		wp_enqueue_style( 'wsi-ui', WSI_URL . 'admin/assets/ui.css', array( 'wsi-admin' ), WSI_VERSION );

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'wsi-admin', WSI_URL . 'admin/assets/admin.js', array( 'jquery', 'wp-color-picker' ), WSI_VERSION, true );

		wp_localize_script(
			'wsi-admin',
			'wsiAdmin',
			array(
				'selector' => WSI_Button::SELECTOR,
				'defaults' => WSI_Settings::defaults(),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::AJAX_NONCE ),
				'samples'  => WSI_Placeholders::sample_values(),
				'i18n'     => array(
					'confirmReset'  => __( 'Reset all Stock Inquiry settings to their defaults?', 'woocommerce-stock-inquiry' ),
					/* translators: %s: product name */
					'productLabel'  => __( 'Product: %s', 'woocommerce-stock-inquiry' ),
					/* translators: %s: product URL */
					'urlLabel'      => __( 'Product URL: %s', 'woocommerce-stock-inquiry' ),
					'testing'       => __( 'Checking…', 'woocommerce-stock-inquiry' ),
					'requestFailed' => __( 'The request failed. Reload the page and try again.', 'woocommerce-stock-inquiry' ),
					'addToCart'     => __( 'Add to cart', 'woocommerce-stock-inquiry' ),
				),
			)
		);
	}

	/**
	 * Reset to defaults (nonce + capability protected).
	 */
	public function handle_reset() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woocommerce-stock-inquiry' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wsi_reset_settings' );

		delete_option( WSI_Settings::OPTION );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAGE,
					'wsi_reset' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Reminder on the Plugins screen until an inquiry method is fully set up.
	 */
	public function unconfigured_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! WSI_Settings::get( 'enabled' ) || WSI_Plugin::get_active_method() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'WooCommerce Stock Inquiry is active but not set up yet, so unavailable products still use the normal WooCommerce button.', 'woocommerce-stock-inquiry' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
			esc_html__( 'Finish setup', 'woocommerce-stock-inquiry' )
		);
	}

	/**
	 * Common checks for the AJAX actions: capability first, then the nonce.
	 */
	private function ajax_guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woocommerce-stock-inquiry' ) ), 403 );
		}
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
	}

	/**
	 * Product for tests and previews: the chosen one, or the newest published product.
	 *
	 * @param int $product_id Product or variation ID (0 = newest published product).
	 * @return WC_Product|null
	 */
	private function get_preview_product( $product_id ) {
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			return ( $product instanceof WC_Product && 'publish' === get_post_status( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ) ? $product : null;
		}
		$found = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => array( 'simple', 'variable' ),
			)
		);
		return $found ? $found[0] : null;
	}

	/**
	 * "Test" buttons. Builds the link a customer would get, using the values currently typed in the
	 * form (they are sanitized exactly like a save, but nothing is saved), and returns it so the
	 * browser can open it. Nothing is sent, and no order, lead or other data is created.
	 */
	public function ajax_test_action() {
		$this->ajax_guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in ajax_guard(); the parsed form values are sanitized field by field in WSI_Settings::sanitize().
		$raw  = isset( $_POST['settings'] ) ? (string) wp_unslash( $_POST['settings'] ) : '';
		$form = array();
		parse_str( $raw, $form );
		$input = ( isset( $form[ WSI_Settings::OPTION ] ) && is_array( $form[ WSI_Settings::OPTION ] ) ) ? $form[ WSI_Settings::OPTION ] : array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in ajax_guard().
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in ajax_guard().
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;

		$methods = WSI_Plugin::get_methods();
		if ( ! isset( $methods[ $kind ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown inquiry method.', 'woocommerce-stock-inquiry' ) ) );
		}

		$clean            = WSI_Settings::sanitize( $input );
		$clean['enabled'] = 1;
		$clean['method']  = $kind;

		$issues = array();
		foreach ( WSI_Validator::check( wp_parse_args( $clean, WSI_Settings::defaults() ) ) as $issue ) {
			if ( 'error' === $issue['severity'] ) {
				$issues[] = $issue['message'];
			}
		}
		if ( $issues ) {
			wp_send_json_error( array( 'message' => implode( ' ', $issues ) ) );
		}

		$product = $this->get_preview_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Create or select a published product to test with.', 'woocommerce-stock-inquiry' ) ) );
		}

		WSI_Settings::use_temporarily( $clean );
		WSI_Resolver::reset();
		$link = $methods[ $kind ]->is_configured() ? $methods[ $kind ]->get_link( $product ) : false;
		$data = array();
		if ( 'popup' === $kind && ! empty( $link ) ) {
			$popup_id = absint( $clean['popup_id'] );
			$data     = array(
				'popupId' => $popup_id,
				'editUrl' => esc_url_raw( admin_url( 'post.php?post=' . $popup_id . '&action=elementor' ) ),
				'product' => WSI_Popup::product_data( $product ),
				'pageUrl' => esc_url_raw( get_permalink( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ),
			);
		}
		WSI_Settings::flush();
		WSI_Resolver::reset();

		if ( empty( $link['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This method is not configured yet.', 'woocommerce-stock-inquiry' ) ) );
		}

		wp_send_json_success(
			array(
				'method'  => $kind,
				'url'     => WSI_Button::clean_url( $link['url'], false ),
				'target'  => ( isset( $link['target'] ) && '_blank' === $link['target'] ) ? '_blank' : '_self',
				'product' => wp_strip_all_tags( $product->get_name() ),
				'data'    => $data,
			)
		);
	}

	/**
	 * Placeholder values for the product picked in the live preview.
	 */
	public function ajax_preview_product() {
		$this->ajax_guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in ajax_guard().
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$product    = $product_id ? $this->get_preview_product( $product_id ) : null;
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'woocommerce-stock-inquiry' ) ) );
		}

		wp_send_json_success( array( 'values' => WSI_Placeholders::values( $product ) ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woocommerce-stock-inquiry' ) );
		}
		require WSI_PATH . 'admin/views/settings-page.php';
	}
}
