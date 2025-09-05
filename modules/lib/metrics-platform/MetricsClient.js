const ContextController = require( './ContextController.js' );
const SamplingController = require( './SamplingController.js' );
const CurationController = require( './CurationController.js' );
const DefaultEventSubmitter = require( './DefaultEventSubmitter.js' );
const Instrument = require( './Instrument.js' );

const SCHEMA = '/analytics/mediawiki/client/metrics_event/2.1.0';

/**
 * @namespace MetricsPlatform
 */

// ---

/**
 * An adaptor for the environment that the Metrics Platform Client is executing in.
 *
 * @interface Integration
 * @memberof MetricsPlatform
 */

/**
 * Fetches stream configs from some source, remote or local.
 *
 * @name MetricsPlatform.Integration#fetchStreamConfigs
 * @return {Promise<EventPlatform.StreamConfigs>}
 * @method
 */

/**
 * Gets the hostname of the current document.
 *
 * @method
 * @name MetricsPlatform.Integration#getHostname
 * @return {string}
 */

/**
 * Logs the warning to whatever logging backend that the execution environment provides, e.g.
 * the console.
 *
 * @method
 * @name MetricsPlatform.Integration#logWarning
 * @param {string} message
 */

/**
 * Gets a deep clone of the object.
 *
 * @method
 * @name MetricsPlatform.Integration#clone
 * @param {Object} obj
 * @return {Object}
 */

/**
 * Gets the values for those context attributes that are available in the execution
 * environment.
 *
 * @method
 * @name MetricsPlatform.Integration#getContextAttributes
 * @return {MetricsPlatform.Context.ContextAttributes}
 */

// NOTE: The following are required for compatibility with the current impl. but the
// information is also available via ::getContextualAttributes() above.

/**
 * Gets a token unique to the current pageview within the execution environment.
 *
 * @method
 * @name MetricsPlatform.Integration#getPageviewId
 * @return {string}
 */

/**
 * Gets a token unique to the current session within the execution environment.
 *
 * @method
 * @name MetricsPlatform.Integration#getSessionId
 * @return {string}
 */

/**
 * Gets the experiment details for the current user.
 *
 * @method
 * @name MetricsPlatform.Integration#getCurrentUserExperiments
 * @return {Object}
 */

/**
 * @method
 * @name MetricsPlatform.Integration#isCurrentUserEnrolled
 * @param {string} experimentName
 * @return {boolean}
 */

// ---

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
 * @param {EventPlatform.StreamConfigs|false} streamConfigs
 * @param {MetricsPlatform.EventSubmitter} [eventSubmitter] An instance of
 *  {@link DefaultEventSubmitter} by default
 * @constructor
 * @class MetricsClient
 * @memberof MetricsPlatform
 */
function MetricsClient(
	integration,
	streamConfigs,
	eventSubmitter
) {
	this.contextController = new ContextController( integration );
	this.samplingController = new SamplingController( integration );
	this.curationController = new CurationController();
	this.integration = integration;
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
		this.integration.logWarning(
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
		return;
	}

	this.addRequiredMetadata( eventData, streamName );

	if ( this.samplingController.isStreamInSample( streamConfig ) ) {
		this.eventSubmitter.submitEvent( eventData );
	}
};

/**
 * Format the custom data so that it is compatible with the Metrics Platform Event schema.
 *
 * `customData` is considered valid if all of its keys are snake_case.
 *
 * @ignore
 *
 * @param {Map<string,any>|undefined} customData
 * @return {FormattedCustomData}
 * @throws {Error} If `customData` is invalid
 */
function getFormattedCustomData( customData ) {
	/** @type {Map<string,MetricsPlatform.EventCustomDatum>} */
	const result = {};

	if ( !customData ) {
		return result;
	}

	for ( const key in customData ) {
		if ( !key.match( /^[$a-z]+[a-z0-9_]*$/ ) ) {
			throw new Error( 'The key "' + key + '" is not snake_case.' );
		}

		const value = customData[ key ];
		const type = value === null ? 'null' : typeof value;

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
 * @param {string} eventName
 * @param {Map<string, any>} [customData]
 * @deprecated
 */
MetricsClient.prototype.dispatch = function ( eventName, customData ) {
	const result = this.validateDispatchCall( eventName, customData );

	if ( result ) {
		this.processDispatchCall( new Date().toISOString(), eventName, result );
	}
};

/**
 * If `streamConfigs` is `false` or the custom data cannot be formatted with
 * {@link getFormattedCustomData}, then a warning is logged and `false` is returned. Otherwise, the
 * formatted custom data is returned.
 *
 * @param {string} eventName
 * @param {Map<string, any>} [customData]
 * @return {MetricsPlatform.FormattedCustomData|false}
 * @protected
 */
MetricsClient.prototype.validateDispatchCall = function ( eventName, customData ) {
	// T309083
	if ( this.streamConfigs === false ) {
		this.integration.logWarning(
			'dispatch( ' + eventName + ', customData ) cannot dispatch events when stream configs are disabled.'
		);

		return false;
	}

	try {
		return getFormattedCustomData( customData );
	} catch ( e ) {
		this.integration.logWarning(
			'dispatch( ' + eventName + ', customData ) called with invalid customData: ' + e.message +
			'No event(s) will be produced.'
		);

		return false;
	}
};

/**
 * Processes the result of a call to {@link MetricsClient.prototype.dispatch}.
 *
 * NOTE: This method should only be called **after** the stream configs have been fetched via
 * {@link MetricsPlatform.MetricsClient#fetchStreamConfigs}.
 *
 * @param {string} timestamp The ISO 8601 formatted timestamp of the original call
 * @param {string} eventName
 * @param {Map<string, any>} [formattedCustomData]
 * @protected
 */
MetricsClient.prototype.processDispatchCall = function (
	timestamp,
	eventName,
	formattedCustomData
) {
	const streamNames = this.getStreamNamesForEvent( eventName );

	// Produce the event(s)
	for ( let i = 0; i < streamNames.length; ++i ) {
		/* eslint-disable camelcase */
		/** @type {MetricsPlatform.EventData} */
		const eventData = {
			$schema: SCHEMA,
			dt: timestamp,
			name: eventName
		};

		if ( formattedCustomData ) {
			eventData.custom_data = formattedCustomData;
		}
		/* eslint-enable camelcase */

		const streamName = streamNames[ i ];
		const streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

		if ( !streamConfig ) {
			// NOTE: This SHOULD never happen.
			continue;
		}

		this.addRequiredMetadata( eventData, streamName );
		this.contextController.addRequestedValues( eventData, streamConfig );

		if (
			this.samplingController.isStreamInSample( streamConfig ) &&
			this.curationController.shouldProduceEvent( eventData, streamConfig )
		) {
			this.eventSubmitter.submitEvent( eventData );
		}
	}
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
		this.integration.logWarning(
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

const WEB_BASE_SCHEMA_ID = '/analytics/product_metrics/web/base/1.4.3';
const WEB_BASE_STREAM_NAME = 'product_metrics.web_base';

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

		instrumentName = streamOrInstrumentName;

		const streamConfig = getStreamConfigInternal( this.streamConfigs, instrumentName );
		const overrideStreamName =
			streamConfig &&
			streamConfig.producers &&
			streamConfig.producers.metrics_platform_client &&
			streamConfig.producers.metrics_platform_client.stream_name;

		streamName = overrideStreamName || WEB_BASE_STREAM_NAME;
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
