<?php
/**
 * Settings page markup.
 *
 * Layout: tabs + controls on the left, sticky live preview on the right (visible on every tab).
 * Each element (Heading, Button, Description) keeps all of its controls in its own tab.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

// This file is included inside WSI_Admin::render_page(), so these variables are local to that method.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited

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

/** "Where to show" row for heading / description. */
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
			<?php if ( 'inherit' !== $s[ $key ] || false ) : ?>
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

$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $categories ) ) {
	$categories = array();
}

$active_method = WSI_Plugin::get_active_method();
$issues        = WSI_Validator::check( $s );
$issue_tabs    = array();
foreach ( $issues as $wsi_issue ) {
	if ( 'info' !== $wsi_issue['severity'] ) {
		$issue_tabs[ $wsi_issue['tab'] ] = true;
	}
}

$tabs = array(
	'general'      => __( 'General', 'woocommerce-stock-inquiry' ),
	'trigger'      => __( 'Trigger', 'woocommerce-stock-inquiry' ),
	'method'       => __( 'Inquiry Method', 'woocommerce-stock-inquiry' ),
	'messages'     => __( 'Messages', 'woocommerce-stock-inquiry' ),
	'products'     => __( 'Products', 'woocommerce-stock-inquiry' ),
	'rules'        => __( 'Rules', 'woocommerce-stock-inquiry' ),
	'display'      => __( 'Display', 'woocommerce-stock-inquiry' ),
	'button'       => __( 'Button', 'woocommerce-stock-inquiry' ),
	'heading'      => __( 'Heading', 'woocommerce-stock-inquiry' ),
	'desc'         => __( 'Description', 'woocommerce-stock-inquiry' ),
	'integrations' => __( 'Integrations', 'woocommerce-stock-inquiry' ),
	'notifications' => __( 'Notifications', 'woocommerce-stock-inquiry' ),
	'advanced'     => __( 'Advanced', 'woocommerce-stock-inquiry' ),
);

$sample          = WSI_Placeholders::sample_values();
$wa_bubble_start = WSI_Placeholders::replace( (string) $s['whatsapp_message'] );
$mail_subject    = WSI_Placeholders::replace( (string) $s['email_subject'] );
$mail_body       = WSI_Placeholders::replace( (string) $s['email_message'] );
$popups          = WSI_Elementor::get_popups();
$elementor_on    = WSI_Elementor::is_active();
$elementor_pro   = WSI_Elementor::is_pro_active();

/** Test button for a method. */
$test_button = static function ( $method_id, $label ) {
	printf(
		'<p><button type="button" class="button wsi-test" data-kind="%1$s">%2$s</button> <span class="wsi-test-result" data-kind="%1$s" role="status" aria-live="polite"></span></p>',
		esc_attr( $method_id ),
		esc_html( $label )
	);
};

/** Text input row. */
$text_row = static function ( $key, $label, $maxlength = 60, $type = 'text', $placeholder = '' ) use ( $field, $s ) {
	$id = 'wsi-' . str_replace( '_', '-', $key );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td><input type="<?php echo esc_attr( $type ); ?>" class="regular-text" id="<?php echo esc_attr( $id ); ?>" maxlength="<?php echo esc_attr( $maxlength ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" name="<?php echo esc_attr( $field( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" /></td>
	</tr>
	<?php
};

