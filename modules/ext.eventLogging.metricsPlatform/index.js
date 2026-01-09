// TODO: Update types once metrics-platform has been upgraded to use JSDoc (see
// https://gitlab.wikimedia.org/repos/data-engineering/metrics-platform/-/merge_requests/73).

/**
 * @module ext.eventLogging.metricsPlatform
 */

const MediaWikiMetricsClientIntegration = require( './MediaWikiMetricsClientIntegration.js' );
const MediaWikiMetricsClientLogger = require( './MediaWikiMetricsClientLogger.js' );
const MetricsClient = require( '../lib/metrics-platform/MetricsClient.js' );
const DefaultEventSubmitter = require( '../lib/metrics-platform/DefaultEventSubmitter.js' );

let integration;
let logger;

/**
 * Creates a new `MetricsClient` instance with the given `EventSubmitter` implementation.
 *
 * Currently, the `MetricsClient` will use a singleton instance of
 * {@link module:ext.eventLogging.metricsPlatform.MediaWikiMetricsClientIntegration}.
 *
 * @param {Object|false} streamConfigs
 * @param {Object} eventSubmitter
 * @return {Object}
 */
function newMetricsClient( streamConfigs, eventSubmitter ) {
	if ( !integration ) {
		integration = new MediaWikiMetricsClientIntegration();
	}

	if ( !logger ) {
		logger = new MediaWikiMetricsClientLogger();
	}

	return new MetricsClient( integration, logger, streamConfigs, eventSubmitter );
}

module.exports = {
	DefaultEventSubmitter,
	newMetricsClient
};
