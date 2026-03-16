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
 * TODO T419481: Logging is being disabled temporarily while working on T419759
 *
 * @param {string} string
 */
// eslint-disable-next-line no-unused-vars
MediaWikiMetricsClientLogger.prototype.logWarning = function ( string ) {};

module.exports = MediaWikiMetricsClientLogger;
