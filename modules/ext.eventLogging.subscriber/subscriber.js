/*!
 * Because subscribers to mw#track receive the full backlog of events
 * matching the subscription, event processing can be safely deferred
 * until the window's load event has fired. This keeps the impact of
 * analytic instrumentation on page load times to a minimum.
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function () {
	/**
	 * Convert the first letter of a string to uppercase.
	 *
	 * @ignore
	 * @private
	 * @param {string} word
	 * @return {string}
	 */
	function titleCase( word ) {
		return word[ 0 ].toUpperCase() + word.slice( 1 );
	}

	/**
	 * mw.track handler for EventLogging events.
	 *
	 * @ignore
	 * @private
	 * @param {string} topic Topic name ('event.*').
	 * @param {Object} event
	 */
	function handleTrackedEvent( topic, event ) {
		var schema = titleCase( topic.slice( topic.indexOf( '.' ) + 1 ) ),
			dependencies = [ 'ext.eventLogging', 'schema.' + schema ];

		mw.loader.using( dependencies, function () {
			mw.eventLog.logEvent( schema, event );
		} );
	}

	/**
	 * @private
	 * @ignore
	 */
	function init() {
		mw.trackSubscribe( 'event.', handleTrackedEvent );
	}

	/**
	 * This a light-weight interface intended to have no dependencies and be
	 * loaded by initialisation code from consumers without loading the rest
	 * of EventLogging that deals with validation and logging to the server.
	 *
	 * This module handles the 'event'-namespaced topics in `mw.track`.
	 *
	 * Extensions can use this topic without depending on EventLogging
	 * as it degrades gracefully when EventLogging is not installed.
	 *
	 * The handler lazy-loads the appropriate schema module and core EventLogging
	 * code and logs the event.
	 *
	 * @class mw.eventLog
	 * @singleton
	 */
	mw.eventLog = {

		/**
		 * Randomise inclusion based on population size and random token.
		 *
		 * Use #eventInSample  or #sessionInSample
		 * Randomise inclusion based on population size and random token.

		 * Note that token is coerced into 32 bits before calculating its mod  with
		 * the population size, while this does not make possible to sample in a rate below
		 * 1/2^32 and our token space is 2^80 this in practice is not a problem
		 * as schemas that are sampled sparsely are so  with ratios like 1/10,000
		 * so our "sampling space" is in practice quite smaller than  the token
		 * "random space"
		 * @private
		 * @param {number} populationSize One in how many should return true.
		 * @param {string} [token] at least 32 bit integer in HEX format
		 * @return {boolean}
		 */
		randomTokenMatch: function ( populationSize, explicitToken ) {
			var token = explicitToken || mw.user.generateRandomSessionId(),
				rand = parseInt( token.slice( 0, 8 ), 16 );
			return rand % populationSize === 0;
		},

		/**
		 * Determine whether the current sessionId is sampled given a sampling ratio.
		 * This method is deterministic given same sampling rate and sessionId,
		 * so sampling is sticky given a session and a sampling rate
		 *
		 * @param {number} populationSize One in how many should be included.
		 *  0 means nobody, 1 is 100%, 2 is 50%, etc.
		 * @return {boolean}
		 */
		sessionInSample: function ( populationSize ) {
			// Use the same unique random identifier within the same  session
			// to allow correlation between multiple events.
			return this.randomTokenMatch( populationSize, mw.user.sessionId() );
		},

		/*
		* @deprecated, use eventInSample
		*/
		inSample: function ( populationSize ) {
			return this.eventInSample( populationSize );
		},

		/**
		 * Determine whether the current event is sampled given a sampling ratio
		 * per pageview
		 *
		 * @param {number} populationSize One in how many should be included.
		 *  0 means nobody, 1 is 100%, 2 is 50%, etc.
		 * @return {boolean}
		 */
		eventInSample: function ( populationSize ) {
			// Use the same unique random identifier within the same page load
			// to allow correlation between multiple events.
			return this.randomTokenMatch( populationSize, mw.user.getPageviewToken() );
		}

	};

	// It's possible for this code to run after the "load" event has already fired.
	if ( document.readyState === 'complete' ) {
		mw.requestIdleCallback( init );
	} else {
		// Avoid the logging of duplicate events (T170018).
		//
		// The load event must only fire once. However, Firefox 51 introduced a
		// bug that causes the event to fire again when returning from the
		// "Back-Forward cache" (BFCache) under certain circumstances (see
		// https://bugzilla.mozilla.org/show_bug.cgi?id=1379762).
		$( window ).one( 'load', init );
	}

}() );
