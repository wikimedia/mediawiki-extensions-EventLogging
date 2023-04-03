var ContextController = require( './ContextController.js' );
var SamplingController = require( './SamplingController.js' );
var CurationController = require( './CurationController.js' );

var STATE_FETCHING_STREAM_CONFIGS = 1;
var STATE_FETCHED_STREAM_CONFIGS = 2;

var CALL_QUEUE_MAX_LENGTH = 128;

var SCHEMA = '/analytics/mediawiki/client/metrics_event/1.2.0';

/**
 * Client for producing events to [the Event Platform](https://wikitech.wikimedia.org/wiki/Event_Platform) and
 * [the Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform).
 *
 * @param {Integration} integration
 * @param {StreamConfigs|false} [streamConfigs]
 * @constructor
 */
function MetricsClient(
	integration,
	streamConfigs
) {
	this.contextController = new ContextController( integration );
	this.samplingController = new SamplingController( integration );
	this.curationController = new CurationController();
	this.integration = integration;

	// MetricsClient( integration: Integration )
	if ( streamConfigs === undefined ) {
		this.streamConfigs = {};
		this.state = STATE_FETCHING_STREAM_CONFIGS;
		this.fetchStreamConfigs();
	} else {
		this.streamConfigs = streamConfigs;
		this.state = STATE_FETCHED_STREAM_CONFIGS;
	}

	this.eventNameToStreamNamesMap = null;

	/** @type {CallQueue} */
	this.callQueue = [];
}

/**
 * Fetch and update the stream configs, and then process the call queue, submitting and dispatching
 * events as necessary.
 */
MetricsClient.prototype.fetchStreamConfigs = function () {
	var that = this;

	that.integration.fetchStreamConfigs()
		.then( function ( /** @type {StreamConfigs} */ streamConfigs ) {
			that.streamConfigs = streamConfigs;
			that.eventNameToStreamNamesMap = null;
			that.state = STATE_FETCHED_STREAM_CONFIGS;

			that.processCallQueue();
		} );
};

/**
 * @param {StreamConfigs|false} streamConfigs
 * @param {string} streamName
 * @return {StreamConfig|undefined}
 */
function getStreamConfigInternal( streamConfigs, streamName ) {
	// If streamConfigs are false, then stream config usage is not enabled.
	// Always return an empty object.
	//
	// FIXME
	//  The convention that disabling stream configuration results
	//  in enabling any caller to send any event, with no sampling,
	//  etc., is correct in the sense of the boolean logic, but
	//  counter-intuitive and likely hard to keep correct as more
	//  behavior is added. We should revisit.

	if ( streamConfigs === false ) {
		return {};
	}

	if ( !streamConfigs[ streamName ] ) {
		// In case no config has been assigned to the given streamName,
		// return undefined, so that the developer can discern between
		// a stream that is not configured, and a stream with config = {}.
		return undefined;
	}

	return streamConfigs[ streamName ];
}

/**
 * Gets a deep clone of the stream config.
 *
 * @param {string} streamName
 * @return {StreamConfig|undefined}
 */
MetricsClient.prototype.getStreamConfig = function ( streamName ) {
	var streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	return streamConfig ? this.integration.clone( streamConfig ) : streamConfig;
};

/**
 * @param {StreamConfigs} streamConfigs
 * @return {Record<string, string[]>}
 */
function getEventNameToStreamNamesMap( streamConfigs ) {
	/** @type Record<string, string[]> */
	var result = {};

	for ( var streamName in streamConfigs ) {
		var streamConfig = streamConfigs[ streamName ];

		if (
			!streamConfig.producers ||
			!streamConfig.producers.metrics_platform_client ||
			!streamConfig.producers.metrics_platform_client.events
		) {
			continue;
		}

		var events = streamConfig.producers.metrics_platform_client.events;

		if ( typeof events === 'string' ) {
			events = [ events ];
		}

		for ( var i = 0; i < events.length; ++i ) {
			if ( !result[ events[ i ] ] ) {
				result[ events[ i ] ] = [];
			}

			result[ events[ i ] ].push( streamName );
		}
	}

	return result;
}

/**
 * Get the names of the streams associated with the event.
 *
 * A stream (S) can be associated with an event by configuring it as follows:
 *
 * ```
 * 'S' => [
 *   'producers' => [
 *     'metrics_platform_client' => [
 *       'events' => [
 *         'event1',
 *         'event2',
 *         // ...
 *       ],
 *     ],
 *   ],
 * ],
 * ```
 *
 * @param {string} eventName
 * @return {string[]}
 */
