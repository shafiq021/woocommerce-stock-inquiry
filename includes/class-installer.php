<?php
/**
 * Database schema for stored inquiries.
 *
 * Two tables, created and upgraded with dbDelta():
 *  - {prefix}wsi_inquiries       one row per submitted inquiry.
 *  - {prefix}wsi_inquiry_notes   internal notes and status history for an inquiry.
 *
 * Timestamps are stored in UTC and converted to the site's timezone only when displayed.
 * WooCommerce tables are never touched.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates, upgrades and removes the plugin's tables.
 */
class WSI_Installer {

	const SCHEMA_OPTION  = 'wsi_schema_version';
	const SCHEMA_VERSION = '1';

	/**
	 * Full table name for a short name ('inquiries' or 'inquiry_notes').
	 *
	 * @param string $name Short table name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wsi_' . $name;
	}

	/**
	 * Create or upgrade the tables. Safe to run repeatedly.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset   = $wpdb->get_charset_collate();
		$inquiries = self::table( 'inquiries' );
		$notes     = self::table( 'inquiry_notes' );

		// dbDelta() is picky: two spaces after PRIMARY KEY, one definition per line.
		$sql_inquiries = "CREATE TABLE {$inquiries} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_name varchar(255) NOT NULL DEFAULT '',
  customer_name varchar(120) NOT NULL DEFAULT '',
  customer_email varchar(100) NOT NULL DEFAULT '',
  customer_phone varchar(40) NOT NULL DEFAULT '',
  message text NOT NULL,
  source varchar(20) NOT NULL DEFAULT 'popup',
  status varchar(20) NOT NULL DEFAULT 'new',
  dedupe_hash char(40) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY product_id (product_id),
  KEY source (source),
  KEY created_at (created_at),
  KEY customer_email (customer_email),
  KEY dedupe_hash (dedupe_hash)
) {$charset};";

		$sql_notes = "CREATE TABLE {$notes} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  inquiry_id bigint(20) unsigned NOT NULL,
  author_id bigint(20) unsigned NOT NULL DEFAULT 0,
  note_type varchar(20) NOT NULL DEFAULT 'note',
  content text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY inquiry_id (inquiry_id)
) {$charset};";

		dbDelta( $sql_inquiries );
		dbDelta( $sql_notes );

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Remove the tables (only used by uninstall when the store owner opted in).
	 */
	public static function drop_tables() {
		global $wpdb;
		// Table names are built from the site prefix, never from user input.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( 'inquiry_notes' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( 'inquiries' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
		delete_option( self::SCHEMA_OPTION );
	}
}
