<?php
/**
 * Elementor availability checks and popup lookup. Everything here is optional: when Elementor
 * (or Elementor Pro, which provides the Popup Builder) is missing these methods simply report
 * "not available" and the rest of the plugin is unaffected.
 *
 * @package WooCommerce_Stock_Inquiry
 */

defined( 'ABSPATH' ) || exit;

/**
 * Optional Elementor / Elementor Pro helpers.
 */
class WSI_Elementor {

	/**
	 * @return bool Elementor (free) is active.
	 */
	public static function is_active() {
		return defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ) > 0;
	}

	/**
	 * @return bool Elementor Pro is active (it provides popups).
	 */
	public static function is_pro_active() {
		return self::is_active() && ( defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin' ) );
	}

	/**
	 * Published Elementor popups (id => title).
	 *
	 * @return array
	 */
	public static function get_popups() {
		if ( ! self::is_active() ) {
			return array();
		}
		$posts  = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only lookup, capped at 100 posts.
				'meta_key'       => '_elementor_template_type',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => 'popup',
			)
		);
		$popups = array();
		foreach ( $posts as $post ) {
			$popups[ (int) $post->ID ] = '' !== $post->post_title ? $post->post_title : sprintf( '#%d', $post->ID );
		}
		return $popups;
	}

	/**
	 * @param int $popup_id Popup (template) ID.
	 * @return bool The ID is a published Elementor popup.
	 */
	public static function popup_exists( $popup_id ) {
		$popup_id = absint( $popup_id );
		if ( ! $popup_id || 'elementor_library' !== get_post_type( $popup_id ) || 'publish' !== get_post_status( $popup_id ) ) {
			return false;
		}
		return 'popup' === get_post_meta( $popup_id, '_elementor_template_type', true );
	}

	/**
	 * Ask Elementor Pro to print the popup on the current page, so it can be opened by our button
	 * even when its display conditions do not cover the page. Guarded: the method belongs to
	 * Elementor Pro's internal API, so a missing or changed method is ignored. If it is ignored,
	 * the popup's own display conditions decide whether it is on the page.
	 *
	 * @param int $popup_id Popup ID.
	 */
	public static function request_popup( $popup_id ) {
		if ( ! self::is_pro_active() || ! class_exists( '\ElementorPro\Modules\Popup\Module' ) ) {
			return;
		}
		try {
			$module = \ElementorPro\Modules\Popup\Module::instance();
			if ( is_object( $module ) && method_exists( $module, 'add_popup_to_location' ) ) {
				$module->add_popup_to_location( absint( $popup_id ) );
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Optional integration must never break the page.
			return;
		}
	}
}
