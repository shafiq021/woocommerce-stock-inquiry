<?php
/**
 * Upgrades stored data from earlier versions, once.
 *
 * Versions 2.0.0 and 3.0.0 keep the `wsi_settings` option and every existing key (3.0.0 only adds the inquiry tables and new settings, which come from the defaults). New keys are added with
 * values that reproduce the earlier behaviour, and nothing the store owner saved is overwritten.
 *
 * The migrator runs on `init` (not only on activation, because updating a plugin does not run
 * its activation hook) and is a single cheap option read once the site is up to date.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * One-time upgrade of stored data from earlier versions.
 */
class WSI_Migrator {

	const VERSION_OPTION = 'wsi_db_version';
	const TARGET         = '3.0.0';
	const NOTIFY_OPTION  = 'wsi_notify_optin_done';

	/**
	 * Run pending migrations.
	 */
	public static function maybe_run() {
		self::keep_customer_emails_opt_in();

		$current = (string) get_option( self::VERSION_OPTION, '' );
		if ( '' !== $current && version_compare( $current, self::TARGET, '>=' ) ) {
			return;
		}

		// 3.0.0: inquiry tables. dbDelta() is idempotent, so running it again is harmless.
		WSI_Installer::install();

		$saved = get_option( WSI_Settings::OPTION, null );
		if ( is_array( $saved ) ) {
			$migrated = self::migrate_settings( $saved );
			if ( $migrated !== $saved ) {
				update_option( WSI_Settings::OPTION, $migrated );
			}
		}

		update_option( self::VERSION_OPTION, self::TARGET );
	}

	/**
	 * Customer emails are new: stores that already had saved settings before they existed must not
	 * start emailing customers until the owner opts in. Runs once, independent of the schema
	 * version, so it also covers sites that already ran an earlier 3.0.0 build.
	 * Fresh installs (no saved settings yet) keep the default of "on".
	 */
	public static function keep_customer_emails_opt_in() {
		if ( get_option( self::NOTIFY_OPTION ) ) {
			return;
		}
		$saved = get_option( WSI_Settings::OPTION, null );
		if ( is_array( $saved ) ) {
			$add = array();
			foreach ( array( 'notify_customer', 'notify_status' ) as $key ) {
				if ( ! array_key_exists( $key, $saved ) ) {
					$add[ $key ] = 0;
				}
			}
			if ( $add ) {
				update_option( WSI_Settings::OPTION, array_merge( $saved, $add ) );
			}
		}
		update_option( self::NOTIFY_OPTION, 1 );
	}

	/**
	 * Pure function: version 1 settings in, version 2 settings out. Existing keys always win.
	 *
	 * @param array $saved Stored settings.
	 * @return array
	 */
	public static function migrate_settings( array $saved ) {
		$new = array();

		// Version 1 switched to inquiry when out of stock OR stock < threshold, and a threshold
		// of 0 meant "only when out of stock". Make that explicit.
		$threshold                = isset( $saved['stock_threshold'] ) ? (int) $saved['stock_threshold'] : 5;
		$new['trigger_mode']      = ( $threshold > 0 ) ? 'out_or_below' : 'out_only';
		$new['threshold_compare'] = 'lt';

		// Very old versions had one shared button text.
		if ( isset( $saved['button_text'] ) && '' !== (string) $saved['button_text'] ) {
			$new['button_text_contact']  = $saved['button_text'];
			$new['button_text_whatsapp'] = $saved['button_text'];
		}

		// The "Powered By" credit was removed in 3.0.0; its old keys are simply ignored.

		// `enable_loops` stays the master switch for listings; the new per-location switches start on.
		foreach ( array( 'shop', 'category', 'search', 'related', 'upsells', 'cross_sells', 'other' ) as $location ) {
			$new[ 'show_loc_' . $location ] = 1;
		}

		// Existing keys always win over the values above.
		return array_merge( $new, $saved );
	}
}
