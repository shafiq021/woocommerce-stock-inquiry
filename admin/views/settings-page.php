<?php
/**
 * Settings page markup.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

$s       = WSI_Settings::all();
$methods = WSI_Plugin::get_methods();
$coming  = WSI_Plugin::get_coming_soon_methods();
$field   = static function ( $key ) {
	return WSI_Settings::OPTION . '[' . $key . ']';
};

$font_families = array( 'inherit', 'Poppins', 'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Raleway', 'Nunito', 'Playfair Display', 'Georgia', 'serif', 'sans-serif', 'monospace' );

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
	'general' => __( 'General', 'woocommerce-stock-inquiry' ),
	'method'  => __( 'Inquiry Method', 'woocommerce-stock-inquiry' ),
	'design'  => __( 'Button Design', 'woocommerce-stock-inquiry' ),
	'rules'   => __( 'Product Rules', 'woocommerce-stock-inquiry' ),
	'advanced' => __( 'Advanced', 'woocommerce-stock-inquiry' ),
);
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

			<!-- ====== LEFT: SETTINGS COLUMN ====== -->
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
								<label class="wsi-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $field( 'enabled' ) ); ?>" value="1" <?php checked( 1, (int) $s['enabled'] ); ?> />
									<div class="wsi-checkmark"></div>
									<?php esc_html_e( 'Replace Add to Cart with the inquiry button when a product is unavailable', 'woocommerce-stock-inquiry' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Turn this off and WooCommerce behaves exactly as if the plugin was not installed.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Where to Use It', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<label class="wsi-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $field( 'enable_loops' ) ); ?>" value="1" <?php checked( 1, (int) $s['enable_loops'] ); ?> />
									<div class="wsi-checkmark"></div>
									<?php esc_html_e( 'Enable on Product Listings (shop, categories, search, related, upsells, cross-sells)', 'woocommerce-stock-inquiry' ); ?>
								</label><br />
								<label class="wsi-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $field( 'enable_single' ) ); ?>" value="1" <?php checked( 1, (int) $s['enable_single'] ); ?> />
									<div class="wsi-checkmark"></div>
									<?php esc_html_e( 'Enable on Single Product Pages', 'woocommerce-stock-inquiry' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Section Heading', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<label class="wsi-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $field( 'enable_heading' ) ); ?>" value="1" <?php checked( 1, (int) $s['enable_heading'] ); ?> />
									<div class="wsi-checkmark"></div>
									<?php esc_html_e( 'Show a heading above the inquiry button', 'woocommerce-stock-inquiry' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Short Description', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<label class="wsi-toggle">
									<input type="checkbox" name="<?php echo esc_attr( $field( 'enable_desc' ) ); ?>" value="1" <?php checked( 1, (int) $s['enable_desc'] ); ?> />
									<div class="wsi-checkmark"></div>
									<?php esc_html_e( 'Show a short description below the inquiry button', 'woocommerce-stock-inquiry' ); ?>
								</label>
							</td>
						</tr>
					</table>
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
									wp_dropdown_pages( array(
										'name'              => $field( 'contact_page' ),
										'id'                => 'wsi-contact-page',
										'selected'          => (int) $s['contact_page'],
										'show_option_none'  => __( '— Select a page —', 'woocommerce-stock-inquiry' ),
										'option_none_value' => '0',
									) );
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
								<th scope="row"><?php esc_html_e( 'WhatsApp Preview', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<div class="wsi-wa-phone-mockup">
										<div class="wsi-wa-phone-screen">
											<div class="wsi-wa-phone-header">
												<div class="wsi-wa-phone-avatar">Y</div>
												<div class="wsi-wa-phone-info">
													<div class="wsi-wa-phone-name"><?php esc_html_e( 'Your Store', 'woocommerce-stock-inquiry' ); ?></div>
													<div class="wsi-wa-phone-status">online</div>
												</div>
											</div>
											<div class="wsi-wa-phone-body">
												<div class="wsi-wa-phone-date"><?php esc_html_e( 'Today', 'woocommerce-stock-inquiry' ); ?></div>
												<div class="wsi-wa-msg-row">
													<div class="wsi-wa-bubble" id="wsi-wa-bubble">
														<span id="wsi-wa-bubble-text"><?php echo esc_html( str_replace( array( '{product_name}', '{product_url}' ), array( 'Example Product', 'https://example.com/product/example' ), $s['whatsapp_message'] ) ); ?></span>
														<span class="wsi-wa-time">10:30</span>
													</div>
												</div>
											</div>
										</div>
									</div>
									<p class="description"><?php esc_html_e( 'Live preview — updates as you type.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Product Details', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<label class="wsi-toggle">
										<input type="checkbox" name="<?php echo esc_attr( $field( 'whatsapp_auto_name' ) ); ?>" value="1" <?php checked( 1, (int) $s['whatsapp_auto_name'] ); ?> />
										<div class="wsi-checkmark"></div>
										<?php esc_html_e( 'Automatically add product name', 'woocommerce-stock-inquiry' ); ?>
									</label><br />
									<label class="wsi-toggle">
										<input type="checkbox" name="<?php echo esc_attr( $field( 'whatsapp_auto_url' ) ); ?>" value="1" <?php checked( 1, (int) $s['whatsapp_auto_url'] ); ?> />
										<div class="wsi-checkmark"></div>
										<?php esc_html_e( 'Add product URL', 'woocommerce-stock-inquiry' ); ?>
									</label>
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

				<!-- ======== BUTTON DESIGN ======== -->
				<div class="wsi-tab" data-tab="design">
					<p class="description wsi-note"><?php esc_html_e( 'Button text is set in Inquiry Method tab. Everything below updates the live preview instantly.', 'woocommerce-stock-inquiry' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wsi-font-size"><?php esc_html_e( 'Font Size', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="number" class="small-text" id="wsi-font-size" min="8" max="48" data-wsi="font_size" name="<?php echo esc_attr( $field( 'font_size' ) ); ?>" value="<?php echo esc_attr( $s['font_size'] ); ?>" /> px</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-font-weight"><?php esc_html_e( 'Font Weight', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-font-weight" data-wsi="font_weight" name="<?php echo esc_attr( $field( 'font_weight' ) ); ?>">
									<?php foreach ( array( 400 => 'Normal (400)', 500 => 'Medium (500)', 600 => 'Semi-bold (600)', 700 => 'Bold (700)', 800 => 'Extra bold (800)' ) as $weight => $weight_label ) : ?>
										<option value="<?php echo esc_attr( $weight ); ?>" <?php selected( (int) $s['font_weight'], $weight ); ?>><?php echo esc_html( $weight_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-btn-font-family"><?php esc_html_e( 'Button Font Family', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-btn-font-family" name="<?php echo esc_attr( $field( 'btn_font_family' ) ); ?>">
									<?php foreach ( $font_families as $ff ) : ?>
										<option value="<?php echo esc_attr( $ff ); ?>" <?php selected( $s['btn_font_family'], $ff ); ?>><?php echo esc_html( $ff ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-heading-font-family"><?php esc_html_e( 'Heading Font Family', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-heading-font-family" name="<?php echo esc_attr( $field( 'heading_font_family' ) ); ?>">
									<?php foreach ( $font_families as $ff ) : ?>
										<option value="<?php echo esc_attr( $ff ); ?>" <?php selected( $s['heading_font_family'], $ff ); ?>><?php echo esc_html( $ff ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-desc-font-family"><?php esc_html_e( 'Description Font Family', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<select id="wsi-desc-font-family" name="<?php echo esc_attr( $field( 'desc_font_family' ) ); ?>">
									<?php foreach ( $font_families as $ff ) : ?>
										<option value="<?php echo esc_attr( $ff ); ?>" <?php selected( $s['desc_font_family'], $ff ); ?>><?php echo esc_html( $ff ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
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
										<input type="number" class="small-text" min="0" max="80" data-wsi="<?php echo esc_attr( $pad_key ); ?>" name="<?php echo esc_attr( $field( $pad_key ) ); ?>" value="<?php echo esc_attr( $s[ $pad_key ] ); ?>" />
									</label>
								<?php endforeach; ?>
								px
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-radius"><?php esc_html_e( 'Border Radius', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="number" class="small-text" id="wsi-radius" min="0" max="100" data-wsi="radius" name="<?php echo esc_attr( $field( 'radius' ) ); ?>" value="<?php echo esc_attr( $s['radius'] ); ?>" /> px</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-border-width"><?php esc_html_e( 'Border Width', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="number" class="small-text" id="wsi-border-width" min="0" max="20" data-wsi="border_width" name="<?php echo esc_attr( $field( 'border_width' ) ); ?>" value="<?php echo esc_attr( $s['border_width'] ); ?>" /> px</td>
						</tr>
						<?php
						$colors = array(
							'bg'                 => __( 'Normal Background', 'woocommerce-stock-inquiry' ),
							'color'              => __( 'Normal Text', 'woocommerce-stock-inquiry' ),
							'border_color'       => __( 'Normal Border', 'woocommerce-stock-inquiry' ),
							'hover_bg'           => __( 'Hover Background', 'woocommerce-stock-inquiry' ),
							'hover_color'        => __( 'Hover Text', 'woocommerce-stock-inquiry' ),
							'hover_border_color' => __( 'Hover Border', 'woocommerce-stock-inquiry' ),
						);
						foreach ( $colors as $color_key => $color_label ) :
							?>
							<tr>
								<th scope="row"><label for="wsi-<?php echo esc_attr( $color_key ); ?>"><?php echo esc_html( $color_label ); ?></label></th>
								<td><input type="text" class="wsi-color" id="wsi-<?php echo esc_attr( $color_key ); ?>" data-wsi="<?php echo esc_attr( $color_key ); ?>" data-default-color="<?php echo esc_attr( $s[ $color_key ] ); ?>" name="<?php echo esc_attr( $field( $color_key ) ); ?>" value="<?php echo esc_attr( $s[ $color_key ] ); ?>" /></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Button Alignment', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( $field( 'button_align' ) ); ?>">
									<option value="left" <?php selected( $s['button_align'], 'left' ); ?>><?php esc_html_e( 'Left', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="center" <?php selected( $s['button_align'], 'center' ); ?>><?php esc_html_e( 'Center', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="right" <?php selected( $s['button_align'], 'right' ); ?>><?php esc_html_e( 'Right', 'woocommerce-stock-inquiry' ); ?></option>
									<option value="block" <?php selected( $s['button_align'], 'block' ); ?>><?php esc_html_e( 'Justify (Full Width)', 'woocommerce-stock-inquiry' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Button Position', 'woocommerce-stock-inquiry' ); ?></th>
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

					<hr />
					<h2><?php esc_html_e( 'Extra Display Elements', 'woocommerce-stock-inquiry' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wsi-heading-text"><?php esc_html_e( 'Heading Text', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="text" class="regular-text" id="wsi-heading-text" name="<?php echo esc_attr( $field( 'heading_text' ) ); ?>" value="<?php echo esc_attr( $s['heading_text'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-heading-color"><?php esc_html_e( 'Heading Color', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="text" class="wsi-color" id="wsi-heading-color" name="<?php echo esc_attr( $field( 'heading_color' ) ); ?>" value="<?php echo esc_attr( $s['heading_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-heading-size"><?php esc_html_e( 'Heading Font Size', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="number" class="small-text" id="wsi-heading-size" min="8" max="48" name="<?php echo esc_attr( $field( 'heading_size' ) ); ?>" value="<?php echo esc_attr( $s['heading_size'] ); ?>" /> px</td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-desc-text-contact"><?php esc_html_e( 'Contact Form Text', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><textarea id="wsi-desc-text-contact" class="large-text" rows="2" name="<?php echo esc_attr( $field( 'desc_text_contact' ) ); ?>"><?php echo esc_textarea( $s['desc_text_contact'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-desc-text-whatsapp"><?php esc_html_e( 'WhatsApp Text', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><textarea id="wsi-desc-text-whatsapp" class="large-text" rows="2" name="<?php echo esc_attr( $field( 'desc_text_whatsapp' ) ); ?>"><?php echo esc_textarea( $s['desc_text_whatsapp'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-desc-color"><?php esc_html_e( 'Description Color', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="text" class="wsi-color" id="wsi-desc-color" name="<?php echo esc_attr( $field( 'desc_color' ) ); ?>" value="<?php echo esc_attr( $s['desc_color'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="wsi-desc-size"><?php esc_html_e( 'Description Font Size', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><input type="number" class="small-text" id="wsi-desc-size" min="8" max="48" name="<?php echo esc_attr( $field( 'desc_size' ) ); ?>" value="<?php echo esc_attr( $s['desc_size'] ); ?>" /> px</td>
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
								<select id="wsi-excluded-products" class="wc-product-search" multiple="multiple" style="width:400px;" name="<?php echo esc_attr( $field( 'excluded_products' ) ); ?>[]" data-placeholder="<?php esc_attr_e( 'Search product name...', 'woocommerce-stock-inquiry' ); ?>" data-action="woocommerce_json_search_products">
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
								<select id="wsi-excluded-categories" class="wc-enhanced-select" multiple="multiple" style="width:400px;" name="<?php echo esc_attr( $field( 'excluded_categories' ) ); ?>[]" data-placeholder="<?php esc_attr_e( 'Select categories...', 'woocommerce-stock-inquiry' ); ?>">
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

			<!-- ====== RIGHT: PREVIEW COLUMN ====== -->
			<div class="wsi-preview-column">
				<div class="wsi-preview-card" id="wsi-preview-card">
					<strong><?php esc_html_e( 'Live Preview — Front-End Section', 'woocommerce-stock-inquiry' ); ?></strong>
					<div class="wsi-preview-stage" id="wsi-preview-container">
						<div id="wsi-preview-heading" class="wsi-inquiry-heading" style="display:none;"></div>
						<a href="#" class="button wsi-inquiry-button" id="wsi-preview-button"><?php echo esc_html( $s['button_text_contact'] ); ?></a>
						<div id="wsi-preview-desc" class="wsi-inquiry-desc" style="display:none;"></div>
					</div>
					<p class="description"><?php esc_html_e( 'Hover over the button to preview hover colors.', 'woocommerce-stock-inquiry' ); ?></p>
				</div>
			</div> <!-- .wsi-preview-column -->

		</div> <!-- .wsi-main-layout -->
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wsi-reset-form">
		<input type="hidden" name="action" value="wsi_reset_settings" />
		<?php wp_nonce_field( 'wsi_reset_settings' ); ?>
	</form>
</div>
