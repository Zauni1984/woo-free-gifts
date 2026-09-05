/* global wfgData, jQuery */
( function ( $ ) {
	'use strict';

	/* -----------------------------------------------------------------
	 * Storage helpers (never throw – private mode, blocked storage, etc.)
	 * -------------------------------------------------------------- */
	function storageGet( store, key ) {
		try {
			return window[ store ].getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	function storageSet( store, key, value ) {
		try {
			window[ store ].setItem( key, value );
		} catch ( e ) {
			/* ignore */
		}
	}

	/* -----------------------------------------------------------------
	 * Popup
	 * -------------------------------------------------------------- */
	function initPopup() {
		var el = document.getElementById( 'wfg-popup' );
		if ( ! el ) {
			return;
		}

		var config;
		try {
			config = JSON.parse( el.getAttribute( 'data-wfg-popup' ) || '{}' );
		} catch ( e ) {
			config = {};
		}

		var key = 'wfg_popup_' + ( config.version || 'v1' );
		var frequency = config.frequency || 'session';
		var now = Date.now();

		function alreadySeen() {
			if ( frequency === 'always' ) {
				return false;
			}
			if ( frequency === 'session' ) {
				return storageGet( 'sessionStorage', key ) === '1';
			}
			var stored = storageGet( 'localStorage', key );
			if ( ! stored ) {
				return false;
			}
			if ( frequency === 'once' ) {
				return true;
			}
			var until = parseInt( stored, 10 );
			return ! isNaN( until ) && until > now;
		}

		function markSeen() {
			if ( frequency === 'session' ) {
				storageSet( 'sessionStorage', key, '1' );
			} else if ( frequency === 'days' ) {
				var days = parseInt( config.days, 10 ) || 7;
				storageSet( 'localStorage', key, String( now + days * 86400000 ) );
			} else if ( frequency === 'once' ) {
				storageSet( 'localStorage', key, '1' );
			}
		}

		if ( alreadySeen() ) {
			el.parentNode.removeChild( el );
			return;
		}

		var dialog = el.querySelector( '.wfg-popup__dialog' );
		var lastFocus = null;

		function close() {
			if ( el.hidden ) {
				return;
			}
			el.hidden = true;
			document.documentElement.classList.remove( 'wfg-popup-open' );
			document.removeEventListener( 'keydown', onKey );
			if ( lastFocus && lastFocus.focus ) {
				lastFocus.focus();
			}
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				close();
				return;
			}
			if ( e.key !== 'Tab' ) {
				return;
			}
			// Minimal focus trap.
			var focusable = dialog.querySelectorAll( 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])' );
			if ( ! focusable.length ) {
				return;
			}
			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}

		function open() {
			lastFocus = document.activeElement;
			el.hidden = false;
			document.documentElement.classList.add( 'wfg-popup-open' );
			document.addEventListener( 'keydown', onKey );
			markSeen();
			window.setTimeout( function () {
				var closeBtn = dialog.querySelector( '.wfg-popup__close' );
				( closeBtn || dialog ).focus();
			}, 50 );
			$( document.body ).trigger( 'wfg_popup_opened' );
		}

		Array.prototype.forEach.call( el.querySelectorAll( '[data-wfg-close]' ), function ( btn ) {
			btn.addEventListener( 'click', close );
		} );

		var delay = Math.max( 0, parseInt( config.delay, 10 ) || 0 ) * 1000;
		window.setTimeout( open, delay );
	}

	/* -----------------------------------------------------------------
	 * Gift choice (progressive enhancement over the nonce link)
	 * -------------------------------------------------------------- */
	function initChoice() {
		$( document.body ).on( 'click', '.wfg-choice__button', function ( e ) {
			if ( typeof wfgData === 'undefined' || ! wfgData.ajaxUrl ) {
				return; // Let the link do its work.
			}
			e.preventDefault();

			var $btn = $( this );
			var $box = $btn.closest( '.wfg-choice' );
			if ( $box.hasClass( 'is-loading' ) ) {
				return;
			}
			$box.addClass( 'is-loading' );

			$.ajax( {
				url: wfgData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wfg_choose_gift',
					nonce: wfgData.nonce,
					rule_id: $btn.data( 'rule' ),
					product_id: $btn.data( 'product' )
				}
			} )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						window.alert( ( response && response.data && response.data.message ) || wfgData.i18n.error );
						$box.removeClass( 'is-loading' );
						return;
					}
					refreshCart();
				} )
				.fail( function () {
					$box.removeClass( 'is-loading' );
					window.alert( wfgData.i18n.error );
				} );
		} );
	}

	function refreshCart() {
		var $form = $( '.woocommerce-cart-form' );
		if ( $form.length ) {
			// Classic cart page: let WooCommerce re-render the cart via AJAX.
			$( document.body ).trigger( 'wc_update_cart' );
			$( document.body ).trigger( 'wc_fragment_refresh' );
			return;
		}
		// Checkout or any other page: a reload is the most reliable refresh.
		window.location.reload();
	}

	/* -----------------------------------------------------------------
	 * Wheel of fortune
	 * -------------------------------------------------------------- */
	function initWheel() {
		var el = document.getElementById( 'wfg-wheel' );
		if ( ! el || typeof wfgData === 'undefined' ) {
			return;
		}

		var config;
		try {
			config = JSON.parse( el.getAttribute( 'data-wfg-wheel' ) || '{}' );
		} catch ( e ) {
			config = {};
		}

		var nextKey = 'wfg_wheel_next';
		var dismissKey = 'wfg_wheel_dismissed_' + ( config.version || 'v1' );
		var now = Date.now();
		var next = Math.max( ( parseInt( config.nextSpin, 10 ) || 0 ) * 1000, parseInt( storageGet( 'localStorage', nextKey ), 10 ) || 0 );

		if ( next > now || storageGet( 'sessionStorage', dismissKey ) === '1' ) {
			el.parentNode.removeChild( el );
			return;
		}

		var dialog = el.querySelector( '.wfg-wheel__dialog' );
		var disc = el.querySelector( '.wfg-wheel__disc' );
		var form = el.querySelector( '.wfg-wheel__form' );
		var spinBtn = el.querySelector( '.wfg-wheel__spin' );
		var error = el.querySelector( '.wfg-wheel__error' );
		var result = el.querySelector( '.wfg-wheel__result' );
		var segments = Math.max( 1, parseInt( config.segments, 10 ) || 1 );
		var segmentAngle = 360 / segments;
		var rotation = 0;
		var spinning = false;
		var done = false;
		var lastFocus = null;

		function showError( msg ) {
			error.textContent = msg || wfgData.i18n.error;
			error.hidden = false;
		}

		function close() {
			if ( el.hidden ) {
				return;
			}
			if ( spinning ) {
				return; // Never interrupt a running spin.
			}
			el.hidden = true;
			document.removeEventListener( 'keydown', onKey );
			if ( ! done ) {
				storageSet( 'sessionStorage', dismissKey, '1' );
			}
			if ( lastFocus && lastFocus.focus ) {
				lastFocus.focus();
			}
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				close();
			}
		}

		function open() {
			lastFocus = document.activeElement;
			el.hidden = false;
			document.addEventListener( 'keydown', onKey );
			window.setTimeout( function () {
				var first = el.querySelector( 'input[type="email"]' ) || spinBtn || dialog;
				first.focus();
			}, 60 );
			$( document.body ).trigger( 'wfg_wheel_opened' );
		}

		function showResult( data ) {
			done = true;
			el.classList.remove( 'is-spinning' );
			el.classList.add( 'is-done' );
			result.querySelector( '.wfg-wheel__result-label' ).textContent = data.label || '';
			result.querySelector( '.wfg-wheel__result-message' ).textContent = data.message || '';
			var codeWrap = result.querySelector( '.wfg-wheel__result-code' );
			if ( data.code ) {
				codeWrap.querySelector( 'code' ).textContent = data.code;
				codeWrap.hidden = false;
			} else {
				codeWrap.hidden = true;
			}
			result.hidden = false;
			$( document.body ).trigger( 'wc_fragment_refresh' );
			if ( $( '.woocommerce-cart-form' ).length ) {
				$( document.body ).trigger( 'wc_update_cart' );
			}
			$( document.body ).trigger( 'wfg_wheel_result', [ data ] );
		}

		function spinTo( data ) {
			spinning = true;
			el.classList.add( 'is-spinning' );
			var index = parseInt( data.index, 10 ) || 0;
			var center = index * segmentAngle + segmentAngle / 2;
			var jitter = ( Math.random() - 0.5 ) * segmentAngle * 0.6;
			var turns = 6 + Math.floor( Math.random() * 3 );
			var target = turns * 360 + ( 360 - center ) + jitter;
			rotation += target - ( rotation % 360 );
			var finished = false;
			function finish() {
				if ( finished ) {
					return;
				}
				finished = true;
				spinning = false;
				showResult( data );
			}
			disc.addEventListener( 'transitionend', finish, { once: true } );
			window.setTimeout( finish, 6200 );
			// Force a reflow so the transition always runs.
			void disc.offsetWidth;
			disc.style.transform = 'rotate(' + rotation + 'deg)';
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( spinning || done ) {
				return;
			}
			error.hidden = true;

			var emailField = form.querySelector( 'input[type="email"]' );
			var consentField = form.querySelector( 'input[name="consent"]' );
			if ( emailField && ! emailField.checkValidity() ) {
				emailField.reportValidity();
				return;
			}
			if ( consentField && ! consentField.checked ) {
				consentField.reportValidity();
				return;
			}

			spinBtn.disabled = true;
			$.ajax( {
				url: wfgData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wfg_wheel_spin',
					nonce: wfgData.nonce,
					email: emailField ? emailField.value : '',
					consent: consentField && consentField.checked ? 1 : 0
				}
			} )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						var payload = response && response.data ? response.data : {};
						if ( payload.nextSpin ) {
							storageSet( 'localStorage', nextKey, String( payload.nextSpin * 1000 ) );
						}
						showError( payload.message );
						spinBtn.disabled = false;
						return;
					}
					if ( response.data.nextSpin ) {
						storageSet( 'localStorage', nextKey, String( response.data.nextSpin * 1000 ) );
					}
					spinTo( response.data );
				} )
				.fail( function ( xhr ) {
					var payload = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
					if ( payload.nextSpin ) {
						storageSet( 'localStorage', nextKey, String( payload.nextSpin * 1000 ) );
					}
					showError( payload.message );
					spinBtn.disabled = false;
				} );
		} );

		var copyBtn = el.querySelector( '.wfg-wheel__copy' );
		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				var code = el.querySelector( '.wfg-wheel__result-code code' ).textContent;
				var label = copyBtn.textContent;
				function ok() {
					copyBtn.textContent = copyBtn.getAttribute( 'data-copied' ) || label;
					window.setTimeout( function () { copyBtn.textContent = label; }, 1800 );
				}
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( code ).then( ok, function () {} );
				} else {
					var ta = document.createElement( 'textarea' );
					ta.value = code;
					document.body.appendChild( ta );
					ta.select();
					try { document.execCommand( 'copy' ); ok(); } catch ( err ) { /* ignore */ }
					document.body.removeChild( ta );
				}
			} );
		}

		Array.prototype.forEach.call( el.querySelectorAll( '[data-wfg-wheel-close]' ), function ( btn ) {
			btn.addEventListener( 'click', close );
		} );

		var delay = Math.max( 0, parseInt( config.delay, 10 ) || 0 ) * 1000;
		window.setTimeout( open, delay );
	}

	$( function () {
		initPopup();
		initChoice();
		initWheel();
	} );
}( jQuery ) );
