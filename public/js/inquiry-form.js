/**
 * WooCommerce Stock Inquiry - inquiry form modal.
 *
 * Opens the modal when an element with data-wsi-form is clicked, validates the fields, posts the
 * inquiry to admin-ajax and shows the success message only after the server confirms that the
 * inquiry was stored. Plain JavaScript, no dependencies.
 */
( function () {
	'use strict';

	var cfg   = window.wsiForm || {};
	var i18n  = cfg.i18n || {};
	var modal = null;
	var form, dialog, titleEl, productEl, statusEl, submitBtn, successEl, successMsg, waLink;
	var current = null;      // { product: {id, variation, name}, origin, send, title }
	var lastTrigger = null;
	var busy = false;

	function $( sel, root ) { return ( root || modal ).querySelector( sel ); }

	function init() {
		modal = document.getElementById( 'wsi-modal' );
		if ( ! modal ) { return false; }
		form       = $( '.wsi-form' );
		dialog     = $( '.wsi-modal__dialog' );
		titleEl    = $( '.wsi-modal__title' );
		productEl  = $( '.wsi-modal__product' );
		statusEl   = $( '.wsi-form__status' );
		submitBtn  = $( '.wsi-form__submit' );
		successEl  = $( '.wsi-success' );
		successMsg = $( '.wsi-success__message' );
		waLink     = $( '.wsi-success__whatsapp' );

		modal.addEventListener( 'click', function ( e ) {
			if ( e.target.closest && e.target.closest( '[data-wsi-close]' ) ) { close(); }
		} );
		modal.addEventListener( 'keydown', onKey );
		form.addEventListener( 'submit', onSubmit );
		return true;
	}

	function parseProduct( el ) {
		var data = { id: 0, variation: 0, name: '' };
		try {
			var parsed = JSON.parse( el.getAttribute( 'data-wsi-product' ) || '{}' ) || {};
			data.id        = parseInt( parsed.id, 10 ) || 0;
			data.variation = parseInt( parsed.variation, 10 ) || 0;
			data.name      = typeof parsed.name === 'string' ? parsed.name : '';
		} catch ( e ) { /* keep defaults */ }
		return data;
	}

	function open( trigger ) {
		if ( ! modal && ! init() ) { return; }
		lastTrigger = trigger;
		current = {
			product: parseProduct( trigger ),
			origin: trigger.getAttribute( 'data-wsi-origin' ) === 'shortcode' ? 'shortcode' : 'popup',
			send: trigger.getAttribute( 'data-wsi-send' ) || '',
			title: trigger.getAttribute( 'data-wsi-title' ) || ''
		};

		form.reset();
		form.hidden = false;
		successEl.hidden = true;
		waLink.hidden = true;
		clearErrors();
		statusEl.textContent = '';
		titleEl.textContent = current.title || modal.getAttribute( 'data-default-title' ) || '';

		if ( current.product.name ) {
			productEl.textContent = ( i18n.productFor || 'Product:' ) + ' ' + current.product.name;
			productEl.hidden = false;
		} else {
			productEl.hidden = true;
		}

		modal.hidden = false;
		document.documentElement.classList.add( 'wsi-modal-open' );
		var first = $( 'input[name="name"]' );
		if ( first ) { first.focus(); } else { dialog.focus(); }
	}

	function close() {
		if ( ! modal || modal.hidden ) { return; }
		modal.hidden = true;
		document.documentElement.classList.remove( 'wsi-modal-open' );
		if ( lastTrigger && lastTrigger.focus ) { lastTrigger.focus(); }
	}

	function onKey( e ) {
		if ( e.key === 'Escape' ) { e.preventDefault(); close(); return; }
		if ( e.key !== 'Tab' ) { return; }
		var focusable = dialog.querySelectorAll( 'a[href]:not([hidden]), button:not([disabled]):not([hidden]), input:not([tabindex="-1"]):not([disabled]), textarea:not([disabled])' );
		var visible = [];
		for ( var i = 0; i < focusable.length; i++ ) {
			if ( focusable[ i ].offsetParent !== null ) { visible.push( focusable[ i ] ); }
		}
		if ( ! visible.length ) { return; }
		var first = visible[ 0 ];
		var last  = visible[ visible.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
		else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
	}

	function clearErrors() {
		var errs = modal.querySelectorAll( '.wsi-field__error' );
		for ( var i = 0; i < errs.length; i++ ) { errs[ i ].textContent = ''; }
		var inputs = modal.querySelectorAll( '.wsi-field input, .wsi-field textarea' );
		for ( var j = 0; j < inputs.length; j++ ) { inputs[ j ].removeAttribute( 'aria-invalid' ); }
	}

	function showErrors( fields ) {
		var firstInvalid = null;
		Object.keys( fields || {} ).forEach( function ( name ) {
			var out = modal.querySelector( '[data-error-for="' + name + '"]' );
			var inp = form.elements[ name ];
			if ( out ) { out.textContent = fields[ name ]; }
			if ( inp ) {
				inp.setAttribute( 'aria-invalid', 'true' );
				if ( ! firstInvalid ) { firstInvalid = inp; }
			}
		} );
		if ( firstInvalid ) { firstInvalid.focus(); }
	}

	/** Quick checks so obvious mistakes do not need a round trip. The server validates again. */
	function clientErrors() {
		var errors = {};
		var v = function ( n ) { return form.elements[ n ] ? String( form.elements[ n ].value ).trim() : ''; };
		var required = i18n.required || 'This field is required.';
		if ( ! v( 'name' ) ) { errors.name = required; }
		if ( ! v( 'email' ) ) { errors.email = required; }
		else if ( v( 'email' ).indexOf( '@' ) < 1 ) { errors.email = i18n.email || required; }
		if ( form.elements.phone && form.elements.phone.required && ! v( 'phone' ) ) { errors.phone = required; }
		if ( ! v( 'message' ) ) { errors.message = required; }
		return errors;
	}

	function post( data ) {
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: data
		} ).then( function ( res ) {
			return res.json().catch( function () { return { success: false, data: {} }; } );
		} );
	}

	function buildBody() {
		var params = new URLSearchParams();
		params.append( 'action', cfg.action );
		params.append( 'nonce', cfg.nonce );
		params.append( 'product_id', current.product.id );
		params.append( 'variation_id', current.product.variation );
		params.append( 'origin', current.origin );
		params.append( 'send', current.send );
		[ 'name', 'email', 'phone', 'message', 'wsi_website' ].forEach( function ( n ) {
			if ( form.elements[ n ] ) { params.append( n, form.elements[ n ].value ); }
		} );
		return params.toString();
	}

	function finish( res ) {
		busy = false;
		submitBtn.disabled = false;
		if ( res && res.success ) {
			form.hidden = true;
			successMsg.textContent = ( res.data && res.data.message ) || '';
			if ( res.data && res.data.whatsapp_url && /^https:\/\/wa\.me\//.test( res.data.whatsapp_url ) ) {
				waLink.href = res.data.whatsapp_url;
				waLink.hidden = false;
			}
			successEl.hidden = false;
			var target = waLink.hidden ? $( '.wsi-success__close' ) : waLink;
			if ( target ) { target.focus(); }
			return;
		}
		var data = ( res && res.data ) || {};
		statusEl.textContent = data.message || ( i18n.error || 'Something went wrong.' );
		showErrors( data.fields );
	}

	function onSubmit( e ) {
		e.preventDefault();
		if ( busy || ! current ) { return; }
		clearErrors();
		statusEl.textContent = '';

		var local = clientErrors();
		if ( Object.keys( local ).length ) { showErrors( local ); return; }

		busy = true;
		submitBtn.disabled = true;
		statusEl.textContent = i18n.sending || '…';

		post( buildBody() ).then( function ( res ) {
			var code = res && res.data && res.data.code;
			if ( ! res.success && code === 'nonce' ) {
				// A cached page can carry an expired nonce: get a new one and retry once.
				return post( 'action=wsi_form_nonce' ).then( function ( n ) {
					if ( n && n.success && n.data && n.data.nonce ) {
						cfg.nonce = n.data.nonce;
						return post( buildBody() );
					}
					return res;
				} );
			}
			return res;
		} ).then( finish ).catch( function () {
			finish( { success: false, data: { message: i18n.error } } );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest ? e.target.closest( '[data-wsi-form]' ) : null;
		if ( ! trigger ) { return; }
		e.preventDefault();
		open( trigger );
	} );
}() );
