/**
 * Product editor: show the override fields only when "Custom settings" is selected.
 */
( function ( $ ) {
	'use strict';
	$( function () {
		var $select = $( '#wsi_custom' );
		var $fields = $( '#wsi_custom_fields' );
		function sync() {
			$fields.toggle( $select.val() === 'custom' );
		}
		$select.on( 'change', sync );
		sync();
	} );
}( jQuery ) );
