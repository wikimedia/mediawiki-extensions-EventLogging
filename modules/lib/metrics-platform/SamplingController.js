var UINT32_MAX = 4294967295; // (2^32) - 1

/**
 * Evaluate events for presence in sample based on the stream configuration.
 *
 * @param {Integration} integration
 * @constructor
 */
function SamplingController( integration ) {
	this.integration = integration;
}

/**
 * Determine whether a stream is in or out of sample.
 *
 * @param {?StreamConfig} streamConfig stream configuration
 * @return {boolean} true if in-sample, false if out-sample.
 */
SamplingController.prototype.streamInSample = function ( streamConfig ) {
	if ( !streamConfig ) {
		// If a stream is not defined, it is not in sample.
		return false;
	}

	if ( !streamConfig.sample ) {
		// If the stream does not specify sampling, it is in-sample.
		return true;
	}

	if (
		( !streamConfig.sample.rate || !streamConfig.sample.unit ) ||
		( streamConfig.sample.rate < 0 || streamConfig.sample.rate > 1 )
	) {
		// If the stream does specify sampling, but it is malformed,
		// it is not in-sample.
		return false;
	}

	var id;
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
