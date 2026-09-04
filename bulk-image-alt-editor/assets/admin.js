/**
 * Bulk Image ALT Editor - admin screen behaviour.
 *
 * Plain DOM, no libraries. Core's own list-table script ticks the row
 * checkboxes with jQuery .prop(), which fires no change event, so selection is
 * recounted from a delegated click listener instead.
 *
 * @package Bulk_Image_Alt_Editor
 */

( function () {
	'use strict';

	var L10N = window.wpbiaeL10n || {};

	function t( key, fallback ) {
		return typeof L10N[ key ] === 'string' ? L10N[ key ] : fallback;
	}

	function fmt( str, value ) {
		return String( str ).replace( '%s', value );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'wpbiae-form' );

		if ( ! form ) {
			return;
		}

		var altInput   = document.getElementById( 'wpbiae-alt-text' );
		var applyAll   = document.getElementById( 'wpbiae-apply-all' );
		var totalField = document.getElementById( 'wpbiae-total-matching' );
		var banner     = document.getElementById( 'wpbiae-selectall' );
		var bannerText = banner ? banner.querySelector( '.wpbiae-selectall-text' ) : null;
		var bannerLink = banner ? banner.querySelector( '.wpbiae-selectall-link' ) : null;
		var counter    = document.getElementById( 'wpbiae-count' );
		var counterBot = form.querySelector( '.wpbiae-count-bottom' );

		var total = totalField ? parseInt( totalField.value, 10 ) : 0;

		if ( isNaN( total ) ) {
			total = 0;
		}

		function boxes() {
			return form.querySelectorAll( 'input[name="wpbiae_ids[]"]' );
		}

		function checkedBoxes() {
			return form.querySelectorAll( 'input[name="wpbiae_ids[]"]:checked' );
		}

		function isApplyAll() {
			return applyAll && '1' === applyAll.value;
		}

		function setApplyAll( on ) {
			if ( applyAll ) {
				applyAll.value = on ? '1' : '0';
			}
		}

		function selectedCount() {
			return isApplyAll() ? total : checkedBoxes().length;
		}

		function renderCounter() {
			var n    = selectedCount();
			var text = n > 0 ? fmt( t( 'allSelected', '%s selected.' ), n ) : '';

			if ( ! isApplyAll() ) {
				text = n > 0 ? n + ' selected' : '';
			}

			if ( counter ) {
				counter.textContent = text;
			}

			if ( counterBot ) {
				counterBot.textContent = text;
			}
		}

		function renderBanner() {
			if ( ! banner ) {
				return;
			}

			var onPage = boxes().length;
			var picked = checkedBoxes().length;

			if ( isApplyAll() ) {
				banner.hidden           = false;
				bannerText.textContent  = fmt( t( 'allSelected', 'All %s images matching this filter are selected.' ), total );
				bannerLink.textContent  = t( 'clearSelection', 'Clear selection' );
				bannerLink.dataset.mode = 'clear';

				return;
			}

			if ( onPage > 0 && picked === onPage && total > onPage ) {
				banner.hidden           = false;
				bannerText.textContent  = '';
				bannerLink.textContent  = fmt( t( 'selectAll', 'Select all %s images matching this filter' ), total );
				bannerLink.dataset.mode = 'all';

				return;
			}

			banner.hidden = true;
		}

		function refresh() {
			renderCounter();
			renderBanner();
		}

		// Delegated: catches both the row checkboxes and the select-all boxes in
		// the table head and foot, whichever the user clicked.
		form.addEventListener( 'click', function ( event ) {
			var el = event.target;

			if ( el && 'INPUT' === el.tagName && 'checkbox' === el.type ) {
				if ( isApplyAll() ) {
					setApplyAll( false );
				}

				// Let core's handler tick the rest of the column first.
				window.setTimeout( refresh, 0 );
			}
		} );

		if ( bannerLink ) {
			bannerLink.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				if ( 'clear' === bannerLink.dataset.mode ) {
					setApplyAll( false );

					Array.prototype.forEach.call( boxes(), function ( box ) {
						box.checked = false;
					} );

					Array.prototype.forEach.call(
						form.querySelectorAll( 'thead .check-column input[type="checkbox"], tfoot .check-column input[type="checkbox"]' ),
						function ( box ) {
							box.checked = false;
						}
					);
				} else {
					setApplyAll( true );
				}

				refresh();
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			var submitter = event.submitter;

			// Only guard the Apply buttons; the search field submits its own form.
			if ( submitter && 'wpbiae_apply' !== submitter.name ) {
				return;
			}

			if ( ! isApplyAll() && 0 === checkedBoxes().length ) {
				event.preventDefault();
				window.alert( t( 'noSelection', 'Tick at least one image first.' ) );

				return;
			}

			if ( isApplyAll() ) {
				if ( ! window.confirm( fmt( t( 'confirmAll', 'This will replace the ALT text on all %s images matching the current filter. Continue?' ), total ) ) ) {
					event.preventDefault();

					return;
				}
			}

			if ( altInput && '' === altInput.value.trim() ) {
				if ( ! window.confirm( t( 'confirmEmpty', 'The ALT text field is empty. Applying it will clear the ALT text on the selected images. Continue?' ) ) ) {
					event.preventDefault();
				}
			}
		} );

		refresh();
	} );
} )();
