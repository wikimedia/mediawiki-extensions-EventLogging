var copyAttributeByName = require( './ContextUtils.js' ).copyAttributeByName;

/**
 * Add context requested in stream configuration.
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
	var requestedValues = streamConfig &&
		streamConfig.producers &&
		streamConfig.producers.metrics_platform_client &&
		streamConfig.producers.metrics_platform_client.provide_values;

	if ( !Array.isArray( requestedValues ) ) {
		requestedValues = [];
	}

	var contextualAttributes = this.integration.getContextAttributes();

	requestedValues.concat( [
		'agent_client_platform',
		'agent_client_platform_family'
	] )
		.forEach( function ( requestedValue ) {
			copyAttributeByName( contextualAttributes, eventData, requestedValue );
		} );

	return eventData;
};

module.exports = ContextController;
