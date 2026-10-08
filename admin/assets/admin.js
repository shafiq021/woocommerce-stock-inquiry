/**
 * WooCommerce Stock Inquiry - settings page behavior.
 * Tabs, method toggle, dependent sections, color pickers, sticky live preview
 * (heading > button > description > branding) and the WhatsApp chat-bubble preview.
 */
( function ( $ ) {
	'use strict';

	var cfg      = window.wsiAdmin || {};
	var defaults = cfg.defaults || {};
	var selector = cfg.selector;
	var i18n     = cfg.i18n || {};
	var hexRe    = /^#(?:[0-9a-f]{3}){1,2}$/i;
	var ctx      = 'single'; // Preview context: 'single' (product page) or 'loops' (listings).

	function $f( key ) {
		return $( '[name="wsi_settings[' + key + ']"]' );
	}

	/** Current value of a setting field (checkbox => bool, radio => checked value). */
	function val( key ) {
		var $e = $f( key );
		if ( ! $e.length ) { return defaults[ key ]; }
		if ( $e.is( ':radio' ) ) {
			var $c = $e.filter( ':checked' );
			return $c.length ? $c.val() : defaults[ key ];
		}
		if ( $e.is( ':checkbox' ) ) { return $e.is( ':checked' ); }
		return $e.val();
	}

	function num( key, min, max ) {
		var v = parseInt( val( key ), 10 );
		if ( isNaN( v ) ) { v = parseInt( defaults[ key ], 10 ); }
		return Math.min( max, Math.max( min, v ) );
	}

	function color( key ) {
		var v = $.trim( val( key ) );
		return hexRe.test( v ) ? v : ( defaults[ key ] || '#000000' );
	}

	function weight( key ) {
		var v = parseInt( val( key ), 10 );
		return $.inArray( v, [ 400, 500, 600, 700, 800 ] ) > -1 ? v : parseInt( defaults[ key ], 10 );
	}

	/** Mirrors WSI_Settings::font_stack(). */
	function stack( name ) {
		if ( ! name || name === 'inherit' ) { return 'inherit'; }
		if ( $.inArray( name, [ 'serif', 'sans-serif', 'monospace' ] ) > -1 ) { return name; }
		var fallback = $.inArray( name, [ 'Georgia', 'Playfair Display' ] ) > -1 ? 'serif' : 'sans-serif';
		return "'" + String( name ).replace( /'/g, '' ) + "', " + fallback;
	}

	function align( key, allowed ) {
		var v = val( key );
		return $.inArray( v, allowed ) > -1 ? v : defaults[ key ];
	}

	/** Mirrors WSI_Button::build_css(). */
	function buildCss() {
		var s = selector;
		var c = '.wsi-inquiry-container';
		var a = align( 'button_align', [ 'left', 'center', 'right', 'block' ] );
		var css;

		if ( a === 'block' ) {
			css = c + '{display:block;width:100%;margin:15px 0;}' + s + '{display:block;width:100%;}';
		} else {
			css = c + '{text-align:' + a + ';margin:15px 0;}' + s + '{display:inline-block;}';
		}

		css += s + '{box-sizing:border-box;min-height:0;font-family:' + stack( val( 'btn_font_family' ) ) +
			';font-size:' + num( 'font_size', 8, 48 ) + 'px;font-weight:' + weight( 'font_weight' ) +
			';line-height:1.4;text-align:center;text-decoration:none;text-shadow:none;box-shadow:none;cursor:pointer;padding:' +
			num( 'pad_top', 0, 80 ) + 'px ' + num( 'pad_right', 0, 80 ) + 'px ' + num( 'pad_bottom', 0, 80 ) + 'px ' + num( 'pad_left', 0, 80 ) +
			'px;border-radius:' + num( 'radius', 0, 100 ) + 'px;border-style:solid;border-width:' + num( 'border_width', 0, 20 ) +
			'px;border-color:' + color( 'border_color' ) + ';background-color:' + color( 'bg' ) + ';color:' + color( 'color' ) +
			';transition:background-color .15s ease,color .15s ease,border-color .15s ease}' +
			s + ':hover,' + s + ':focus{background-color:' + color( 'hover_bg' ) + ';color:' + color( 'hover_color' ) +
			';border-color:' + color( 'hover_border_color' ) + ';text-decoration:none}';

		css += c + ' .wsi-inquiry-heading{margin:0 0 10px;line-height:1.3;font-family:' + stack( val( 'heading_font_family' ) ) +
			';font-size:' + num( 'heading_size', 8, 48 ) + 'px;font-weight:' + weight( 'heading_weight' ) + ';color:' + color( 'heading_color' ) + '}';

		css += c + ' .wsi-inquiry-desc{margin:10px 0 0;line-height:1.5;font-family:' + stack( val( 'desc_font_family' ) ) +
			';font-size:' + num( 'desc_size', 8, 48 ) + 'px;color:' + color( 'desc_color' ) + '}' +
			c + ' .wsi-inquiry-desc p{margin:0 0 10px;color:inherit;font-size:inherit;font-family:inherit}' +
			c + ' .wsi-inquiry-desc p:last-child{margin-bottom:0}';

		css += c + ' .wsi-branding-sk{display:block;margin:10px 0 0;opacity:.7;color:#999;line-height:1.5;font-family:' + stack( val( 'branding_font_family' ) ) +
			';font-size:' + num( 'branding_size', 8, 24 ) + 'px;text-align:' + align( 'branding_align', [ 'left', 'center', 'right' ] ) + '}';

		return css;
	}

	function escapeHtml( t ) {
		return $( '<div>' ).text( t ).html();
	}

	/** Same shape as wpautop(): blank line = paragraph, single newline = <br>. */
	function paragraphs( t ) {
		return $.map( String( t ).replace( /\r\n?/g, '\n' ).split( /\n{2,}/ ), function ( p ) {
			p = $.trim( p );
			return p ? '<p>' + escapeHtml( p ).replace( /\n/g, '<br>' ) + '</p>' : null;
		} ).join( '' );
	}

	/** Mirrors WSI_WhatsApp::build_message() with example product data. */
	function waMessage() {
		var name = 'Example Product';
		var url  = 'https://example.com/product/example';
		var t    = String( $f( 'whatsapp_message' ).val() || '' ).replace( /\r\n?/g, '\n' );
		if ( ! $.trim( t ) ) { t = String( defaults.whatsapp_message || '' ); }

		var hasName = t.indexOf( '{product_name}' ) > -1;
		var hasUrl  = t.indexOf( '{product_url}' ) > -1;
		var m       = t.split( '{product_name}' ).join( name ).split( '{product_url}' ).join( url );
		var extra   = [];

		if ( val( 'whatsapp_auto_name' ) && ! hasName ) {
			extra.push( ( i18n.productLabel || 'Product: %s' ).replace( '%s', name ) );
		}
		if ( val( 'whatsapp_auto_url' ) && ! hasUrl ) {
			extra.push( ( i18n.urlLabel || 'Product URL: %s' ).replace( '%s', url ) );
		}
		if ( extra.length ) {
			m = m.replace( /\s+$/, '' ) + '\n\n' + extra.join( '\n' );
		}
		return $.trim( m );
	}

	function updatePreview() {
		var isWa = val( 'method' ) === 'whatsapp';
		var sfx  = ctx;

		// Button.
		var text = isWa
			? ( $.trim( val( 'button_text_whatsapp' ) ) || defaults.button_text_whatsapp || 'Send WhatsApp' )
			: ( $.trim( val( 'button_text_contact' ) ) || defaults.button_text_contact || 'Send Inquiry' );
		$( '#wsi-preview-button' ).text( text );

		// Heading.
		var hText = $.trim( val( 'heading_text' ) );
		$( '#wsi-preview-heading' ).text( hText ).toggle( !! ( val( 'enable_heading' ) && val( 'heading_show_' + sfx ) && hText ) );

		// Description.
		var dText = $.trim( val( isWa ? 'desc_text_whatsapp' : 'desc_text_contact' ) );
		$( '#wsi-preview-desc' ).html( paragraphs( dText ) ).toggle( !! ( val( 'enable_desc' ) && val( 'desc_show_' + sfx ) && dText ) );

		// Branding.
		$( '#wsi-preview-branding' ).toggle( !! val( 'branding_show_' + sfx ) );

		// WhatsApp bubble.
		$( '#wsi-wa-bubble-text' ).text( waMessage() );

		$( '#wsi-preview-style' ).text( buildCss() );
	}

	/** Show a section's controls only while its master toggle is on. */
	function updateDepends() {
		$( '[data-depends]' ).each( function () {
			$( this ).toggle( !! val( $( this ).data( 'depends' ) ) );
		} );
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
		var method = val( 'method' );
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

		// Preview context switch (product page vs listings).
		$( '.wsi-preview-ctx button' ).on( 'click', function () {
			ctx = $( this ).data( 'ctx' ) === 'loops' ? 'loops' : 'single';
			$( '.wsi-preview-ctx button' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
			$( this ).addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
			updatePreview();
		} );

		// Color pickers.
		$( '.wsi-color' ).wpColorPicker( {
			change: function ( event, ui ) {
				$( event.target ).val( ui.color.toString() );
				updatePreview();
			},
			clear: function () { setTimeout( updatePreview, 0 ); }
		} );

		// Any field change refreshes the preview and dependent sections.
		$( '#wsi-settings-form' ).on( 'input change keyup', 'input, textarea, select', function () {
			updateDepends();
			updatePreview();
		} );
		$( '#wsi-preview-button' ).on( 'click', function ( e ) { e.preventDefault(); } );

		updateDepends();
		updatePreview();

		$( '.wsi-reset' ).on( 'click', function ( e ) {
			if ( ! window.confirm( i18n.confirmReset || 'Reset?' ) ) {
				e.preventDefault();
			}
		} );
	} );
}( jQuery ) );
