/*!
 * EventLogging client
 * @author Ori Livneh <ori@wikimedia.org>
 */
'use strict';

// config contains:
// - baseUrl: corresponds to the $wgEventLoggingBaseUri configuration in PHP.
//            If set to false (default), then mw.eventLog.logEvent will not log events.
// - serviceUri: corresponds to $wgEventLoggingServiceUri configuration in PHP.
//               If set to false (default), then mw.eventLog.submit will not log events.
// - schemasInfo: Object mapping schema names to revision IDs or $schema URIs
// - streamConfigs: Object mapping stream name to stream config (sampling rate, etc.)
let config = require( './data.json' );
const BackgroundQueue = require( './BackgroundQueue.js' );
const queue = ( new BackgroundQueue( config.queueLingerSeconds ) );

// Support both 1 or "1" (T54542)
const debugMode = Number( mw.user.options.get( 'eventlogging-display-console' ) ) === 1;

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
 * @classdesc Client-side EventLogging API, including pub/sub subscriber functionality.
 *
 * The main API is `mw.eventLog.logEvent`.  This is set up as a listener for
 * `event`-namespace topics in `mw.track`. Sampling utility methods are available
 * in two flavors.  Other methods represent internal functionality, exposed only
 * to ease debugging code and writing tests.
 *
 * @class mw.eventLog
 * @singleton
 * @hideconstructor
 * @borrows MetricsClient#submit as submit
 * @borrows MetricsClient#submitInteraction as submitInteraction
 * @borrows MetricsClient#submitClick as submitClick
 * @borrows MetricsClient#newInstrument as newInstrument
 */