MetricsClient.prototype.getStreamNamesForEvent = function ( eventName ) {
	if ( this.streamConfigs === false ) {
		return [];
	}

	if ( !this.eventNameToStreamNamesMap ) {
		this.eventNameToStreamNamesMap = getEventNameToStreamNamesMap( this.streamConfigs );
	}

	/** @type string[] */
	var result = [];

	for ( var key in this.eventNameToStreamNamesMap ) {
		if ( eventName.indexOf( key ) === 0 ) {
			result = result.concat( this.eventNameToStreamNamesMap[ key ] );
		}
	}

	return result;
};

/**
 * Enqueues a call to be processed.
 *
 * The call queue has a maximum capacity of 128 calls. If it is full, then the earliest call is
 * dropped and a warning is logged.
 *
 * @ignore
 *
 * @param {CallQueueEntry} call
 */
MetricsClient.prototype.queueCall = function ( call ) {
	this.callQueue.push( call );

	if ( this.callQueue.length > CALL_QUEUE_MAX_LENGTH ) {
		var droppedCall = this.callQueue.shift();

		if ( droppedCall ) {
			var callString = [
				droppedCall[ 0 ],
				'( ',
				droppedCall[ 2 ],
				', ',
				droppedCall[ 0 ] === 'submit' ? 'eventData' : 'customData',
				' )'
			].join( '' );

			this.integration.logWarning( 'Call to ' + callString + ' dropped because the queue is full.' );
		}
	}

	this.processCallQueue();
};

/**
 * Processes calls to {@link MetricsClient.prototype.submit} and
 * {@link MetricsClient.prototype.dispatch} if the stream configs have been fetched and updated.
 *
 * @ignore
 */
MetricsClient.prototype.processCallQueue = function () {
	if ( this.state === STATE_FETCHING_STREAM_CONFIGS ) {
		return;
	}

	while ( this.callQueue.length ) {
		var call = this.callQueue.shift();

		if ( !call ) {
			// NOTE: This should never happen.
			continue;
		}

		if ( call[ 0 ] === 'submit' ) {
			this.processSubmitCall( call[ 1 ], call[ 2 ], call[ 3 ] );
		} else if ( call[ 0 ] === 'dispatch' ) {
			this.processDispatchCall( call[ 1 ], call[ 2 ], call[ 3 ] );
		}
	}
};

/**
 * Processes the result of a call to {@link MetricsClient.prototype.submit}.
 *
 * NOTE: This method should only be called **after** the stream configs have been fetched via
 * {@link MetricsClient.prototype.fetchStreamConfigs}.
 *
 * @ignore
 *
 * @param {string} timestamp The ISO 8601 formatted timestamp of the original call
 * @param {string} streamName The name of the stream to send the event data to
 * @param {BaseEventData} eventData The event data
 */
MetricsClient.prototype.processSubmitCall = function ( timestamp, streamName, eventData ) {
	eventData.dt = timestamp;

	var streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	if ( !streamConfig ) {
		return;
	}

	this.addRequiredMetadata( eventData, streamName );

	if ( this.samplingController.streamInSample( streamConfig ) ) {
		this.integration.enqueueEvent( eventData );
		this.integration.onSubmit( streamName, eventData );
	}
};

/**
 * Processes the result of a call to {@link MetricsClient.prototype.dispatch}.
 *
 * NOTE: This method should only be called **after** the stream configs have been fetched via
 * {@link MetricsClient.prototype.fetchStreamConfigs}.
 *
 * @ignore
 *
 * @param {string} timestamp The ISO 8601 formatted timestamp of the original call
 * @param {string} eventName
 * @param {Record<string, any>} [formattedCustomData]
 */
MetricsClient.prototype.processDispatchCall = function (
	timestamp,
	eventName,
	formattedCustomData
) {
	var streamNames = this.getStreamNamesForEvent( eventName );

	// Produce the event(s)
	for ( var i = 0; i < streamNames.length; ++i ) {
		/* eslint-disable camelcase */
		/** @type {MetricsPlatformEventData} */
		var eventData = {
			$schema: SCHEMA,
			dt: timestamp,
			name: eventName
		};

		if ( formattedCustomData ) {
			eventData.custom_data = formattedCustomData;
		}
		/* eslint-enable camelcase */

		var streamName = streamNames[ i ];
		var streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

		if ( !streamConfig ) {
			// NOTE: This SHOULD never happen.
			continue;
		}

		this.addRequiredMetadata( eventData, streamName );
		this.contextController.addRequestedValues( eventData, streamConfig );

		if (
			this.samplingController.streamInSample( streamConfig ) &&
			this.curationController.shouldProduceEvent( eventData, streamConfig )
		) {
			this.integration.enqueueEvent( eventData );
			this.integration.onSubmit( streamName, eventData );
		}
	}
};

