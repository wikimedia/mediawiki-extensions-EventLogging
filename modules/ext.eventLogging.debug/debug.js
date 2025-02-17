/**
 * EventLogging client-side debug mode: Inspect events and validation errors on
 * calls to mw.eventLog.logEvent.  New Event Client code does the same
 * but for calls to mw.eventLog.submit.
 *
 * To enable, run the following from the browser console:
 *
 *     mw.loader.using( 'mediawiki.api' ).then( function () {
 *         new mw.Api().saveOption( 'eventlogging-display-web', '1' );
 *     } );
 *
 * To disable:
 *
 *     mw.loader.using( 'mediawiki.api' ).then( function () {
 *         new mw.Api().saveOption( 'eventlogging-display-web', '0' );
 *     } );
 *
 * This will log events to the browser console, and also show them in a popup
 * (via mw.notify). Use 'eventlogging-display-console' instead of
 * 'eventlogging-display-web' to only log to the console.
 *
 * See EventLoggingHooks.php for the module loading, and user option registation.
 *
 * @private
 * @class mw.eventLog.Debug
 * @singleton
 */
'use strict';

const schemaApiQueryUrl = require( './data.json' ).EventLoggingSchemaApiUri;
const schemaApiQueryParams = {
	action: 'query',
	prop: 'revisions',
	rvprop: 'content',
	rvslots: 'main',
	rawcontinue: '1',
	format: 'json',
	origin: '*',
	indexpageids: ''
};
const baseUrl = ( schemaApiQueryUrl || '' ).replace( 'api.php', 'index.php' );

/**
 * Whether to show a popup notice as part of the debug output, or just write to console.
 *
 * @return {boolean}
 */
function shouldShowNotice() {
	// This file gets evaluated before mw.user.options is set up, so we can't just put this
	// value into a variable in the file scope.
	return Number( mw.user.options.get( 'eventlogging-display-web' ) ) === 1;
}

/**
 * Checks whether a JavaScript value conforms to a specified
 * JSON Schema type.
 *
 * @private
 * @param {Object} value Object to test.
 * @param {string} type JSON Schema type.
 * @return {boolean} Whether value is instance of type.
 */
function isInstanceOf( value, type ) {
	// eslint-disable-next-line no-jquery/no-type
	const jsType = $.type( value );
	switch ( type ) {
		case 'integer':
			return jsType === 'number' && value % 1 === 0;
		case 'number':
			return jsType === 'number' && isFinite( value );
		case 'timestamp':
			return jsType === 'date' || ( jsType === 'number' && value >= 0 && value % 1 === 0 );
		default:
			return jsType === type;
	}
}

/**
 * Check whether a JavaScript object conforms to a JSON Schema.
 *
 * @private
 * @param {Object} obj Object to validate.
 * @param {Object} schema JSON Schema object.
 * @return {Array} An array of validation errors (empty if valid).
 */
function validate( obj, schema ) {
	const errors = [];

	if ( !schema || !schema.properties ) {
		errors.push( 'Missing or empty schema' );
		return errors;
	}

	for ( const key in obj ) {
		if ( !Object.hasOwnProperty.call( schema.properties, key ) ) {
			errors.push( mw.format( 'Undeclared property "$1"', key ) );
		}
	}

	for ( const key in schema.properties ) {
		const prop = schema.properties[ key ];

		if ( !Object.hasOwnProperty.call( obj, key ) ) {
			if ( prop.required ) {
				errors.push( mw.format( 'Missing property "$1"', key ) );
			}
			continue;
		}
		const val = obj[ key ];

		if ( !( isInstanceOf( val, prop.type ) ) ) {
			errors.push( mw.format(
				'Value $1 is the wrong type for property "$2" ($3 expected)',
				JSON.stringify( val ), key, prop.type
			) );
			continue;
		}

		if ( prop.enum && prop.enum.indexOf( val ) === -1 ) {
			errors.push( mw.format(
				'Value $1 for property "$2" is not one of $3',
				JSON.stringify( val ), key, JSON.stringify( prop.enum )
			) );
		}
	}

	return errors;
}

/**
 * @private
 * @return {jQuery.Promise} Yields a function to open an OOUI Window
 */
function makeDialogPromise() {
	return mw.loader.using( 'oojs-ui-windows' ).then( () => {
		const manager = new OO.ui.WindowManager(),
			dialog = new OO.ui.MessageDialog();
		$( document.body ).append( manager.$element );
		manager.addWindows( [ dialog ] );

		return function openDialog( args ) {
			manager.openWindow( dialog, Object.assign( {
				verbose: true,
				size: 'large',
				actions: [
					{
						action: 'accept',
						label: mw.msg( 'ooui-dialog-message-accept' ),
						flags: 'primary'
					}
				]
			}, args ) );
		};
	} );
}

let dialogPromise;