const core = {

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
	 * @property {number} maxUrlSize
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
		const
			event = {
				event: eventData,
				schema: schemaName,
				webHost: mw.config.get( 'wgServerName' ),
				wiki: mw.config.get( 'wgDBname' )
			},
			revisionOrSchemaUri = core.getRevisionOrSchemaUri( schemaName );

		// Forward compatibility with Event Platform schemas and EventGate.
		// If the wgEventLoggingSchemas entry for this schemaName is a string,
		// assume it is the Event Platform relative $schema URI and that
		// we want this event POSTed to EventGate.
		// Make the event data forward compatible.
		if ( typeof revisionOrSchemaUri === 'string' ) {
			event.$schema = revisionOrSchemaUri;
			// eslint-disable-next-line
			event.client_dt = new Date().toISOString();
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
		const queryString = encodeURIComponent( JSON.stringify( data ) );
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
		let message;
		if ( url.length > core.maxUrlSize ) {
			message = 'Url exceeds maximum length';
			core.logFailure( schemaName, 'urlSize' );
			mw.track( 'eventlogging.error', mw.format( '[$1] $2', schemaName, message ) );
			return message;
		}
	},

	/**
	 * Make a "fire and forget" HTTP request to a specified URL.
	 *
	 * In older browsers that lack the Beacon API (`navigator.sendBeacon`),
	 * this falls back to a detached Image request.
	 *
	 * @param {string} url URL to request from the server.
	 * @memberof mw.eventLog
	 */
	sendBeacon: function ( url ) {
		if ( navigator.sendBeacon ) {
			try {
				navigator.sendBeacon( url );
			} catch ( e ) {
				// Ignore, T86680.
			}
		} else {
			// Support IE 11: Fallback for Beacon API
			document.createElement( 'img' ).src = url;
		}
	},

	/**
	 * Add a pending callback to be flushed at a later time by the background queue.
	 *
	 * @param {Function} callback to enqueue and run when the queue is processed
	 * @return undefined
	 * @memberof mw.eventLog
	 * @method
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
	 * @memberof mw.eventLog
	 */
	logEvent: function ( schemaName, eventData ) {
		const event = core.prepare( schemaName, eventData );
		const deferred = $.Deferred();

		// Assume that if $schema was set by core.prepare(), this
		// event should be POSTed to EventGate.
		if ( event.$schema ) {
			core.submit( makeLegacyStreamName( schemaName ), event );
			deferred.resolveWith( event, [ event ] );
		} else {
			const url = core.makeBeaconUrl( event );
			const sizeError = core.checkUrlSize( schemaName, url );

			if ( !sizeError ) {
				if ( config.baseUrl || debugMode ) {
					core.enqueue( () => {
						core.sendBeacon( url );
					} );
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
	 * @memberof mw.eventLog
	 */
	logFailure: function ( schemaName, errorCode ) {
		// Record this failure as a simple counter. By default "counter.*" goes nowhere.
		// The WikimediaEvents extension sends it to statsd.
		mw.track( 'counter.eventlogging.client_errors.' + schemaName + '.' + errorCode );
		mw.track( 'stats.mediawiki_eventlogging_client_errors_total', { schemaName, errorCode } );
	},

	/**
	 * Randomise inclusion based on population size and random token.
	 *
	 * Use #pageviewInSample or #sessionInSample instead.
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
		const token = explicitToken || mw.user.generateRandomSessionId(),
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
	 * @memberof mw.eventLog
	 */
	sessionInSample: function ( populationSize ) {
		// Use the same unique random identifier within the same  session
		// to allow correlation between multiple events.
		return this.randomTokenMatch( populationSize, mw.user.sessionId() );
	},

	/**
	 * Determine whether the current event is sampled given a sampling ratio
	 * per pageview
	 *
	 * @deprecated Use #pageviewInSample
	 * @param {number} populationSize One in how many should be included.
	 *  0 means nobody, 1 is 100%, 2 is 50%, etc.
	 * @return {boolean}
	 */
	eventInSample: function ( populationSize ) {
		// Use the same unique random identifier within the same page load
		// to allow correlation between multiple events.
		return this.randomTokenMatch( populationSize, mw.user.getPageviewToken() );
	},

	/**
	 * Determine whether the current event is sampled given a sampling ratio
	 * per pageview.
	 *
	 * @param {number} populationSize One in how many should be included.
	 *  0 means nobody, 1 is 100%, 2 is 50%, etc.
	 * @return {boolean}
	 * @memberof mw.eventLog
	 */
	pageviewInSample: function ( populationSize ) {
		// Use the same unique random identifier within the same page load
		// to allow correlation between multiple events.
		return this.randomTokenMatch( populationSize, mw.user.getPageviewToken() );
	}
};

// Deprecate the old core.inSample function and introduce a
// replacement, core.pageviewInSample, to transition to the
// new function for handling pageview sampling.
// Apply mw.log.deprecate to the old inSample function.
mw.log.deprecate( core, 'inSample', core.pageviewInSample, 'Use "mw.eventLog.pageviewInSample" instead.', 'mw.eventLog.inSample' );
// ////////////////////////////////////////////////////////////////////
// MEP Upgrade Zone
//
// As we upgrade EventLogging to use MEP components, we will refactor
// code from above to here. https://phabricator.wikimedia.org/T238544
// ////////////////////////////////////////////////////////////////////

const EventSubmitter = require( './EventSubmitter.js' );
const metricsPlatform = require( 'ext.eventLogging.metricsPlatform' );

function initMetricsClient() {
	const eventSubmitter = new EventSubmitter(
		config.serviceUri,
		core.enqueue.bind( core ),
		debugMode
	);
	const metricsClient = metricsPlatform.newMetricsClient(
		config.streamConfigs,
		eventSubmitter
	);

	// TODO (phuedx, 2024/09/09): DRY this up
	core.submit = metricsClient.submit.bind( metricsClient );
	core.submitInteraction = metricsClient.submitInteraction.bind( metricsClient );
	core.submitClick = metricsClient.submitClick.bind( metricsClient );
	core.newInstrument = metricsClient.newInstrument.bind( metricsClient );
}
initMetricsClient();

core.storage = {
	get: function ( name ) {
		return mw.cookie.get( 'el-' + name );
	},
	set: function ( name, value ) {
		mw.cookie.set( 'el-' + name, value );
	},
	unset: function ( name ) {
		mw.cookie.set( 'el-' + name, null );
	}
};

core.id = ( function () {
	const UINT32_MAX = 4294967295; // (2^32) - 1
	let
		pageviewId = null,
		sessionId = null;

	// Provided by the sessionTick instrument in WikimediaEvents.
	mw.trackSubscribe( 'sessionReset', () => {
		core.id.resetSessionId();
	} );

	return {
		resetPageviewId: function () {
			pageviewId = null;
		},

		resetSessionId: function () {
			sessionId = null;
			core.storage.unset( 'sessionId' );
		},

		generateId: function () {
			return mw.user.generateRandomSessionId();
		},

		normalizeId: function ( id ) {
			return parseInt( id.slice( 0, 8 ), 16 ) / UINT32_MAX;
		},

		getPageviewId: function () {
			if ( !pageviewId ) {
				pageviewId = core.id.generateId();
			}
			return pageviewId;
		},

		getSessionId: function () {
			if ( !sessionId ) {
				//
				// If there is no runtime value for SESSION_ID,
				// try to load a value from persistent store.
				//
				sessionId = core.storage.get( 'sessionId' );

				if ( !sessionId ) {
					//
					// If there is no value in the persistent store,
					// generate a new value for SESSION_ID, and write
					// the update to the persistent store.
					//
					sessionId = core.id.generateId();
					core.storage.set( 'sessionId', sessionId );
				}
			}
			return sessionId;
		}
	};
}() );

/**
 * Provide the user's edit count as a low-granularity bucket name.
 *
 * @param {number|null} editCount User edit count, or null for anonymous performers.
 * @return {string|null} `null` for anonymous performers.
 *
 * Do not use this value in conjunction with other edit count
 * bucketing, or you will deanonymize users to some degree.
 *
 * @memberof mw.eventLog
 */
function getUserEditCountBucket( editCount ) {
	if ( editCount === null ) {
		return null;
	}
	if ( editCount === 0 ) {
		return '0 edits';
	}
	if ( editCount < 5 ) {
		return '1-4 edits';
	}
	if ( editCount < 100 ) {
		return '5-99 edits';
	}
	if ( editCount < 1000 ) {
		return '100-999 edits';
	}
	return '1000+ edits';
}
mw.config.set(
	'wgUserEditCountBucket',
	getUserEditCountBucket( mw.config.get( 'wgUserEditCount' ) )
);

// Not allowed outside unit tests
if ( window.QUnit ) {
	core.setOptionsForTest = function ( opts ) {
		const originalOptions = config;
		config = opts;

		// Reinitialise the Metrics Platform client.
		initMetricsClient();

		return originalOptions;
	};
	core.BackgroundQueue = BackgroundQueue;
	core.makeLegacyStreamName = makeLegacyStreamName;
	core.getUserEditCountBucket = getUserEditCountBucket;
	core.getQueue = function () {
		return queue;
	};
}

module.exports = core;
