/**
 * EventLogging client-side debug mode: Inspect events enqueued for submission via
 * `mw.eventLog.logEvent()` or `mw.eventLog.submit()` in the console.
 *
 * To enable, run the following from the browser console:
 *
 *     mw.loader.using( 'mediawiki.api' ).then( function () {
 *         new mw.Api().saveOption( 'eventlogging-display-console', '1' );
 *     } );
 *
 * To disable:
 *
 *     mw.loader.using( 'mediawiki.api' ).then( function () {
 *         new mw.Api().saveOption( 'eventlogging-display-console', '0' );
 *     } );
 *
 * See `EventLoggingHooks.php` for the module loading and `eventlogging-display-console` user option
 * registration.
 *
 * @private
 * @class mw.eventLog.Debug
 * @singleton
 */
'use strict';

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

const handleEventSubmitDebug = function ( topic, params ) {
	/* eslint-disable no-console */
	if ( window.console && console.info ) {
		console.info( params.eventData );
	}
};

mw.trackSubscribe( 'eventlogging.eventSubmitDebug', handleEventSubmitDebug );
