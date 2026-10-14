<?php
/**
 * Single product add-to-cart wrapper.
 *
 * Included by WooCommerce in place of single-product/add-to-cart/simple.php or variable.php
 * when the product needs the inquiry button. Variables WooCommerce passes to the original
 * template (e.g. $available_variations) are in scope here and reach the original when it is included.
 *
 * Modes (see WSI_Button::filter_template()):
 *  - static:  the button replaces the form, or sits before/after it.
 *  - dynamic: variable product where only some variations need the inquiry. The normal form is
 *             always printed; the button is hidden and frontend.js shows it for those variations.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

$wsi_pending = WSI_Button::take_pending();
if ( ! $wsi_pending ) {
	return;
}

global $product;

if ( 'dynamic' === $wsi_pending['mode'] ) {
	include $wsi_pending['original'];
	echo $wsi_pending['button']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WSI_Button::render().
	return;
}

$wsi_position = WSI_Settings::get( 'position' );

if ( 'before' === $wsi_position ) {
	echo $wsi_pending['button']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WSI_Button::render().
}

if ( 'replace' === $wsi_position ) {
	// Keep WooCommerce's own stock message (e.g. "Out of stock") above the button for simple products.
	if ( $wsi_pending['is_simple'] && function_exists( 'wc_get_stock_html' ) && apply_filters( 'wsi_show_stock_text', true, $product ) ) {
		echo wc_get_stock_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce output.
	}
	echo $wsi_pending['button']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WSI_Button::render().
} else {
	include $wsi_pending['original'];
}

if ( 'after' === $wsi_position ) {
	echo $wsi_pending['button']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WSI_Button::render().
}