/** Open-in row. */
$target_row = static function ( $key ) use ( $field, $s ) {
	?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Open in', 'woocommerce-stock-inquiry' ); ?></th>
		<td>
			<label class="wsi-radio"><input type="radio" name="<?php echo esc_attr( $field( $key ) ); ?>" value="_self" <?php checked( $s[ $key ], '_self' ); ?> /> <?php esc_html_e( 'Same Tab', 'woocommerce-stock-inquiry' ); ?></label>
			<label class="wsi-radio"><input type="radio" name="<?php echo esc_attr( $field( $key ) ); ?>" value="_blank" <?php checked( $s[ $key ], '_blank' ); ?> /> <?php esc_html_e( 'New Tab', 'woocommerce-stock-inquiry' ); ?></label>
		</td>
	</tr>
	<?php
};
?>
<div class="wrap wsi-wrap">
	<header class="wsi-hero">
		<span class="wsi-hero__icon" aria-hidden="true"><span class="dashicons dashicons-email-alt"></span></span>
		<div class="wsi-hero__text">
			<h1 class="wsi-hero__title"><?php esc_html_e( 'Stock Inquiry', 'woocommerce-stock-inquiry' ); ?></h1>
			<p class="wsi-hero__sub"><?php esc_html_e( 'Quote requests, product inquiries and lead management for WooCommerce. Use it for out-of-stock products, wholesale (B2B) catalogues or any product you sell by quotation.', 'woocommerce-stock-inquiry' ); ?></p>
		</div>
		<div class="wsi-hero__meta">
			<span class="wsi-pill">v<?php echo esc_html( WSI_VERSION ); ?></span>
			<a class="wsi-pill" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WSI_Inquiry_Admin::PAGE ) ); ?>"><?php esc_html_e( 'Open inquiries', 'woocommerce-stock-inquiry' ); ?> &rarr;</a>
		</div>
	</header>
	<hr class="wp-header-end" />

	<?php settings_errors(); ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['wsi_reset'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings were reset to their defaults.', 'woocommerce-stock-inquiry' ); ?></p></div>
	<?php endif; ?>

	<?php foreach ( $issues as $wsi_issue ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $wsi_issue['severity'] ? 'warning' : ( 'info' === $wsi_issue['severity'] ? 'info' : 'warning' ) ); ?> inline">
			<p>
				<?php echo esc_html( $wsi_issue['message'] ); ?>
				<?php if ( 'error' === $wsi_issue['severity'] ) : ?>
					<?php esc_html_e( 'Until this is fixed, products keep the normal WooCommerce button.', 'woocommerce-stock-inquiry' ); ?>
				<?php endif; ?>
				<a href="#<?php echo esc_attr( $wsi_issue['tab'] ); ?>" class="wsi-goto" data-tab="<?php echo esc_attr( $wsi_issue['tab'] ); ?>"><?php esc_html_e( 'Open', 'woocommerce-stock-inquiry' ); ?></a>
			</p>
		</div>
	<?php endforeach; ?>

	<form method="post" action="options.php" id="wsi-settings-form">
		<?php settings_fields( WSI_Settings::GROUP ); ?>

		<div class="wsi-main-layout">

			<!-- ====== LEFT: CONTROLS ====== -->
			<div class="wsi-settings-column">
				<nav class="nav-tab-wrapper wsi-tabs">
					<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
						<a href="#<?php echo esc_attr( $tab_id ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $tab_id ); ?>"><?php echo esc_html( $tab_label ); ?>
						<?php
						if ( isset( $issue_tabs[ $tab_id ] ) ) :
							?>
							<span class="wsi-tab-issue" title="<?php esc_attr_e( 'Needs attention', 'woocommerce-stock-inquiry' ); ?>">!</span><?php endif; ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="wsi-tab-contents">

				<!-- ======== GENERAL ======== -->
				<div class="wsi-tab" data-tab="general">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Stock Inquiry', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<?php $toggle( 'enabled', __( 'Replace Add to Cart with the inquiry button when a product matches the trigger', 'woocommerce-stock-inquiry' ) ); ?>
								<p class="description"><?php esc_html_e( 'Turn this off and WooCommerce behaves exactly as if the plugin was not installed.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="description wsi-note"><?php esc_html_e( 'No shortcode is needed: the button is placed automatically through WooCommerce. The plugin checks each product\'s current stock on every page load, so restocking a product brings Add to Cart back automatically.', 'woocommerce-stock-inquiry' ); ?></p>
					<p class="description wsi-note"><?php esc_html_e( 'Quick start: 1) choose when the button appears (Trigger), 2) choose how customers reach you (Inquiry Method), 3) check the live preview on the right.', 'woocommerce-stock-inquiry' ); ?></p>
				</div>

				<!-- ======== TRIGGER ======== -->
				<div class="wsi-tab" data-tab="trigger">
					<h2 class="wsi-section-title"><?php esc_html_e( 'When should the inquiry button appear?', 'woocommerce-stock-inquiry' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Trigger', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<fieldset>
									<?php
									$trigger_help = array(
										'out_only'     => __( 'Only when WooCommerce marks the product out of stock.', 'woocommerce-stock-inquiry' ),
										'below'        => __( 'Only when managed stock is below the threshold (an out-of-stock product with managed stock is below it too).', 'woocommerce-stock-inquiry' ),
										'out_or_below' => __( 'When out of stock, or when managed stock is below the threshold. This is how version 1 worked.', 'woocommerce-stock-inquiry' ),
										'always'       => __( 'Every product, whatever its stock.', 'woocommerce-stock-inquiry' ),
										'disabled'     => __( 'Never (only products with their own custom settings show the button).', 'woocommerce-stock-inquiry' ),
									);
									foreach ( WSI_Settings::trigger_modes() as $mode => $mode_label ) :
										?>
										<label class="wsi-radio wsi-block">
											<input type="radio" name="<?php echo esc_attr( $field( 'trigger_mode' ) ); ?>" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $s['trigger_mode'], $mode ); ?> />
											<strong><?php echo esc_html( $mode_label ); ?></strong>
											<span class="description"> &mdash; <?php echo esc_html( $trigger_help[ $mode ] ); ?></span>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
					</table>
					<div data-depends-mode="below,out_or_below">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsi-threshold"><?php esc_html_e( 'Stock Threshold', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<input type="number" class="small-text" id="wsi-threshold" min="0" max="100000" name="<?php echo esc_attr( $field( 'stock_threshold' ) ); ?>" value="<?php echo esc_attr( $s['stock_threshold'] ); ?>" />
									<p class="description"><?php esc_html_e( 'Applies to products with "Manage stock" on. Products with backorders allowed are never counted as low stock. A threshold of 0 never matches.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-threshold-compare"><?php esc_html_e( 'Comparison', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<select id="wsi-threshold-compare" name="<?php echo esc_attr( $field( 'threshold_compare' ) ); ?>">
										<option value="lt" <?php selected( $s['threshold_compare'], 'lt' ); ?>><?php esc_html_e( 'Stock is less than the threshold (default)', 'woocommerce-stock-inquiry' ); ?></option>
										<option value="lte" <?php selected( $s['threshold_compare'], 'lte' ); ?>><?php esc_html_e( 'Stock is less than or equal to the threshold', 'woocommerce-stock-inquiry' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
						<p class="description wsi-note" id="wsi-trigger-example"></p>
					</div>
					<p class="description wsi-note"><?php esc_html_e( 'Variable products are checked variation by variation, so the button follows the variation the customer selects. Individual products can override all of this in the product editor (Product data > Stock Inquiry).', 'woocommerce-stock-inquiry' ); ?></p>
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
							<?php $text_row( 'button_text_contact', __( 'Button Text', 'woocommerce-stock-inquiry' ) ); ?>
							<tr>
								<th scope="row"><label for="wsi-contact-page"><?php esc_html_e( 'Contact Page', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<?php
									// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the dropdown output; the arguments are plain labels.
									wp_dropdown_pages(
										array(
											'name'     => $field( 'contact_page' ),
											'id'       => 'wsi-contact-page',
											'selected' => (int) $s['contact_page'],
											'show_option_none' => __( '— Select a page —', 'woocommerce-stock-inquiry' ),
											'option_none_value' => '0',
										)
									);
									// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
									<p class="description"><?php esc_html_e( 'Customers land on this page with the product added to the address, e.g. /contact-us/?wsi_product=123. Works with any form plugin.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<?php $target_row( 'contact_target' ); ?>
						</table>
						<?php $test_button( 'contact', __( 'Test Contact Link', 'woocommerce-stock-inquiry' ) ); ?>
					</div>

					<!-- WhatsApp method -->
					<div class="wsi-method-fields" data-method="whatsapp">
						<h2><?php esc_html_e( 'WhatsApp', 'woocommerce-stock-inquiry' ); ?></h2>
						<table class="form-table" role="presentation">
							<?php $text_row( 'button_text_whatsapp', __( 'Button Text', 'woocommerce-stock-inquiry' ) ); ?>
							<tr>
								<th scope="row"><label for="wsi-whatsapp-number"><?php esc_html_e( 'WhatsApp Number', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<input type="text" class="regular-text" id="wsi-whatsapp-number" placeholder="+15551234567" name="<?php echo esc_attr( $field( 'whatsapp_number' ) ); ?>" value="<?php echo esc_attr( '' !== $s['whatsapp_number'] ? '+' . $s['whatsapp_number'] : '' ); ?>" />
									<p class="description"><?php esc_html_e( 'International format with country code. Spaces/dashes removed automatically. The message template is in the Messages tab.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
							<?php $target_row( 'whatsapp_target' ); ?>
						</table>

						<h3><?php esc_html_e( 'More WhatsApp numbers', 'woocommerce-stock-inquiry' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Optional. Add a number for each department, for example Furniture or Electronics, then pick it in the Rules tab or on a single product. The number above is the default and is used whenever a chosen number is missing. Customers only open a WhatsApp chat; nothing is sent automatically. A message here replaces the global WhatsApp message for that number.', 'woocommerce-stock-inquiry' ); ?></p>
						<?php
						$dest_row = static function ( $index, $dest ) use ( $field ) {
							$base = $field( 'whatsapp_destinations' ) . '[' . $index . ']';
							?>
							<tr class="wsi-dest-row">
								<td>
									<input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $dest['id'] ); ?>" />
									<label class="screen-reader-text"><?php esc_html_e( 'Name', 'woocommerce-stock-inquiry' ); ?></label>
									<input type="text" maxlength="60" placeholder="<?php esc_attr_e( 'Furniture department', 'woocommerce-stock-inquiry' ); ?>" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $dest['label'] ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text"><?php esc_html_e( 'WhatsApp number', 'woocommerce-stock-inquiry' ); ?></label>
									<input type="text" placeholder="+15551234567" name="<?php echo esc_attr( $base . '[number]' ); ?>" value="<?php echo esc_attr( '' !== $dest['number'] ? '+' . $dest['number'] : '' ); ?>" />
								</td>
								<td>
									<label class="screen-reader-text"><?php esc_html_e( 'Message (optional)', 'woocommerce-stock-inquiry' ); ?></label>
									<textarea rows="2" maxlength="1000" placeholder="<?php esc_attr_e( 'Use the global message', 'woocommerce-stock-inquiry' ); ?>" name="<?php echo esc_attr( $base . '[message]' ); ?>"><?php echo esc_textarea( $dest['message'] ); ?></textarea>
								</td>
								<td><button type="button" class="button-link button-link-delete wsi-dest-remove"><?php esc_html_e( 'Remove', 'woocommerce-stock-inquiry' ); ?></button></td>
							</tr>
							<?php
						};
						?>
						<div class="wsi-table-scroll">
						<table class="widefat striped wsi-dest-table">
							<thead><tr>
								<th><?php esc_html_e( 'Name', 'woocommerce-stock-inquiry' ); ?></th>
								<th><?php esc_html_e( 'WhatsApp number', 'woocommerce-stock-inquiry' ); ?></th>
								<th><?php esc_html_e( 'Message (optional)', 'woocommerce-stock-inquiry' ); ?></th>
								<th></th>
							</tr></thead>
							<tbody id="wsi-dest-body">
								<?php
								foreach ( WSI_Destinations::all() as $dest_index => $saved_dest ) {
									$dest_row( $dest_index, $saved_dest );
								}
								?>
							</tbody>
						</table>
						</div>
						<p><button type="button" class="button" id="wsi-add-dest"><?php esc_html_e( 'Add WhatsApp number', 'woocommerce-stock-inquiry' ); ?></button></p>
						<template id="wsi-dest-template">
							<?php $dest_row( '__INDEX__', array( 'id' => '', 'label' => '', 'number' => '', 'message' => '' ) ); ?>
						</template>

						<?php $test_button( 'whatsapp', __( 'Test WhatsApp', 'woocommerce-stock-inquiry' ) ); ?>
					</div>

					<!-- Inquiry form (modal) method -->
					<div class="wsi-method-fields" data-method="form">
						<h2><?php esc_html_e( 'Inquiry Form (modal)', 'woocommerce-stock-inquiry' ); ?></h2>
						<p class="description"><?php esc_html_e( 'The button opens a simple form (name, email, phone, message). Every submission is saved in WooCommerce > Inquiries, and you get an email. No form plugin is needed.', 'woocommerce-stock-inquiry' ); ?></p>
						<table class="form-table" role="presentation">
							<?php $text_row( 'button_text_form', __( 'Button Text', 'woocommerce-stock-inquiry' ) ); ?>
							<?php $text_row( 'form_title', __( 'Form Title', 'woocommerce-stock-inquiry' ), 100 ); ?>
							<?php $text_row( 'form_button_text', __( 'Submit Button Text', 'woocommerce-stock-inquiry' ) ); ?>
							<tr>
								<th scope="row"><label for="wsi-form-success"><?php esc_html_e( 'Success Message', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><textarea id="wsi-form-success" class="large-text" rows="2" maxlength="300" name="<?php echo esc_attr( $field( 'form_success' ) ); ?>"><?php echo esc_textarea( $s['form_success'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Shown only after the inquiry has been saved.', 'woocommerce-stock-inquiry' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Phone Field', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<?php $toggle( 'form_show_phone', __( 'Show a phone field', 'woocommerce-stock-inquiry' ) ); ?><br />
									<?php $toggle( 'form_require_phone', __( 'Make the phone number required', 'woocommerce-stock-inquiry' ) ); ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-form-followup"><?php esc_html_e( 'After Saving', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<select id="wsi-form-followup" name="<?php echo esc_attr( $field( 'form_followup' ) ); ?>">
										<option value="none" <?php selected( $s['form_followup'], 'none' ); ?>><?php esc_html_e( 'Show the success message only', 'woocommerce-stock-inquiry' ); ?></option>
										<option value="whatsapp" <?php selected( $s['form_followup'], 'whatsapp' ); ?>><?php esc_html_e( 'Also offer a "Continue on WhatsApp" button', 'woocommerce-stock-inquiry' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'The WhatsApp button uses the WhatsApp number from the WhatsApp settings. The customer chooses whether to open it, so a WhatsApp message is never counted as sent. The inquiry itself is already saved.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
						</table>
						<p><strong><?php esc_html_e( 'Use it anywhere with a shortcode:', 'woocommerce-stock-inquiry' ); ?></strong></p>
						<p><code>[wsi_inquiry_form]</code> <?php esc_html_e( 'a button that opens the form for the current product', 'woocommerce-stock-inquiry' ); ?><br />
						<code>[wsi_inquiry_form product_id="123" text="Ask about this" send="whatsapp"]</code></p>
					</div>

					<!-- Popup method -->
					<div class="wsi-method-fields" data-method="popup">
						<h2><?php esc_html_e( 'Popup', 'woocommerce-stock-inquiry' ); ?></h2>
						<?php if ( ! $elementor_on ) : ?>
							<div class="notice notice-warning inline"><p><?php esc_html_e( 'Elementor is not active. Install and activate Elementor Pro to use the popup method. The rest of the plugin works without it.', 'woocommerce-stock-inquiry' ); ?></p></div>
						<?php elseif ( ! $elementor_pro ) : ?>
							<div class="notice notice-warning inline"><p><?php esc_html_e( 'Elementor Pro is not active. The Popup Builder is part of Elementor Pro.', 'woocommerce-stock-inquiry' ); ?></p></div>
						<?php elseif ( ! $popups ) : ?>
							<div class="notice notice-info inline"><p><?php esc_html_e( 'No published Elementor popups were found. Create one in Templates > Popups, add your form to it, and publish it.', 'woocommerce-stock-inquiry' ); ?></p></div>
						<?php endif; ?>
						<table class="form-table" role="presentation">
							<?php $text_row( 'button_text_popup', __( 'Button Text', 'woocommerce-stock-inquiry' ) ); ?>
							<tr>
								<th scope="row"><label for="wsi-popup-provider"><?php esc_html_e( 'Popup Provider', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<select id="wsi-popup-provider" name="<?php echo esc_attr( $field( 'popup_provider' ) ); ?>">
										<option value="elementor" selected="selected"><?php esc_html_e( 'Elementor', 'woocommerce-stock-inquiry' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-popup-id"><?php esc_html_e( 'Select Popup', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td>
									<select id="wsi-popup-id" name="<?php echo esc_attr( $field( 'popup_id' ) ); ?>" <?php disabled( ! $elementor_on ); ?>>
										<option value="0"><?php esc_html_e( '— Select a popup —', 'woocommerce-stock-inquiry' ); ?></option>
										<?php foreach ( $popups as $popup_id => $popup_title ) : ?>
											<option value="<?php echo esc_attr( $popup_id ); ?>" <?php selected( (int) $s['popup_id'], $popup_id ); ?>><?php echo esc_html( $popup_title ); ?></option>
										<?php endforeach; ?>
										<?php if ( (int) $s['popup_id'] && ! isset( $popups[ (int) $s['popup_id'] ] ) ) : ?>
											<option value="<?php echo esc_attr( $s['popup_id'] ); ?>" selected="selected"><?php /* translators: %d: popup ID */ echo esc_html( sprintf( __( 'Popup #%d (not available)', 'woocommerce-stock-inquiry' ), (int) $s['popup_id'] ) ); ?></option>
										<?php endif; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Build the popup with Elementor and put your own form inside it. The button opens it. See the Integrations tab to pre-fill the product into your form.', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
						</table>
						<?php $test_button( 'popup', __( 'Test Popup', 'woocommerce-stock-inquiry' ) ); ?>
					</div>

					<!-- Email method -->
					<div class="wsi-method-fields" data-method="email">
						<h2><?php esc_html_e( 'Email', 'woocommerce-stock-inquiry' ); ?></h2>
						<table class="form-table" role="presentation">
							<?php $text_row( 'button_text_email', __( 'Button Text', 'woocommerce-stock-inquiry' ) ); ?>
							<?php $text_row( 'email_address', __( 'Email Address', 'woocommerce-stock-inquiry' ), 100, 'email', 'sales@example.com' ); ?>
						</table>
						<p class="description"><?php esc_html_e( 'The button opens the customer\'s own email app with the subject and message filled in (a mailto: link). Nothing is sent from your website, so delivery cannot be confirmed. The subject and message templates are in the Messages tab.', 'woocommerce-stock-inquiry' ); ?></p>
						<?php $test_button( 'email', __( 'Test Email', 'woocommerce-stock-inquiry' ) ); ?>
					</div>

					<!-- Phone method -->
					<div class="wsi-method-fields" data-method="phone">
						<h2><?php esc_html_e( 'Phone', 'woocommerce-stock-inquiry' ); ?></h2>
						<table class="form-table" role="presentation">
							<?php $text_row( 'button_text_phone', __( 'Button Text', 'woocommerce-stock-inquiry' ) ); ?>
							<?php $text_row( 'phone_number', __( 'Phone Number', 'woocommerce-stock-inquiry' ), 30, 'text', '+15551234567' ); ?>
						</table>
						<p class="description"><?php esc_html_e( 'Use the international format starting with + and the country code. Spaces and dashes are removed automatically. On phones the button starts a call.', 'woocommerce-stock-inquiry' ); ?></p>
						<?php $test_button( 'phone', __( 'Test Call Link', 'woocommerce-stock-inquiry' ) ); ?>
					</div>

					<?php
					$builtin_methods = array( 'contact', 'whatsapp', 'form', 'popup', 'email', 'phone' );
					foreach ( $methods as $method_id => $method ) {
						if ( ! in_array( $method_id, $builtin_methods, true ) ) {
							echo '<div class="wsi-method-fields" data-method="' . esc_attr( $method_id ) . '">';
							do_action( 'wsi_admin_method_fields', $method_id, $s );
							echo '</div>';
						}
					}
					?>
				</div>

				<!-- ======== MESSAGES ======== -->
				<div class="wsi-tab" data-tab="messages">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Message templates', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="wsi-section-intro"><?php esc_html_e( 'Placeholders are replaced with the real product details when a customer clicks the button. A product can have its own message in the product editor.', 'woocommerce-stock-inquiry' ); ?></p>

					<h3><?php esc_html_e( 'WhatsApp', 'woocommerce-stock-inquiry' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wsi-whatsapp-message"><?php esc_html_e( 'Message Template', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td>
								<textarea id="wsi-whatsapp-message" class="large-text" rows="6" maxlength="1000" name="<?php echo esc_attr( $field( 'whatsapp_message' ) ); ?>"><?php echo esc_textarea( $s['whatsapp_message'] ); ?></textarea>
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
					</table>

					<h3><?php esc_html_e( 'Email', 'woocommerce-stock-inquiry' ); ?></h3>
					<table class="form-table" role="presentation">
						<?php $text_row( 'email_subject', __( 'Subject', 'woocommerce-stock-inquiry' ), 200 ); ?>
						<tr>
							<th scope="row"><label for="wsi-email-message"><?php esc_html_e( 'Message', 'woocommerce-stock-inquiry' ); ?></label></th>
							<td><textarea id="wsi-email-message" class="large-text" rows="8" maxlength="2000" name="<?php echo esc_attr( $field( 'email_message' ) ); ?>"><?php echo esc_textarea( $s['email_message'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email Preview', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<div class="wsi-mail-demo">
									<div class="wsi-mail-subject" id="wsi-mail-subject"><?php echo esc_html( $mail_subject ); ?></div>
									<div class="wsi-mail-body" id="wsi-mail-body"><?php echo esc_html( $mail_body ); ?></div>
								</div>
							</td>
						</tr>
					</table>

					<h3><?php esc_html_e( 'Available placeholders', 'woocommerce-stock-inquiry' ); ?></h3>
					<table class="widefat striped wsi-placeholder-table">
						<tbody>
						<?php foreach ( WSI_Placeholders::tags() as $tag => $tag_help ) : ?>
							<tr><td><code><?php echo esc_html( $tag ); ?></code></td><td><?php echo esc_html( $tag_help ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- ======== PRODUCTS ======== -->
				<div class="wsi-tab" data-tab="products">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Exclusions', 'woocommerce-stock-inquiry' ); ?></h2>
					<table class="form-table" role="presentation">
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
					<h2 class="wsi-section-title"><?php esc_html_e( 'Per-product settings', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="description wsi-note"><?php esc_html_e( 'To change the trigger, threshold, method, button text or message for one product, edit the product and open Product data > Stock Inquiry > "Custom settings for this product". A product with custom settings ignores the exclusions above.', 'woocommerce-stock-inquiry' ); ?></p>
				</div>

				<!-- ======== RULES ======== -->
				<div class="wsi-tab" data-tab="rules">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Rules by category (quotes, wholesale, departments)', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="description wsi-note"><?php esc_html_e( 'Example for a wholesale (B2B) catalogue: category "Wholesale" > method "Inquiry Form (modal)" > show "Always show inquiry" > button text "Request a Quote". Products in that category then get a quotation button instead of Add to Cart, whatever their stock, and every request is saved under WooCommerce > Inquiries where you can update its status and email the customer.', 'woocommerce-stock-inquiry' ); ?></p>
					<p class="description wsi-note"><?php esc_html_e( 'Use a different inquiry method for products in a category. Priority: 1) the product\'s own custom settings, 2) the category rule, 3) the global method. If a product is in several categories with rules, the most specific category wins (a sub-category beats its parent); if they are equally specific, the rule listed first wins. A rule whose method is not set up is ignored and the global method is used. A rule can also change when the button appears and its text; the product\'s own custom settings still win, and your exclusions still keep normal WooCommerce behavior.', 'woocommerce-stock-inquiry' ); ?></p>

					<?php
					$rule_methods = array();
					foreach ( $methods as $rule_method_id => $rule_method ) {
						$rule_methods[ $rule_method_id ] = $rule_method->get_label();
					}
					$rule_row = static function ( $index, $rule ) use ( $field, $categories, $rule_methods ) {
						$base = $field( 'category_rules' ) . '[' . $index . ']';
						?>
						<tr class="wsi-rule-row">
							<td>
								<label class="screen-reader-text"><?php esc_html_e( 'Category', 'woocommerce-stock-inquiry' ); ?></label>
								<select name="<?php echo esc_attr( $base . '[category]' ); ?>">
									<option value="0"><?php esc_html_e( 'Select a category...', 'woocommerce-stock-inquiry' ); ?></option>
									<?php foreach ( $categories as $category ) : ?>
										<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( (int) $rule['category'], (int) $category->term_id ); ?>><?php echo esc_html( $category->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<label class="screen-reader-text"><?php esc_html_e( 'Inquiry method', 'woocommerce-stock-inquiry' ); ?></label>
								<select name="<?php echo esc_attr( $base . '[method]' ); ?>">
									<?php foreach ( $rule_methods as $method_value => $method_label ) : ?>
										<option value="<?php echo esc_attr( $method_value ); ?>" <?php selected( $rule['method'], $method_value ); ?>><?php echo esc_html( $method_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<label class="screen-reader-text"><?php esc_html_e( 'When to show the button', 'woocommerce-stock-inquiry' ); ?></label>
								<select name="<?php echo esc_attr( $base . '[trigger]' ); ?>">
									<option value=""><?php esc_html_e( 'Use the global trigger', 'woocommerce-stock-inquiry' ); ?></option>
									<?php foreach ( WSI_Settings::trigger_modes() as $trigger_value => $trigger_label ) : ?>
										<option value="<?php echo esc_attr( $trigger_value ); ?>" <?php selected( isset( $rule['trigger'] ) ? $rule['trigger'] : '', $trigger_value ); ?>><?php echo esc_html( $trigger_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<label class="screen-reader-text"><?php esc_html_e( 'Button text', 'woocommerce-stock-inquiry' ); ?></label>
								<input type="text" maxlength="60" placeholder="<?php esc_attr_e( 'Default text', 'woocommerce-stock-inquiry' ); ?>" name="<?php echo esc_attr( $base . '[button_text]' ); ?>" value="<?php echo esc_attr( isset( $rule['button_text'] ) ? $rule['button_text'] : '' ); ?>" />
							</td>
							<td>
								<label class="screen-reader-text"><?php esc_html_e( 'WhatsApp destination', 'woocommerce-stock-inquiry' ); ?></label>
								<select name="<?php echo esc_attr( $base . '[destination]' ); ?>">
									<?php foreach ( WSI_Destinations::choices() as $dest_value => $dest_label ) : ?>
										<option value="<?php echo esc_attr( $dest_value ); ?>" <?php selected( isset( $rule['destination'] ) ? $rule['destination'] : '', $dest_value ); ?>><?php echo esc_html( $dest_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><button type="button" class="button-link button-link-delete wsi-rule-remove"><?php esc_html_e( 'Remove', 'woocommerce-stock-inquiry' ); ?></button></td>
						</tr>
						<?php
					};
					?>
					<div class="wsi-table-scroll">
					<table class="widefat striped wsi-rules-table" >
						<thead><tr>
							<th><?php esc_html_e( 'Category', 'woocommerce-stock-inquiry' ); ?></th>
							<th><?php esc_html_e( 'Inquiry method', 'woocommerce-stock-inquiry' ); ?></th>
							<th><?php esc_html_e( 'When to show', 'woocommerce-stock-inquiry' ); ?></th>
							<th><?php esc_html_e( 'Button text', 'woocommerce-stock-inquiry' ); ?></th>
							<th><?php esc_html_e( 'WhatsApp number', 'woocommerce-stock-inquiry' ); ?></th>
							<th></th>
						</tr></thead>
						<tbody id="wsi-rules-body">
							<?php
							foreach ( WSI_Rules::all() as $rule_index => $saved_rule ) {
								$rule_row( $rule_index, $saved_rule );
							}
							?>
						</tbody>
					</table>
					</div>
					<p class="description wsi-rules-empty"<?php echo WSI_Rules::all() ? ' hidden' : ''; ?>><?php esc_html_e( 'No rules yet. Every product uses the global inquiry method.', 'woocommerce-stock-inquiry' ); ?></p>
					<p><button type="button" class="button" id="wsi-add-rule"><?php esc_html_e( 'Add rule', 'woocommerce-stock-inquiry' ); ?></button></p>
					<p class="description"><?php esc_html_e( 'Rows are saved in the order shown. Rows without a category are ignored.', 'woocommerce-stock-inquiry' ); ?></p>
					<template id="wsi-rule-template">
						<?php $rule_row( '__INDEX__', array( 'category' => 0, 'method' => (string) $s['method'], 'destination' => '', 'trigger' => '', 'button_text' => '' ) ); ?>
					</template>
				</div>

				<!-- ======== DISPLAY ======== -->
				<div class="wsi-tab" data-tab="display">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Where should the button appear?', 'woocommerce-stock-inquiry' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Single Product Page', 'woocommerce-stock-inquiry' ); ?></th>
							<td><?php $toggle( 'enable_single', __( 'Show the inquiry button on single product pages', 'woocommerce-stock-inquiry' ) ); ?></td>
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
								<p class="description"><?php esc_html_e( '"Before" and "After" matter when the product can still be bought, for example low stock or the "Always show" trigger. Out-of-stock products have no Add to Cart to sit next to. Applies to listings too.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Product Listings', 'woocommerce-stock-inquiry' ); ?></th>
							<td><?php $toggle( 'enable_loops', __( 'Show the inquiry button in product listings', 'woocommerce-stock-inquiry' ) ); ?></td>
						</tr>
					</table>
					<div data-depends="enable_loops">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Listing Locations', 'woocommerce-stock-inquiry' ); ?></th>
								<td>
									<?php foreach ( WSI_Settings::locations() as $location_key => $location_label ) : ?>
										<?php $toggle( $location_key, $location_label ); ?><br />
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Locations are detected from WooCommerce. A theme or builder that prints its own product grid may be reported as "Other product grids".', 'woocommerce-stock-inquiry' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
					<p class="description wsi-note"><?php esc_html_e( 'Heading and description each have their own "Where to Show" setting in their tabs, so you can show the full section on the product page and only the button in listings.', 'woocommerce-stock-inquiry' ); ?></p>
					<p class="description wsi-note"><?php esc_html_e( 'The [wsi_button] shortcode is optional and only for places WooCommerce does not reach. Automatic placement does not need it.', 'woocommerce-stock-inquiry' ); ?></p>
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
							<?php
							$desc_fields = array(
								'contact'  => __( 'Contact Page Text', 'woocommerce-stock-inquiry' ),
								'whatsapp' => __( 'WhatsApp Text', 'woocommerce-stock-inquiry' ),
								'email'    => __( 'Email Text', 'woocommerce-stock-inquiry' ),
								'phone'    => __( 'Phone Text', 'woocommerce-stock-inquiry' ),
								'popup'    => __( 'Popup Text', 'woocommerce-stock-inquiry' ),
								'form'     => __( 'Inquiry Form Text', 'woocommerce-stock-inquiry' ),
							);
							foreach ( $desc_fields as $desc_method => $desc_label ) :
								?>
								<tr>
									<th scope="row"><label for="wsi-desc-text-<?php echo esc_attr( $desc_method ); ?>"><?php echo esc_html( $desc_label ); ?></label></th>
									<td><textarea id="wsi-desc-text-<?php echo esc_attr( $desc_method ); ?>" class="large-text" rows="2" maxlength="300" name="<?php echo esc_attr( $field( 'desc_text_' . $desc_method ) ); ?>"><?php echo esc_textarea( $s[ 'desc_text_' . $desc_method ] ); ?></textarea></td>
								</tr>
							<?php endforeach; ?>
							<?php $where_row( 'desc' ); ?>
							<?php $font_row( 'desc_font_family', __( 'Font Family', 'woocommerce-stock-inquiry' ) ); ?>
							<?php $number_row( 'desc_size', __( 'Font Size', 'woocommerce-stock-inquiry' ), 8, 48 ); ?>
							<?php $color_row( 'desc_color', __( 'Text Color', 'woocommerce-stock-inquiry' ) ); ?>
						</table>
					</div>
				</div>

				<!-- ======== INTEGRATIONS ======== -->
				<div class="wsi-tab" data-tab="integrations">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Status', 'woocommerce-stock-inquiry' ); ?></h2>
					<table class="widefat striped wsi-status-table">
						<tbody>
							<tr>
								<td><?php esc_html_e( 'WhatsApp', 'woocommerce-stock-inquiry' ); ?></td>
								<td><?php echo '' !== WSI_Settings::clean_phone( $s['whatsapp_number'] ) ? esc_html__( 'Number saved', 'woocommerce-stock-inquiry' ) : esc_html__( 'No number saved', 'woocommerce-stock-inquiry' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Elementor', 'woocommerce-stock-inquiry' ); ?></td>
								<td><?php echo $elementor_on ? esc_html__( 'Active', 'woocommerce-stock-inquiry' ) : esc_html__( 'Not active (optional, only needed for the popup method)', 'woocommerce-stock-inquiry' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Elementor Pro (Popup Builder)', 'woocommerce-stock-inquiry' ); ?></td>
								<td><?php echo $elementor_pro ? esc_html__( 'Active', 'woocommerce-stock-inquiry' ) : esc_html__( 'Not active', 'woocommerce-stock-inquiry' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Published Elementor popups', 'woocommerce-stock-inquiry' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( count( $popups ) ) ); ?></td>
							</tr>
						</tbody>
					</table>

					<h2 class="wsi-section-title"><?php esc_html_e( 'Pre-filling your popup form', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="description wsi-note"><?php esc_html_e( 'The plugin does not include a form. Put your own form (any form plugin or the Elementor Form widget) inside the Elementor popup. When the popup opens, the plugin fills in any field or element that has one of these CSS classes (set it in the field\'s Advanced > CSS Classes):', 'woocommerce-stock-inquiry' ); ?></p>
					<table class="widefat striped wsi-placeholder-table">
						<tbody>
							<tr><td><code>wsi-product-name</code></td><td><?php esc_html_e( 'Product name', 'woocommerce-stock-inquiry' ); ?></td></tr>
							<tr><td><code>wsi-product-url</code></td><td><?php esc_html_e( 'Product link', 'woocommerce-stock-inquiry' ); ?></td></tr>
							<tr><td><code>wsi-product-id</code></td><td><?php esc_html_e( 'Product ID', 'woocommerce-stock-inquiry' ); ?></td></tr>
							<tr><td><code>wsi-product-sku</code></td><td><?php esc_html_e( 'SKU', 'woocommerce-stock-inquiry' ); ?></td></tr>
							<tr><td><code>wsi-product-price</code></td><td><?php esc_html_e( 'Price', 'woocommerce-stock-inquiry' ); ?></td></tr>
							<tr><td><code>wsi-message</code></td><td><?php esc_html_e( 'The product\'s custom message, when it has one', 'woocommerce-stock-inquiry' ); ?></td></tr>
						</tbody>
					</table>
					<p class="description wsi-note"><?php esc_html_e( 'Developers: the product is also available as window.wsiProduct and in the "wsi:product" jQuery event on the document.', 'woocommerce-stock-inquiry' ); ?></p>
				</div>

				<!-- ======== NOTIFICATIONS ======== -->
				<div class="wsi-tab" data-tab="notifications">
					<h2 class="wsi-section-title"><?php esc_html_e( 'Email notifications', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="wsi-section-intro"><?php esc_html_e( 'Sent with the standard WordPress mail function (wp_mail), so your existing SMTP plugin works. A failed email never prevents an inquiry from being saved. Internal notes are never emailed. These emails are sent for inquiries saved by the Inquiry Form (modal) and the [wsi_inquiry_form] shortcode.', 'woocommerce-stock-inquiry' ); ?></p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Admin notification', 'woocommerce-stock-inquiry' ); ?></th>
							<td><?php $toggle( 'notify_admin', __( 'Email me when a new inquiry is saved', 'woocommerce-stock-inquiry' ) ); ?></td>
						</tr>
						<?php $text_row( 'notify_email', __( 'Send To', 'woocommerce-stock-inquiry' ), 100, 'email', get_option( 'admin_email' ) ); ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Customer confirmation', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<?php $toggle( 'notify_customer', __( 'Email the customer a confirmation that the inquiry was received', 'woocommerce-stock-inquiry' ) ); ?>
								<p class="description"><?php esc_html_e( 'Customer replies go to the "Send To" address. Because anyone can type any email address into a form, the plugin limits submissions per visitor and ignores repeats.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status-change emails', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<?php $toggle( 'notify_status', __( 'Email the customer when the inquiry status changes', 'woocommerce-stock-inquiry' ) ); ?>
								<p class="description"><?php esc_html_e( 'Off by default. Moving an inquiry back to "New" never sends an email. A bulk status change emails every selected customer.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="description"><?php esc_html_e( 'Leave "Send To" empty to use the site administrator email.', 'woocommerce-stock-inquiry' ); ?></p>

					<h2 class="wsi-section-title"><?php esc_html_e( 'Email templates', 'woocommerce-stock-inquiry' ); ?></h2>
					<p class="wsi-section-intro"><?php esc_html_e( 'Subjects and messages are plain text. Leave a field empty and save to restore its default.', 'woocommerce-stock-inquiry' ); ?></p>
					<p><strong><?php esc_html_e( 'Placeholders:', 'woocommerce-stock-inquiry' ); ?></strong>
						<?php
						foreach ( WSI_Notifications::placeholders() as $tag => $tag_label ) {
							echo '<code title="' . esc_attr( $tag_label ) . '">' . esc_html( $tag ) . '</code> ';
						}
						?>
					</p>

					<?php
					$mail_groups = array(
						'admin'    => __( 'Admin notification', 'woocommerce-stock-inquiry' ),
						'customer' => __( 'Customer confirmation', 'woocommerce-stock-inquiry' ),
						'status'   => __( 'Status change', 'woocommerce-stock-inquiry' ),
					);
					foreach ( $mail_groups as $group => $group_label ) :
						?>
						<h3><?php echo esc_html( $group_label ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="wsi-<?php echo esc_attr( $group ); ?>-subject"><?php esc_html_e( 'Subject', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><input type="text" class="large-text" maxlength="200" id="wsi-<?php echo esc_attr( $group ); ?>-subject" name="<?php echo esc_attr( $field( $group . '_subject' ) ); ?>" value="<?php echo esc_attr( $s[ $group . '_subject' ] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="wsi-<?php echo esc_attr( $group ); ?>-message"><?php esc_html_e( 'Message', 'woocommerce-stock-inquiry' ); ?></label></th>
								<td><textarea id="wsi-<?php echo esc_attr( $group ); ?>-message" class="large-text code" rows="9" maxlength="3000" name="<?php echo esc_attr( $field( $group . '_message' ) ); ?>"><?php echo esc_textarea( $s[ $group . '_message' ] ); ?></textarea></td>
							</tr>
						</table>
					<?php endforeach; ?>

					<h3><?php esc_html_e( 'Test', 'woocommerce-stock-inquiry' ); ?></h3>
					<p>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wsi_send_test_email' ), 'wsi_send_test_email' ) ); ?>"><?php esc_html_e( 'Send a test email', 'woocommerce-stock-inquiry' ); ?></a>
						<span class="description"><?php esc_html_e( 'Sends the saved admin template with sample data to the "Send To" address. Save your changes first.', 'woocommerce-stock-inquiry' ); ?></span>
					</p>

					<h2 class="wsi-section-title"><?php esc_html_e( 'Privacy', 'woocommerce-stock-inquiry' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Plugin Deletion', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<?php $toggle( 'delete_data_on_uninstall', __( 'Delete all inquiries and notes when the plugin is deleted', 'woocommerce-stock-inquiry' ) ); ?>
								<p class="description"><?php esc_html_e( 'Off by default, so customer inquiries are kept if you delete and reinstall the plugin. Inquiries can be exported or erased per customer from Tools > Export / Erase Personal Data.', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- ======== ADVANCED ======== -->
				<div class="wsi-tab" data-tab="advanced">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Button Shortcode (optional)', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<p><?php esc_html_e( 'Shortcode is optional. The button is placed automatically; use this only where WooCommerce does not reach, such as a custom Elementor template. It prints the button only when the product needs the inquiry.', 'woocommerce-stock-inquiry' ); ?></p>
								<p><code>[wsi_button]</code> <?php esc_html_e( 'current product', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_button product_id="123"]</code> <?php esc_html_e( 'a specific product', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_button text="Ask us" method="whatsapp"]</code> <?php esc_html_e( 'custom text and method', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_inquiry_form]</code> <?php esc_html_e( 'button that opens the inquiry form modal (saved in Inquiries)', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Contact Form Pre-fill', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<p><?php esc_html_e( 'On your contact page you can show or pre-fill the product with these shortcodes:', 'woocommerce-stock-inquiry' ); ?></p>
								<p><code>[wsi_product]</code> <?php esc_html_e( 'product name', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_product field="url"]</code> <?php esc_html_e( 'product link', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_product field="id"]</code> <?php esc_html_e( 'product ID', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_product field="sku"]</code> <?php esc_html_e( 'SKU', 'woocommerce-stock-inquiry' ); ?><br />
								<code>[wsi_product field="price"]</code> <?php esc_html_e( 'price', 'woocommerce-stock-inquiry' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Developer Hooks', 'woocommerce-stock-inquiry' ); ?></th>
							<td>
								<p><code>wsi_needs_inquiry</code>, <code>wsi_product_config</code>, <code>wsi_placeholder_values</code>, <code>wsi_inquiry_methods</code>, <code>wsi_button_html</code>, <code>wsi_loop_location</code>, <code>wsi_supported_product_types</code>, <code>wsi_show_stock_text</code>, <code>wsi_admin_method_fields</code></p>
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
					<div class="wsi-preview-ctx" role="group" aria-label="<?php esc_attr_e( 'Preview stock state', 'woocommerce-stock-inquiry' ); ?>">
						<button type="button" class="button button-small is-active" data-state="out" aria-pressed="true"><?php esc_html_e( 'Out of stock', 'woocommerce-stock-inquiry' ); ?></button>
						<button type="button" class="button button-small" data-state="low" aria-pressed="false"><?php esc_html_e( 'Low stock', 'woocommerce-stock-inquiry' ); ?></button>
						<button type="button" class="button button-small" data-state="in" aria-pressed="false"><?php esc_html_e( 'In stock', 'woocommerce-stock-inquiry' ); ?></button>
					</div>
					<p class="wsi-preview-state-note" id="wsi-preview-state-note"></p>
					<div class="wsi-preview-stage">
						<div class="wsi-fake-cart" id="wsi-preview-cart"><span class="wsi-fake-qty">1</span><span class="button wsi-fake-atc"><?php esc_html_e( 'Add to cart', 'woocommerce-stock-inquiry' ); ?></span></div>
						<div class="wsi-inquiry-container" id="wsi-preview-container">
							<div class="wsi-inquiry-heading" id="wsi-preview-heading" style="display:none;"></div>
							<a href="#" class="button wsi-inquiry-button" id="wsi-preview-button"><?php echo esc_html( $s['button_text_contact'] ); ?></a>
							<div class="wsi-inquiry-desc" id="wsi-preview-desc" style="display:none;"></div>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Heading → button → description, as shown on the front end. Hover the button to preview hover colors.', 'woocommerce-stock-inquiry' ); ?></p>
					<p class="wsi-preview-product">
						<label for="wsi-preview-product"><?php esc_html_e( 'Preview with a real product (optional)', 'woocommerce-stock-inquiry' ); ?></label>
						<select id="wsi-preview-product" class="wc-product-search" style="width:100%;" data-placeholder="<?php esc_attr_e( 'Search product name...', 'woocommerce-stock-inquiry' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true"></select>
					</p>
				</div>
			</div> <!-- .wsi-preview-column -->

		</div> <!-- .wsi-main-layout -->
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wsi-reset-form">
		<input type="hidden" name="action" value="wsi_reset_settings" />
		<?php wp_nonce_field( 'wsi_reset_settings' ); ?>
	</form>
</div>
