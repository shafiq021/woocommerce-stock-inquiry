<?php
/**
 * Admin: WooCommerce > Inquiries (dashboard, all inquiries, inquiry details).
 *
 * One menu page with three views: dashboard (default), list and single inquiry. Settings stay on
 * the existing WooCommerce > Stock Inquiry page. Every action checks the capability and a nonce.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inquiry management screens.
 */
class WSI_Inquiry_Admin {

	const PAGE = 'wsi-inquiries';
	const CAP  = 'manage_woocommerce';

	/** @var string Page hook suffix. */
	private $hook = '';

	/** @var WSI_Inquiries_List_Table|null */
	private $table = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wsi_update_status', array( $this, 'handle_update_status' ) );
		add_action( 'admin_post_wsi_add_note', array( $this, 'handle_add_note' ) );
		add_action( 'admin_post_wsi_delete_inquiry', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_wsi_send_test_email', array( $this, 'handle_test_email' ) );
		add_action( 'admin_post_wsi_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_notices', array( $this, 'test_email_notice' ) );
		add_filter( 'woocommerce_screen_ids', array( $this, 'add_screen_id' ) );
	}

	/* ---------- URLs ---------- */

	public static function view_url( $id ) {
		return admin_url( 'admin.php?page=' . self::PAGE . '&view=single&id=' . absint( $id ) );
	}

	public static function delete_url( $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=wsi_delete_inquiry&id=' . absint( $id ) ), 'wsi_delete_' . absint( $id ) );
	}

	/**
	 * Link that downloads the inquiries matching the given list filters as CSV.
	 *
	 * @param array $filters Filters from WSI_Inquiries_List_Table::filters().
	 * @return string
	 */
	public static function export_url( array $filters = array() ) {
		$args = array( 'action' => 'wsi_export_csv' );
		foreach ( array( 'status', 'source', 'product_id', 'search', 'date_from', 'date_to' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$args[ 'search' === $key ? 's' : $key ] = $filters[ $key ];
			}
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'wsi_export_csv' );
	}

