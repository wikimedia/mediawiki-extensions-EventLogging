var c = mw.config.get.bind( mw.config );

// Support both 1 or "1" (T54542)
var isDebugMode = Number( mw.user.options.get( 'eventlogging-display-web' ) ) === 1;

/**
 * Adapts the MediaWiki execution environment for the JavaScript Metrics Platform Client.
 *
 * See [Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform) on Wikitech.
 *
 * @param {Object} eventLog
 * @param {Object} eventLogConfig
 * @constructor
 */
function MediaWikiMetricsClientIntegration( eventLog, eventLogConfig ) {
	this.eventLog = eventLog;
	this.eventLogConfig = eventLogConfig;
}

/**
 * Enqueues the event to be submitted to the event ingestion service.
 *
 * @param {Object} eventData
 */
MediaWikiMetricsClientIntegration.prototype.enqueueEvent = function ( eventData ) {
	var serviceUri = this.eventLogConfig.serviceUri;

	if ( serviceUri ) {
		this.eventLog.enqueue( function () {
			navigator.sendBeacon(
				serviceUri,
				JSON.stringify( eventData )
			);
		} );
	}
};

/**
 * Called when an event is enqueued to be submitted to the event ingestion service.
 *
 * @param {string} streamName
 * @param {Object} eventData
 */
MediaWikiMetricsClientIntegration.prototype.onSubmit = function ( streamName, eventData ) {
	if ( isDebugMode ) {
		mw.track(
			'eventlogging.eventSubmitDebug',
			{ streamName: streamName, eventData: eventData }
		);
	}
};

/**
 * Gets the hostname of the current document.
 *
 * @param {string} string
 */
MediaWikiMetricsClientIntegration.prototype.logWarning = function ( string ) {
	mw.log.warn( string );
};

/**
 * Logs the warning to whatever logging backend that the execution environment, e.g. the
 * console.
 *
 * @return {string}
 */
MediaWikiMetricsClientIntegration.prototype.getHostname = function () {
	return String( c( 'wgServerName' ) );
};

/**
 * Clones the object.
 *
 * @param {Object} obj
 * @return {Object}
 */
MediaWikiMetricsClientIntegration.prototype.clone = function ( obj ) {
	return $.extend( {}, obj );
};

/**
 * Gets the values for those context attributes that are available in the execution
 * environment.
 *
 * @return {Object}
 */
MediaWikiMetricsClientIntegration.prototype.getContextAttributes = function () {

	// See https://phabricator.wikimedia.org/T299772
	var isMobileFrontendActive = document.body.classList.contains( 'mw-mf' );

	var version = String( c( 'wgVersion' ) );

	/* eslint-disable camelcase */
	var result = {
		agent: {
			client_platform: 'mediawiki_js',
			client_platform_family: isMobileFrontendActive ? 'mobile_browser' : 'desktop_browser'
		},
		page: {
			id: c( 'wgArticleId' ),
			title: c( 'wgTitle' ),
			namespace: c( 'wgNamespaceNumber' ),
			namespace_name: c( 'wgCanonicalNamespace' ),
			revision_id: c( 'wgRevisionId' ),
			wikidata_id: c( 'wgWikibaseItemId' ),
			content_language: c( 'wgPageContentLanguage' ),
			is_redirect: c( 'wgIsRedirect' ),
			user_groups_allowed_to_move: c( 'wgRestrictionMove' ),
			user_groups_allowed_to_edit: c( 'wgRestrictionEdit' )
		},
		mediawiki: {
			skin: c( 'skin' ),
			version: version,
			is_production: version.indexOf( 'wmf' ) !== -1,
			is_debug_mode: isDebugMode,
			db_name: c( 'wgDBname' ),
			site_content_language: c( 'wgContentLanguage' )
		},
		performer: {
			is_logged_in: !mw.user.isAnon(),
			id: mw.user.getId(),
			name: mw.user.getName(),

			// NOTE: This method is expected to execute synchronously. mw.user.getGroups returns a
			// promise (jQuery.Promise) so get the information from the global config instead.
			groups: c( 'wgUserGroups' ),

			// NOTE: As above, this method is expected to execute synchronously. We should test
			// whether the user has the "bot" right but mw.user.getRights() returns a promise
			// (jQuery.Promise). Fortunately, the "bot" group, which grants users the "bot" right,
			// is a default MediaWiki user group [0].
			//
			// [0] https://www.mediawiki.org/wiki/Help:User_rights_and_groups#User_rights_and_groups_on_your_wiki
			is_bot: this.getUserGroups().indexOf( 'bot' ) !== -1,

			language: c( 'wgUserLanguage' ),
			language_variant: c( 'wgUserVariant' ),
			can_probably_edit_page: c( 'wgIsProbablyEditable' ),
			edit_count: c( 'wgUserEditCount' ),
			edit_count_bucket: c( 'wgUserEditCountBucket' ),
			registration_dt: c( 'wgUserRegistration' )
		}
	};
	/* eslint-enable camelcase */

	var self = this;

	Object.defineProperty( result.performer, 'session_id', {
		get: function () {
			return self.getSessionId();
		}
	} );

	Object.defineProperty( result.performer, 'pageview_id', {
		get: function () {
			return self.getPageviewId();
		}
	} );

	return result;
};

// NOTE: The following are required for compatibility with the current impl. but the
// information is also available via ::getContextualAttributes() above.

/**
 * Gets a token unique to the current pageview within the execution environment.
 *
 * @return {string}
 */
MediaWikiMetricsClientIntegration.prototype.getPageviewId = function () {
	return mw.user.getPageviewToken();
};

/**
 * Gets a token unique to the current session within the execution environment.
 *
 * @return {string}
 */
MediaWikiMetricsClientIntegration.prototype.getSessionId = function () {
	return mw.user.sessionId();
};

module.exports = MediaWikiMetricsClientIntegration;
