<?php
/**
 * Data access for inquiries and their notes. Every query is prepared; list queries are paginated,
 * so inquiries are never loaded all at once.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inquiry storage.
 */
class WSI_Inquiry_Repository {

	/**
	 * Inquiry statuses (value => label).
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'new'         => __( 'New', 'woocommerce-stock-inquiry' ),
			'contacted'   => __( 'Contacted', 'woocommerce-stock-inquiry' ),
			'in_progress' => __( 'In Progress', 'woocommerce-stock-inquiry' ),
			'completed'   => __( 'Completed', 'woocommerce-stock-inquiry' ),
			'closed'      => __( 'Closed', 'woocommerce-stock-inquiry' ),
		);
	}

	/**
	 * Inquiry sources (value => label).
	 *
	 * @return array
	 */
	public static function sources() {
		return array(
			'popup'     => __( 'Popup form (button)', 'woocommerce-stock-inquiry' ),
			'shortcode' => __( 'Shortcode form', 'woocommerce-stock-inquiry' ),
			'other'     => __( 'Other', 'woocommerce-stock-inquiry' ),
		);
	}

	/**
	 * @return string Current UTC time for storage.
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Display a stored UTC timestamp in the site's timezone.
	 *
	 * @param string $utc    Stored UTC datetime.
	 * @param string $format Optional PHP date format (defaults to the site's date and time format).
	 * @return string
	 */
	public static function display_date( $utc, $format = '' ) {
		if ( '' === $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}
		return (string) get_date_from_gmt( (string) $utc, $format );
	}

	/**
	 * @param string $table Short table name.
	 * @return string
	 */
	private static function t( $table = 'inquiries' ) {
		return WSI_Installer::table( $table );
	}