	/**
	 * Make a value safe to open in a spreadsheet: a cell that starts with = + - @ (or a tab or
	 * carriage return) could be run as a formula, so it is prefixed with an apostrophe.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = str_replace( "\0", '', (string) $value );
		if ( preg_match( '/^[\s]*[=+\-@]/', $value ) || preg_match( '/^[\t\r]/', $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	/**
	 * Stream the CSV export. Rows are read and written in batches of 500, so the whole table is
	 * never held in memory. Internal notes are not exported.
	 */
	public function handle_export_csv() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to export inquiries.', 'woocommerce-stock-inquiry' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wsi_export_csv' );

		$filters = WSI_Inquiries_List_Table::filters();
		unset( $filters['orderby'], $filters['order'] );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wsi-inquiries-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a download.
		if ( ! $out ) {
			wp_die( esc_html__( 'The export could not be started.', 'woocommerce-stock-inquiry' ) );
		}
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 byte order mark so Excel reads accents correctly. // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv(
			$out,
			array(
				'ID',
				__( 'Date', 'woocommerce-stock-inquiry' ),
				__( 'Updated', 'woocommerce-stock-inquiry' ),
				__( 'Status', 'woocommerce-stock-inquiry' ),
				__( 'Source', 'woocommerce-stock-inquiry' ),
				__( 'Product ID', 'woocommerce-stock-inquiry' ),
				__( 'Variation ID', 'woocommerce-stock-inquiry' ),
				__( 'Product', 'woocommerce-stock-inquiry' ),
				__( 'Name', 'woocommerce-stock-inquiry' ),
				__( 'Email', 'woocommerce-stock-inquiry' ),
				__( 'Phone', 'woocommerce-stock-inquiry' ),
				__( 'Message', 'woocommerce-stock-inquiry' ),
			),
			',',
			'"',
			''
		);

		$statuses = WSI_Inquiry_Repository::statuses();
		$sources  = WSI_Inquiry_Repository::sources();
		$after    = 0;
		do {
			$rows = WSI_Inquiry_Repository::batch( $filters, $after, 500 );
			foreach ( $rows as $row ) {
				$after = max( $after, (int) $row->id );
				fputcsv(
					$out,
					array(
						(int) $row->id,
						WSI_Inquiry_Repository::display_date( $row->created_at, 'Y-m-d H:i:s' ),
						WSI_Inquiry_Repository::display_date( $row->updated_at, 'Y-m-d H:i:s' ),
						self::csv_cell( isset( $statuses[ $row->status ] ) ? $statuses[ $row->status ] : $row->status ),
						self::csv_cell( isset( $sources[ $row->source ] ) ? $sources[ $row->source ] : $row->source ),
						(int) $row->product_id,
						(int) $row->variation_id,
						self::csv_cell( $row->product_name ),
						self::csv_cell( $row->customer_name ),
						self::csv_cell( $row->customer_email ),
						self::csv_cell( $row->customer_phone ),
						self::csv_cell( $row->message ),
					),
					',',
					'"',
					''
				);
			}
			flush();
		} while ( count( $rows ) === 500 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Small coloured status label.
	 *
	 * @param string $status Status key.
	 * @return string HTML.
	 */
	public static function status_badge( $status ) {
		$statuses = WSI_Inquiry_Repository::statuses();
		$label    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
		return '<span class="wsi-status wsi-status--' . esc_attr( sanitize_html_class( $status ) ) . '">' . esc_html( $label ) . '</span>';
	}

	/* ---------- Menu and assets ---------- */

	public function add_menu() {
		$new   = WSI_Inquiry_Repository::new_count();
		$bubble = $new > 0 ? ' <span class="awaiting-mod">' . esc_html( number_format_i18n( $new ) ) . '</span>' : '';

		$this->hook = add_submenu_page(
			'woocommerce',
			__( 'Inquiries', 'woocommerce-stock-inquiry' ),
			__( 'Inquiries', 'woocommerce-stock-inquiry' ) . $bubble,
			self::CAP,
			self::PAGE,
			array( $this, 'render_page' )
		);
		add_action( 'load-' . $this->hook, array( $this, 'on_load' ) );
	}

	public function add_screen_id( $ids ) {
		$ids[] = 'woocommerce_page_' . self::PAGE;
		return $ids;
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'wsi-inquiries', WSI_URL . 'admin/assets/inquiries.css', array(), WSI_VERSION );
		wp_enqueue_style( 'wsi-ui', WSI_URL . 'admin/assets/ui.css', array( 'wsi-inquiries' ), WSI_VERSION );
		wp_enqueue_script( 'wsi-inquiries', WSI_URL . 'admin/assets/inquiries.js', array(), WSI_VERSION, true );
		wp_localize_script(
			'wsi-inquiries',
			'wsiInquiries',
			array(
				'confirmDelete'     => __( 'Delete this inquiry and its notes? This cannot be undone.', 'woocommerce-stock-inquiry' ),
				'confirmBulkDelete' => __( 'Delete the selected inquiries and their notes? This cannot be undone.', 'woocommerce-stock-inquiry' ),
			)
		);
	}

	/* ---------- Request handling ---------- */

	/**
	 * Which view is requested.
	 *
	 * @return string dashboard|list|single
	 */
	private function current_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';
		return in_array( $view, array( 'dashboard', 'list', 'single', 'analytics' ), true ) ? $view : 'dashboard';
	}

	/**
	 * Runs before output: processes bulk actions and prepares the list.
	 */
	public function on_load() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( 'list' !== $this->current_view() ) {
			return;
		}
		$this->table = new WSI_Inquiries_List_Table();
		$this->process_bulk();

		// Tidy the address bar: drop the nonce, referer and empty filters after a filter or search.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect only.
		if ( ! empty( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'action', 'action2', 'filter_action', 'inquiry' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			exit;
		}
	}

	/**
	 * Bulk status update and bulk delete from the list.
	 */
	private function process_bulk() {
		$action = $this->table->current_action();
		if ( ! $action ) {
			return;
		}
		check_admin_referer( 'bulk-inquiries' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woocommerce-stock-inquiry' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
		$ids = isset( $_REQUEST['inquiry'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['inquiry'] ) ) : array();
		$ids = array_values( array_filter( $ids ) );
		$url = admin_url( 'admin.php?page=' . self::PAGE . '&view=list' );
		if ( ! $ids ) {
			wp_safe_redirect( $url );
			exit;
		}

		if ( 'delete' === $action ) {
			$n = WSI_Inquiry_Repository::delete( $ids );
			wp_safe_redirect( add_query_arg( array( 'wsi_msg' => 'deleted', 'wsi_n' => $n ), $url ) );
			exit;
		}
		if ( 0 === strpos( $action, 'status_' ) ) {
			$status = substr( $action, 7 );
			if ( array_key_exists( $status, WSI_Inquiry_Repository::statuses() ) ) {
				$n = 0;
				foreach ( $ids as $id ) {
					if ( WSI_Inquiry_Repository::update_status( $id, $status, get_current_user_id() ) ) {
						++$n;
					}
				}
				wp_safe_redirect( add_query_arg( array( 'wsi_msg' => 'status', 'wsi_n' => $n ), $url ) );
				exit;
			}
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Shared guard for the admin-post handlers.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return int Inquiry ID.
	 */
	private function guard( $nonce_action_prefix ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woocommerce-stock-inquiry' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Nonce is checked on the next line.
		$id = isset( $_REQUEST['id'] ) ? absint( wp_unslash( $_REQUEST['id'] ) ) : 0;
		check_admin_referer( $nonce_action_prefix . $id );
		return $id;
	}

	public function handle_update_status() {
		$id = $this->guard( 'wsi_status_' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$msg    = 'nochange';
		if ( WSI_Inquiry_Repository::get( $id ) && array_key_exists( $status, WSI_Inquiry_Repository::statuses() ) ) {
			$msg = WSI_Inquiry_Repository::update_status( $id, $status, get_current_user_id() ) ? 'status' : 'nochange';
		}
		wp_safe_redirect( add_query_arg( 'wsi_msg', $msg, self::view_url( $id ) ) );
		exit;
	}

	public function handle_add_note() {
		$id = $this->guard( 'wsi_note_' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$note = isset( $_POST['note'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) ) : '';
		$msg  = 'badnote';
		if ( '' !== $note && WSI_Inquiry_Repository::get( $id ) ) {
			$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 2000 ) : substr( $note, 0, 2000 );
			$msg  = WSI_Inquiry_Repository::add_note( $id, get_current_user_id(), $note, 'note' ) ? 'note' : 'badnote';
		}
		wp_safe_redirect( add_query_arg( 'wsi_msg', $msg, self::view_url( $id ) ) );
		exit;
	}

	public function handle_delete() {
		$id = $this->guard( 'wsi_delete_' );
		$n  = WSI_Inquiry_Repository::delete( array( $id ) );
		wp_safe_redirect( add_query_arg( array( 'wsi_msg' => 'deleted', 'wsi_n' => $n ), admin_url( 'admin.php?page=' . self::PAGE . '&view=list' ) ) );
		exit;
	}

	/**
	 * Send the saved admin template to the notification address, then return to the settings tab.
	 */
	public function handle_test_email() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'woocommerce-stock-inquiry' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wsi_send_test_email' );
		$sent = WSI_Notifications::send_test();
		wp_safe_redirect( admin_url( 'admin.php?page=' . WSI_Admin::PAGE . '&wsi_mail=' . ( $sent ? 'sent' : 'failed' ) . '#notifications' ) );
		exit;
	}

	/**
	 * Result of the test email, shown on the settings page.
	 */
	public function test_email_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( ! isset( $_GET['page'], $_GET['wsi_mail'] ) || WSI_Admin::PAGE !== $_GET['page'] ) {
			return;
		}
		$result = sanitize_key( wp_unslash( $_GET['wsi_mail'] ) );
		// phpcs:enable
		if ( 'sent' === $result ) {
			/* translators: %s: email address */
			$text = sprintf( __( 'Test email handed to WordPress for %s. If it does not arrive, check your spam folder or your site\'s mail (SMTP) setup.', 'woocommerce-stock-inquiry' ), WSI_Notifications::recipient() );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
		} elseif ( 'failed' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'WordPress could not send the test email. Check your site\'s mail (SMTP) setup.', 'woocommerce-stock-inquiry' ) . '</p></div>';
		}
	}

	/* ---------- Rendering ---------- */

	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'woocommerce-stock-inquiry' ), '', array( 'response' => 403 ) );
		}
		$view = $this->current_view();
		?>
		<div class="wrap wsi-inquiries">
			<header class="wsi-hero">
				<span class="wsi-hero__icon" aria-hidden="true"><span class="dashicons dashicons-email-alt"></span></span>
				<div class="wsi-hero__text">
					<h1 class="wsi-hero__title"><?php esc_html_e( 'Inquiries', 'woocommerce-stock-inquiry' ); ?></h1>
					<p class="wsi-hero__sub"><?php esc_html_e( 'Every quote request and product inquiry in one place: review it, add private notes, update its status and keep the customer informed.', 'woocommerce-stock-inquiry' ); ?></p>
				</div>
				<div class="wsi-hero__meta">
					<a class="wsi-pill" href="<?php echo esc_url( admin_url( 'admin.php?page=' . WSI_Admin::PAGE ) ); ?>"><?php esc_html_e( 'Settings', 'woocommerce-stock-inquiry' ); ?> &rarr;</a>
				</div>
			</header>
			<hr class="wp-header-end" />
			<?php $this->render_nav( $view ); ?>
			<?php $this->render_notice(); ?>
			<?php
			if ( 'list' === $view ) {
				$this->render_list();
			} elseif ( 'single' === $view ) {
				$this->render_single();
			} elseif ( 'analytics' === $view ) {
				$this->render_analytics();
			} else {
				$this->render_dashboard();
			}
			?>
		</div>
		<?php
	}

	private function render_nav( $view ) {
		$tabs = array(
			'dashboard' => array( __( 'Dashboard', 'woocommerce-stock-inquiry' ), admin_url( 'admin.php?page=' . self::PAGE ) ),
			'list'      => array( __( 'All Inquiries', 'woocommerce-stock-inquiry' ), admin_url( 'admin.php?page=' . self::PAGE . '&view=list' ) ),
			'analytics' => array( __( 'Analytics', 'woocommerce-stock-inquiry' ), admin_url( 'admin.php?page=' . self::PAGE . '&view=analytics' ) ),
		);
		echo '<nav class="nav-tab-wrapper wsi-nav" aria-label="' . esc_attr__( 'Inquiries sections', 'woocommerce-stock-inquiry' ) . '">';
		foreach ( $tabs as $key => $tab ) {
			$active = ( $key === $view || ( 'single' === $view && 'list' === $key ) ) ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $active ) . '" href="' . esc_url( $tab[1] ) . '">' . esc_html( $tab[0] ) . '</a>';
		}
		echo '<a class="nav-tab" href="' . esc_url( admin_url( 'admin.php?page=' . WSI_Admin::PAGE ) ) . '">' . esc_html__( 'Settings', 'woocommerce-stock-inquiry' ) . '</a>';
		echo '</nav>';
	}

	private function render_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$msg = isset( $_GET['wsi_msg'] ) ? sanitize_key( wp_unslash( $_GET['wsi_msg'] ) ) : '';
		$n   = isset( $_GET['wsi_n'] ) ? absint( wp_unslash( $_GET['wsi_n'] ) ) : 0;
		// phpcs:enable
		$text = '';
		$type = 'success';
		switch ( $msg ) {
			case 'status':
				/* translators: %s: number of inquiries */
				$text = $n > 0 ? sprintf( _n( 'Status updated for %s inquiry.', 'Status updated for %s inquiries.', $n, 'woocommerce-stock-inquiry' ), number_format_i18n( $n ) ) : __( 'Status updated.', 'woocommerce-stock-inquiry' );
				break;
			case 'nochange':
				$text = __( 'Nothing changed.', 'woocommerce-stock-inquiry' );
				$type = 'info';
				break;
			case 'note':
				$text = __( 'Note added.', 'woocommerce-stock-inquiry' );
				break;
			case 'badnote':
				$text = __( 'The note was empty or could not be saved.', 'woocommerce-stock-inquiry' );
				$type = 'error';
				break;
			case 'deleted':
				/* translators: %s: number of inquiries */
				$text = sprintf( _n( '%s inquiry deleted.', '%s inquiries deleted.', $n, 'woocommerce-stock-inquiry' ), number_format_i18n( $n ) );
				break;
		}
		if ( '' !== $text ) {
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
		}
	}

	/* ---------- Dashboard ---------- */

	private function render_dashboard() {
		$counts  = WSI_Inquiry_Repository::counts_by_status();
		$total   = array_sum( $counts );
		$recent  = WSI_Inquiry_Repository::recent( 8 );
		$top     = WSI_Inquiry_Repository::top_products( 5 );
		$sources = WSI_Inquiry_Repository::counts_by_source();
		$list    = admin_url( 'admin.php?page=' . self::PAGE . '&view=list' );

		$cards = array(
			array( __( 'Total inquiries', 'woocommerce-stock-inquiry' ), $total, $list, 'total' ),
		);
		foreach ( WSI_Inquiry_Repository::statuses() as $key => $label ) {
			$cards[] = array( $label, $counts[ $key ], add_query_arg( 'status', $key, $list ), $key );
		}

		echo '<div class="wsi-cards">';
		foreach ( $cards as $card ) {
			printf(
				'<a class="wsi-card wsi-card--%1$s" href="%2$s"><span class="wsi-card__number">%3$s</span><span class="wsi-card__label">%4$s</span></a>',
				esc_attr( sanitize_html_class( $card[3] ) ),
				esc_url( $card[2] ),
				esc_html( number_format_i18n( $card[1] ) ),
				esc_html( $card[0] )
			);
		}
		echo '</div>';

		if ( 0 === $total ) {
			echo '<div class="wsi-empty"><h2>' . esc_html__( 'No inquiries yet', 'woocommerce-stock-inquiry' ) . '</h2><p>' . esc_html__( 'Inquiries sent through the Inquiry Form (modal) or the [wsi_inquiry_form] shortcode appear here. Set the form up under Settings > Inquiry Method.', 'woocommerce-stock-inquiry' ) . '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . WSI_Admin::PAGE ) ) . '">' . esc_html__( 'Open Settings', 'woocommerce-stock-inquiry' ) . '</a></p></div>';
			return;
		}
		?>
		<div class="wsi-columns">
			<div class="wsi-panel wsi-panel--wide">
				<h2><?php esc_html_e( 'Recent inquiries', 'woocommerce-stock-inquiry' ); ?></h2>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'ID', 'woocommerce-stock-inquiry' ); ?></th>
						<th><?php esc_html_e( 'Product', 'woocommerce-stock-inquiry' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'woocommerce-stock-inquiry' ); ?></th>
						<th><?php esc_html_e( 'Status', 'woocommerce-stock-inquiry' ); ?></th>
						<th><?php esc_html_e( 'Date', 'woocommerce-stock-inquiry' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( self::view_url( $row->id ) ); ?>">#<?php echo (int) $row->id; ?></a></td>
							<td><?php echo esc_html( '' !== $row->product_name ? $row->product_name : __( '(general inquiry)', 'woocommerce-stock-inquiry' ) ); ?></td>
							<td><?php echo esc_html( $row->customer_name ); ?></td>
							<td><?php echo self::status_badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?></td>
							<td><?php echo esc_html( WSI_Inquiry_Repository::display_date( $row->created_at ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><a href="<?php echo esc_url( $list ); ?>"><?php esc_html_e( 'View all inquiries', 'woocommerce-stock-inquiry' ); ?> &rarr;</a></p>
			</div>

			<div class="wsi-panel">
				<h2><?php esc_html_e( 'Most requested products', 'woocommerce-stock-inquiry' ); ?></h2>
				<?php if ( $top ) : ?>
					<table class="widefat striped"><tbody>
					<?php foreach ( $top as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( add_query_arg( 'product_id', (int) $row->product_id, $list ) ); ?>"><?php echo esc_html( '' !== $row->product_name ? $row->product_name : '#' . $row->product_id ); ?></a></td>
							<td class="wsi-num"><?php echo esc_html( number_format_i18n( (int) $row->total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No product inquiries yet.', 'woocommerce-stock-inquiry' ); ?></p>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Inquiry sources', 'woocommerce-stock-inquiry' ); ?></h2>
				<table class="widefat striped"><tbody>
				<?php foreach ( WSI_Inquiry_Repository::sources() as $key => $label ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( add_query_arg( 'source', $key, $list ) ); ?>"><?php echo esc_html( $label ); ?></a></td>
						<td class="wsi-num"><?php echo esc_html( number_format_i18n( $sources[ $key ] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
		</div>
		<?php
	}

	/* ---------- Analytics ---------- */

	/**
	 * One horizontal bar row: label, bar, number. Width is a clamped integer percentage.
	 *
	 * @param string $label Label (plain text, escaped here).
	 * @param int    $value Value.
	 * @param int    $max   Largest value in the set.
	 * @param string $url   Optional link for the label.
	 */
	private static function bar_row( $label, $value, $max, $url = '' ) {
		$width = $max > 0 ? max( 0, min( 100, (int) round( $value / $max * 100 ) ) ) : 0;
		$text  = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
		printf(
			'<tr><td class="wsi-bar-label">%1$s</td><td class="wsi-bar-cell"><span class="wsi-bar" style="width:%2$d%%"></span></td><td class="wsi-num">%3$s</td></tr>',
			$text, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			(int) $width,
			esc_html( number_format_i18n( (int) $value ) )
		);
	}

	/**
	 * Analytics from stored inquiry records only (aggregated queries; no tracking scripts).
	 */
	private function render_analytics() {
		$counts   = WSI_Inquiry_Repository::counts_by_status();
		$total    = array_sum( $counts );
		$sources  = WSI_Inquiry_Repository::counts_by_source();
		$products = WSI_Inquiry_Repository::top_products( 20 );
		$months   = WSI_Inquiry_Repository::monthly_counts( 12 );
		$list     = admin_url( 'admin.php?page=' . self::PAGE . '&view=list' );

		echo '<div class="wsi-cards"><span class="wsi-card wsi-card--total"><span class="wsi-card__number">' . esc_html( number_format_i18n( $total ) ) . '</span><span class="wsi-card__label">' . esc_html__( 'Stored inquiries', 'woocommerce-stock-inquiry' ) . '</span></span></div>';

		echo '<p class="description wsi-note">' . esc_html__( 'These numbers count inquiries that were actually saved in your database. Clicks on an inquiry button are not counted, and opening WhatsApp or an email app is never counted as an inquiry, because the plugin cannot know whether the customer sent anything.', 'woocommerce-stock-inquiry' ) . '</p>';

		if ( 0 === $total ) {
			echo '<div class="wsi-empty"><h2>' . esc_html__( 'No data yet', 'woocommerce-stock-inquiry' ) . '</h2><p>' . esc_html__( 'Statistics appear after the first inquiry is stored.', 'woocommerce-stock-inquiry' ) . '</p></div>';
			return;
		}
		?>
		<div class="wsi-columns">
			<div class="wsi-panel wsi-panel--wide">
				<h2><?php esc_html_e( 'Inquiries per month (last 12 months, UTC)', 'woocommerce-stock-inquiry' ); ?></h2>
				<table class="widefat striped wsi-bars"><tbody>
				<?php
				$max = max( $months );
				foreach ( array_reverse( $months, true ) as $ym => $count ) {
					self::bar_row( wp_date( 'F Y', strtotime( $ym . '-01 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) ), $count, $max );
				}
				?>
				</tbody></table>

				<h2><?php esc_html_e( 'Most requested products', 'woocommerce-stock-inquiry' ); ?></h2>
				<?php if ( $products ) : ?>
				<table class="widefat striped wsi-bars"><tbody>
					<?php
					$max = (int) $products[0]->total;
					foreach ( $products as $row ) {
						self::bar_row( '' !== $row->product_name ? $row->product_name : '#' . $row->product_id, (int) $row->total, $max, add_query_arg( 'product_id', (int) $row->product_id, $list ) );
					}
					?>
				</tbody></table>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No product inquiries yet.', 'woocommerce-stock-inquiry' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="wsi-panel">
				<h2><?php esc_html_e( 'By status', 'woocommerce-stock-inquiry' ); ?></h2>
				<table class="widefat striped wsi-bars"><tbody>
				<?php
				$max = max( $counts );
				foreach ( WSI_Inquiry_Repository::statuses() as $key => $label ) {
					self::bar_row( $label, $counts[ $key ], $max, add_query_arg( 'status', $key, $list ) );
				}
				?>
				</tbody></table>

				<h2><?php esc_html_e( 'By source', 'woocommerce-stock-inquiry' ); ?></h2>
				<table class="widefat striped wsi-bars"><tbody>
				<?php
				$max = max( $sources );
				foreach ( WSI_Inquiry_Repository::sources() as $key => $label ) {
					self::bar_row( $label, $sources[ $key ], $max, add_query_arg( 'source', $key, $list ) );
				}
				?>
				</tbody></table>
			</div>
		</div>
		<?php
	}

	/* ---------- List ---------- */

	private function render_list() {
		if ( ! $this->table ) {
			$this->table = new WSI_Inquiries_List_Table();
		}
		$this->table->prepare_items();
		$f = WSI_Inquiries_List_Table::filters();
		?>
		<p class="wsi-export">
			<a class="button" href="<?php echo esc_url( self::export_url( $f ) ); ?>"><?php esc_html_e( 'Export CSV', 'woocommerce-stock-inquiry' ); ?></a>
			<span class="description"><?php esc_html_e( 'Exports the inquiries matching the current filters. The file contains customers\' personal data, so store it safely. Internal notes are not included.', 'woocommerce-stock-inquiry' ); ?></span>
		</p>
		<form method="get" class="wsi-list-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
			<input type="hidden" name="view" value="list" />
			<?php if ( '' !== $f['status'] ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $f['status'] ); ?>" />
			<?php endif; ?>
			<?php $this->table->views(); ?>
			<?php $this->table->search_box( __( 'Search inquiries', 'woocommerce-stock-inquiry' ), 'wsi-inquiry' ); ?>
			<?php $this->table->display(); ?>
		</form>
		<?php
	}

	/* ---------- Single inquiry ---------- */

	private function render_single() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view.
		$id      = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$inquiry = $id ? WSI_Inquiry_Repository::get( $id ) : null;
		$back    = admin_url( 'admin.php?page=' . self::PAGE . '&view=list' );
		if ( ! $inquiry ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'This inquiry was not found. It may have been deleted.', 'woocommerce-stock-inquiry' ) . '</p></div>';
			echo '<p><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Back to all inquiries', 'woocommerce-stock-inquiry' ) . '</a></p>';
			return;
		}

		$statuses = WSI_Inquiry_Repository::statuses();
		$sources  = WSI_Inquiry_Repository::sources();
		$notes    = WSI_Inquiry_Repository::get_notes( $inquiry->id );

		// The product may have been deleted or unpublished: fall back to the stored name.
		$product   = $inquiry->product_id ? wc_get_product( (int) $inquiry->product_id ) : null;
		$available = $product instanceof WC_Product && 'trash' !== get_post_status( (int) $inquiry->product_id );
		$name      = '' !== $inquiry->product_name ? $inquiry->product_name : __( '(general inquiry)', 'woocommerce-stock-inquiry' );
		?>
		<p><a href="<?php echo esc_url( $back ); ?>">&larr; <?php esc_html_e( 'Back to all inquiries', 'woocommerce-stock-inquiry' ); ?></a></p>
		<h2 class="wsi-single-title">
			<?php /* translators: %d: inquiry ID */ echo esc_html( sprintf( __( 'Inquiry #%d', 'woocommerce-stock-inquiry' ), (int) $inquiry->id ) ); ?>
			<?php echo self::status_badge( $inquiry->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?>
		</h2>

		<div class="wsi-columns">
			<div class="wsi-panel wsi-panel--wide">
				<h3><?php esc_html_e( 'Product', 'woocommerce-stock-inquiry' ); ?></h3>
				<div class="wsi-product">
					<?php if ( $available && $product->get_image_id() ) : ?>
						<div class="wsi-product__image"><?php echo wp_kses_post( $product->get_image( array( 80, 80 ) ) ); ?></div>
					<?php endif; ?>
					<div>
						<strong><?php echo esc_html( $name ); ?></strong>
						<?php if ( $available ) : ?>
							<br /><a href="<?php echo esc_url( get_permalink( (int) $inquiry->product_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View product', 'woocommerce-stock-inquiry' ); ?></a>
							<?php if ( current_user_can( 'edit_post', (int) $inquiry->product_id ) ) : ?>
								&middot; <a href="<?php echo esc_url( get_edit_post_link( (int) $inquiry->product_id ) ); ?>"><?php esc_html_e( 'Edit product', 'woocommerce-stock-inquiry' ); ?></a>
							<?php endif; ?>
						<?php elseif ( $inquiry->product_id ) : ?>
							<br /><span class="description"><?php esc_html_e( 'This product has been deleted. The name above is the one stored with the inquiry.', 'woocommerce-stock-inquiry' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<h3><?php esc_html_e( 'Customer', 'woocommerce-stock-inquiry' ); ?></h3>
				<table class="widefat striped wsi-details"><tbody>
					<tr><th><?php esc_html_e( 'Name', 'woocommerce-stock-inquiry' ); ?></th><td><?php echo esc_html( $inquiry->customer_name ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Email', 'woocommerce-stock-inquiry' ); ?></th><td><a href="mailto:<?php echo esc_attr( $inquiry->customer_email ); ?>"><?php echo esc_html( $inquiry->customer_email ); ?></a></td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'woocommerce-stock-inquiry' ); ?></th><td>
						<?php if ( '' !== $inquiry->customer_phone ) : ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $inquiry->customer_phone ) ); ?>"><?php echo esc_html( $inquiry->customer_phone ); ?></a>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Source', 'woocommerce-stock-inquiry' ); ?></th><td><?php echo esc_html( isset( $sources[ $inquiry->source ] ) ? $sources[ $inquiry->source ] : $inquiry->source ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Submitted', 'woocommerce-stock-inquiry' ); ?></th><td><?php echo esc_html( WSI_Inquiry_Repository::display_date( $inquiry->created_at ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Last updated', 'woocommerce-stock-inquiry' ); ?></th><td><?php echo esc_html( WSI_Inquiry_Repository::display_date( $inquiry->updated_at ) ); ?></td></tr>
				</tbody></table>

				<h3><?php esc_html_e( 'Message', 'woocommerce-stock-inquiry' ); ?></h3>
				<div class="wsi-message"><?php echo nl2br( esc_html( $inquiry->message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by esc_html(). ?></div>
			</div>

			<div class="wsi-panel">
				<h3><?php esc_html_e( 'Status', 'woocommerce-stock-inquiry' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wsi-status-form">
					<input type="hidden" name="action" value="wsi_update_status" />
					<input type="hidden" name="id" value="<?php echo esc_attr( $inquiry->id ); ?>" />
					<?php wp_nonce_field( 'wsi_status_' . (int) $inquiry->id ); ?>
					<label class="screen-reader-text" for="wsi-status-select"><?php esc_html_e( 'Status', 'woocommerce-stock-inquiry' ); ?></label>
					<select name="status" id="wsi-status-select">
						<?php foreach ( $statuses as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $inquiry->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Update status', 'woocommerce-stock-inquiry' ); ?></button>
				</form>

				<h3><?php esc_html_e( 'Internal notes and history', 'woocommerce-stock-inquiry' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Only administrators see these. They are never shown to customers or included in emails.', 'woocommerce-stock-inquiry' ); ?></p>
				<?php if ( $notes ) : ?>
					<ul class="wsi-notes">
					<?php foreach ( $notes as $note ) : ?>
						<?php
						$author = $note->author_id ? get_userdata( (int) $note->author_id ) : false;
						?>
						<li class="wsi-note wsi-note--<?php echo esc_attr( sanitize_html_class( $note->note_type ) ); ?>">
							<div class="wsi-note__text"><?php echo nl2br( esc_html( $note->content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by esc_html(). ?></div>
							<div class="wsi-note__meta">
								<?php echo esc_html( $author ? $author->display_name : __( 'System', 'woocommerce-stock-inquiry' ) ); ?>
								&middot; <?php echo esc_html( WSI_Inquiry_Repository::display_date( $note->created_at ) ); ?>
							</div>
						</li>
					<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No notes yet.', 'woocommerce-stock-inquiry' ); ?></p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wsi-note-form">
					<input type="hidden" name="action" value="wsi_add_note" />
					<input type="hidden" name="id" value="<?php echo esc_attr( $inquiry->id ); ?>" />
					<?php wp_nonce_field( 'wsi_note_' . (int) $inquiry->id ); ?>
					<label class="screen-reader-text" for="wsi-note"><?php esc_html_e( 'Add a note', 'woocommerce-stock-inquiry' ); ?></label>
					<textarea name="note" id="wsi-note" rows="3" maxlength="2000" class="large-text" placeholder="<?php esc_attr_e( 'Add a private note…', 'woocommerce-stock-inquiry' ); ?>"></textarea>
					<button type="submit" class="button"><?php esc_html_e( 'Add note', 'woocommerce-stock-inquiry' ); ?></button>
				</form>

				<p class="wsi-danger"><a href="<?php echo esc_url( self::delete_url( $inquiry->id ) ); ?>" class="button-link-delete wsi-confirm-delete"><?php esc_html_e( 'Delete this inquiry', 'woocommerce-stock-inquiry' ); ?></a></p>
			</div>
		</div>
		<?php
	}
}