/**
 * @private
 * @param {Object} event As formatted by mw.eventLog.prepare()
 * @param {Object} errors found during validation
 */
function displayLoggedEvent( event, errors ) {
	const hasErrors = errors && errors.length,
		eventWithAnyErrors = mw.format(
			'$1$2',
			JSON.stringify( event, null, 2 ),
			hasErrors ? mw.format( '\n\nErrors\n======\n$1', errors.join( '\n' ) ) : ''
		),
		formatted = mw.format(
			mw.html.escape( 'Log event ($1)$2: $3' ),
			mw.html.element( 'a',
				{ href: baseUrl + '?oldid=' + event.revision },
				'Schema: ' + event.schema
			),
			hasErrors ? mw.format( ' ($1 errors)', errors.length ) : '',
			mw.html.element( 'tt', {},
				JSON.stringify( event.event, null, 1 ).slice( 0, 100 ) + '...'
			)
		),
		$content = $( '<p>' ).html( formatted );

	$content.on( 'click', () => {
		dialogPromise = dialogPromise || makeDialogPromise();
		dialogPromise.then( ( openDialog ) => {
			openDialog( {
				title: 'Schema: ' + event.schema,
				message: $( '<pre>' ).text( eventWithAnyErrors )
			} );
		} );
	} );

	/* eslint-disable no-console */
	if ( window.console && console.info ) {
		console.info( event.schema, event );
	}
	/* eslint-enable no-console */
	if ( shouldShowNotice() ) {
		mw.notification.notify( $content, { autoHide: true, autoHideSeconds: 'long' } );
	}
}

function validateAndDisplay( event, schema ) {
	const errors = validate( event.event, schema );

	errors.forEach( ( error ) => {
		mw.track( 'eventlogging.error', mw.format( '[$1] $2', event.schema, error ) );
	} );

	mw.loader.using( [ 'mediawiki.notification', 'oojs-ui-windows' ] ).then( () => {
		displayLoggedEvent( event, errors );
	} );
}

const handleEventLoggingDebug = !schemaApiQueryUrl ?
	function () {} :
	function ( topic, event ) {
		$.ajax( {
			url: schemaApiQueryUrl,
			data: Object.assign(
				{},
				schemaApiQueryParams,
				{ titles: mw.format( 'Schema:$1', event.schema ) }
			),
			dataType: 'json'
		} ).then(
			( data ) => {
				let page;
				try {
					page = data.query.pages[ data.query.pageids[ 0 ] ];
					validateAndDisplay(
						event,
						JSON.parse( page.revisions[ 0 ].slots.main[ '*' ] )
					);
				} catch ( e ) {
					mw.track( 'eventlogging.error', mw.format( 'Could not parse schema $1: $2', event.schema, e ) );
				}
			},
			() => {
				mw.track( 'eventlogging.error', mw.format( 'Could not load schema: $1', event.schema ) );
			}
		);
	};

mw.trackSubscribe( 'eventlogging.debug', handleEventLoggingDebug );

// Output validation errors to the browser console, if available.
mw.trackSubscribe( 'eventlogging.error', ( topic, error ) => {
	mw.log.error( mw.format( '$1: $2', 'EventLogging Validation', error ) );
} );

// ////////////////////////////////////////////////////////////////////
// MEP Upgrade Zone
//
// As we upgrade EventLogging to use MEP components, we will refactor
// code from above to here. https://phabricator.wikimedia.org/T238544
// ////////////////////////////////////////////////////////////////////

/**
 * @private
 * @param {string} streamName name of the stream to submit eventData to
 * @param {Object} eventData submitted
 */
function displaySubmittedEvent( streamName, eventData ) {
	const
		formatted = mw.format(
			mw.html.escape( 'Submitted event to stream $1 $2' ),
			streamName,
			mw.html.element( 'tt', {},
				JSON.stringify( eventData, null, 1 ).slice( 0, 100 ) + '...'
			)
		),
		$content = $( '<p>' ).html( formatted );

	/* eslint-disable no-console */
	if ( window.console && console.info ) {
		console.info( eventData );
	}
	/* eslint-enable no-console */
	if ( shouldShowNotice() ) {
		mw.notification.notify( $content, { autoHide: true, autoHideSeconds: 'long' } );
	}
}

const handleEventSubmitDebug = function ( topic, params ) {
	mw.loader.using( [ 'mediawiki.notification', 'oojs-ui-windows' ] ).then( () => {
		displaySubmittedEvent( params.streamName, params.eventData );
	} );
};

mw.trackSubscribe( 'eventlogging.eventSubmitDebug', handleEventSubmitDebug );

if ( typeof QUnit !== 'undefined' ) {
	/**
	 * For testing only. Subject to change any time.
	 *
	 * @private
	 */
	module.exports = {
		validate: validate,
		isInstanceOf: isInstanceOf
	};
}
