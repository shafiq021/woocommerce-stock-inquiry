<?php
/**
 * Admin: WooCommerce > Stock Inquiry settings page.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

class WSI_Admin {

	const PAGE = 'wsi-stock-inquiry';

	/** @var string Page hook suffix. */
	private $hook = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wsi_reset_settings', array( $this, 'handle_reset' ) );
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

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'wsi-admin', WSI_URL . 'admin/assets/admin.js', array( 'jquery', 'wp-color-picker' ), WSI_VERSION, true );

		wp_localize_script(
			'wsi-admin',
			'wsiAdmin',
			array(
				'selector' => WSI_Button::SELECTOR,
				'defaults' => WSI_Settings::defaults(),
				'i18n'     => array(
					'confirmReset' => __( 'Reset all Stock Inquiry settings to their defaults?', 'woocommerce-stock-inquiry' ),
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

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'wsi_reset' => 1 ), admin_url( 'admin.php' ) ) );
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
			esc_html__( 'WooCommerce Stock Inquiry is active but not set up yet, so out-of-stock products still use the normal WooCommerce button.', 'woocommerce-stock-inquiry' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
			esc_html__( 'Finish setup', 'woocommerce-stock-inquiry' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woocommerce-stock-inquiry' ) );
		}
		require WSI_PATH . 'admin/views/settings-page.php';
	}
}
