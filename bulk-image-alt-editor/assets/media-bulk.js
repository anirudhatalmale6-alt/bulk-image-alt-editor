/**
 * Bulk Image ALT Editor - inline ALT field for the Media Library bulk action.
 *
 * Drops a text input next to each bulk-action dropdown on upload.php and shows
 * it only while "Set ALT text" is the chosen action. Plain DOM, no libraries.
 *
 * @package Bulk_Image_Alt_Editor
 */

( function () {
	'use strict';

	var L10N = window.wpbiaeMediaL10n || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'posts-filter' );

		if ( ! form ) {
			return;
		}

		var selects = form.querySelectorAll( 'select[name="action"], select[name="action2"]' );

		if ( ! selects.length ) {
			return;
		}

		var fields = [];

		Array.prototype.forEach.call( selects, function ( select ) {
			// Only build the field where the action actually exists.
			if ( ! select.querySelector( 'option[value="' + L10N.action + '"]' ) ) {
				return;
			}

			var wrap = document.createElement( 'span' );
			wrap.className = 'wpbiae-bulk-alt-wrap';

			var label = document.createElement( 'label' );
			label.className = 'screen-reader-text';
			label.textContent = L10N.label || 'New ALT text:';

			var input = document.createElement( 'input' );
			input.type = 'text';
			input.name = 'wpbiae_bulk_alt';
			input.className = 'regular-text';
			input.autocomplete = 'off';
			input.placeholder = L10N.placeholder || '';
			input.size = 34;

			label.setAttribute( 'for', 'wpbiae-bulk-alt-' + fields.length );
			input.id = 'wpbiae-bulk-alt-' + fields.length;

			wrap.appendChild( label );
			wrap.appendChild( input );

			// Sit between the dropdown and its Apply button.
			select.parentNode.insertBefore( wrap, select.nextSibling );

			fields.push( { select: select, wrap: wrap, input: input } );
		} );

		if ( ! fields.length ) {
			return;
		}

		function activeField() {
			for ( var i = 0; i < fields.length; i++ ) {
				if ( L10N.action === fields[ i ].select.value ) {
					return fields[ i ];
				}
			}

			return null;
		}

		function sync( value ) {
			fields.forEach( function ( field ) {
				if ( field.input.value !== value ) {
					field.input.value = value;
				}
			} );
		}

		function refresh() {
			fields.forEach( function ( field ) {
				var on = L10N.action === field.select.value;

				field.wrap.classList.toggle( 'is-visible', on );
				// Keep an inactive field out of the submitted query string.
				field.input.disabled = ! on;
			} );
		}

		fields.forEach( function ( field ) {
			field.select.addEventListener( 'change', refresh );

			field.input.addEventListener( 'input', function () {
				sync( field.input.value );
			} );
		} );

		form.addEventListener( 'submit', function ( event ) {
			var field = activeField();

			if ( ! field ) {
				return;
			}

			if ( ! form.querySelector( 'input[name="media[]"]:checked' ) ) {
				event.preventDefault();
				window.alert( L10N.noSelection || 'Tick at least one image first.' );

				return;
			}

			if ( '' === field.input.value.trim() ) {
				if ( ! window.confirm( L10N.confirmEmpty || 'Clear the ALT text on the selected images?' ) ) {
					event.preventDefault();
				}
			}
		} );

		refresh();
	} );
} )();
