<?php
/**
 * Plugin bootstrap and inquiry-method registry.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap and inquiry-method registry.
 */
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
		require_once WSI_PATH . 'includes/class-migrator.php';
		require_once WSI_PATH . 'includes/class-installer.php';

		// Tables are needed on every activation (fresh install or re-activation).
		WSI_Installer::install();

		if ( false === get_option( WSI_Settings::OPTION ) ) {
			$defaults                 = WSI_Settings::defaults();
			$defaults['contact_page'] = WSI_Settings::detect_contact_page();
			add_option( WSI_Settings::OPTION, $defaults );
			// Fresh install: nothing to migrate.
			update_option( WSI_Migrator::VERSION_OPTION, WSI_Migrator::TARGET );
		}
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		$this->load_files();

		// Before anything reads settings. Runs on `init` so no translation is loaded too early.
		add_action( 'init', array( 'WSI_Migrator', 'maybe_run' ), 1 );

		WSI_Settings::init();
		new WSI_Button();
		WSI_Form::init();
		WSI_Inquiry_Handler::init();
		WSI_Notifications::init();
		WSI_Privacy::init();
		add_shortcode( 'wsi_product', array( 'WSI_Contact', 'shortcode' ) );
		add_shortcode( 'wsi_button', array( 'WSI_Shortcode', 'render' ) );

		if ( is_admin() ) {
			require_once WSI_PATH . 'admin/class-product-meta.php';
			require_once WSI_PATH . 'admin/class-admin.php';
			require_once WSI_PATH . 'admin/class-inquiries-list-table.php';
			require_once WSI_PATH . 'admin/class-inquiry-admin.php';
			new WSI_Product_Meta();
			// The dashboard and inquiry list are registered first so they lead the menu.
			new WSI_Inquiry_Admin();
			new WSI_Admin();
		}
	}

	private function load_files() {
		$files = array(
			'class-settings.php',
			'class-migrator.php',
			'class-installer.php',
			'class-inquiry-repository.php',
			'class-elementor.php',
			'class-placeholders.php',
			'class-validator.php',
			'class-product-rules.php',
			'class-destinations.php',
			'class-rules.php',
			'class-resolver.php',
			'class-stock-checker.php',
			'interface-inquiry-method.php',
			'class-contact.php',
			'class-whatsapp.php',
			'class-email.php',
			'class-phone.php',
			'class-popup.php',
			'class-form.php',
			'class-inquiry-handler.php',
			'class-notifications.php',
			'class-privacy.php',
			'class-button.php',
			'class-shortcode.php',
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
	 * Available inquiry methods. To add a method, implement WSI_Inquiry_Method and add it with
	 * the `wsi_inquiry_methods` filter.
	 *
	 * @return WSI_Inquiry_Method[]
	 */
	public static function get_methods() {
		if ( null === self::$methods ) {
			$methods = array();
			foreach ( array( new WSI_Contact(), new WSI_WhatsApp(), new WSI_Form(), new WSI_Popup(), new WSI_Email(), new WSI_Phone() ) as $method ) {
				$methods[ $method->get_id() ] = $method;
			}
			self::$methods = apply_filters( 'wsi_inquiry_methods', $methods );
		}
		return self::$methods;
	}

	/**
	 * Methods that are shown in the admin as "Coming Soon" and are not selectable.
	 * Empty since version 2.0.0 (the popup method shipped); kept for add-ons.
	 *
	 * @return array id => label
	 */
	public static function get_coming_soon_methods() {
		return (array) apply_filters( 'wsi_coming_soon_methods', array() );
	}

	/**
	 * Forget the cached method list (tests, or methods registered late).
	 */
	public static function reset_methods() {
		self::$methods = null;
	}

	/**
	 * A configured method by ID, or null when the plugin is off, the ID is unknown or the method
	 * is not fully configured. Null means WooCommerce keeps its normal behavior (fail-safe).
	 *
	 * @param string $id Method ID.
	 * @return WSI_Inquiry_Method|null
	 */
	public static function get_method( $id ) {
		if ( ! WSI_Settings::get( 'enabled' ) ) {
			return null;
		}
		$methods = self::get_methods();
		$id      = (string) $id;
		if ( ! isset( $methods[ $id ] ) || ! $methods[ $id ]->is_configured() ) {
			return null;
		}
		return $methods[ $id ];
	}

	/**
	 * The globally selected method, or null (see get_method()).
	 *
	 * @return WSI_Inquiry_Method|null
	 */
	public static function get_active_method() {
		return self::get_method( WSI_Settings::get( 'method' ) );
	}
}
