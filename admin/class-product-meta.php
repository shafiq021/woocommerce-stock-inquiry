<?php
/**
 * "Stock Inquiry" tab in the WooCommerce product editor: per-product overrides.
 *
 * Kept deliberately small: use the global settings, or custom settings for trigger, threshold,
 * method, button text and message. Values are stored as post meta (see WSI_Resolver).
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-product override panel in the WooCommerce product editor.
 */
class WSI_Product_Meta {

	const NONCE_ACTION = 'wsi_save_product_override';
	const NONCE_FIELD  = 'wsi_product_nonce';

	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function add_tab( $tabs ) {
		$tabs['wsi_inquiry'] = array(
			'label'    => __( 'Stock Inquiry', 'woocommerce-stock-inquiry' ),
			'target'   => 'wsi_inquiry_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 85,
		);
		return $tabs;
	}

	/**
	 * Small script for the panel (shows the custom fields only for "Custom settings").
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || 'product' !== get_post_type() ) {
			return;
		}
		wp_enqueue_script( 'wsi-product', WSI_URL . 'admin/assets/product.js', array( 'jquery' ), WSI_VERSION, true );
	}

	public function render_panel() {
		global $post;
		$product_id = $post ? (int) $post->ID : 0;

		$custom    = $product_id ? get_post_meta( $product_id, WSI_Resolver::META_CUSTOM, true ) : '';
		$trigger   = $product_id ? (string) get_post_meta( $product_id, WSI_Resolver::META_TRIGGER, true ) : '';
		$threshold = $product_id ? get_post_meta( $product_id, WSI_Resolver::META_THRESHOLD, true ) : '';
		$method    = $product_id ? (string) get_post_meta( $product_id, WSI_Resolver::META_METHOD, true ) : '';
		$text      = $product_id ? (string) get_post_meta( $product_id, WSI_Resolver::META_TEXT, true ) : '';
		$message   = $product_id ? (string) get_post_meta( $product_id, WSI_Resolver::META_MESSAGE, true ) : '';
		$wa_dest   = $product_id ? (string) get_post_meta( $product_id, WSI_Resolver::META_DESTINATION, true ) : '';
		$wa_choices = WSI_Destinations::choices( __( 'Use the category rule or the default number', 'woocommerce-stock-inquiry' ) );

		$triggers = array( '' => __( 'Use global setting', 'woocommerce-stock-inquiry' ) ) + WSI_Settings::trigger_modes();
		$methods  = array( '' => __( 'Use global setting', 'woocommerce-stock-inquiry' ) );
		foreach ( WSI_Plugin::get_methods() as $method_id => $method_object ) {
			$methods[ $method_id ] = $method_object->get_label();
		}
		?>
		<div id="wsi_inquiry_data" class="panel woocommerce_options_panel hidden">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
			<div class="options_group">
				<p class="form-field">
					<label for="wsi_custom"><?php esc_html_e( 'Inquiry settings', 'woocommerce-stock-inquiry' ); ?></label>
					<select id="wsi_custom" name="wsi_custom" class="select short">
						<option value="global" <?php selected( 'custom' !== $custom ); ?>><?php esc_html_e( 'Use global settings', 'woocommerce-stock-inquiry' ); ?></option>
						<option value="custom" <?php selected( 'custom', $custom ); ?>><?php esc_html_e( 'Custom settings for this product', 'woocommerce-stock-inquiry' ); ?></option>
					</select>
					<?php echo wc_help_tip( __( 'Custom settings replace the global settings for this product only. Choose "Disabled" as the trigger to turn the inquiry off for this product.', 'woocommerce-stock-inquiry' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce helper returns escaped HTML. ?>
				</p>
			</div>
			<div class="options_group" id="wsi_custom_fields">
				<p class="form-field">
					<label for="wsi_trigger"><?php esc_html_e( 'Trigger', 'woocommerce-stock-inquiry' ); ?></label>
					<select id="wsi_trigger" name="wsi_trigger" class="select short">
						<?php foreach ( $triggers as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $trigger, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="form-field">
					<label for="wsi_threshold"><?php esc_html_e( 'Stock threshold', 'woocommerce-stock-inquiry' ); ?></label>
					<input type="number" id="wsi_threshold" name="wsi_threshold" class="short" min="0" max="100000" step="1" value="<?php echo esc_attr( $threshold ); ?>" placeholder="<?php echo esc_attr( WSI_Settings::get( 'stock_threshold' ) ); ?>" />
					<?php echo wc_help_tip( __( 'Leave empty to use the global threshold.', 'woocommerce-stock-inquiry' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce helper returns escaped HTML. ?>
				</p>
				<p class="form-field">
					<label for="wsi_method"><?php esc_html_e( 'Inquiry method', 'woocommerce-stock-inquiry' ); ?></label>
					<select id="wsi_method" name="wsi_method" class="select short">
						<?php foreach ( $methods as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $method, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="form-field">
					<label for="wsi_wa_destination"><?php esc_html_e( 'WhatsApp destination', 'woocommerce-stock-inquiry' ); ?></label>
					<select id="wsi_wa_destination" name="wsi_wa_destination" class="select short">
						<?php foreach ( $wa_choices as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $wa_dest, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php echo wc_help_tip( __( 'Which WhatsApp number this product uses when its method is WhatsApp. Add numbers under Stock Inquiry settings > Inquiry Method > WhatsApp.', 'woocommerce-stock-inquiry' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce helper returns escaped HTML. ?>
				</p>
				<p class="form-field">
					<label for="wsi_button_text"><?php esc_html_e( 'Button text', 'woocommerce-stock-inquiry' ); ?></label>
					<input type="text" id="wsi_button_text" name="wsi_button_text" class="short" maxlength="60" value="<?php echo esc_attr( $text ); ?>" placeholder="<?php esc_attr_e( 'Use the method default', 'woocommerce-stock-inquiry' ); ?>" />
				</p>
				<p class="form-field">
					<label for="wsi_message"><?php esc_html_e( 'Custom message', 'woocommerce-stock-inquiry' ); ?></label>
					<textarea id="wsi_message" name="wsi_message" class="short" rows="4" maxlength="1000" placeholder="<?php esc_attr_e( 'Use the global message template', 'woocommerce-stock-inquiry' ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Used by WhatsApp, Email and Popup. Placeholders such as {product_name} and {product_url} work here.', 'woocommerce-stock-inquiry' ); ?></span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save the override. WooCommerce already checks its own nonce and the edit capability before
	 * this action; ours adds a second check, and the capability is re-checked for the exact product.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$values = self::sanitize_override(
			array(
				'custom'    => isset( $_POST['wsi_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['wsi_custom'] ) ) : '',
				'trigger'   => isset( $_POST['wsi_trigger'] ) ? sanitize_text_field( wp_unslash( $_POST['wsi_trigger'] ) ) : '',
				'threshold' => isset( $_POST['wsi_threshold'] ) ? sanitize_text_field( wp_unslash( $_POST['wsi_threshold'] ) ) : '',
				'method'    => isset( $_POST['wsi_method'] ) ? sanitize_text_field( wp_unslash( $_POST['wsi_method'] ) ) : '',
				'text'      => isset( $_POST['wsi_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['wsi_button_text'] ) ) : '',
				'message'   => isset( $_POST['wsi_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wsi_message'] ) ) : '',
				'destination' => isset( $_POST['wsi_wa_destination'] ) ? sanitize_text_field( wp_unslash( $_POST['wsi_wa_destination'] ) ) : '',
			)
		);

		$map = array(
			WSI_Resolver::META_CUSTOM    => 'custom',
			WSI_Resolver::META_TRIGGER   => 'trigger',
			WSI_Resolver::META_THRESHOLD => 'threshold',
			WSI_Resolver::META_METHOD    => 'method',
			WSI_Resolver::META_TEXT      => 'text',
			WSI_Resolver::META_MESSAGE   => 'message',
			WSI_Resolver::META_DESTINATION => 'destination',
		);
		foreach ( $map as $meta_key => $field ) {
			if ( 'custom' === $field && 'custom' !== $values['custom'] ) {
				// Back to global: remove everything so the product leaves no data behind.
				foreach ( array_keys( $map ) as $key ) {
					$product->delete_meta_data( $key );
				}
				break;
			}
			if ( '' === (string) $values[ $field ] ) {
				$product->delete_meta_data( $meta_key );
			} else {
				$product->update_meta_data( $meta_key, $values[ $field ] );
			}
		}

		WSI_Stock_Checker::reset();
	}

	/**
	 * Validate raw override input against allow-lists and limits. Pure function (unit tested).
	 *
	 * @param array $raw custom, trigger, threshold, method, text, message.
	 * @return array Same keys, safe to store ('' = not set).
	 */
	public static function sanitize_override( array $raw ) {
		$custom = ( isset( $raw['custom'] ) && 'custom' === $raw['custom'] ) ? 'custom' : 'global';

		$trigger = isset( $raw['trigger'] ) ? sanitize_key( $raw['trigger'] ) : '';
		if ( ! array_key_exists( $trigger, WSI_Settings::trigger_modes() ) ) {
			$trigger = '';
		}

		$threshold = '';
		if ( isset( $raw['threshold'] ) && '' !== trim( (string) $raw['threshold'] ) && is_numeric( $raw['threshold'] ) ) {
			$threshold = (string) max( 0, min( 100000, (int) $raw['threshold'] ) );
		}

		$method = isset( $raw['method'] ) ? sanitize_key( $raw['method'] ) : '';
		if ( '' !== $method && ! array_key_exists( $method, WSI_Plugin::get_methods() ) ) {
			$method = '';
		}

		$text    = isset( $raw['text'] ) ? trim( sanitize_text_field( $raw['text'] ) ) : '';
		$message = isset( $raw['message'] ) ? trim( sanitize_textarea_field( $raw['message'] ) ) : '';

		$destination = isset( $raw['destination'] ) ? sanitize_key( $raw['destination'] ) : '';
		if ( '' !== $destination && ! WSI_Destinations::find( $destination ) ) {
			$destination = '';
		}

		return array(
			'destination' => $destination,
			'custom'    => $custom,
			'trigger'   => $trigger,
			'threshold' => $threshold,
			'method'    => $method,
			'text'      => function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 60 ) : substr( $text, 0, 60 ),
			'message'   => function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 1000 ) : substr( $message, 0, 1000 ),
		);
	}
}
