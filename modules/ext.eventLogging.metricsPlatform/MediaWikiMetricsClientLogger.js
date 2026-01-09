/**
 * @class
 * @classdesc Adapts the MediaWiki execution environment for the JavaScript Metrics Platform Client.
 * @constructor
 * See [Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform) on Wikitech.
 *
 * @memberof module:ext.eventLogging.metricsPlatform
 */
function MediaWikiMetricsClientLogger() {
}

/**
 * Logs the warning to whatever logging backend that the execution environment, e.g. the
 * console
 *
 * @param {string} string
 */
MediaWikiMetricsClientLogger.prototype.logWarning = function ( string ) {
	mw.log.warn( string );
};

module.exports = MediaWikiMetricsClientLogger;
