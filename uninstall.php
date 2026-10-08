<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 * The plugin never writes to products, so there is nothing else to clean up.
 *
 * @package WooCommerce_Stock_Inquiry
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wsi_settings' );

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $wsi_site_id ) {
		switch_to_blog( $wsi_site_id );
		delete_option( 'wsi_settings' );
		restore_current_blog();
	}
}
