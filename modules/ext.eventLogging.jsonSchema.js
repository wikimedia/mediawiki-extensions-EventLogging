/**
 * JavaScript enhancements of JSON Schema article pages.
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function ( mw, $ ) {
	'use strict';

	$( function () {
		// Make the '<>' icon toggle code samples:
		var $samples = $( '.mw-json-schema-code-samples' );
		$( '.mw-json-schema-code-glyph' ).on( 'click', function () {
			$samples.toggle();
		} );
	} );
} ( mediaWiki, jQuery ) );
