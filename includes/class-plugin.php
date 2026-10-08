<?php
/**
 * Plugin bootstrap and inquiry-method registry.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

final class WSI_Plugin {

	/** @var WSI_Plugin|null */
	private static $instance = null;

	/** @var array|null Registered inquiry methods, keyed by id. */
	private static $methods = null;

	/**
	 * Boot the plugin once.
	 *
	 * @return WSI_Plugin
	 */
	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Activation: store defaults once and try to pre-select an existing contact page.
	 */
	public static function activate() {
		require_once WSI_PATH . 'includes/class-settings.php';

		if ( false === get_option( WSI_Settings::OPTION ) ) {
			$defaults                 = WSI_Settings::defaults();
			$defaults['contact_page'] = WSI_Settings::detect_contact_page();
			add_option( WSI_Settings::OPTION, $defaults );
		}
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		$this->load_files();

		WSI_Settings::init();
		new WSI_Button();
		add_shortcode( 'wsi_product', array( 'WSI_Contact', 'shortcode' ) );

		if ( is_admin() ) {
			require_once WSI_PATH . 'admin/class-admin.php';
			new WSI_Admin();
		}
	}

	private function load_files() {
		$files = array(
			'class-settings.php',
			'class-product-rules.php',
			'class-stock-checker.php',
			'interface-inquiry-method.php',
			'class-contact.php',
			'class-whatsapp.php',
			'class-button.php',
		);
		foreach ( $files as $file ) {
			require_once WSI_PATH . 'includes/' . $file;
		}
	}

	/**
	 * Admin notice shown when WooCommerce is missing. Nothing else in the plugin runs.
	 */
	public function missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce Stock Inquiry requires WooCommerce to be installed and active.', 'woocommerce-stock-inquiry' ) . '</p></div>';
	}

	/**
	 * Available inquiry methods. To add a method later (e.g. a popup), implement
	 * WSI_Inquiry_Method and add it with the `wsi_inquiry_methods` filter.
	 *
	 * @return WSI_Inquiry_Method[]
	 */
	public static function get_methods() {
		if ( null === self::$methods ) {
			$methods = array();
			foreach ( array( new WSI_Contact(), new WSI_WhatsApp() ) as $method ) {
				$methods[ $method->get_id() ] = $method;
			}
			self::$methods = apply_filters( 'wsi_inquiry_methods', $methods );
		}
		return self::$methods;
	}

	/**
	 * Methods that are shown in the admin as "Coming Soon" and are not selectable.
	 *
	 * @return array id => label
	 */
	public static function get_coming_soon_methods() {
		return apply_filters(
			'wsi_coming_soon_methods',
			array( 'popup' => __( 'Popup', 'woocommerce-stock-inquiry' ) )
		);
	}

	/**
	 * The selected method, or null when the plugin is off or the method is not fully configured.
	 * Returning null means WooCommerce keeps its normal behavior (fail-safe).
	 *
	 * @return WSI_Inquiry_Method|null
	 */
	public static function get_active_method() {
		if ( ! WSI_Settings::get( 'enabled' ) ) {
			return null;
		}
		$methods = self::get_methods();
		$id      = WSI_Settings::get( 'method' );
		if ( ! isset( $methods[ $id ] ) || ! $methods[ $id ]->is_configured() ) {
			return null;
		}
		return $methods[ $id ];
	}
}
