/*!
 * @module ext.eventLogging.core
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function () {
	'use strict';

	var self, baseUrl, debugMode;

	// `baseUrl` corresponds to $wgEventLoggingBaseUri, as declared
	// in EventLogging.php. If the default value of 'false' has not
	// been overridden, events will not be sent to the server.
	baseUrl = mw.config.get( 'wgEventLoggingBaseUri' );

	// Support both 1 or "1" (T54542)
	debugMode = Number( mw.user.options.get( 'eventlogging-display-web' ) ) === 1;

	/**
	 * Client-side EventLogging API.
	 *
	 * The main API is `mw.eventLog.logEvent`. Most other methods represent internal
	 * functionality, which is exposed only to ease debugging code and writing tests.
	 *
	 * @private
	 * @class mw.eventLog.Core
	 * @singleton
	 */
	self = {

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
			return mw.config.get( 'wgEventLoggingSchemaRevision' )[ schemaName ] || -1;
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
				revision: self.getRevision( schemaName ),
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
			return baseUrl + '?' + queryString + ';';
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
			if ( url.length > self.maxUrlSize ) {
				message = 'Url exceeds maximum length';
				mw.eventLog.logFailure( schemaName, 'urlSize' );
				mw.track( 'eventlogging.error', mw.format( '[$1] $2', schemaName, message ) );
				return message;
			}
		},

		/**
		 * Transfer data to a remote server by making a lightweight HTTP
		 * request to the specified URL.
		 *
		 * If the user expressed a preference not to be tracked, or if
		 * $wgEventLoggingBaseUri is unset, this method is a no-op.
		 *
		 * See https://developer.mozilla.org/en-US/docs/Web/API/Navigator/doNotTrack
		 *
		 * @param {string} url URL to request from the server.
		 * @return undefined
		 */
		sendBeacon: (
			// Support: Firefox < 32 (yes/no)
			/1|yes/.test( navigator.doNotTrack ) ||
				// Support: IE 11, Safari 7.1.3+ (window.doNotTrack)
				window.doNotTrack === '1' ||
				// Support: IE 9, IE 10 (navigator.msDoNotTrack)
				navigator.msDoNotTrack === '1' ||
				!baseUrl
		) ?
			$.noop :
			navigator.sendBeacon ?
				function ( url ) { try { navigator.sendBeacon( url ); } catch ( e ) {} } :
				function ( url ) { document.createElement( 'img' ).src = url; },

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
			var event = self.prepare( schemaName, eventData ),
				url = self.makeBeaconUrl( event ),
				sizeError = self.checkUrlSize( schemaName, url ),
				deferred = $.Deferred();

			if ( !sizeError ) {
				self.sendBeacon( url );
				if ( debugMode ) {
					mw.track( 'eventlogging.debug', event );
				}
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
		}

	};

	/**
	 * @class mw.eventLog
	 * @mixins mw.eventLog.Core
	 */
	$.extend( mw.eventLog, self );

}() );