	/**
	 * Prepare only when there are values to bind (prepare() without placeholders is a notice).
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return string
	 */
	private static function prep( $sql, array $args ) {
		global $wpdb;
		return $args ? $wpdb->prepare( $sql, $args ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are built by the callers.
	}

	/**
	 * Hash used to spot a repeated submission.
	 *
	 * @param string $email        Email.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $message      Message.
	 * @return string
	 */
	public static function dedupe_hash( $email, $product_id, $variation_id, $message ) {
		return sha1( strtolower( trim( $email ) ) . '|' . (int) $product_id . '|' . (int) $variation_id . '|' . trim( $message ) );
	}

	/**
	 * @param string $hash    Dedupe hash.
	 * @param int    $minutes Look-back window.
	 * @return int Existing inquiry ID, or 0.
	 */
	public static function find_recent_duplicate( $hash, $minutes = 10 ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $minutes ) * MINUTE_IN_SECONDS ) );
		$table = self::t();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE dedupe_hash = %s AND created_at >= %s ORDER BY id DESC LIMIT 1", $hash, $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Insert an already sanitized inquiry.
	 *
	 * @param array $data product_id, variation_id, product_name, customer_name, customer_email,
	 *                    customer_phone, message, source.
	 * @return int New inquiry ID, or 0 when it could not be stored.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$now    = self::now();
		$source = isset( $data['source'] ) && array_key_exists( $data['source'], self::sources() ) ? $data['source'] : 'other';

		$row = array(
			'product_id'     => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'variation_id'   => absint( isset( $data['variation_id'] ) ? $data['variation_id'] : 0 ),
			'product_name'   => (string) ( isset( $data['product_name'] ) ? $data['product_name'] : '' ),
			'customer_name'  => (string) $data['customer_name'],
			'customer_email' => (string) $data['customer_email'],
			'customer_phone' => (string) ( isset( $data['customer_phone'] ) ? $data['customer_phone'] : '' ),
			'message'        => (string) $data['message'],
			'source'         => $source,
			'status'         => 'new',
			'dedupe_hash'    => self::dedupe_hash( $data['customer_email'], isset( $data['product_id'] ) ? $data['product_id'] : 0, isset( $data['variation_id'] ) ? $data['variation_id'] : 0, $data['message'] ),
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$ok = $wpdb->insert( self::t(), $row, array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		self::flush_new_count();
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	const NEW_COUNT_KEY = 'wsi_new_count';

	/**
	 * Number of "New" inquiries for the menu bubble. Cached for a few minutes because the menu is
	 * built on every admin page; the cache is cleared whenever an inquiry is added, changed or deleted.
	 *
	 * @return int
	 */
	public static function new_count() {
		$cached = get_transient( self::NEW_COUNT_KEY );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$counts = self::counts_by_status();
		$count  = (int) $counts['new'];
		set_transient( self::NEW_COUNT_KEY, $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Forget the cached menu count.
	 */
	public static function flush_new_count() {
		delete_transient( self::NEW_COUNT_KEY );
	}

	/**
	 * @param int $id Inquiry ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::t();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Build the WHERE clause shared by listing and counting.
	 *
	 * @param array $args status, source, product_id, search, date_from, date_to (Y-m-d, site timezone).
	 * @return array array( string $where, array $values )
	 */
	private static function where( array $args ) {
		global $wpdb;
		$where  = array();
		$values = array();

		if ( ! empty( $args['status'] ) && array_key_exists( $args['status'], self::statuses() ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['source'] ) && array_key_exists( $args['source'], self::sources() ) ) {
			$where[]  = 'source = %s';
			$values[] = $args['source'];
		}
		if ( ! empty( $args['product_id'] ) ) {
			$where[]  = 'product_id = %d';
			$values[] = absint( $args['product_id'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = get_gmt_from_date( $args['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = get_gmt_from_date( $args['date_to'] . ' 23:59:59' );
		}
		if ( isset( $args['search'] ) && '' !== trim( (string) $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( trim( (string) $args['search'] ) ) . '%';
			$where[]  = '(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR product_name LIKE %s OR message LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		return array( $where ? ' WHERE ' . implode( ' AND ', $where ) : '', $values );
	}

	/**
	 * One page of inquiries.
	 *
	 * @param array $args Filters (see where()) plus orderby (id|created_at|status), order, per_page, paged.
	 * @return array array( 'items' => object[], 'total' => int )
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$table    = self::t();
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 20;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$orderby  = ( isset( $args['orderby'] ) && in_array( $args['orderby'], array( 'id', 'created_at', 'status' ), true ) ) ? $args['orderby'] : 'created_at';
		$order    = ( isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

		list( $where, $values ) = self::where( $args );

		$total = (int) $wpdb->get_var( self::prep( "SELECT COUNT(*) FROM {$table}{$where}", $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$list_values   = $values;
		$list_values[] = $per_page;
		$list_values[] = ( $paged - 1 ) * $per_page;
		// $orderby and $order come from allow-lists above.
		$items = $wpdb->get_results( self::prep( "SELECT * FROM {$table}{$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d", $list_values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Rows for an export, fetched in batches so the whole table is never in memory.
	 *
	 * @param array $args   Filters (see where()).
	 * @param int   $after  Return rows with an ID greater than this one.
	 * @param int   $limit  Batch size.
	 * @return object[]
	 */
	public static function batch( array $args, $after, $limit = 500 ) {
		global $wpdb;
		$table                  = self::t();
		list( $where, $values ) = self::where( $args );
		$where                 .= ( '' === $where ? ' WHERE ' : ' AND ' ) . 'id > %d';
		$values[]               = absint( $after );
		$values[]               = max( 1, min( 1000, (int) $limit ) );
		$rows                   = $wpdb->get_results( self::prep( "SELECT * FROM {$table}{$where} ORDER BY id ASC LIMIT %d", $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array status => count (every status present, zero when empty).
	 */
	public static function counts_by_status() {
		global $wpdb;
		$table  = self::t();
		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->total;
			}
		}
		return $counts;
	}

	/**
	 * @return int Total inquiries.
	 */
	public static function total() {
		global $wpdb;
		$table = self::t();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param int $limit Number of rows.
	 * @return object[] Newest inquiries.
	 */
	public static function recent( $limit = 10 ) {
		global $wpdb;
		$table = self::t();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", max( 1, (int) $limit ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Products with inquiries, most requested first. Inquiries without a product are skipped.
	 *
	 * @param int $limit Number of products.
	 * @return object[] product_id, product_name, total.
	 */
	public static function top_products( $limit = 5 ) {
		global $wpdb;
		$table = self::t();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, MAX(product_name) AS product_name, COUNT(*) AS total FROM {$table} WHERE product_id > 0 GROUP BY product_id ORDER BY total DESC, product_id DESC LIMIT %d", max( 1, (int) $limit ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array source => count (every source present).
	 */
	public static function counts_by_source() {
		global $wpdb;
		$table  = self::t();
		$counts = array_fill_keys( array_keys( self::sources() ), 0 );
		$rows   = $wpdb->get_results( "SELECT source, COUNT(*) AS total FROM {$table} GROUP BY source" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$key            = isset( $counts[ $row->source ] ) ? $row->source : 'other';
			$counts[ $key ] = ( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 ) + (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Inquiries per calendar month for the last N months (UTC months), oldest first. Months with no
	 * inquiries are included with a zero. One aggregated query.
	 *
	 * @param int $months Number of months (1-36).
	 * @return array array( 'Y-m' => count )
	 */
	public static function monthly_counts( $months = 12 ) {
		global $wpdb;
		$table  = self::t();
		$months = max( 1, min( 36, (int) $months ) );

		$counts = array();
		$cursor = strtotime( gmdate( 'Y-m-01 00:00:00' ) . ' UTC' );
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$counts[ gmdate( 'Y-m', strtotime( '-' . $i . ' months', $cursor ) ) ] = 0;
		}
		$start = gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $months - 1 ) . ' months', $cursor ) );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT SUBSTRING(created_at, 1, 7) AS ym, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY ym", $start ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row->ym ] ) ) {
				$counts[ $row->ym ] = (int) $row->total;
			}
		}
		return $counts;
	}

	/**
	 * Products that have inquiries, for the list filter dropdown (capped).
	 *
	 * @return object[] product_id, product_name.
	 */
	public static function products_with_inquiries() {
		global $wpdb;
		$table = self::t();
		$rows  = $wpdb->get_results( "SELECT product_id, MAX(product_name) AS product_name FROM {$table} WHERE product_id > 0 GROUP BY product_id ORDER BY product_name ASC LIMIT 200" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Change an inquiry's status, recording the change in its history.
	 *
	 * @param int    $id      Inquiry ID.
	 * @param string $status  New status.
	 * @param int    $user_id User making the change.
	 * @return bool True when the status changed.
	 */
	public static function update_status( $id, $status, $user_id = 0 ) {
		global $wpdb;

		$statuses = self::statuses();
		$inquiry  = self::get( $id );
		if ( ! $inquiry || ! array_key_exists( $status, $statuses ) || $inquiry->status === $status ) {
			return false;
		}

		$old = isset( $statuses[ $inquiry->status ] ) ? $statuses[ $inquiry->status ] : $inquiry->status;
		$ok  = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::t(),
			array(
				'status'     => $status,
				'updated_at' => self::now(),
			),
			array( 'id' => (int) $inquiry->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return false;
		}
		self::flush_new_count();

		/* translators: 1: old status, 2: new status */
		self::add_note( $inquiry->id, $user_id, sprintf( __( 'Status changed from %1$s to %2$s.', 'woocommerce-stock-inquiry' ), $old, $statuses[ $status ] ), 'status_change', false );

		/**
		 * Fires after an inquiry status changed.
		 *
		 * @param int    $id         Inquiry ID.
		 * @param string $status     New status.
		 * @param string $old_status Previous status.
		 */
		do_action( 'wsi_inquiry_status_changed', (int) $inquiry->id, $status, $inquiry->status );
		return true;
	}

	/**
	 * Add a note (or a history entry) to an inquiry.
	 *
	 * @param int    $inquiry_id Inquiry ID.
	 * @param int    $user_id    Author.
	 * @param string $content    Text (already sanitized).
	 * @param string $type       'note' or 'status_change'.
	 * @param bool   $touch      Update the inquiry's modification time.
	 * @return int Note ID, or 0.
	 */
	public static function add_note( $inquiry_id, $user_id, $content, $type = 'note', $touch = true ) {
		global $wpdb;
		$type = ( 'status_change' === $type ) ? 'status_change' : 'note';
		$ok   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::t( 'inquiry_notes' ),
			array(
				'inquiry_id' => absint( $inquiry_id ),
				'author_id'  => absint( $user_id ),
				'note_type'  => $type,
				'content'    => (string) $content,
				'created_at' => self::now(),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return 0;
		}
		$note_id = (int) $wpdb->insert_id;
		if ( $touch ) {
			$wpdb->update( self::t(), array( 'updated_at' => self::now() ), array( 'id' => absint( $inquiry_id ) ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		return $note_id;
	}

	/**
	 * @param int $inquiry_id Inquiry ID.
	 * @return object[] Notes and history, oldest first.
	 */
	public static function get_notes( $inquiry_id ) {
		global $wpdb;
		$table = self::t( 'inquiry_notes' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE inquiry_id = %d ORDER BY created_at ASC, id ASC", absint( $inquiry_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete inquiries and their notes.
	 *
	 * @param int[] $ids Inquiry IDs.
	 * @return int Number of inquiries deleted.
	 */
	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$marks   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$notes   = self::t( 'inquiry_notes' );
		$table   = self::t();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$notes} WHERE inquiry_id IN ({$marks})", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$marks})", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		self::flush_new_count();
		return (int) $deleted;
	}

	/**
	 * Inquiries for a customer email (privacy export), one page at a time.
	 *
	 * @param string $email    Email address.
	 * @param int    $page     Page, starting at 1.
	 * @param int    $per_page Rows per page.
	 * @return object[]
	 */
	public static function get_by_email( $email, $page = 1, $per_page = 50 ) {
		global $wpdb;
		$table = self::t();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE customer_email = %s ORDER BY id ASC LIMIT %d OFFSET %d", $email, $per_page, ( max( 1, (int) $page ) - 1 ) * $per_page ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete every inquiry (and its notes) for a customer email (privacy eraser).
	 *
	 * @param string $email Email address.
	 * @return int Number of inquiries deleted.
	 */
	public static function delete_by_email( $email ) {
		global $wpdb;
		$table = self::t();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE customer_email = %s", $email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::delete( array_map( 'intval', (array) $ids ) );
	}
}
