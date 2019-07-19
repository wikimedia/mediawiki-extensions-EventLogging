/*!
 * EventLogging client
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function () {
	'use strict';

	var core, debugMode,
		// config contains:
		// - baseUrl: corresponds to the $wgEventLoggingBaseUri configuration in PHP.
		//            If set to false (default), then events will not be logged.
		// - schemaRevision: Object mapping schema names to revision IDs
		config = require( './data.json' ),
		BackgroundQueue = require( './BackgroundQueue.js' ),
		queue = ( new BackgroundQueue() ),
		isDntEnabled;

	// Support both 1 or "1" (T54542)
	debugMode = Number( mw.user.options.get( 'eventlogging-display-web' ) ) === 1;

	isDntEnabled = (
		// Support: Firefox < 32 (yes/no)
		/1|yes/.test( navigator.doNotTrack ) ||
		// Support: IE 11, Safari 7.1.3+ (window.doNotTrack)
		window.doNotTrack === '1'
	);

	if ( isDntEnabled ) {
		mw.log.warn( 'DNT is on, logging disabled' );
	}

	/**
	 * Client-side EventLogging API, including pub/sub subscriber functionality.
	 *
	 * The main API is `mw.eventLog.logEvent`.  This is set up as a listener for
	 * `event`-namespace topics in `mw.track`. Sampling utility methods are available
	 * in two flavors.  Other methods represent internal functionality, exposed only
	 * to ease debugging code and writing tests.
	 *
	 * @class mw.eventLog
	 * @singleton
	 */
	core = {

		/**
		 * Maximum length in chars that a beacon URL can have.
		 * Relevant:
		 *
		 * - Length that browsers support (http://stackoverflow.com/a/417184/319266)
		 * - Length that proxies support (e.g. Varnish)
		 * - varnishlog (shm_reclen)
		 * - varnishkafka
		 *
		 * @private
		 * @property maxUrlSize
		 * @type Number
		 */
		maxUrlSize: 2000,

		/**
		 * Get the configured revision id to use with events in a particular schema
		 *
		 * @private
		 * @param {string} schemaName Canonical schema name.
		 * @return {Number} the revision id configured for this schema by instrumentation.
		 */
		getRevision: function ( schemaName ) {
			return config.schemaRevision[ schemaName ] || -1;
		},

		/**
		 * Prepares an event for dispatch by filling defaults for any missing
		 * properties and by encapsulating the event object in an object which
		 * contains metadata about the event itself.
		 *
		 * @private
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object} eventData Event instance.
		 * @return {Object} Encapsulated event.
		 */
		prepare: function ( schemaName, eventData ) {
			return {
				event: eventData,
				revision: core.getRevision( schemaName ),
				schema: schemaName,
				webHost: location.hostname,
				wiki: mw.config.get( 'wgDBname' )
			};
		},

		/**
		 * Constructs the EventLogging URI based on the base URI and the
		 * encoded and stringified data.
		 *
		 * @private
		 * @param {Object} data Payload to send
		 * @return {string|boolean} The URI to log the event.
		 */
		makeBeaconUrl: function ( data ) {
			var queryString = encodeURIComponent( JSON.stringify( data ) );
			return config.baseUrl + '?' + queryString + ';';
		},

		/**
		 * Checks whether a beacon url is short enough,
		 * so that it does not get truncated by varnishncsa.
		 *
		 * @private
		 * @param {string} schemaName Canonical schema name.
		 * @param {string} url Beacon url.
		 * @return {string|undefined} The error message in case of error.
		 */
		checkUrlSize: function ( schemaName, url ) {
			var message;
			if ( url.length > core.maxUrlSize ) {
				message = 'Url exceeds maximum length';
				core.logFailure( schemaName, 'urlSize' );
				mw.track( 'eventlogging.error', mw.format( '[$1] $2', schemaName, message ) );
				return message;
			}
		},

		/**
		 * Whether the user expressed a preference to not be tracked
		 *
		 * See https://developer.mozilla.org/en-US/docs/Web/API/Navigator/doNotTrack
		 *
		 * @property isDntEnabled
		 * @type boolean
		 */
		isDntEnabled: isDntEnabled,

		/**
		 * Makes a lightweight HTTP request to a specified URL, using the best means
		 * available to this user agent.  Respects DNT and falls back to creating an img
		 * element on user agents without navigator.sendBeacon.
		 *
		 * @param {string} url URL to request from the server.
		 * @return undefined
		 */
		sendBeacon: isDntEnabled ?
			function () {} :
			navigator.sendBeacon ?
				function ( url ) {
					try {
						navigator.sendBeacon( url );
					} catch ( e ) {}
				} :
				function ( url ) { document.createElement( 'img' ).src = url; },

		/**
		 * Add a pending callback to be flushed at a later time by the background queue
		 *
		 * @param {Function} callback to enqueue and run when the queue is processed
		 * @return undefined
		 */
		enqueue: queue.add,

		/**
		 * Construct and transmit to a remote server a record of some event
		 * having occurred. Events are represented as JavaScript objects that
		 * conform to a JSON Schema. The schema describes the properties the
		 * event object may (or must) contain and their type. This method
		 * represents the public client-side API of EventLogging.
		 *
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object} eventData Event object.
		 * @return {jQuery.Promise} jQuery Promise object for the logging call.
		 */
		logEvent: function ( schemaName, eventData ) {
			var event = core.prepare( schemaName, eventData ),
				url = core.makeBeaconUrl( event ),
				sizeError = core.checkUrlSize( schemaName, url ),
				deferred = $.Deferred();

			if ( !sizeError ) {
				if ( config.baseUrl || debugMode ) {
					core.enqueue( function () {
						core.sendBeacon( url );
					} );
				}

				if ( debugMode ) {
					mw.track( 'eventlogging.debug', event );
				}
				// TODO: deprecate the signature of this method by returning a meaningless
				// promise and moving the sizeError checking into debug mode
				deferred.resolveWith( event, [ event ] );
			} else {
				deferred.rejectWith( event, [ event, sizeError ] );
			}
			return deferred.promise();
		},

		/**
		 * Increment the error count in statsd for this schema.
		 *
		 * Should be called instead of logEvent in case of an error.
		 *
		 * @param {string} schemaName
		 * @param {string} errorCode
		 */
		logFailure: function ( schemaName, errorCode ) {
			// Record this failure as a simple counter. By default "counter.*" goes nowhere.
			// The WikimediaEvents extension sends it to statsd.
			mw.track( 'counter.eventlogging.client_errors.' + schemaName + '.' + errorCode );
		},

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
		 * @param {string} [explicitToken] at least 32 bit integer in HEX format
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

	// Not allowed outside unit tests
	if ( window.QUnit ) {
		core.setOptionsForTest = function ( opts ) {
			var oldConfig = config;
			config = $.extend( {}, config, opts );
			return oldConfig;
		};
		core.BackgroundQueue = BackgroundQueue;
	}

	module.exports = core;

}() );
