/**
 * WooCommerce Stock Inquiry - Inquiries screens: delete confirmations.
 * Plain JavaScript, loaded only on WooCommerce > Inquiries.
 */
( function () {
	'use strict';
	var cfg = window.wsiInquiries || {};

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest ? e.target.closest( '.wsi-confirm-delete' ) : null;
		if ( link && ! window.confirm( cfg.confirmDelete || 'Delete this inquiry?' ) ) {
			e.preventDefault();
		}
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form.classList || ! form.classList.contains( 'wsi-list-form' ) ) { return; }
		var top = form.querySelector( '#bulk-action-selector-top' );
		var bottom = form.querySelector( '#bulk-action-selector-bottom' );
		var action = ( document.activeElement && document.activeElement.id === 'doaction2' ) ? bottom : top;
		if ( action && action.value === 'delete' && ! window.confirm( cfg.confirmBulkDelete || 'Delete the selected inquiries?' ) ) {
			e.preventDefault();
		}
	} );
}() );
