var ContextController = require( './ContextController.js' );
var SamplingController = require( './SamplingController.js' );
var CurationController = require( './CurationController.js' );

/**
 * Client for producing events to the Wikimedia metrics platform.
 *
 * Produce events with `MetricsClient.submit()`.
 *
 * @param {Integration} integration
 * @param {StreamConfigs|false} streamConfigs
 * @constructor
 */
function MetricsClient( integration, streamConfigs ) {
	this.contextController = new ContextController( integration );
	this.samplingController = new SamplingController( integration );
	this.curationController = new CurationController();
	this.integration = integration;
	this.streamConfigs = streamConfigs;
	this.eventNameToStreamNamesMap = null;
}

/**
 * @param {StreamConfigs|false} streamConfigs
 * @param {string} streamName
 * @return {StreamConfig|undefined}
 */
function getStreamConfigInternal( streamConfigs, streamName ) {
	// If streamConfigs are false, then stream config usage is not enabled.
	// Always return an empty object.
	// FIXME: naming
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
 * Submit an event according to the given stream's configuration.
 *
 * @param {string} streamName name of the stream to send eventData to
 * @param {BaseEventData} eventData data to send to the stream
 */
MetricsClient.prototype.submit = function ( streamName, eventData ) {
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
		this.integration.logWarning(
			'submit( ' + streamName + ', eventData ) called with eventData missing required ' +
			'field "$schema". No event will be produced.'
		);
		return;
	}

	//
	// NOTE
	// If stream configuration is disabled (config.streamConfigs === false),
	// then client.streamConfig will return an empty object {},
	// i.e. a truthy value, for all stream names.
	//
	// FIXME
	// The convention that disabling stream configuration results
	// in enabling any caller to send any event, with no sampling,
	// etc., is correct in the sense of the boolean logic, but
	// counter-intuitive and likely hard to keep correct as more
	// behavior is added. We should revisit.
	var streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	if ( !streamConfig ) {
		//
		// If stream configurations are enabled but no
		// stream configuration has been loaded for streamName
		// (and we are not in debugMode), we assume the client
		// is misconfigured. Rather than produce potentially
		// inconsistent data, the event submission does not
		// proceed.
		//
		// FIXME
		// See comment above; this should be made less
		// confusing.
		return;
	}

	// If stream is not in sample, do not log the event.
	if ( !this.samplingController.streamInSample( streamConfig ) ) {
		return;
	}

	this.addRequiredMetadata( eventData, streamName );

	this.integration.enqueueEvent( eventData );

	this.integration.onSubmit( streamName, eventData );
};

/**
 * Format the custom data so that it is compatible with the Metrics Platform Event schema.
 *
 * `customData` is considered valid if all of its keys are snake_case.
 *
 * @param {Record<string,any>|undefined} customData
 * @return {Record<string,EventCustomDatum>}
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
 * The Metrics Platform Event for a stream (S) is constructed by: first initializing the minimum
 * valid event (E) that can be submitted to S; and, second mixing the context attributes requested
 * in the configuration for S into E.
 *
 * The Metrics Platform Event is submitted to a stream (S) if: 1) S is in sample; and 2) the event
 * is filtered due to the filtering rules for S.
 *
 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform
 *
 * @param {string} eventName
 * @param {Record<string, any>} [customData]
 */
MetricsClient.prototype.dispatch = function ( eventName, customData ) {
	var streamNames = this.getStreamNamesForEvent( eventName );
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

	var dt = new Date().toISOString();

	// Produce the event(s)
	for ( var i = 0; i < streamNames.length; ++i ) {
		/* eslint-disable camelcase */
		/** @type {MetricsPlatformEventData} */
		var eventData = {
			$schema: '/analytics/mediawiki/client/metrics_event/1.0.0',
			dt: dt,
			name: eventName
		};

		if ( customData ) {
			eventData.custom_data = formattedCustomData;
		}
		/* eslint-enable camelcase */

		var streamName = streamNames[ i ];
		var streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

		if ( !streamConfig ) {
			// NOTE: This SHOULD never happen.
			continue;
		}

		this.contextController.addRequestedValues( eventData, streamConfig );

		if ( this.curationController.shouldProduceEvent( eventData, streamConfig ) ) {
			this.submit( streamName, eventData );
		}
	}
};

module.exports = MetricsClient;
