/**
 * WooCommerce Stock Inquiry - front end.
 *
 * Only loaded on pages that print a variable-product inquiry button or a popup button.
 *  1. Variable products: shows or hides the inquiry button when the selected variation changes,
 *     using the `wsi` data the plugin adds to each variation (found_variation event).
 *  2. Popup button: opens the selected Elementor Pro popup and fills product details into it.
 *
 * Without JavaScript the normal WooCommerce form is shown, so nothing breaks.
 */
( function ( $ ) {
	'use strict';

	var SAFE_URL  = /^(https?:|mailto:|tel:|#)/i;
	var SAFE_ATTR = /^(?:data-[a-z0-9_-]+|aria-[a-z0-9_-]+|role)$/i;

	/* ---------- Variable products ---------- */

	function applyLink( $a, d ) {
		if ( d.url && SAFE_URL.test( d.url ) ) {
			$a.attr( 'href', d.url );
		}
		if ( d.text ) {
			$a.text( d.text );
		}
		if ( d.label ) {
			$a.attr( 'aria-label', d.label );
		}
		if ( d.target === '_blank' ) {
			$a.attr( { target: '_blank', rel: 'noopener noreferrer' } );
		} else {
			$a.removeAttr( 'target' ).removeAttr( 'rel' );
		}

		// Replace the method's own attributes (popup id, product data) with the variation's.
		$.each( $a[ 0 ].attributes, function () {
			if ( this && SAFE_ATTR.test( this.name ) && this.name.indexOf( 'aria-label' ) !== 0 ) {
				$a.removeAttr( this.name );
			}
		} );
		$.each( d.attrs || {}, function ( name, value ) {
			if ( SAFE_ATTR.test( name ) ) {
				$a.attr( name, String( value ) );
			}
		} );
	}

	function setupForm( $form ) {
		var $box = $form.nextAll( '.wsi-inquiry-container[data-wsi-dynamic]' ).first();
		if ( ! $box.length ) {
			$box = $form.closest( '.product, .elementor-widget-container, body' ).find( '.wsi-inquiry-container[data-wsi-dynamic]' ).first();
		}
		if ( ! $box.length || $box.data( 'wsiReady' ) ) {
			return;
		}
		$box.data( 'wsiReady', true );

		var position = $box.attr( 'data-wsi-position' ) || 'replace';
		var $block   = $form.find( '.woocommerce-variation-add-to-cart' ).first();
		var $link    = $box.find( 'a.wsi-inquiry-button' ).first();

		// Put the button next to WooCommerce's add to cart block.
		if ( $block.length ) {
			if ( position === 'before' ) {
				$box.insertBefore( $block );
			} else {
				$box.insertAfter( $block );
			}
		}

		function hide() {
			$box.css( 'display', 'none' );
			$block.css( 'display', '' );
		}

		function show( d ) {
			applyLink( $link, d );
			$box.css( 'display', '' );
			if ( position === 'replace' ) {
				$block.css( 'display', 'none' );
			} else {
				$block.css( 'display', '' );
			}
		}

		$form.on( 'found_variation', function ( event, variation ) {
			var d = variation && variation.wsi;
			if ( d && d.inquiry ) {
				show( d );
			} else {
				hide();
			}
		} );
		$form.on( 'reset_data hide_variation', hide );

		// A variation may already be selected (default attributes) before this script was ready.
		$form.trigger( 'check_variations' );
	}

	/* ---------- Elementor Pro popup ---------- */

	var pending = null; // { id: number, data: object }

	function fill( $root, data ) {
		var map = {
			'wsi-product-id':    data.id,
			'wsi-product-name':  data.name,
			'wsi-product-url':   data.url,
			'wsi-product-sku':   data.sku,
			'wsi-product-price': data.price,
			'wsi-message':       data.message
		};
		$.each( map, function ( cssClass, value ) {
			if ( value === undefined || value === null || value === '' ) {
				return;
			}
			$root.find( '.' + cssClass ).each( function () {
				var $el     = $( this );
				var $fields = $el.is( 'input, textarea, select' ) ? $el : $el.find( 'input, textarea' );
				if ( $fields.length ) {
					$fields.val( value ).trigger( 'input' ).trigger( 'change' );
				} else {
					$el.text( value ); // Text only, never HTML.
				}
			} );
		} );
	}

	function fillPopup( popupId ) {
		if ( ! pending || pending.id !== popupId ) {
			return;
		}
		var $root = $( '#elementor-popup-modal-' + popupId );
		if ( ! $root.length ) {
			$root = $( '[data-elementor-type="popup"][data-elementor-id="' + popupId + '"]' );
		}
		if ( $root.length ) {
			fill( $root, pending.data );
		}
	}

	function openPopup( popupId, data ) {
		pending = { id: popupId, data: data };
		window.wsiProduct = data;
		$( document ).trigger( 'wsi:product', [ data ] );

		var pro = window.elementorProFrontend;
		if ( pro && pro.modules && pro.modules.popup && typeof pro.modules.popup.showPopup === 'function' ) {
			pro.modules.popup.showPopup( { id: popupId } );
			setTimeout( function () { fillPopup( popupId ); }, 350 );
		} else if ( window.console && window.console.warn ) {
			window.console.warn( 'WooCommerce Stock Inquiry: the Elementor Pro popup API is not available on this page.' );
		}
	}

	$( document ).on( 'elementor/popup/show', function ( event, id ) {
		var popupId = parseInt( ( id && typeof id === 'object' ) ? id.id : id, 10 );
		if ( popupId ) {
			fillPopup( popupId );
		}
	} );

	$( document ).on( 'click', 'a[data-wsi-popup]', function ( event ) {
		event.preventDefault();
		var $a      = $( this );
		var popupId = parseInt( $a.attr( 'data-wsi-popup' ), 10 );
		var data    = {};
		try {
			data = JSON.parse( $a.attr( 'data-wsi-product' ) || '{}' ) || {};
		} catch ( e ) {
			data = {};
		}
		if ( popupId ) {
			openPopup( popupId, data );
		}
	} );

	$( function () {
		$( 'form.variations_form' ).each( function () {
			setupForm( $( this ) );
		} );
	} );
}( jQuery ) );
