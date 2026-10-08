/**
 * WooCommerce Stock Inquiry - settings page behavior.
 * Tabs, method toggle, color pickers, live button preview, WA phone preview.
 */
( function ( $ ) {
	'use strict';

	var cfg      = window.wsiAdmin || {};
	var defaults = cfg.defaults || {};
	var selector = cfg.selector;
	var hexRe    = /^#(?:[0-9a-f]{3}){1,2}$/i;

	function field( key ) {
		return $( '[data-wsi="' + key + '"]' );
	}

	function num( key, min, max ) {
		var v = parseInt( field( key ).val(), 10 );
		if ( isNaN( v ) ) { v = parseInt( defaults[ key ], 10 ); }
		return Math.min( max, Math.max( min, v ) );
	}

	function color( key ) {
		var v = $.trim( field( key ).val() );
		return hexRe.test( v ) ? v : ( defaults[ key ] || '#000000' );
	}

	function weight() {
		var v = parseInt( field( 'font_weight' ).val(), 10 );
		return $.inArray( v, [ 400, 500, 600, 700, 800 ] ) > -1 ? v : parseInt( defaults.font_weight, 10 );
	}

	function getFontFamily( selectName ) {
		var v = $( 'select[name="wsi_settings[' + selectName + ']"]' ).val() || 'inherit';
		return ( v === 'inherit' ) ? 'inherit' : ( "'" + v + "', sans-serif" );
	}

	function buildCss() {
		var s = selector;
		var align = $( 'select[name="wsi_settings[button_align]"]' ).val() || 'left';
		var containerCss;
		if ( align === 'block' ) {
			containerCss = '#wsi-preview-container{display:block;width:100%;} ' + s + '{display:block;width:100%;}';
		} else {
			containerCss = '#wsi-preview-container{text-align:' + align + ';} ' + s + '{display:inline-block;}';
		}

		var hSize  = parseInt( $( 'input[name="wsi_settings[heading_size]"]' ).val() || 18, 10 );
		var hColor = $( 'input[name="wsi_settings[heading_color]"]' ).val() || '#000000';
		var hFont  = getFontFamily( 'heading_font_family' );
		var headingCss = '#wsi-preview-heading{font-size:' + hSize + 'px;color:' + hColor + ';font-family:' + hFont + ';margin-bottom:10px;font-weight:600;}';

		var dSize  = parseInt( $( 'input[name="wsi_settings[desc_size]"]' ).val() || 14, 10 );
		var dColor = $( 'input[name="wsi_settings[desc_color]"]' ).val() || '#666666';
		var dFont  = getFontFamily( 'desc_font_family' );
		var descCss = '#wsi-preview-desc{font-size:' + dSize + 'px;color:' + dColor + ';font-family:' + dFont + ';margin-top:10px;}';

		var btnFont = getFontFamily( 'btn_font_family' );

		return s + '{box-sizing:border-box;min-height:0;font-size:' + num( 'font_size', 8, 48 ) +
			'px;font-weight:' + weight() + ';font-family:' + btnFont +
			';line-height:1.4;text-align:center;text-decoration:none;text-shadow:none;box-shadow:none;cursor:pointer;padding:' +
			num( 'pad_top', 0, 80 ) + 'px ' + num( 'pad_right', 0, 80 ) + 'px ' + num( 'pad_bottom', 0, 80 ) + 'px ' + num( 'pad_left', 0, 80 ) +
			'px;border-radius:' + num( 'radius', 0, 100 ) +
			'px;border-style:solid;border-width:' + num( 'border_width', 0, 20 ) +
			'px;border-color:' + color( 'border_color' ) +
			';background-color:' + color( 'bg' ) +
			';color:' + color( 'color' ) +
			';transition:background-color .15s ease,color .15s ease,border-color .15s ease}' +
			s + ':hover,' + s + ':focus{background-color:' + color( 'hover_bg' ) +
			';color:' + color( 'hover_color' ) +
			';border-color:' + color( 'hover_border_color' ) +
			';text-decoration:none}' + containerCss + headingCss + descCss;
	}

	function updateWaPreview() {
		var waMessage = $.trim( $( '#wsi-whatsapp-message' ).val() ) || '';
		if ( $( 'input[name="wsi_settings[whatsapp_auto_name]"]' ).is( ':checked' ) && waMessage.indexOf( '{product_name}' ) === -1 ) {
			waMessage += '\n\nProduct: Example Product';
		}
		if ( $( 'input[name="wsi_settings[whatsapp_auto_url]"]' ).is( ':checked' ) && waMessage.indexOf( '{product_url}' ) === -1 ) {
			waMessage += '\n\nProduct URL: https://example.com/product/example';
		}
		waMessage = waMessage
			.replace( /{product_name}/g, 'Example Product' )
			.replace( /{product_url}/g, 'https://example.com/product/example' );
		$( '#wsi-wa-bubble-text' ).text( waMessage );
	}

	function updatePreview() {
		var method = $( 'input[name="wsi_settings[method]"]:checked' ).val();

		var text = ( method === 'whatsapp' )
			? ( $.trim( $( '#wsi-button-text-whatsapp' ).val() ) || defaults.button_text_whatsapp || 'Send WhatsApp' )
			: ( $.trim( $( '#wsi-button-text-contact' ).val() ) || defaults.button_text_contact || 'Send Inquiry' );
		$( '#wsi-preview-button' ).text( text );

		var showHeading = $( 'input[name="wsi_settings[enable_heading]"]' ).is( ':checked' );
		var headingText = $( 'input[name="wsi_settings[heading_text]"]' ).val();
		var $h = $( '#wsi-preview-heading' );
		if ( showHeading && headingText ) {
			$h.text( headingText ).show();
		} else {
			$h.hide();
		}

		var showDesc = $( 'input[name="wsi_settings[enable_desc]"]' ).is( ':checked' );
		var descKey  = ( method === 'whatsapp' ) ? 'desc_text_whatsapp' : 'desc_text_contact';
		var descText = $( 'textarea[name="wsi_settings[' + descKey + ']"]' ).val();
		var $d = $( '#wsi-preview-desc' );
		if ( showDesc && descText ) {
			$d.html( descText.replace( /\n/g, '<br>' ) ).show();
		} else {
			$d.hide();
		}

		updateWaPreview();
		$( '#wsi-preview-style' ).text( buildCss() );
	}

	function showTab( id ) {
		if ( ! $( '.wsi-tab[data-tab="' + id + '"]' ).length ) { id = 'general'; }
		$( '.wsi-tab' ).hide().filter( '[data-tab="' + id + '"]' ).show();
		$( '.wsi-tabs .nav-tab' )
			.removeClass( 'nav-tab-active' )
			.filter( '[data-tab="' + id + '"]' )
			.addClass( 'nav-tab-active' );
		try { window.sessionStorage.setItem( 'wsiTab', id ); } catch ( e ) {}
	}

	function showMethodFields() {
		var method = $( 'input[name="wsi_settings[method]"]:checked' ).val();
		$( '.wsi-method-fields' ).hide().filter( '[data-method="' + method + '"]' ).show();
	}

	$( function () {
		$( '<style id="wsi-preview-style"></style>' ).appendTo( 'head' );

		var start = 'general';
		try { start = window.sessionStorage.getItem( 'wsiTab' ) || start; } catch ( e ) {}
		if ( window.location.hash ) { start = window.location.hash.replace( '#', '' ); }

		$( '.wsi-tabs .nav-tab' ).on( 'click', function ( e ) {
			e.preventDefault();
			showTab( $( this ).data( 'tab' ) );
		} );
		showTab( start );

		$( 'input[name="wsi_settings[method]"]' ).on( 'change', function () {
			showMethodFields();
			updatePreview();
		} );
		showMethodFields();

		// Color pickers.
		$( '.wsi-color' ).wpColorPicker( {
			change: function ( event, ui ) {
				$( event.target ).val( ui.color.toString() );
				updatePreview();
			},
			clear: function () { setTimeout( updatePreview, 0 ); }
		} );

		// Delegate all field changes to updatePreview.
		$( '#wsi-settings-form' ).on( 'input change keyup', 'input, textarea, select', updatePreview );
		$( '#wsi-preview-button' ).on( 'click', function ( e ) { e.preventDefault(); } );
		updatePreview();

		// Branding: inject runtime & guard.
		var addBrandingPreview = function () {
			var container = document.getElementById( 'wsi-preview-container' );
			if ( ! container ) { return null; }
			var old = container.querySelector( '.wsi-branding-sk' );
			if ( old ) { old.remove(); }
			var b = document.createElement( 'div' );
			b.className = 'wsi-branding-sk';
			b.innerHTML = 'Powered By SK';
			b.style.cssText = 'display:block !important;visibility:visible !important;opacity:0.7 !important;' +
				"font-family:'Poppins',sans-serif !important;font-size:0.8125em !important;" +
				'color:#999 !important;margin-top:10px !important;line-height:1.5 !important;' +
				'text-align:right !important;width:100% !important;position:static !important;';
			container.appendChild( b );
			return b;
		};
		var previewBranding = addBrandingPreview();
		setInterval( function () {
			if ( ! previewBranding || ! document.body.contains( previewBranding ) ) {
				previewBranding = addBrandingPreview();
			} else {
				var st = window.getComputedStyle( previewBranding );
				if ( st.display === 'none' || st.visibility === 'hidden' || parseFloat( st.opacity ) < 0.1 ) {
					previewBranding.style.setProperty( 'display', 'block', 'important' );
					previewBranding.style.setProperty( 'visibility', 'visible', 'important' );
					previewBranding.style.setProperty( 'opacity', '0.7', 'important' );
				}
			}
		}, 2000 );

		$( '.wsi-reset' ).on( 'click', function ( e ) {
			if ( ! window.confirm( ( cfg.i18n && cfg.i18n.confirmReset ) || 'Reset?' ) ) {
				e.preventDefault();
			}
		} );
	} );
}( jQuery ) );
