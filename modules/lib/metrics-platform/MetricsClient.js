const ContextController = require( './ContextController.js' );
const SamplingController = require( './SamplingController.js' );
const DefaultEventSubmitter = require( './DefaultEventSubmitter.js' );
const Instrument = require( './Instrument.js' );

const SCHEMA = '/analytics/mediawiki/client/metrics_event/2.1.0';

/**
 * @namespace EventPlatform
 */

/**
 * @typedef {Object} EventData
 * @property {string} $schema
 * @property {EventPlatform.EventMetaData} [meta]
 * @property {string} [client_dt]
 * @property {string} [dt]
 * @memberof EventPlatform
 */

/**
 * @typedef {Object} EventMetaData
 * @property {string} [domain]
 * @property {string} stream
 * @memberof EventPlatform
 */

// ---

/**
 * @typedef {EventPlatform.EventData|MetricsPlatform.Context.ContextAttributes} EventData
 * @property {string} name
 * @property {MetricsPlatform.FormattedCustomData} [custom_data]
 * @memberof MetricsPlatform
 */

/**
 * @typedef {Map<string,MetricsPlatform.EventCustomDatum>} FormattedCustomData
 * @memberof MetricsPlatform
 */

/**
 * @typedef {Object} EventCustomDatum
 * @property {string} data_type
 * @property {string} value
 * @memberof MetricsPlatform
 */

/**
 * Optional data related to the interaction.
 *
 * @typedef {Object} InteractionContextData
 * @property {string} action_subtype
 * @property {string} action_source
 * @property {string} action_context
 * @property {number} funnel_event_sequence_position
 * @property {string} instrument_name
 * @memberof MetricsPlatform
 */

/**
 * Data for the interaction.
 *
 * This interface and the {@link MetricsPlatform.InteractionContextData} interface allow for the
 * creation of many convenience methods that fill the `action` property (and/or other properties in
 * future), e.g. {@link MetricsPlatform.MetricsClient#submitClick}.
 *
 * @typedef {Object} InteractionData
 * @property {string} action
 * @memberof MetricsPlatform
 */

/**
 * @typedef {Object} ElementInteractionData
 * @property {string} element_id
 * @property {string} element_friendly_name
 * @memberof MetricsPlatform
 */

// ---

/**
 * Client for producing events to [the Event Platform](https://wikitech.wikimedia.org/wiki/Event_Platform) and
 * [the Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform).
 *
 * @param {MetricsPlatform.Integration} integration
 * @param {MetricsPlatform.Logger} logger
 * @param {EventPlatform.StreamConfigs|false} streamConfigs
 * @param {MetricsPlatform.EventSubmitter} [eventSubmitter] An instance of
 *  {@link DefaultEventSubmitter} by default
 * @constructor
 * @class MetricsClient
 * @memberof MetricsPlatform
 */
function MetricsClient(
	integration,
	logger,
	streamConfigs,
	eventSubmitter
) {
	this.contextController = new ContextController( integration );
	this.samplingController = new SamplingController( integration );
	this.integration = integration;
	this.logger = logger;
	this.streamConfigs = streamConfigs;
	this.eventSubmitter = eventSubmitter || new DefaultEventSubmitter();
	this.eventNameToStreamNamesMap = null;
}

/**
 * @ignore
 *
 * @param {EventPlatform.StreamConfigs|false} streamConfigs
 * @param {string} streamName
 * @return {EventPlatform.StreamConfig|undefined}
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
 * @return {EventPlatform.StreamConfig|undefined}
 */
MetricsClient.prototype.getStreamConfig = function ( streamName ) {
	const streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	return streamConfig ? this.integration.clone( streamConfig ) : streamConfig;
};

/**
 * @ignore
 *
 * @param {StreamConfigs} streamConfigs
 * @return {Map<string, string[]>}
 */
