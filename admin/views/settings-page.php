<?php
/**
 * Settings page markup.
 *
 * Layout: tabs + controls on the left, sticky live preview on the right (visible on every tab).
 * Each element (Heading, Button, Description, Branding) keeps all of its controls in its own tab.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

$s       = WSI_Settings::all();
$methods = WSI_Plugin::get_methods();
$coming  = WSI_Plugin::get_coming_soon_methods();
$fonts   = WSI_Settings::font_families();
$field   = static function ( $key ) {
	return WSI_Settings::OPTION . '[' . $key . ']';
};

/** Toggle (custom checkbox). */
$toggle = static function ( $key, $label ) use ( $field, $s ) {
	printf(
		'<label class="wsi-toggle"><input type="checkbox" name="%1$s" value="1" %2$s /><span class="wsi-checkmark"></span><span>%3$s</span></label>',
		esc_attr( $field( $key ) ),
		checked( 1, (int) $s[ $key ], false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper returns a safe attribute.
		esc_html( $label )
	);
};

/** "Where to show" row for heading / description / branding. */
$where_row = static function ( $prefix ) use ( $toggle ) {
	?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Where to Show', 'woocommerce-stock-inquiry' ); ?></th>
		<td>
			<?php $toggle( $prefix . '_show_single', __( 'Single product page', 'woocommerce-stock-inquiry' ) ); ?><br />
			<?php $toggle( $prefix . '_show_loops', __( 'Product listings (shop, categories, search, related, upsells, cross-sells)', 'woocommerce-stock-inquiry' ) ); ?>
		</td>
	</tr>
	<?php
};

/** Font family select row. */
$font_row = static function ( $key, $label ) use ( $field, $s, $fonts ) {
	$id = 'wsi-' . str_replace( '_', '-', $key );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field( $key ) ); ?>">
				<?php foreach ( $fonts as $value => $name ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s[ $key ], $value ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( 'inherit' !== $s[ $key ] || 'branding_font_family' === $key ) : ?>
				<p class="description"><?php esc_html_e( 'Web fonts must be loaded by your theme; otherwise the browser falls back to a similar font.', 'woocommerce-stock-inquiry' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
};

/** Number row. */
$number_row = static function ( $key, $label, $min, $max ) use ( $field, $s ) {
	$id = 'wsi-' . str_replace( '_', '-', $key );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td><input type="number" class="small-text" id="<?php echo esc_attr( $id ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" name="<?php echo esc_attr( $field( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" /> px</td>
	</tr>
	<?php
};

/** Font weight row. */
$weight_row = static function ( $key, $label ) use ( $field, $s ) {
	$id      = 'wsi-' . str_replace( '_', '-', $key );
	$weights = array(
		400 => __( 'Normal (400)', 'woocommerce-stock-inquiry' ),
		500 => __( 'Medium (500)', 'woocommerce-stock-inquiry' ),
		600 => __( 'Semi-bold (600)', 'woocommerce-stock-inquiry' ),
		700 => __( 'Bold (700)', 'woocommerce-stock-inquiry' ),
		800 => __( 'Extra bold (800)', 'woocommerce-stock-inquiry' ),
	);
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field( $key ) ); ?>">
				<?php foreach ( $weights as $weight => $weight_label ) : ?>
					<option value="<?php echo esc_attr( $weight ); ?>" <?php selected( (int) $s[ $key ], $weight ); ?>><?php echo esc_html( $weight_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<?php
};

/** Color picker row. */
$color_row = static function ( $key, $label ) use ( $field, $s ) {
	$id = 'wsi-' . str_replace( '_', '-', $key );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td><input type="text" class="wsi-color" id="<?php echo esc_attr( $id ); ?>" data-default-color="<?php echo esc_attr( $s[ $key ] ); ?>" name="<?php echo esc_attr( $field( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" /></td>
	</tr>
	<?php
};

// Selected products.
$selected_products = array();
foreach ( (array) $s['excluded_products'] as $product_id ) {
	$selected_product = wc_get_product( $product_id );
	if ( $selected_product ) {
		$selected_products[ $product_id ] = wp_strip_all_tags( $selected_product->get_formatted_name() );
	}
}

$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
if ( is_wp_error( $categories ) ) {
	$categories = array();
}

$active_method = WSI_Plugin::get_active_method();
$tabs          = array(
	'general'  => __( 'General', 'woocommerce-stock-inquiry' ),
	'method'   => __( 'Inquiry Method', 'woocommerce-stock-inquiry' ),
	'heading'  => __( 'Heading', 'woocommerce-stock-inquiry' ),
	'button'   => __( 'Button', 'woocommerce-stock-inquiry' ),
	'desc'     => __( 'Description', 'woocommerce-stock-inquiry' ),
	'branding' => __( 'Branding', 'woocommerce-stock-inquiry' ),
	'rules'    => __( 'Product Rules', 'woocommerce-stock-inquiry' ),
	'advanced' => __( 'Advanced', 'woocommerce-stock-inquiry' ),
);

$example_url     = 'https://example.com/product/example';
$wa_bubble_start = str_replace( array( '{product_name}', '{product_url}' ), array( 'Example Product', $example_url ), (string) $s['whatsapp_message'] );
?>
<div class="wrap wsi-wrap">
	<h1><?php esc_html_e( 'Stock Inquiry', 'woocommerce-stock-inquiry' ); ?></h1>

	<?php settings_errors(); ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['wsi_reset'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings were reset to their defaults.', 'woocommerce-stock-inquiry' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $s['enabled'] && ! $active_method ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				if ( 'whatsapp' === $s['method'] ) {
					esc_html_e( 'Enter a valid WhatsApp number (Inquiry Method tab). Until then, out-of-stock products use the normal WooCommerce button.', 'woocommerce-stock-inquiry' );
				} else {
					esc_html_e( 'Select a published contact page (Inquiry Method tab). Until then, out-of-stock products use the normal WooCommerce button.', 'woocommerce-stock-inquiry' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" id="wsi-settings-form">
		<?php settings_fields( WSI_Settings::GROUP ); ?>

		<div class="wsi-main-layout">

			<!-- ====== LEFT: CONTROLS ====== -->
			<div class="wsi-settings-column">
				<nav class="nav-tab-wrapper wsi-tabs">
					<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
						<a href="#<?php echo esc_attr( $tab_id ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $tab_id ); ?>"><?php echo esc_html( $tab_label ); ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="wsi-tab-contents">

				<!-- ======== GENERAL ======== -->
				<div class="wsi-tab" data-tab="general">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Stock Inquiry', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<?php $toggle( 'enabled', __( 'Replace Add to Cart with the inquiry button when a product is unavailable', 'woocommerce-stock-inquiry' ) ); ?>
								<p class="description"><?php esc_html_e( 'Turn this off and WooCommerce behaves exactly as if the plugin was not installed.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Show Button On', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<?php $toggle( 'enable_loops', __( 'Product listings (shop, categories, search, related, upsells, cross-sells)', 'woocommerce-stock-inquiry' ) ); ?><br />
								<?php $toggle( 'enable_single', __( 'Single product pages', 'woocommerce-stock-inquiry' ) ); ?>
							</td>
						</tr>
					</table>
					<p class="description wsi-note"><?php esc_html_e( 'Heading, description and branding each have their own "Where to Show" setting in their tabs, so you can show the full section on the product page and only the button in listings.', 'woocommerce-stock-inquiry' ); ?></p>
					<p class="description wsi-note"><?php esc_html_e( 'The plugin checks each product\'s current stock on every page load, so restocking a product in WooCommerce brings Add to Cart back automatically.', 'woocommerce-stock-inquiry' ); ?></p>
				</div>

				<!-- ======== INQUIRY METHOD ======== -->
				<div class="wsi-tab" data-tab="method">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Inquiry Type', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<fieldset>
									<?php foreach ( $methods as $method_id => $method ) : ?>
										<label class="wsi-radio">
											<input type="radio" name="<?php echo esc_attr( $field( 'method' ) ); ?>" value="<?php echo esc_attr( $method_id ); ?>" <?php checked( $s['method'], $method_id ); ?> />
											<?php echo esc_html( $method->get_label() ); ?>
										</label>
									<?php endforeach; ?>
									<?php foreach ( $coming as $coming_id => $coming_label ) : ?>
										<label class="wsi-radio wsi-disabled">
											<input type="radio" disabled="disabled" />
											<?php echo esc_html( $coming_label ); ?>
											<span class="wsi-badge"><?php esc_html_e( 'Coming Soon', 'woocommerce-stock-inquiry' ); ?></span>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
					</table>

					<!-- Contact method -->
					<div class="wsi-method-fields" data-method="contact">
						<h2><?php esc_html_e( 'Contact Page', 'woocommerce-stock-inquiry' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsi-button-text-contact"><?php esc_html_e( 'Button Text', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wsi-button-text-contact" maxlength="60" name="<?php echo esc_attr( $field( 'button_text_contact' ) ); ?>" value="<?php echo esc_attr( $s['button_text_contact'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-contact-page"><?php esc_html_e( 'Contact Page', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<?php
									wp_dropdown_pages(
										array(
											'name'              => $field( 'contact_page' ),
											'id'                => 'wsi-contact-page',
											'selected'          => (int) $s['contact_page'],
											'show_option_none'  => __( '— Select a page —', 'woocommerce-stock-inquiry' ),
											'option_none_value' => '0',
										)
									);
									?>
									<p class="description"><?php esc_html_e( 'Customers land on this page with the product added to the address, e.g. /contact-us/?wsi_product=123. Works with any form plugin.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Open in', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<label class="wsi-radio"><input type="radio" name="<?php echo esc_attr( $field( 'contact_target' ) ); ?>" value="_self" <?php checked( $s['contact_target'], '_self' ); ?> /> <?php esc_html_e( 'Same Tab', 'woocommerce-stock-inquiry' ); ?></label>
									<label class="wsi-radio"><input type="radio" name="<?php echo esc_attr( $field( 'contact_target' ) ); ?>" value="_blank" <?php checked( $s['contact_target'], '_blank' ); ?> /> <?php esc_html_e( 'New Tab', 'woocommerce-stock-inquiry' ); ?></label>
								</td>
							</tr>
						</table>
					</div>

					<!-- WhatsApp method -->
					<div class="wsi-method-fields" data-method="whatsapp">
						<h2><?php esc_html_e( 'WhatsApp', 'woocommerce-stock-inquiry' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsi-button-text-whatsapp"><?php esc_html_e( 'Button Text', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wsi-button-text-whatsapp" maxlength="60" name="<?php echo esc_attr( $field( 'button_text_whatsapp' ) ); ?>" value="<?php echo esc_attr( $s['button_text_whatsapp'] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-whatsapp-number"><?php esc_html_e( 'WhatsApp Number', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<input type="text" class="regular-text" id="wsi-whatsapp-number" placeholder="+15551234567" name="<?php echo esc_attr( $field( 'whatsapp_number' ) ); ?>" value="<?php echo esc_attr( '' !== $s['whatsapp_number'] ? '+' . $s['whatsapp_number'] : '' ); ?>" />
									<p class="description"><?php esc_html_e( 'International format with country code. Spaces/dashes removed automatically.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-whatsapp-message"><?php esc_html_e( 'Message Template', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<textarea id="wsi-whatsapp-message" class="large-text" rows="5" maxlength="1000" name="<?php echo esc_attr( $field( 'whatsapp_message' ) ); ?>"><?php echo esc_textarea( $s['whatsapp_message'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Placeholders:', 'woocommerce-stock-inquiry' ); ?> <code>{product_name}</code> <code>{product_url}</code></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Message Preview', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<div class="wsi-bubble-demo" aria-label="<?php esc_attr_e( 'WhatsApp message preview', 'woocommerce-stock-inquiry' ); ?>">
										<div class="wsi-bubble wsi-bubble--in"><?php esc_html_e( 'Hello! How can we help you?', 'woocommerce-stock-inquiry' ); ?></div>
										<div class="wsi-bubble wsi-bubble--out" id="wsi-wa-bubble"><span id="wsi-wa-bubble-text"><?php echo esc_html( $wa_bubble_start ); ?></span></div>
									</div>
									<p class="description"><?php esc_html_e( 'The full message the customer will send. Updates live as you type the template.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Product Details', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<?php $toggle( 'whatsapp_auto_name', __( 'Automatically add product name', 'woocommerce-stock-inquiry' ) ); ?><br />
									<?php $toggle( 'whatsapp_auto_url', __( 'Add product URL', 'woocommerce-stock-inquiry' ) ); ?>
									<p class="description"><?php esc_html_e( 'Appended only if your template does not already include the placeholder.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Open in', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<label class="wsi-radio"><input type="radio" name="<?php echo esc_attr( $field( 'whatsapp_target' ) ); ?>" value="_self" <?php checked( $s['whatsapp_target'], '_self' ); ?> /> <?php esc_html_e( 'Same Tab', 'woocommerce-stock-inquiry' ); ?></label>
									<label class="wsi-radio"><input type="radio" name="<?php echo esc_attr( $field( 'whatsapp_target' ) ); ?>" value="_blank" <?php checked( $s['whatsapp_target'], '_blank' ); ?> /> <?php esc_html_e( 'New Tab', 'woocommerce-stock-inquiry' ); ?></label>
								</td>
							</tr>
						</table>
					</div>

					<?php
					foreach ( $methods as $method_id => $method ) {
						if ( 'contact' !== $method_id && 'whatsapp' !== $method_id ) {
							echo '<div class="wsi-method-fields" data-method="' . esc_attr( $method_id ) . '">';
							do_action( 'wsi_admin_method_fields', $method_id, $s );
							echo '</div>';
						}
					}
					?>
				</div>

				<!-- ======== HEADING ======== -->
				<div class="wsi-tab" data-tab="heading">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Heading', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="wsi-section-intro"><?php esc_html_e( 'A title shown above the inquiry button.', 'woocommerce-stock-inquiry' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show Heading', 'woocommerce-stock-inquiry' ); ?></th>
							<td><?php $toggle( 'enable_heading', __( 'Show a heading above the inquiry button', 'woocommerce-stock-inquiry' ) ); ?></td>
						</tr>
					</table>
					<div data-depends="enable_heading">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsi-heading-text"><?php esc_html_e( 'Heading Text', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wsi-heading-text" maxlength="100" name="<?php echo esc_attr( $field( 'heading_text' ) ); ?>" value="<?php echo esc_attr( $s['heading_text'] ); ?>" /></td>
							</tr>
							<?php $where_row( 'heading' ); ?>
							<?php $font_row( 'heading_font_family', __( 'Font Family', 'woocommerce-stock-inquiry' ) ); ?>
							<?php $number_row( 'heading_size', __( 'Font Size', 'woocommerce-stock-inquiry' ), 8, 48 ); ?>
							<?php $weight_row( 'heading_weight', __( 'Font Weight', 'woocommerce-stock-inquiry' ) ); ?>
							<?php $color_row( 'heading_color', __( 'Text Color', 'woocommerce-stock-inquiry' ) ); ?>
						</table>
					</div>
				</div>

				<!-- ======== BUTTON ======== -->
				<div class="wsi-tab" data-tab="button">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Button', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="wsi-section-intro"><?php esc_html_e( 'Button text is set in the Inquiry Method tab. Everything below updates the live preview instantly.', 'woocommerce-stock-inquiry' ); ?></p>
					<table class="form-table" role="presentation">
						<?php $font_row( 'btn_font_family', __( 'Font Family', 'woocommerce-stock-inquiry' ) ); ?>
						<?php $number_row( 'font_size', __( 'Font Size', 'woocommerce-stock-inquiry' ), 8, 48 ); ?>
						<?php $weight_row( 'font_weight', __( 'Font Weight', 'woocommerce-stock-inquiry' ) ); ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Padding', 'woocommerce-stock-inquiry' ); ?></th>
							<td class="wsi-padding">
								<?php
								$paddings = array(
									'pad_top'    => __( 'Top', 'woocommerce-stock-inquiry' ),
									'pad_right'  => __( 'Right', 'woocommerce-stock-inquiry' ),
									'pad_bottom' => __( 'Bottom', 'woocommerce-stock-inquiry' ),
									'pad_left'   => __( 'Left', 'woocommerce-stock-inquiry' ),
								);
								foreach ( $paddings as $pad_key => $pad_label ) :
									?>
									<label>
										<span><?php echo esc_html( $pad_label ); ?></span>
										<input type="number" class="small-text" min="0" max="80" name="<?php echo esc_attr( $field( $pad_key ) ); ?>" value="<?php echo esc_attr( $s[ $pad_key ] ); ?>" />
									</label>
								<?php endforeach; ?>
								px
							</td>
						</tr>
						<?php $number_row( 'radius', __( 'Border Radius', 'woocommerce-stock-inquiry' ), 0, 100 ); ?>
						<?php $number_row( 'border_width', __( 'Border Width', 'woocommerce-stock-inquiry' ), 0, 20 ); ?>
						<?php
						$colors = array(
							'bg'                 => __( 'Normal Background', 'woocommerce-stock-inquiry' ),
							'color'              => __( 'Normal Text', 'woocommerce-stock-inquiry' ),
							'border_color'       => __( 'Normal Border', 'woocommerce-stock-inquiry' ),
							'hover_bg'           => __( 'Hover Background', 'woocommerce-stock-inquiry' ),
							'hover_color'        => __( 'Hover Text', 'woocommerce-stock-inquiry' ),
							'hover_border_color' => __( 'Hover Border', 'woocommerce-stock-inquiry' ),
						);
						foreach ( $colors as $color_key => $color_label ) {
							$color_row( $color_key, $color_label );
						}
						?>
						<tr>
							<th scope="row"><label for="wsi-button-align"><?php esc_html_e( 'Alignment', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-button-align" name="<?php echo esc_attr( $field( 'button_align' ) ); ?>">
									<option value="left" <?php selected( $s['button_align'], 'left' ); ?>><?php esc_html_e( 'Left', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="center" <?php selected( $s['button_align'], 'center' ); ?>><?php esc_html_e( 'Center', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="right" <?php selected( $s['button_align'], 'right' ); ?>><?php esc_html_e( 'Right', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="block" <?php selected( $s['button_align'], 'block' ); ?>><?php esc_html_e( 'Justify (Full Width)', 'woocommerce-stock-inquiry' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Position', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<?php
								$positions = array(
									'replace' => __( 'Replace Add to Cart', 'woocommerce-stock-inquiry' ),
									'before'  => __( 'Before Add to Cart', 'woocommerce-stock-inquiry' ),
									'after'   => __( 'After Add to Cart', 'woocommerce-stock-inquiry' ),
								);
								foreach ( $positions as $position_key => $position_label ) :
									?>
									<label class="wsi-radio"><input type="radio" name="<?php echo esc_attr( $field( 'position' ) ); ?>" value="<?php echo esc_attr( $position_key ); ?>" <?php checked( $s['position'], $position_key ); ?> /> <?php echo esc_html( $position_label ); ?></label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( '"Before" and "After" apply when stock is low but still purchasable.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- ======== DESCRIPTION ======== -->
				<div class="wsi-tab" data-tab="desc">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Description', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="wsi-section-intro"><?php esc_html_e( 'A short text shown below the inquiry button. The text used depends on the selected inquiry method.', 'woocommerce-stock-inquiry' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show Description', 'woocommerce-stock-inquiry' ); ?></th>
							<td><?php $toggle( 'enable_desc', __( 'Show a short description below the inquiry button', 'woocommerce-stock-inquiry' ) ); ?></td>
						</tr>
					</table>
					<div data-depends="enable_desc">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsi-desc-text-contact"><?php esc_html_e( 'Contact Page Text', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><textarea id="wsi-desc-text-contact" class="large-text" rows="2" maxlength="300" name="<?php echo esc_attr( $field( 'desc_text_contact' ) ); ?>"><?php echo esc_textarea( $s['desc_text_contact'] ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-desc-text-whatsapp"><?php esc_html_e( 'WhatsApp Text', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><textarea id="wsi-desc-text-whatsapp" class="large-text" rows="2" maxlength="300" name="<?php echo esc_attr( $field( 'desc_text_whatsapp' ) ); ?>"><?php echo esc_textarea( $s['desc_text_whatsapp'] ); ?></textarea></td>
							</tr>
							<?php $where_row( 'desc' ); ?>
							<?php $font_row( 'desc_font_family', __( 'Font Family', 'woocommerce-stock-inquiry' ) ); ?>
							<?php $number_row( 'desc_size', __( 'Font Size', 'woocommerce-stock-inquiry' ), 8, 48 ); ?>
							<?php $color_row( 'desc_color', __( 'Text Color', 'woocommerce-stock-inquiry' ) ); ?>
						</table>
					</div>
				</div>

				<!-- ======== BRANDING ======== -->
				<div class="wsi-tab" data-tab="branding">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Branding', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="wsi-section-intro"><?php esc_html_e( 'The small "Powered By SK" line under the section. Font family, size and placement are adjustable; the color is fixed.', 'woocommerce-stock-inquiry' ); ?></p>
					<table class="form-table" role="presentation">
						<?php $where_row( 'branding' ); ?>
						<?php $font_row( 'branding_font_family', __( 'Font Family', 'woocommerce-stock-inquiry' ) ); ?>
						<?php $number_row( 'branding_size', __( 'Font Size', 'woocommerce-stock-inquiry' ), 8, 24 ); ?>
						<tr>
							<th scope="row"><label for="wsi-branding-align"><?php esc_html_e( 'Placement', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-branding-align" name="<?php echo esc_attr( $field( 'branding_align' ) ); ?>">
									<option value="left" <?php selected( $s['branding_align'], 'left' ); ?>><?php esc_html_e( 'Left', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="center" <?php selected( $s['branding_align'], 'center' ); ?>><?php esc_html_e( 'Center', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="right" <?php selected( $s['branding_align'], 'right' ); ?>><?php esc_html_e( 'Right', 'woocommerce-stock-inquiry' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<!-- ======== PRODUCT RULES ======== -->
				<div class="wsi-tab" data-tab="rules">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wsi-threshold"><?php esc_html_e( 'Show Inquiry When Stock Is Less Than', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<input type="number" class="small-text" id="wsi-threshold" min="0" max="100000" name="<?php echo esc_attr( $field( 'stock_threshold' ) ); ?>" value="<?php echo esc_attr( $s['stock_threshold'] ); ?>" />
								<p class="description"><?php esc_html_e( 'The inquiry button appears when stock is below this number. Use 0 to switch only when actually out of stock.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-excluded-products"><?php esc_html_e( 'Exclude Products', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-excluded-products" class="wc-product-search" multiple="multiple" style="width:400px;max-width:100%;" name="<?php echo esc_attr( $field( 'excluded_products' ) ); ?>[]" data-placeholder="<?php esc_attr_e( 'Search product name...', 'woocommerce-stock-inquiry' ); ?>" data-action="woocommerce_json_search_products">
									<?php foreach ( $selected_products as $excluded_id => $excluded_name ) : ?>
										<option value="<?php echo esc_attr( $excluded_id ); ?>" selected="selected"><?php echo esc_html( $excluded_name ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'These products keep normal WooCommerce behavior.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-excluded-categories"><?php esc_html_e( 'Exclude Categories', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-excluded-categories" class="wc-enhanced-select" multiple="multiple" style="width:400px;max-width:100%;" name="<?php echo esc_attr( $field( 'excluded_categories' ) ); ?>[]" data-placeholder="<?php esc_attr_e( 'Select categories...', 'woocommerce-stock-inquiry' ); ?>">
									<?php foreach ( $categories as $category ) : ?>
										<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( in_array( (int) $category->term_id, array_map( 'intval', (array) $s['excluded_categories'] ), true ) ); ?>><?php echo esc_html( $category->name ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Products in these categories keep normal WooCommerce behavior.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- ======== ADVANCED ======== -->
				<div class="wsi-tab" data-tab="advanced">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Contact Form Pre-fill', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<p><?php esc_html_e( 'On your contact page you can show or pre-fill the product with these shortcodes:', 'woocommerce-stock-inquiry' ); ?></p>
								<p><code>[wsi_product]</code> <?php esc_html_e( 'product name', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_product field="url"]</code> <?php esc_html_e( 'product link', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_product field="id"]</code> <?php esc_html_e( 'product ID', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Reset', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<button type="submit" form="wsi-reset-form" class="button wsi-reset"><?php esc_html_e( 'Reset All Settings to Defaults', 'woocommerce-stock-inquiry' ); ?></button>
							</td>
						</tr>
					</table>
				</div>

				</div> <!-- .wsi-tab-contents -->
				<?php submit_button(); ?>
			</div> <!-- .wsi-settings-column -->

			<!-- ====== RIGHT: STICKY LIVE PREVIEW (all tabs) ====== -->
			<div class="wsi-preview-column">
				<div class="wsi-preview-card" id="wsi-preview-card">
					<strong><?php esc_html_e( 'Live Preview', 'woocommerce-stock-inquiry' ); ?></strong>
					<div class="wsi-preview-ctx" role="group" aria-label="<?php esc_attr_e( 'Preview location', 'woocommerce-stock-inquiry' ); ?>">
						<button type="button" class="button button-small is-active" data-ctx="single" aria-pressed="true"><?php esc_html_e( 'Product page', 'woocommerce-stock-inquiry' ); ?></button>
						<button type="button" class="button button-small" data-ctx="loops" aria-pressed="false"><?php esc_html_e( 'Listings', 'woocommerce-stock-inquiry' ); ?></button>
					</div>
					<div class="wsi-preview-stage">
						<div class="wsi-inquiry-container" id="wsi-preview-container">
							<div class="wsi-inquiry-heading" id="wsi-preview-heading" style="display:none;"></div>
							<a href="#" class="button wsi-inquiry-button" id="wsi-preview-button"><?php echo esc_html( $s['button_text_contact'] ); ?></a>
							<div class="wsi-inquiry-desc" id="wsi-preview-desc" style="display:none;"></div>
							<div class="wsi-branding-sk" id="wsi-preview-branding">Powered By SK</div>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Heading → button → description → branding, as shown on the front end. Hover the button to preview hover colors.', 'woocommerce-stock-inquiry' ); ?></p>
				</div>
			</div> <!-- .wsi-preview-column -->

		</div> <!-- .wsi-main-layout -->
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wsi-reset-form">
		<input type="hidden" name="action" value="wsi_reset_settings" />
		<?php wp_nonce_field( 'wsi_reset_settings' ); ?>
	</form>
</div>
