/*!
 * JavaScript enhancements of JSON Schema article pages.
 *
 * @module ext.eventLogging.jsonSchema
 * @author Ori Livneh <ori@wikimedia.org>
 */
/* eslint-disable no-jquery/no-global-selector */
$( () => {
	'use strict';

	// Make the '<>' icon toggle code samples:
	const $samples = $( '.mw-json-schema-code-samples' );

	$( '.mw-json-schema-code-glyph' ).on( 'click', ( e ) => {
		$samples.toggle();
		e.stopPropagation();
	} );

	$( document ).on( 'click', () => {
		$samples.hide();
	} );

	$samples.on( 'click', ( e ) => {
		e.stopPropagation();
	} );

} );
