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

	/**
	 * Convert the first letter of a string to uppercase.
	 *
	 * @param {String} word
	 * @return {String}
	 */
	function titleCase( word ) {
		return word.charAt( 0 ).toUpperCase() + word.slice( 1 );
	}

	/**
	 * mw#track handler for EventLogging events.
	 *
	 * @param {String} topic Topic name ('event.*').
	 * @param {Object} event
	 */
	function handleTrackedEvent( topic, event ) {
		var schema = titleCase( topic.slice( topic.indexOf( '.' ) + 1 ) ),
			dependencies = [ 'ext.eventLogging', 'schema.' + schema ];

		mediaWiki.loader.using( dependencies, function () {
			mw.eventLog.logEvent( schema, event );
		} );
	}

	$( window ).on( 'load', function () {
		mw.trackSubscribe( 'event.', handleTrackedEvent );
	} );

}( mediaWiki, jQuery ) );
