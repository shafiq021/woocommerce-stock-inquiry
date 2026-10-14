/**
 * WooCommerce Stock Inquiry - settings page behavior.
 * Tabs, method toggle, dependent sections, color pickers, sticky live preview
 * (heading > button > description) and the WhatsApp chat-bubble preview.
 */
( function ( $ ) {
	'use strict';

	var cfg      = window.wsiAdmin || {};
	var defaults = cfg.defaults || {};
	var selector = cfg.selector;
	var i18n     = cfg.i18n || {};
	var hexRe    = /^#(?:[0-9a-f]{3}){1,2}$/i;
	var ctx      = 'single'; // Preview context: 'single' (product page) or 'loops' (listings).
	var state    = 'out';    // Preview stock state: 'out', 'low' or 'in'.
	var values   = $.extend( {}, cfg.samples || {} ); // Placeholder values (samples, or a real product's).

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

	/** Mirrors WSI_Placeholders::replace(): one pass, "\r\n" and a typed backslash-n become line breaks. */
	function fillPlaceholders( template ) {
		var t = String( template || '' ).replace( /\r\n?/g, '\n' ).split( '\\n' ).join( '\n' );
		return t.replace( /\{[a-z_]+\}/g, function ( tag ) {
			return Object.prototype.hasOwnProperty.call( values, tag ) ? String( values[ tag ] ) : tag;
		} );
	}

	/** Mirrors WSI_WhatsApp::build_message(). */
	function waMessage() {
		var raw = String( $f( 'whatsapp_message' ).val() || '' );
		if ( ! $.trim( raw ) ) { raw = String( defaults.whatsapp_message || '' ); }
		var t       = raw.replace( /\r\n?/g, '\n' ).split( '\\n' ).join( '\n' );
		var hasName = t.indexOf( '{product_name}' ) > -1;
		var hasUrl  = t.indexOf( '{product_url}' ) > -1;
		var m       = fillPlaceholders( t );
		var extra   = [];

		if ( val( 'whatsapp_auto_name' ) && ! hasName ) {
			extra.push( ( i18n.productLabel || 'Product: %s' ).replace( '%s', values[ '{product_name}' ] ) );
		}
		if ( val( 'whatsapp_auto_url' ) && ! hasUrl ) {
			extra.push( ( i18n.urlLabel || 'Product URL: %s' ).replace( '%s', values[ '{product_url}' ] ) );
		}
		if ( extra.length ) {
			m = m.replace( /\s+$/, '' ) + '\n\n' + extra.join( '\n' );
		}
		return $.trim( m );
	}

	/**
	 * Mirrors WSI_Stock_Checker::unit_matches() for the three preview states.
	 * Sample stock: out = 0 (out of stock), low = threshold - 1 (or the threshold itself with
	 * "less than or equal"), in = plenty. Backorders and unmanaged stock are not simulated.
	 */
	function inquiryShows() {
		var mode      = val( 'trigger_mode' );
		var threshold = num( 'stock_threshold', 0, 100000 );
		if ( mode === 'disabled' ) { return false; }
		if ( mode === 'always' ) { return true; }
		var useOut   = mode === 'out_only' || mode === 'out_or_below';
		var useBelow = mode === 'below' || mode === 'out_or_below';
		if ( state === 'out' ) {
			return useOut || ( useBelow && threshold > 0 );
		}
		if ( state === 'low' ) {
			return useBelow && threshold > 0;
		}
		return false;
	}

	function triggerNote() {
		var mode      = val( 'trigger_mode' );
		var threshold = num( 'stock_threshold', 0, 100000 );
		var lte       = val( 'threshold_compare' ) === 'lte';
		if ( mode === 'disabled' ) { return 'Disabled: the button never appears unless a product has its own settings.'; }
		if ( mode === 'always' ) { return 'Always: the inquiry button appears for every product.'; }
		var notes = [];
		if ( mode === 'out_only' ) {
			return 'Out of stock only: stock 0 (out of stock) shows the inquiry; any in-stock quantity keeps Add to Cart.';
		}
		var test = [ threshold + 5, threshold + 1, threshold, threshold - 1, 0 ];
		$.each( test, function ( i, q ) {
			if ( q < 0 || ( i > 0 && q === test[ i - 1 ] ) ) { return; }
			var hit = threshold > 0 && ( lte ? q <= threshold : q < threshold );
			if ( q === 0 && mode === 'out_or_below' ) { hit = true; }
			notes.push( 'stock ' + q + ' \u2192 ' + ( hit ? 'Inquiry' : 'Add to Cart' ) );
		} );
		return 'Threshold ' + threshold + ( lte ? ' (less than or equal)' : ' (less than)' ) + ': ' + notes.join( ', ' ) + '.';
	}

	function updatePreview() {
		var method = val( 'method' );
		var sfx    = ctx;

		// Button.
		var text = $.trim( val( 'button_text_' + method ) ) || defaults[ 'button_text_' + method ] || 'Send Inquiry';
		$( '#wsi-preview-button' ).text( text );

		// Stock state: what a customer would see. An out-of-stock product has no Add to Cart to keep;
		// otherwise Add to Cart stays unless the inquiry button replaces it.
		var showInquiry = inquiryShows();
		var position    = val( 'position' );
		var showCart    = ( state === 'out' ) ? false : ( ! showInquiry || position !== 'replace' );

		$( '#wsi-preview-container' ).toggle( showInquiry );
		$( '#wsi-preview-cart' ).toggle( showCart );

		var note;
		if ( showInquiry ) {
			note = ( state === 'out' ) ? 'Out of stock: the inquiry button is shown.' :
				( position === 'replace' ? 'The inquiry button replaces Add to Cart.' : 'The inquiry button is shown next to Add to Cart.' );
		} else {
			note = ( state === 'out' ) ? 'Out of stock, but the trigger does not use it: WooCommerce shows its own out-of-stock message.' :
				'Normal WooCommerce Add to Cart (no inquiry for this state).';
		}
		$( '#wsi-preview-state-note' ).text( note );

		// Heading.
		var hText = $.trim( val( 'heading_text' ) );
		$( '#wsi-preview-heading' ).text( hText ).toggle( !! ( val( 'enable_heading' ) && val( 'heading_show_' + sfx ) && hText ) );

		// Description.
		var dText = $.trim( val( 'desc_text_' + method ) );
		$( '#wsi-preview-desc' ).html( paragraphs( dText ) ).toggle( !! ( val( 'enable_desc' ) && val( 'desc_show_' + sfx ) && dText ) );

		// WhatsApp bubble and email preview.
		$( '#wsi-wa-bubble-text' ).text( waMessage() );
		$( '#wsi-mail-subject' ).text( $.trim( fillPlaceholders( $f( 'email_subject' ).val() ).replace( /\s+/g, ' ' ) ) );
		$( '#wsi-mail-body' ).text( $.trim( fillPlaceholders( $f( 'email_message' ).val() ) ) );

		$( '#wsi-trigger-example' ).text( triggerNote() );
		$( '#wsi-preview-style' ).text( buildCss() );
	}

	/** Show a section's controls only while its master toggle is on. */
	function updateDepends() {
		$( '[data-depends]' ).each( function () {
			$( this ).toggle( !! val( $( this ).data( 'depends' ) ) );
		} );
		var mode = val( 'trigger_mode' );
		$( '[data-depends-mode]' ).each( function () {
			var modes = String( $( this ).data( 'dependsMode' ) ).split( ',' );
			$( this ).toggle( $.inArray( mode, modes ) > -1 );
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
		$( '.wsi-preview-ctx button[data-ctx]' ).on( 'click', function () {
			ctx = $( this ).data( 'ctx' ) === 'loops' ? 'loops' : 'single';
			$( '.wsi-preview-ctx button[data-ctx]' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
			$( this ).addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
			updatePreview();
		} );

		// Preview stock state (out / low / in).
		$( '.wsi-preview-ctx button[data-state]' ).on( 'click', function () {
			state = $( this ).data( 'state' );
			$( '.wsi-preview-ctx button[data-state]' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
			$( this ).addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
			updatePreview();
		} );

		// Jump to the tab named in a validation notice.
		$( '.wsi-goto' ).on( 'click', function ( e ) {
			e.preventDefault();
			showTab( $( this ).data( 'tab' ) );
		} );

		// Preview with a real product.
		$( '#wsi-preview-product' ).on( 'change', function () {
			var id = parseInt( $( this ).val(), 10 );
			if ( ! id ) {
				values = $.extend( {}, cfg.samples || {} );
				updatePreview();
				return;
			}
			$.post( cfg.ajaxUrl, { action: 'wsi_preview_product', nonce: cfg.nonce, product_id: id }, function ( res ) {
				if ( res && res.success && res.data && res.data.values ) {
					values = $.extend( {}, cfg.samples || {}, res.data.values );
					updatePreview();
				}
			} );
		} );

		// Test buttons: build the real link from the values typed in the form and open it.
		$( '.wsi-test' ).on( 'click', function () {
			var $btn    = $( this );
			var kind    = $btn.data( 'kind' );
			var $result = $( '.wsi-test-result[data-kind="' + kind + '"]' );
			var pid     = parseInt( $( '#wsi-preview-product' ).val(), 10 ) || 0;
			// Open the window now (inside the click) so pop-up blockers allow it.
			var win = ( kind === 'whatsapp' || kind === 'contact' ) ? window.open( 'about:blank', '_blank' ) : null;
			if ( win ) { win.opener = null; }

			$btn.prop( 'disabled', true );
			$result.text( i18n.testing || '…' );
			$.post( cfg.ajaxUrl, {
				action: 'wsi_test_action',
				nonce: cfg.nonce,
				kind: kind,
				product_id: pid,
				settings: $( '#wsi-settings-form' ).find( '[name^="wsi_settings["]' ).serialize()
			} ).done( function ( res ) {
				if ( ! res || ! res.success ) {
					if ( win ) { win.close(); }
					$result.text( ( res && res.data && res.data.message ) || i18n.requestFailed );
					return;
				}
				var d = res.data;
				if ( ! /^(https?:|mailto:|tel:)/i.test( d.url ) && kind !== 'popup' ) {
					if ( win ) { win.close(); }
					$result.text( i18n.requestFailed );
					return;
				}
				if ( kind === 'popup' ) {
					$result.empty().append( document.createTextNode( 'Popup found. Elementor popups open on the store front, so test it there: ' ) );
					if ( d.data && /^https?:/i.test( d.data.pageUrl ) ) {
						$( '<a target="_blank" rel="noopener noreferrer">' ).attr( 'href', d.data.pageUrl ).text( d.product ).appendTo( $result );
					}
					return;
				}
				if ( win ) {
					win.location = d.url;
				} else {
					window.location.href = d.url; // mailto: / tel: open the mail or phone app.
				}
				$result.text( kind === 'email' ? 'Opened your email app. Nothing was sent.' : 'Opened with: ' + d.product );
			} ).fail( function () {
				if ( win ) { win.close(); }
				$result.text( i18n.requestFailed );
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
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

		// Category rules (Rules tab): add and remove rows.
		var ruleIndex = Date.now();
		function syncRulesEmpty() {
			$( '.wsi-rules-empty' ).prop( 'hidden', $( '#wsi-rules-body .wsi-rule-row' ).length > 0 );
		}
		$( '#wsi-add-rule' ).on( 'click', function () {
			var tpl = document.getElementById( 'wsi-rule-template' );
			if ( ! tpl ) { return; }
			ruleIndex += 1;
			var html = tpl.innerHTML.replace( /__INDEX__/g, String( ruleIndex ) );
			$( '#wsi-rules-body' ).append( html );
			syncRulesEmpty();
		} );
		$( '#wsi-rules-body' ).on( 'click', '.wsi-rule-remove', function () {
			$( this ).closest( '.wsi-rule-row' ).remove();
			syncRulesEmpty();
		} );

		// WhatsApp destinations (Inquiry Method tab): add and remove rows.
		$( '#wsi-add-dest' ).on( 'click', function () {
			var tpl = document.getElementById( 'wsi-dest-template' );
			if ( ! tpl ) { return; }
			ruleIndex += 1;
			$( '#wsi-dest-body' ).append( tpl.innerHTML.replace( /__INDEX__/g, String( ruleIndex ) ) );
		} );
		$( '#wsi-dest-body' ).on( 'click', '.wsi-dest-remove', function () {
			$( this ).closest( '.wsi-dest-row' ).remove();
		} );

		updateDepends();
		updatePreview();

		$( '.wsi-reset' ).on( 'click', function ( e ) {
			if ( ! window.confirm( i18n.confirmReset || 'Reset?' ) ) {
				e.preventDefault();
			}
		} );
	} );
}( jQuery ) );
