const isValidSample = require( './StreamConfig.js' ).isValidSample;

const UINT32_MAX = 4294967295; // (2^32) - 1

/**
 * Evaluate events for presence in sample based on the stream configuration.
 *
 * @param {MetricsPlatform.Integration} integration
 * @constructor
 * @memberof MetricsPlatform
 */
function SamplingController( integration ) {
	this.integration = integration;
}

/**
 * Determine whether a stream is in or out of sample.
 *
 * @param {?EventPlatform.StreamConfig} streamConfig
 * @return {boolean} true if in-sample, false if out-sample.
 */
SamplingController.prototype.isStreamInSample = function ( streamConfig ) {
	if ( !streamConfig ) {
		// If a stream is not defined, it is not in sample.
		return false;
	}

	if ( !streamConfig.sample ) {
		// If the stream does not specify sampling, it is in-sample.
		return true;
	}

	if ( !isValidSample( streamConfig.sample ) ) {
		return false;
	}

	let id;
	switch ( streamConfig.sample.unit ) {
		case 'pageview':
			id = this.integration.getPageviewId();
			break;
		case 'session':
			id = this.integration.getSessionId();
			break;
		default:
			return false;
	}

	return parseInt( id.slice( 0, 8 ), 16 ) / UINT32_MAX < streamConfig.sample.rate;
};

module.exports = SamplingController;