/**
 * Adds required fields:
 *
 * - `meta.stream`: the target stream name
 * - `meta.domain`: the domain associated with this event
 * - `dt`: the client-side timestamp (unless this is a migrated legacy event,
 *         in which case the timestamp will already be present as `client_dt`).
 *
 * @param {BaseEventData} eventData
 * @param {string} streamName
 * @return {BaseEventData}
 */
MetricsClient.prototype.addRequiredMetadata = function ( eventData, streamName ) {
	if ( eventData.meta ) {
		eventData.meta.stream = streamName;
		eventData.meta.domain = this.integration.getHostname();
	} else {
		eventData.meta = {
			stream: streamName,
			domain: this.integration.getHostname()
		};
	}

	//
	// The 'dt' field is reserved for the internal use of this library,
	// and should not be set by any other caller.
	//
	// (1) 'dt' is a client-side timestamp for new events
	//      and a server-side timestamp for legacy events.
	// (2) 'dt' will be provided by EventGate if omitted here,
	//      so it should be omitted for legacy events (and
	//      deleted if present).
	//
	// We detect legacy events by looking for the 'client_dt'.
	//
	if ( eventData.client_dt ) {
		delete eventData.dt;
	} else {
		eventData.dt = eventData.dt || new Date().toISOString();
	}

	return eventData;
};

/**
 * Submit an event to a stream.
 *
 * The event (E) is submitted to the stream (S) if E has the `$schema` property and S is in
 * sample. If E does not have the `$schema` property, then a warning is logged.
 *
 * NOTE: Calls to `submit()` are processed after the stream configs are fetched and updated. If
 * the Metrics Platform Client was initialized with stream configs, then calls to `submit()` are
 * processed immediately.
 *
 * @param {string} streamName The name of the stream to send the event data to
 * @param {BaseEventData} eventData The event data
 */
MetricsClient.prototype.submit = function ( streamName, eventData ) {
	if ( !eventData || !eventData.$schema ) {
		this.integration.logWarning(
			'submit( ' + streamName + ', eventData ) called with eventData missing required ' +
			'field "$schema". No event will be produced.'
		);
		return;
	}

	this.queueCall( [
		'submit',
		new Date().toISOString(),
		streamName,
		eventData
	] );
};

/**
 * Format the custom data so that it is compatible with the Metrics Platform Event schema.
 *
 * `customData` is considered valid if all of its keys are snake_case.
 *
 * @param {Record<string,any>|undefined} customData
 * @return {FormattedCustomData}
 * @throws {Error} If `customData` is invalid
 */
function getFormattedCustomData( customData ) {
	/** @type {Record<string,EventCustomDatum>} */
	var result = {};

	if ( !customData ) {
		return result;
	}

	for ( var key in customData ) {
		if ( !key.match( /^[$a-z]+[a-z0-9_]*$/ ) ) {
			throw new Error( 'The key "' + key + '" is not snake_case.' );
		}

		var value = customData[ key ];
		var type = value === null ? 'null' : typeof value;

		result[ key ] = {
			// eslint-disable-next-line camelcase
			data_type: type,
			value: String( value )
		};
	}

	return result;
}

/**
 * Construct and submits a Metrics Platform Event from the event name and custom data for each
 * stream that is interested in those events.
 *
 * The Metrics Platform Event for a stream (S) is constructed by first initializing the minimum
 * valid event (E) that can be submitted to S, and then mixing the context attributes requested
 * in the configuration for S into E.
 *
 * The Metrics Platform Event is submitted to a stream (S) if S is in sample and the event
 * is not filtered according to the filtering rules for S.
 *
 * NOTE: Calls to `dispatch()` are processed after the stream configs are fetched and updated. If
 * the Metrics Platform Client was initialized with stream configs, then calls to `dispatch()` are
 * processed immediately.
 *
 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform
 *
 * @param {string} eventName
 * @param {Record<string, any>} [customData]
 */
MetricsClient.prototype.dispatch = function ( eventName, customData ) {
	var formattedCustomData;

	try {
		formattedCustomData = getFormattedCustomData( customData );
	} catch ( e ) {
		this.integration.logWarning(
			// @ts-ignore TS2571
			'dispatch( ' + eventName + ', customData ) called with invalid customData: ' + e.message +
			'No event(s) will be produced.'
		);

		return;
	}

	// T309083
	if ( this.streamConfigs === false ) {
		this.integration.logWarning(
			'dispatch( ' + eventName + ', customData ) cannot dispatch events when stream configs are disabled.'
		);

		return;
	}

	this.queueCall( [
		'dispatch',
		new Date().toISOString(),
		eventName,
		formattedCustomData
	] );
};

module.exports = MetricsClient;
module.exports.SCHEMA = SCHEMA;
