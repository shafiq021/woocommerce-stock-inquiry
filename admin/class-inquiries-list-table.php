<?php
/**
 * All Inquiries list table (WordPress-native WP_List_Table).
 *
 * Every query is paginated and prepared in WSI_Inquiry_Repository; nothing is loaded in bulk.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Inquiry list.
 */
class WSI_Inquiries_List_Table extends WP_List_Table {

	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'inquiry',
				'plural'   => 'inquiries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Current filters, read from the query string (GET only, sanitized).
	 *
	 * @return array
	 */
	public static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$get = static function ( $key ) {
			return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : '';
		};
		$date = static function ( $value ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && false !== strtotime( $value ) ? $value : '';
		};
		$out = array(
			'status'     => sanitize_key( $get( 'status' ) ),
			'source'     => sanitize_key( $get( 'source' ) ),
			'product_id' => absint( $get( 'product_id' ) ),
			'search'     => $get( 's' ),
			'date_from'  => $date( $get( 'date_from' ) ),
			'date_to'    => $date( $get( 'date_to' ) ),
			'orderby'    => sanitize_key( $get( 'orderby' ) ),
			'order'      => sanitize_key( $get( 'order' ) ),
		);
		// phpcs:enable
		return $out;
	}

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'id'       => __( 'ID', 'woocommerce-stock-inquiry' ),
			'product'  => __( 'Product', 'woocommerce-stock-inquiry' ),
			'customer' => __( 'Customer', 'woocommerce-stock-inquiry' ),
			'email'    => __( 'Email', 'woocommerce-stock-inquiry' ),
			'phone'    => __( 'Phone', 'woocommerce-stock-inquiry' ),
			'source'   => __( 'Source', 'woocommerce-stock-inquiry' ),
			'status'   => __( 'Status', 'woocommerce-stock-inquiry' ),
			'date'     => __( 'Date', 'woocommerce-stock-inquiry' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'id'     => array( 'id', false ),
			'status' => array( 'status', false ),
			'date'   => array( 'created_at', true ),
		);
	}

	protected function get_bulk_actions() {
		$actions = array();
		foreach ( WSI_Inquiry_Repository::statuses() as $key => $label ) {
			/* translators: %s: status name */
			$actions[ 'status_' . $key ] = sprintf( __( 'Mark as %s', 'woocommerce-stock-inquiry' ), $label );
		}
		$actions['delete'] = __( 'Delete', 'woocommerce-stock-inquiry' );
		return $actions;
	}

	/**
	 * Status links with live counts above the table.
	 *
	 * @return array
	 */
	protected function get_views() {
		$filters = self::filters();
		$counts  = WSI_Inquiry_Repository::counts_by_status();
		$base    = admin_url( 'admin.php?page=' . WSI_Inquiry_Admin::PAGE . '&view=list' );
		$views   = array();

		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( $base ),
			'' === $filters['status'] ? ' class="current" aria-current="page"' : '',
			esc_html__( 'All', 'woocommerce-stock-inquiry' ),
			esc_html( number_format_i18n( array_sum( $counts ) ) )
		);
		foreach ( WSI_Inquiry_Repository::statuses() as $key => $label ) {
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'status', $key, $base ) ),
				$filters['status'] === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $counts[ $key ] ) )
			);
		}
		return $views;
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="inquiry[]" value="' . esc_attr( $item->id ) . '" />';
	}

	protected function column_id( $item ) {
		$view   = WSI_Inquiry_Admin::view_url( $item->id );
		$delete = WSI_Inquiry_Admin::delete_url( $item->id );
		$actions = array(
			'view'   => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'woocommerce-stock-inquiry' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete ) . '" class="submitdelete wsi-confirm-delete">' . esc_html__( 'Delete', 'woocommerce-stock-inquiry' ) . '</a>',
		);
		return '<strong><a href="' . esc_url( $view ) . '">#' . (int) $item->id . '</a></strong>' . $this->row_actions( $actions );
	}

	protected function column_product( $item ) {
		$name = '' !== $item->product_name ? $item->product_name : __( '(general inquiry)', 'woocommerce-stock-inquiry' );
		if ( $item->product_id && 'publish' === get_post_status( (int) $item->product_id ) ) {
			return '<a href="' . esc_url( get_edit_post_link( (int) $item->product_id ) ) . '">' . esc_html( $name ) . '</a>';
		}
		return esc_html( $name );
	}

	protected function column_customer( $item ) {
		return esc_html( $item->customer_name );
	}

	protected function column_email( $item ) {
		return '<a href="mailto:' . esc_attr( $item->customer_email ) . '">' . esc_html( $item->customer_email ) . '</a>';
	}

	protected function column_phone( $item ) {
		if ( '' === $item->customer_phone ) {
			return '&mdash;';
		}
		return '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $item->customer_phone ) ) . '">' . esc_html( $item->customer_phone ) . '</a>';
	}

	protected function column_source( $item ) {
		$sources = WSI_Inquiry_Repository::sources();
		return esc_html( isset( $sources[ $item->source ] ) ? $sources[ $item->source ] : $item->source );
	}

	protected function column_status( $item ) {
		return WSI_Inquiry_Admin::status_badge( $item->status );
	}

	protected function column_date( $item ) {
		return '<abbr title="' . esc_attr( WSI_Inquiry_Repository::display_date( $item->created_at, 'Y-m-d H:i:s' ) ) . '">' . esc_html( WSI_Inquiry_Repository::display_date( $item->created_at, get_option( 'date_format' ) ) ) . '</abbr>';
	}

	protected function column_default( $item, $column_name ) {
		return '';
	}

	public function no_items() {
		esc_html_e( 'No inquiries found.', 'woocommerce-stock-inquiry' );
	}

	/**
	 * Product, source and date filters.
	 *
	 * @param string $which top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$f = self::filters();
		?>
		<div class="alignleft actions wsi-filters">
			<label class="screen-reader-text" for="wsi-filter-product"><?php esc_html_e( 'Filter by product', 'woocommerce-stock-inquiry' ); ?></label>
			<select name="product_id" id="wsi-filter-product">
				<option value="0"><?php esc_html_e( 'All products', 'woocommerce-stock-inquiry' ); ?></option>
				<?php foreach ( WSI_Inquiry_Repository::products_with_inquiries() as $row ) : ?>
					<option value="<?php echo esc_attr( $row->product_id ); ?>" <?php selected( $f['product_id'], (int) $row->product_id ); ?>><?php echo esc_html( '' !== $row->product_name ? $row->product_name : '#' . $row->product_id ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="wsi-filter-source"><?php esc_html_e( 'Filter by source', 'woocommerce-stock-inquiry' ); ?></label>
			<select name="source" id="wsi-filter-source">
				<option value=""><?php esc_html_e( 'All sources', 'woocommerce-stock-inquiry' ); ?></option>
				<?php foreach ( WSI_Inquiry_Repository::sources() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['source'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="wsi-date-from" class="screen-reader-text"><?php esc_html_e( 'From date', 'woocommerce-stock-inquiry' ); ?></label>
			<input type="date" id="wsi-date-from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" />
			<label for="wsi-date-to" class="screen-reader-text"><?php esc_html_e( 'To date', 'woocommerce-stock-inquiry' ); ?></label>
			<input type="date" id="wsi-date-to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" />
			<?php submit_button( __( 'Filter', 'woocommerce-stock-inquiry' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Load one page of rows.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$filters = self::filters();
		$result  = WSI_Inquiry_Repository::query(
			array_merge(
				$filters,
				array(
					'per_page' => self::PER_PAGE,
					'paged'    => $this->get_pagenum(),
				)
			)
		);

		$this->items = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => self::PER_PAGE,
			)
		);
	}
}
