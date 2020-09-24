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
		// - schemasInfo: Object mapping schema names to revision IDs or $schema URIs
		// - streamConfigs: Object mapping stream name to stream config (sampling rate, etc.)
		config = require( './data.json' ),
		BackgroundQueue = require( './BackgroundQueue.js' ),
		queue = ( new BackgroundQueue( config.queueLingerSeconds ) ),
		// samplingCache caches in-sample vs. out-of-sample determinations for
		// streams, so that the determination only needs to happen the first time an
		// an event is logged to each stream
		samplingCache = Object.create( null );

	// Support both 1 or "1" (T54542)
	debugMode = Number( mw.user.options.get( 'eventlogging-display-web' ) ) === 1;

	/**
	 * Construct the streamName for a legacy EventLogging Schema.
	 *
	 * Legacy EventLogging Schemas are single use and have only one associated stream.
	 *
	 * @ignore
	 * @private
	 * @param  {string} schemaName
	 * @return {string}
	 */
	function makeLegacyStreamName( schemaName ) {
		return 'eventlogging_' + schemaName;
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
		 *
		 * Relevant:
		 * - Length that browsers support (http://stackoverflow.com/a/417184/319266)
		 * - Length that proxies support (e.g. Varnish)
		 * - varnishlog (shm_reclen)
		 * - varnishkafka
		 *
		 * @private
		 * @property maxUrlSize
		 * @type number
		 */
		maxUrlSize: 2000,

		/**
		 * Get the configured revision id or $schema URI
		 * to use with events of a particular (legacy metawiki) EventLogging schema.
		 *
		 * @private
		 * @param {string} schemaName Canonical schema name.
		 * @return {number|string}
		 *         The revision id configured for this schema by instrumentation,
		 *         or a string $schema URI for use with Event Platform.
		 */
		getRevisionOrSchemaUri: function ( schemaName ) {
			return config.schemasInfo[ schemaName ] || -1;
		},

		/**
		 * Prepare an event for dispatch.
		 *
		 * This encapsulates the event data in a wrapper object with
		 * the default metadata for the current web page.
		 *
		 * NOTE: for forwards compatibility with Event Platform schemas,
		 * we hijack the wgEventLoggingSchemas revision to encode the
		 * $schema URI. If the value for a schema defined in
		 * EventLoggingSchemas is a string, it is assumed
		 * to be an Event Platform $schema URI, not a MW revision id.
		 * In this case, the event will be prepared to be POSTed to EventGate.
		 *
		 * @private
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object} eventData Event data.
		 * @return {Object} Encapsulated event.
		 */
		prepare: function ( schemaName, eventData ) {
			// Wrap eventData in EventLogging's EventCapsule.
			var
				event = {
					event: eventData,
					schema: schemaName,
					webHost: location.hostname,
					wiki: mw.config.get( 'wgDBname' )
				},
				revisionOrSchemaUri = core.getRevisionOrSchemaUri( schemaName );

			// Foward compatibilty with Event Platform schemas and EventGate.
			// If the wgEventLoggingSchemas entry for this schemaName is a string,
			// assume it is the Event Platform relative $schema URI and that
			// we want this event POSTed to EventGate.
			// Make the event data forward compatible.
			if ( typeof revisionOrSchemaUri === 'string' ) {
				event.$schema = revisionOrSchemaUri;
				// eslint-disable-next-line
				event.client_dt = new Date().toISOString();
				event.meta = {
					// meta.domain should be the same as top level EventCapsule webHost.
					domain: event.webHost
				};
				// Note: some fields will have defaults set by eventgate-wikimedia.
				// See:
				// - https://gerrit.wikimedia.org/r/plugins/gitiles/eventgate-wikimedia/+/refs/heads/master/eventgate-wikimedia.js#358
				// - https://wikitech.wikimedia.org/wiki/Event_Platform/Schemas/Guidelines#Automatically_populated_fields
			} else {
				// Deprecated:
				// Assume revisionOrSchemaUri is the MW revision id for this
				// EventLogging schema.
				event.revision = revisionOrSchemaUri;
			}

			return event;
		},

		/**
		 * Construct the EventLogging URI based on the base URI and the
		 * encoded and stringified data.
		 *
		 * @private
		 * @param {Object} data Payload to send.
		 * @return {string|boolean} The URI to log the event.
		 */
		makeBeaconUrl: function ( data ) {
			var queryString = encodeURIComponent( JSON.stringify( data ) );
			return config.baseUrl + '?' + queryString + ';';
		},

		/**
		 * Check whether a beacon url is short enough.
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
		 * Make a lightweight HTTP request to a specified URL, using the best means
		 * available to this user agent.
		 *
		 * This respect DNT. It falls back to creating an detached image element for
		 * browsers without `navigator.sendBeacon`.
		 *
		 * @param {string} url URL to request from the server.
		 */
		sendBeacon: function ( url ) {
			if ( navigator.sendBeacon ) {
				try {
					navigator.sendBeacon( url );
				} catch ( e ) {}
			} else {
				document.createElement( 'img' ).src = url;
			}
		},

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
			var url,
				sizeError,
				event = core.prepare( schemaName, eventData ),
				deferred = $.Deferred();

			// Assume that if $schema was set by core.prepare(), this
			// event should be POSTed to EventGate.
			if ( event.$schema ) {
				core.submit( makeLegacyStreamName( schemaName ), event );
				deferred.resolveWith( event, [ event ] );
			} else {
				url = core.makeBeaconUrl( event );
				sizeError = core.checkUrlSize( schemaName, url );

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
		 * Use #eventInSample or #sessionInSample instead.
		 *
		 * Note that token is coerced into 32 bits before calculating its mod  with
		 * the population size, while this does not make possible to sample in a rate below
		 * 1/2^32 and our token space is 2^80 this in practice is not a problem
		 * as schemas that are sampled sparsely are so  with ratios like 1/10,000
		 * so our "sampling space" is in practice quite smaller than  the token
		 * "random space"
		 *
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

		/**
		 * @deprecated Use #eventInSample
		 * @param {number} populationSize
		 * @return {boolean}
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

	// ////////////////////////////////////////////////////////////////////
	// MEP Upgrade Zone
	//
	// As we upgrade EventLogging to use MEP components, we will refactor
	// code from above to here. https://phabricator.wikimedia.org/T238544
	// ////////////////////////////////////////////////////////////////////

	/**
	 * Submit an event according to the given stream's configuration.
	 * If DNT is enabled, this method does nothing.
	 *
	 * @param {string} streamName name of the stream to send eventData to
	 * @param {Object} eventData data to send to streamName
	 */
	core.submit = function ( streamName, eventData ) {

		if ( !core.streamConfig( streamName ) ) {
			//
			// If no stream configuration has been loaded
			// for streamName (and we are not in debugMode),
			// we assume the client is misconfigured. Rather
			// than produce potentially inconsistent data, the
			// event submission does not proceed.
			// If config.streamConfigs === false,
			// this will always return {} and proceed.
			//
			// Note for the future: when stream cc-ing feature
			// is added, the cc-ing needs to happen BEFORE this
			// step, so that a lack of streamName does not
			// block submitting an event to streamName.ccStream
			// and so on.
			//
			return;
		}

		if ( !eventData || !eventData.$schema ) {
			//
			// If the caller has not provided a $schema field
			// in eventData, the event submission does not
			// proceed.
			//
			// The $schema field represents the (versioned)
			// schema which the caller expects eventData
			// will validate against (once the appropriate
			// additions have been made by this client).
			//
			mw.log.warn(
				'submit( ' + streamName + ', eventData ) called with eventData ' +
				'missing required field "$schema". No event will issue.'
			);
			return;
		}

		eventData.meta = eventData.meta || {};
		eventData.meta.stream = streamName;
		//
		// The 'client_dt' field is reserved for the internal use of this library,
		// and should not be set by any other caller. The 'meta.dt' field is
		// reserved for EventGate and will be set at ingestion to act as a record
		// of when the event was received.
		//
		// If 'client_dt' is provided, its value is not modified.
		// If 'client_dt' is not provided, a new value is computed.
		//
		// eslint-disable-next-line
		eventData.client_dt = eventData.client_dt || new Date().toISOString();

		// This will use a MediaWiki notification in the browser to display the event data.
		if ( debugMode ) {
			mw.track(
				'eventlogging.eventSubmitDebug',
				{ streamName: streamName, eventData: eventData }
			);
		}

		// If stream is not in sample, do not log the event.
		if ( !core.streamInSample( streamName ) ) {
			return;
		}

		//
		// Send the processed event to be produced.
		//
		if ( config.serviceUri ) {
			core.enqueue( function () {
				navigator.sendBeacon(
					config.serviceUri,
					JSON.stringify( eventData )
				);
			} );
		}
	};

	/**
	 * Return the in-sample/out-of-sample determination of the given stream name.
	 *
	 * Refer to https://www.mediawiki.org/wiki/Wikimedia_Product/Analytics_Infrastructure/Stream_configuration#Sampling_settings
	 * for documentation of the sampling config behavior and how the sampling rate
	 * works with regards to increases and "widening-the-stationary-net" behavior.
	 *
	 * While developing with MediaWiki-Vagrant, streams should still be configured
	 * in $wgEventStreams and registered with $wgEventLoggingStreamNames inside
	 * LocalSettings.php for events to be sent. See https://wikitech.wikimedia.org/wiki/Event_Platform/Instrumentation_How_To
	 * for more information on configuring streams in MediaWiki-Vagrant and in
	 * production through mediawiki-config.
	 *
	 * @private
	 * @param {string} streamName name of the stream to return config for
	 * @return {boolean} determination for the given streamName, defaulting to
	 	false if the stream is not enabled.
	 */
	core.streamInSample = function ( streamName ) {
		var uInt32Max = Math.pow( 2, 32 ) - 1,
			samplingConfig,
			samplingRate,
			samplingId,
			parsedId;

		// If the determination for the stream has not already been made on this
		// page load, we need to go through the process of determining whether the
		// stream is in-sample or out-of-sample based on its 'sampling' config.
		if ( streamName in samplingCache ) {
			return samplingCache[ streamName ];
		}

		// Stream determination not in cache, proceed with making a determination:
		if ( core.streamConfig( streamName ) === undefined ) {
			// If a stream is NOT DEFINED in the stream config, it is NOT IN SAMPLE.
			samplingCache[ streamName ] = false;
			return samplingCache[ streamName ];
		}

		samplingConfig = core.streamConfig( streamName ).sampling;
		if ( !samplingConfig ) {
			// Default to 100% (always in-sample) for stream if the stream *is*
			// configured but its sampling config is not explicitly defined.
			samplingCache[ streamName ] = true;
			return samplingCache[ streamName ];
		}

		samplingRate = samplingConfig.rate;
		if ( samplingRate === undefined && samplingConfig.identifier !== 'device' ) {
			// If rate is not provided and stream is not configured to use 'device',
			// we default to in-sample. Specifying 'device' ID-based sampling disables
			// the stream on MediaWiki.
			samplingCache[ streamName ] = true;
			return samplingCache[ streamName ];
		} else if ( samplingConfig.identifier === 'pageview' ) {
			samplingId = mw.user.getPageviewToken();
		} else if ( samplingConfig.identifier === 'session' || !samplingConfig.identifier ) {
			// Either the identifier was explicitly set to "session" or it was omitted
			// from sampling config, in which case we default to "session".
			samplingId = mw.user.sessionId();
		} else {
			// Either the identifier was set to 'device' or it was not recognized.
			samplingCache[ streamName ] = false;
			return samplingCache[ streamName ];
		}
		parsedId = parseInt( samplingId.slice( 0, 8 ), 16 );
		samplingCache[ streamName ] = parsedId / uInt32Max < samplingRate;
		return samplingCache[ streamName ];

	};

	/**
	 * Return the configuration object of the given stream name.
	 *
	 * Modifications to the returned object will not change the actual
	 * configuration. If there's no configuration for the passed stream,
	 * undefined is returned.
	 *
	 * @private
	 * @param {string} streamName name of the stream to return config for
	 * @return {Object|null} Stream configuration for the given streamName, or
	 *  undefined if the given stream was not enabled (or not loaded).
	 */
	core.streamConfig = function ( streamName ) {
		// If streamConfigs are false, then
		// stream config usage is not enabled.
		// Always return an empty object.
		if ( config.streamConfigs === false ) {
			return { };
		}

		if ( !config.streamConfigs[ streamName ] ) {
			// In case no config has been assigned to the given streamName,
			// return undefined, so that the developer can discern between
			// a stream that is not configured, and a stream with config = {}.
			return undefined;
		}
		return $.extend( true, {}, config.streamConfigs[ streamName ] );
	};

	// Not allowed outside unit tests
	if ( window.QUnit ) {
		core.setOptionsForTest = function ( opts ) {
			var oldConfig = config;
			config = $.extend( {}, config, opts );
			return oldConfig;
		};
		core.BackgroundQueue = BackgroundQueue;
		core.streamConfigs = config.streamConfigs;
		core.makeLegacyStreamName = makeLegacyStreamName;
	}

	module.exports = core;

}() );
