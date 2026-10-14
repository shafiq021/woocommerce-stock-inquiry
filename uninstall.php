<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen: the settings option,
 * the migration marker and the per-product override meta (`_wsi_*`).
 *
 * Stored inquiries (customer data) are only removed when the store owner switched on
 * "Delete all inquiries when the plugin is deleted" in the settings.
 *
 * @package WooCommerce_Stock_Inquiry
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's data on the current site.
 */
function wsi_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'wsi_settings', array() );
	if ( is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) ) {
		// Table names come from the site prefix, never from input.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsi_inquiry_notes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsi_inquiries" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( 'wsi_schema_version' );
	}

	delete_option( 'wsi_settings' );
	delete_option( 'wsi_db_version' );
	delete_option( 'wsi_notify_optin_done' );
	delete_transient( 'wsi_new_count' );

	// One-off cleanup of the product override meta. Uninstall only, so no caching is needed.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wsi_custom','_wsi_trigger','_wsi_threshold','_wsi_method','_wsi_button_text','_wsi_message','_wsi_wa_destination')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Static query, no user input.
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $wsi_site_id ) {
		switch_to_blog( $wsi_site_id );
		wsi_uninstall_site();
		restore_current_blog();
	}
} else {
	wsi_uninstall_site();
}
