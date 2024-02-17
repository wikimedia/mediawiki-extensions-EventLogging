const ContextController = require( './ContextController.js' );
const SamplingController = require( './SamplingController.js' );
const CurationController = require( './CurationController.js' );

const SCHEMA = '/analytics/mediawiki/client/metrics_event/2.1.0';

/**
 * Client for producing events to [the Event Platform](https://wikitech.wikimedia.org/wiki/Event_Platform) and
 * [the Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform).
 *
 * @ignore
 *
 * @param {Integration} integration
 * @param {StreamConfigs|false} streamConfigs
 * @constructor
 * @class MetricsClient
 */
function MetricsClient(
	integration,
	streamConfigs
) {
	this.contextController = new ContextController( integration );
	this.samplingController = new SamplingController( integration );
	this.curationController = new CurationController();
	this.integration = integration;
	this.streamConfigs = streamConfigs;
	this.eventNameToStreamNamesMap = null;
}

/**
 * @ignore
 *
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
 * @ignore
 *
 * @param {string} streamName
 * @return {StreamConfig|undefined}
 */
MetricsClient.prototype.getStreamConfig = function ( streamName ) {
	const streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

	return streamConfig ? this.integration.clone( streamConfig ) : streamConfig;
};

/**
 * @ignore
 *
 * @param {StreamConfigs} streamConfigs
 * @return {Record<string, string[]>}
 */
function getEventNameToStreamNamesMap( streamConfigs ) {
	/**
	 * @ignore
	 *
	 * @type Record<string, string[]>
	 */
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
 * @ignore
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
 * @param {string} streamName The name of the stream to send the event data to
 * @param {Object} eventData The event data
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
 * @ignore
 *
 * @param {string} streamName
 * @param {BaseEventData} eventData
 * @return {boolean}
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
 * @param {BaseEventData} eventData The event data
 */
MetricsClient.prototype.processSubmitCall = function ( timestamp, streamName, eventData ) {
	eventData.dt = timestamp;

	const streamConfig = getStreamConfigInternal( this.streamConfigs, streamName );

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
 * Format the custom data so that it is compatible with the Metrics Platform Event schema.
 *
 * `customData` is considered valid if all of its keys are snake_case.
 *
 * @ignore
 *
 * @param {Record<string,any>|undefined} customData
 * @return {FormattedCustomData}
 * @throws {Error} If `customData` is invalid
 */
function getFormattedCustomData( customData ) {
	/**
	 * @ignore
	 *
	 * @type {Record<string,EventCustomDatum>}
	 */
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
 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform
 *
 * @param {string} eventName
 * @param {Object.<string, any>} [customData]
 *
 * @unstable
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
 * @ignore
 *
 * @param {string} eventName
 * @param {Record<string, any>} [customData]
 * @return {FormattedCustomData|false}
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
			// @ts-ignore TS2571
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
	const streamNames = this.getStreamNamesForEvent( eventName );

	// Produce the event(s)
	for ( let i = 0; i < streamNames.length; ++i ) {
		/* eslint-disable camelcase */
		/**
		 * @ignore
		 *
		 * @type {MetricsPlatformEventData}
		 */
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
			this.samplingController.streamInSample( streamConfig ) &&
			this.curationController.shouldProduceEvent( eventData, streamConfig )
		) {
			this.integration.enqueueEvent( eventData );
			this.integration.onSubmit( streamName, eventData );
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
 * An interaction event (E) MUST validate against the
 * /analytics/product_metrics/web/base/1.0.0 schema. At the time of writing, this means that E
 * MUST have the `action` property and MAY have the following properties:
 *
 * `action_subtype`
 * `action_source`
 * `action_context`
 *
 * If E does not have the `action` property, then a warning is logged.
 *
 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/Implementations
 * @todo Should we create an API subpage?
 * @todo Link to the page created as part of https://phabricator.wikimedia.org/T345906
 *
 * @unstable
 *
 * @param {string} streamName
 * @param {string} schemaID
 * @param {InteractionAction} action
 * @param {InteractionContextData} [interactionData]
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

const CLICK_SCHEMA_ID = '/analytics/product_metrics/web/base/1.0.0';

/**
 * See `MetricsClient#submitInteraction()`.
 *
 * @unstable
 *
 * @param {string} streamName
 * @param {ElementInteractionData} interactionData
 */
MetricsClient.prototype.submitClick = function ( streamName, interactionData ) {
	this.submitInteraction( streamName, CLICK_SCHEMA_ID, 'click', interactionData );
};

module.exports = MetricsClient;
module.exports.SCHEMA = SCHEMA;
