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

	$( function () {
		initPopup();
		initChoice();
	} );
}( jQuery ) );
