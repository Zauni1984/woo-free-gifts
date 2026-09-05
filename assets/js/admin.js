/* global wfgAdmin, jQuery, wp */
( function ( $ ) {
	'use strict';

	var i18n = ( window.wfgAdmin && wfgAdmin.i18n ) || {};

	/* -----------------------------------------------------------------
	 * Gift repeater
	 * -------------------------------------------------------------- */
	function nextIndex( $container ) {
		var max = -1;
		$container.find( '.wfg-gift-row' ).each( function () {
			var idx = parseInt( $( this ).attr( 'data-index' ), 10 );
			if ( ! isNaN( idx ) && idx > max ) {
				max = idx;
			}
		} );
		return max + 1;
	}

	function toggleRowType( $row ) {
		var type = $row.find( '.wfg-gift-type' ).val();
		$row.find( '.wfg-gift-row__product' ).prop( 'hidden', type !== 'product' );
		$row.find( '.wfg-gift-row__custom' ).prop( 'hidden', type !== 'custom' );
		$row.find( '.wfg-custom-name' ).prop( 'required', type === 'custom' );
	}

	function initRepeater() {
		var $container = $( '.wfg-gift-rows' );
		if ( ! $container.length ) {
			return;
		}

		$container.find( '.wfg-gift-row' ).each( function () {
			toggleRowType( $( this ) );
		} );

		$container.on( 'change', '.wfg-gift-type', function () {
			toggleRowType( $( this ).closest( '.wfg-gift-row' ) );
		} );

		$container.on( 'click', '.wfg-gift-remove', function ( e ) {
			e.preventDefault();
			var $row = $( this ).closest( '.wfg-gift-row' );
			if ( $container.find( '.wfg-gift-row' ).length === 1 ) {
				// Keep one row, just reset it.
				$row.find( 'input[type="text"], textarea' ).val( '' );
				$row.find( '.wfg-image-id' ).val( '0' );
				$row.find( '.wfg-image-preview' ).html( '<span class="dashicons dashicons-format-image"></span>' );
				$row.find( '.wfg-product-select' ).val( null ).trigger( 'change' );
				return;
			}
			$row.remove();
		} );

		$( '.wfg-gift-add' ).on( 'click', function ( e ) {
			e.preventDefault();
			var template = $( '#wfg-gift-row-template' ).html();
			if ( ! template ) {
				return;
			}
			var html = template.replace( /__INDEX__/g, String( nextIndex( $container ) ) );
			var $row = $( html );
			$container.append( $row );
			toggleRowType( $row );
			$( document.body ).trigger( 'wc-enhanced-select-init' );
		} );
	}

	/* -----------------------------------------------------------------
	 * Media picker (custom gift image + popup image)
	 * -------------------------------------------------------------- */
	function initMedia() {
		$( document ).on( 'click', '.wfg-image-pick', function ( e ) {
			e.preventDefault();
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			var $wrap = $( this ).closest( '.wfg-custom-image' );
			var frame = wp.media( {
				title: i18n.chooseImage || 'Choose image',
				button: { text: i18n.useImage || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = ( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) || attachment.url;
				$wrap.find( '.wfg-image-id' ).val( attachment.id );
				$wrap.find( '.wfg-image-preview' ).html( $( '<img>', { src: url, alt: '' } ) );
				$wrap.find( '.wfg-image-clear' ).prop( 'hidden', false );
			} );
			frame.open();
		} );

		$( document ).on( 'click', '.wfg-image-clear', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.wfg-custom-image' );
			$wrap.find( '.wfg-image-id' ).val( '0' );
			$wrap.find( '.wfg-image-preview' ).html( '<span class="dashicons dashicons-format-image"></span>' );
			$( this ).prop( 'hidden', true );
		} );
	}

	/* -----------------------------------------------------------------
	 * Misc
	 * -------------------------------------------------------------- */
	function initMisc() {
		$( document ).on( 'click', '.wfg-delete', function ( e ) {
			if ( ! window.confirm( i18n.confirmDelete || 'Delete?' ) ) {
				e.preventDefault();
			}
		} );

		$( document ).on( 'click', '.wfg-confirm', function ( e ) {
			var msg = $( this ).data( 'confirm' );
			if ( msg && ! window.confirm( msg ) ) {
				e.preventDefault();
			}
		} );

		if ( $.fn.wpColorPicker ) {
			$( '.wfg-color-field' ).wpColorPicker();
		}
	}

	/* -----------------------------------------------------------------
	 * Wheel segments repeater
	 * -------------------------------------------------------------- */
	function toggleSegmentType( $row ) {
		var type = $row.find( '.wfg-segment-type' ).val();
		$row.find( '.wfg-segment__coupon' ).prop( 'hidden', type !== 'coupon' );
		$row.find( '.wfg-segment__gift' ).prop( 'hidden', type !== 'gift' );
	}

	function initSegments() {
		var $container = $( '.wfg-segments' );
		if ( ! $container.length ) {
			return;
		}

		function renumber() {
			$container.find( '.wfg-segment' ).each( function ( i ) {
				$( this ).find( '.wfg-segment__index' ).text( i + 1 );
			} );
			updatePreview();
		}

		function updatePreview() {
			var $preview = $( '.wfg-segments-preview' );
			if ( ! $preview.length ) {
				return;
			}
			var colors = [];
			var weights = 0;
			$container.find( '.wfg-segment' ).each( function () {
				colors.push( $( this ).find( '.wfg-segment-color' ).val() || '#ccc' );
				weights += parseInt( $( this ).find( '.wfg-segment-weight' ).val(), 10 ) || 0;
			} );
			var n = colors.length || 1;
			var a = 360 / n;
			var stops = colors.map( function ( c, i ) {
				return c + ' ' + ( i * a ) + 'deg ' + ( ( i + 1 ) * a ) + 'deg';
			} );
			$preview.css( 'background', 'conic-gradient(from 0deg, ' + stops.join( ', ' ) + ')' );
			$container.find( '.wfg-segment' ).each( function () {
				var w = parseInt( $( this ).find( '.wfg-segment-weight' ).val(), 10 ) || 0;
				$( this ).find( '.wfg-segment__chance' ).text( weights ? ( Math.round( ( w / weights ) * 1000 ) / 10 ) + ' %' : '–' );
			} );
		}

		$container.find( '.wfg-segment' ).each( function () {
			toggleSegmentType( $( this ) );
		} );
		renumber();

		$container.on( 'change', '.wfg-segment-type', function () {
			toggleSegmentType( $( this ).closest( '.wfg-segment' ) );
		} );
		$container.on( 'input change', '.wfg-segment-color, .wfg-segment-weight', updatePreview );

		$container.on( 'click', '.wfg-segment-remove', function ( e ) {
			e.preventDefault();
			if ( $container.find( '.wfg-segment' ).length <= 2 ) {
				window.alert( i18n.minSegments || 'At least 2 segments.' );
				return;
			}
			$( this ).closest( '.wfg-segment' ).remove();
			renumber();
		} );

		$( '.wfg-segment-add' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( $container.find( '.wfg-segment' ).length >= 12 ) {
				return;
			}
			var template = $( '#wfg-segment-template' ).html();
			if ( ! template ) {
				return;
			}
			var max = -1;
			$container.find( '.wfg-segment' ).each( function () {
				var idx = parseInt( $( this ).attr( 'data-index' ), 10 );
				if ( ! isNaN( idx ) && idx > max ) {
					max = idx;
				}
			} );
			var $row = $( template.replace( /__INDEX__/g, String( max + 1 ) ) );
			$container.append( $row );
			toggleSegmentType( $row );
			renumber();
			$( document.body ).trigger( 'wc-enhanced-select-init' );
		} );
	}

	$( function () {
		initRepeater();
		initMedia();
		initMisc();
		initSegments();
	} );
}( jQuery ) );
