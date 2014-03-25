/**
 * This module registers an EventLogging handler for 'event'-namespaced
 * events logged via mw#track. The handler ensures that the appropriate
 * schema module and core EventLogging code are loaded, and then it logs
 * the event.
 *
 * Because subscribers to mw#track receive the full backlog of events
 * matching the subscription, event processing can be safely deferred
 * until the window's load event has fired. This keeps the impact of
 * analytic instrumentation on page load times to a minimum.
 *
 * @module ext.eventLogging.subscriber
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function ( mw, $ ) {
	$( window ).on( 'load', function () {
		mw.trackSubscribe( 'event', function ( topic, eventInstance ) {
			var prefixLength = topic.indexOf( '.' ),
				schemaName = topic.charAt( prefixLength + 1 ).toUpperCase() + topic.slice( prefixLength + 2 );
			mw.loader.using( [ 'ext.eventLogging', 'schema.' + schemaName ], function () {
				mw.eventLog.logEvent( schemaName, eventInstance );
			} );
		} );
	} );
} ( mediaWiki, jQuery ) );
