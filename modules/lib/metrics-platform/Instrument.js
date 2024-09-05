// TypeScript can't parse the MetricsClient type without explicitly importing it. On the other
// hand, JSDoc can. Prioritise documentation over type checking for now.
// @ts-nocheck

/**
 * Provides a convenient API for writing an instrument.
 *
 * This class provides the same methods as {@link MetricsClient} but bound to the stream
 * name/schema ID pair, allowing developers to minimize repetition.
 *
 * Further, each method will mix in a value for `funnel_event_sequence_position` to each submitted
 * event. The value increases by 1 after the event is submitted. For example, if an instrument
 * submits an `init` and a `click` event:
 *
 * ```
 * // i is an instance of Instrument
 * i.submitInteraciton( 'init' );
 * i.submitClick( '#button', 'my_button' );
 * ```
 *
 * then the events will look like `action="init",funnel_event_sequence_postion=0` and
 * `action="click",funnel_event_sequence_position=1`.
 *
 * Note well that this class is not expected to be constructed directly. Instead, developers should
 * use {@link MetricsClient#newInstrument}, e.g.
 *
 * ```
 * const m = require( '/path/to/metrics-platform' ).createMetricsClient();
 * const i = m.newInstrument( STREAM_NAME, SCHEMA_ID );
 *
 * // ...
 *
 * i.submitInteraction( 'init' );
 * ```
 *
 * @param {MetricsClient} metricsClient
 * @param {string} streamName
 * @param {string} schemaID
 * @constructor
 */
function Instrument( metricsClient, streamName, schemaID ) {
	this.metricsClient = metricsClient;
	this.streamName = streamName;
	this.schemaID = schemaID;
	this.eventSequencePosition = 1;
}

/**
 * See {@link MetricsClient#isStreamInSample}.
 *
 * @return {boolean}
 */
Instrument.prototype.isStreamInSample = function () {
	return this.metricsClient.isStreamInSample( this.streamName );
};

/**
 * @return {boolean}
 */
Instrument.prototype.isEnabled = function () {
	return this.isStreamInSample();
};

/**
 * See {@link MetricsClient#submitInteraction}.
 *
 * @param {string} action
 * @param {InteractionContextData} [interactionData]
 */
Instrument.prototype.submitInteraction = function ( action, interactionData ) {
	interactionData = Object.assign(
		{},
		interactionData || {},
		{
			// eslint-disable-next-line camelcase
			funnel_event_sequence_position: this.eventSequencePosition++
		}
	);

	this.metricsClient.submitInteraction(
		this.streamName,
		this.schemaID,
		action,
		interactionData
	);
};

/**
 * See {@link MetricsClient#submitClick}.
 *
 * @param {ElementInteractionData} interactionData
 */
Instrument.prototype.submitClick = function ( interactionData ) {

	// eslint-disable-next-line camelcase
	interactionData.funnel_event_sequence_position = this.eventSequencePosition++;

	this.metricsClient.submitClick( this.streamName, interactionData );
};

module.exports = Instrument;