function getEventNameToStreamNamesMap( streamConfigs ) {
	/** @type {Map<string, string[]>} */
	const result = {};

	for ( const streamName in streamConfigs ) {
		const streamConfig = streamConfigs[ streamName ];

		if (
			!streamConfig.producers ||
			!streamConfig.producers.metrics_platform_client ||
			!streamConfig.producers.metrics_platform_client.events
		) {
			continue;
		}

		let events = streamConfig.producers.metrics_platform_client.events;

		if ( typeof events === 'string' ) {
			events = [ events ];
		}

		for ( let i = 0; i < events.length; ++i ) {
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
	let result = [];

	for ( const key in this.eventNameToStreamNamesMap ) {
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
 * @ignore
 *
 * @param {EventPlatform.EventData} eventData
 * @param {string} streamName
 * @return {EventPlatform.EventData}
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
 * @param {string} streamName The name of the stream to send the event data to
 * @param {EventPlatform.EventData} eventData The event data
 *
 * @stable
 */
MetricsClient.prototype.submit = function ( streamName, eventData ) {
	const result = this.validateSubmitCall( streamName, eventData );

	if ( result ) {
		this.processSubmitCall( new Date().toISOString(), streamName, eventData );
	}
};

/**
 * If `eventData` is falsy or does not have the `$schema` property set, then a warning is logged
 * and `false` is returned. Otherwise, `true` is returned.
 *
 * @param {string} streamName
 * @param {EventPlatform.EventData} eventData
 * @return {boolean}
 * @protected
 */
MetricsClient.prototype.validateSubmitCall = function ( streamName, eventData ) {
	if ( !eventData || !eventData.$schema ) {
		this.logger.logWarning(
			'submit( ' + streamName + ', eventData ) called with eventData missing required ' +
			'field "$schema". No event will be produced.'
		);

		return false;
	}

	return true;
};

/**
 * Processes the result of a call to {@link MetricsClient.prototype.submit}.
 *
 * @ignore
 *
 * @param {string} timestamp The ISO 8601 formatted timestamp of the original call
 * @param {string} streamName The name of the stream to send the event data to
 * @param {EventPlatform.EventData} eventData The event data
 */
MetricsClient.prototype.processSubmitCall = function ( timestamp, streamName, eventData ) {
	eventData.dt = timestamp;

	const streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	if ( !streamConfig ) {
		this.logger.logWarning(
			'The stream ' + streamName + ' is not configured. No event will be sent'
		);
		return;
	}

	if ( !this.samplingController.isStreamInSample( streamConfig ) ) {
		this.logger.logWarning(
			'The stream ' + streamName + ' is out of sample. No event will be sent'
		);
		return;
	}

	// Should the event be redirected to a different stream?
	const targetStreamName =
		streamConfig &&
		streamConfig.producers &&
		streamConfig.producers.metrics_platform_client &&
		streamConfig.producers.metrics_platform_client.stream_name;

	if ( targetStreamName ) {

		// The event should be redirected but the target stream isn't defined?
		//
		// NOTE: We could use recursion to DRY this up. However, we haven't discussed whether
		// redirection should be generally available to analytics instrumentation owners and, in
		// particular, if/how we should handle multiple sampling checks and how that could be
		// stored in the event.
		if ( !getStreamConfigInternal( this.streamConfigs, targetStreamName ) ) {
			return;
		}

		streamName = targetStreamName;
	}

	this.addRequiredMetadata( eventData, streamName );

	this.eventSubmitter.submitEvent( eventData );
};

/**
 * Submit an interaction event to a stream.
 *
 * An interaction event is meant to represent a basic interaction with some target or some event
 * occurring, e.g. the user (**performer**) tapping/clicking a UI element, or an app notifying the
 * server of its current state.
 *
 * An interaction event (`E`) MUST validate against the
 * /analytics/product_metrics/web/base/1.0.0 schema. At the time of writing, this means that `E`
 * MUST have the `action` property and MAY have the following properties:
 *
 * `action_subtype`
 * `action_source`
 * `action_context`
 *
 * If `E` does not have the `action` property, then a warning is logged.
 *
 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/JavaScript_API#Submit_an_interaction_event
 *
 * @param {string} streamName
 * @param {string} schemaID
 * @param {string} action
 * @param {MetricsPlatform.InteractionContextData} [interactionData]
 * @stable
 */
MetricsClient.prototype.submitInteraction = function (
	streamName,
	schemaID,
	action,
	interactionData
) {
	if ( !action ) {
		this.logger.logWarning(
			'submitInteraction( ' + streamName + ', ..., action ) ' +
			'called without required field "action". No event will be produced.'
		);

		return;
	}

	const eventData = Object.assign(
		{
			action
		},
		interactionData || {},
		{
			$schema: schemaID
		}
	);

	const streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	if ( !streamConfig ) {
		return;
	}

	this.contextController.addRequestedValues( eventData, streamConfig );

	this.submit( streamName, eventData );
};

const WEB_BASE_SCHEMA_ID = '/analytics/product_metrics/web/base/1.5.0';

/**
 * See {@link MetricsPlatform.MetricsClient#submitInteraction}.
 *
 * @param {string} streamName
 * @param {MetricsPlatform.ElementInteractionData} interactionData
 */
MetricsClient.prototype.submitClick = function ( streamName, interactionData ) {
	this.submitInteraction( streamName, WEB_BASE_SCHEMA_ID, 'click', interactionData );
};

/**
 *  Checks if a stream is in or out of sample.
 *
 * @param {string} streamName
 * @return {boolean}
 * @stable
 */
MetricsClient.prototype.isStreamInSample = function ( streamName ) {
	const streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	return streamConfig ? this.samplingController.isStreamInSample( streamConfig ) : false;
};

/**
 * Creates a new {@link MetricsPlatform.Instrument} instance, which is bound to this
 * `MetricsClient` instance.
 *
 * @example
 * // Create a new instrument by name:
 *
 * const m = require( '/path/to/metrics-platform' ).createMetricsClient();
 * let i = m.newInstrument( 'my_instrument' );
 *
 * // … and by stream name/schema ID pair:
 *
 * i = m.newInstrument( 'my_stream_name', '/analytics/my/schema/id/1.0.0' );
 *
 * // … and by instrument name and stream name/schema ID pair:
 *
 * i = m.newInstrument( 'my_instrument', 'my_stream_name', '/analytics/my/schema/id/1.0.0' );
 *
 * @param {string} streamOrInstrumentName
 * @param {string} [streamNameOrSchemaID]
 * @param {string} [schemaID]
 * @return {MetricsPlatform.Instrument}
 * @stable
 */
MetricsClient.prototype.newInstrument = function (
	streamOrInstrumentName,
	streamNameOrSchemaID,
	schemaID
) {
	let instrumentName;
	let streamName;

	if ( streamNameOrSchemaID === undefined ) {
		// #newInstrument( instrumentName )

		streamName = instrumentName = streamOrInstrumentName;
		schemaID = WEB_BASE_SCHEMA_ID;
	} else if ( schemaID === undefined ) {
		// #newInstrument( streamName, schemaID )

		streamName = streamOrInstrumentName;
		schemaID = streamNameOrSchemaID;
	} else {
		// #newInstrument( instrumentName, streamName, schemaID )

		instrumentName = streamOrInstrumentName;
		streamName = streamNameOrSchemaID;
	}

	const result = new Instrument( this, streamName, schemaID );

	if ( instrumentName ) {
		result.setInstrumentName( instrumentName );
	}

	return result;
};

module.exports = MetricsClient;
module.exports.SCHEMA = SCHEMA;
