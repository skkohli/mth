/**
 * Listeo Add-ons dashboard.
 *  - Filter pills (Active / Inactive / Available / Premium).
 *  - Click-to-copy promo codes.
 *  - Install button → AJAX (verify.php signed download + Plugin_Upgrader).
 */
( function () {
	'use strict';

	var cfg = ( typeof window.listeoAddons !== 'undefined' ) ? window.listeoAddons : {};
	var i18n = cfg.i18n || {};

	/* ---------- Filter pills ---------- */

	function applyFilter( filter ) {
		var cards = document.querySelectorAll( '.lba-card' );
		cards.forEach( function ( card ) {
			var state = card.getAttribute( 'data-filter-state' );
			var show  = ( 'all' === filter ) || ( state === filter );
			card.classList.toggle( 'is-hidden', ! show );
		} );

		var pills = document.querySelectorAll( '.lba-pill[data-filter]' );
		pills.forEach( function ( pill ) {
			pill.classList.toggle( 'lba-pill--on', pill.getAttribute( 'data-filter' ) === filter );
		} );
	}

	function handleFilterClick( event ) {
		var pill = event.target.closest( '.lba-pill[data-filter]' );
		if ( ! pill ) { return; }
		event.preventDefault();
		applyFilter( pill.getAttribute( 'data-filter' ) );
	}

	/* ---------- Copy promo code ---------- */

	function copyToClipboard( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			try {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.setAttribute( 'readonly', '' );
				ta.style.position = 'absolute';
				ta.style.left = '-9999px';
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				resolve();
			} catch ( err ) {
				reject( err );
			}
		} );
	}

	function handlePromoCopy( event ) {
		var btn = event.target.closest( '.lba-discount__code' );
		if ( ! btn ) { return; }
		event.preventDefault();
		var code = btn.getAttribute( 'data-copy' );
		if ( ! code ) { return; }

		var label = btn.querySelector( '.lba-discount__copy' );
		var original = label ? label.textContent : ( i18n.copy || 'Copy' );
		copyToClipboard( code ).then( function () {
			if ( label ) {
				label.textContent = i18n.copied || 'Copied!';
				setTimeout( function () { label.textContent = original; }, 1600 );
			}
		} ).catch( function () { /* silent */ } );
	}

	/* ---------- Install flow ---------- */

	function showError( card, message ) {
		var existing = card.querySelector( '.lba-card__error' );
		if ( existing ) { existing.remove(); }
		var box = document.createElement( 'div' );
		box.className = 'lba-card__error';
		box.textContent = message;
		var actions = card.querySelector( '.lba-card__actions' );
		if ( actions ) { actions.appendChild( box ); }
		setTimeout( function () { if ( box.parentNode ) { box.remove(); } }, 8000 );
	}

	function setButtonBusy( btn, busy, busyText ) {
		if ( busy ) {
			btn.dataset.originalLabel = btn.dataset.originalLabel || btn.innerHTML;
			btn.disabled = true;
			btn.classList.add( 'is-busy' );
			btn.innerHTML = '';
			var spinner = document.createElement( 'span' );
			spinner.className = 'lba-spinner';
			spinner.setAttribute( 'aria-hidden', 'true' );
			btn.appendChild( spinner );
			if ( busyText ) {
				btn.appendChild( document.createTextNode( ' ' + busyText ) );
			}
		} else {
			btn.disabled = false;
			btn.classList.remove( 'is-busy' );
			if ( btn.dataset.originalLabel ) {
				btn.innerHTML = btn.dataset.originalLabel;
				delete btn.dataset.originalLabel;
			}
		}
	}

	function postFormUrlEncoded( url, body ) {
		var formBody = Object.keys( body ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( body[ k ] );
		} ).join( '&' );

		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: formBody,
		} ).then( function ( res ) {
			return res.json().then( function ( json ) {
				return { ok: res.ok, status: res.status, json: json };
			} ).catch( function () {
				return { ok: false, status: res.status, json: null };
			} );
		} );
	}

	function transitionCardToActive( card ) {
		card.setAttribute( 'data-filter-state', 'active' );
		var actions = card.querySelector( '.lba-card__actions' );
		if ( actions ) {
			actions.innerHTML =
				'<button type="button" class="lba-btn lba-btn--ghost" disabled aria-disabled="true">' +
				( i18n.active || 'Active' ) +
				'</button>';

			var learnMoreUrl = card.getAttribute( 'data-learn-more-url' );
			if ( learnMoreUrl ) {
				var learnMore = document.createElement( 'a' );
				learnMore.className = 'lba-link';
				learnMore.href = learnMoreUrl;
				learnMore.target = '_blank';
				learnMore.rel = 'noopener';
				learnMore.textContent = i18n.learnMore || 'Learn more';
				actions.appendChild( learnMore );
			}
		}
		var badge = card.querySelector( '.lba-badge' );
		if ( badge ) {
			badge.className = 'lba-badge lba-badge--green';
			badge.textContent = i18n.active || 'Active';
		}
	}

	function formatRetryAfter( seconds ) {
		seconds = parseInt( seconds, 10 );
		if ( ! seconds || seconds < 0 ) { return ''; }
		if ( seconds < 90 ) { return seconds + 's'; }
		return Math.ceil( seconds / 60 ) + ' min';
	}

	function handleInstallClick( event ) {
		var btn = event.target.closest( '.lba-install' );
		if ( ! btn ) { return; }
		event.preventDefault();

		if ( ! cfg.ajaxUrl || ! cfg.installAction ) { return; }

		var card = btn.closest( '.lba-card' );
		if ( ! card ) { return; }

		var slug = btn.getAttribute( 'data-slug' );
		if ( ! slug ) { return; }

		if ( ! cfg.hasLicense ) {
			if ( cfg.licenseUrl ) {
				window.location.href = cfg.licenseUrl;
			} else {
				showError( card, i18n.licensePrompt || 'Activate your Listeo license first.' );
			}
			return;
		}

		setButtonBusy( btn, true, i18n.installing || 'Installing…' );

		postFormUrlEncoded( cfg.ajaxUrl, {
			action: cfg.installAction,
			nonce: cfg.installNonce,
			slug: slug,
			activate: 1,
		} ).then( function ( result ) {
			var data = result.json && result.json.data ? result.json.data : null;

			if ( result.json && result.json.success && data && 'active' === data.state ) {
				transitionCardToActive( card );
				return;
			}

			// Installed but not activated — keep the button so a second click
			// re-runs the same handler; the server short-circuits to activation.
			if ( result.json && result.json.success && data && 'inactive' === data.state ) {
				setButtonBusy( btn, false );
				// Swap label to "Activate"
				btn.innerHTML = '';
				btn.appendChild( document.createTextNode( i18n.activate || 'Activate' ) );
				if ( data.activate_warn ) {
					showError( card, data.activate_warn );
				}
				return;
			}

			setButtonBusy( btn, false );
			var message = ( data && data.message ) ? data.message : ( i18n.genericError || 'Something went wrong.' );
			if ( data && data.retry_after ) {
				message = message + ' (' + formatRetryAfter( data.retry_after ) + ')';
			}
			showError( card, message );

			if ( data && 'no_license' === data.code && data.license_url ) {
				var link = document.createElement( 'a' );
				link.className = 'lba-link';
				link.href = data.license_url;
				link.textContent = i18n.goToLicense || 'Go to License';
				var actions = card.querySelector( '.lba-card__actions' );
				if ( actions ) { actions.appendChild( link ); }
			}
		} ).catch( function () {
			setButtonBusy( btn, false );
			showError( card, i18n.genericError || 'Something went wrong.' );
		} );
	}

	/* ---------- Bindings ---------- */

	document.addEventListener( 'click', function ( event ) {
		handleFilterClick( event );
		handlePromoCopy( event );
		handleInstallClick( event );
	} );

} )();
