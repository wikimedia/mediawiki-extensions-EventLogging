const copyAttributeByName = require( './ContextUtils.js' ).copyAttributeByName;
const isValidSample = require( './StreamConfigUtils.js' ).isValidSample;

/**
 * Add context attributes requested in stream configuration.
 *
 * @param {Integration} integration
 * @constructor
 */
function ContextController( integration ) {
	this.integration = integration;
}

/**
 * Mix the context attributes requested in stream configuration into the given event data.
 *
 * @param {MetricsPlatformEventData} eventData
 * @param {StreamConfig} streamConfig
 * @return {MetricsPlatformEventData}
 */
ContextController.prototype.addRequestedValues = function ( eventData, streamConfig ) {
	let requestedValues = streamConfig &&
		streamConfig.producers &&
		streamConfig.producers.metrics_platform_client &&
		streamConfig.producers.metrics_platform_client.provide_values;

	if ( !Array.isArray( requestedValues ) ) {
		requestedValues = [];
	}

	const contextAttributes = this.integration.getContextAttributes();

	requestedValues.concat( [
		'agent_client_platform',
		'agent_client_platform_family'
	] )
		.forEach( function ( requestedValue ) {
			copyAttributeByName( contextAttributes, eventData, requestedValue );
		} );

	// Record sampling unit and rate. See https://phabricator.wikimedia.org/T310693 for more
	// detail.
	if ( streamConfig.sample && isValidSample( streamConfig.sample ) ) {
		eventData.sample = streamConfig.sample;
	}

	return eventData;
};

module.exports = ContextController;
