var c = mw.config.get.bind( mw.config );

// Support both 1 or "1" (T54542)
var isDebugMode = Number( mw.user.options.get( 'eventlogging-display-web' ) ) === 1 ||
	Number( mw.user.options.get( 'eventlogging-display-console' ) ) === 1;

// Module-local cache for the result of MediaWikiMetricsClientIntegration::getContextAttributes().
// Since the result of ::getContextAttributes() does not vary by instance, it is safe to cache the
// result at this level.
var contextAttributes = null;

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
			try {
				navigator.sendBeacon(
					serviceUri,
					JSON.stringify( eventData )
				);
			} catch ( e ) {
				// Ignore. See T86680, T273374, and T308311.
			}
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
 * Clones the object deeply.
 *
 * @param {Object} obj
 * @return {Object}
 */
MediaWikiMetricsClientIntegration.prototype.clone = function ( obj ) {
	return $.extend( true, {}, obj );
};

/**
 * Gets the values for those context attributes that are available in the execution
 * environment.
 *
 * @return {Object}
 */
MediaWikiMetricsClientIntegration.prototype.getContextAttributes = function () {
	if ( contextAttributes ) {
		return contextAttributes;
	}

	// TODO: Replace this with whatever config variable is decided on in
	//  https://phabricator.wikimedia.org/T299772.
	//
	// This used to be determined by checking whether <body> had the "mw-mf" class. However, this
	// was determined to be a non-trivial read from the DOM and one that could cause a forced style
	// recalculation in certain situations.
	//
	// See https://gerrit.wikimedia.org/r/c/mediawiki/extensions/WikimediaEvents/+/799353/1#message-21b63aebf69dc330933ef27deb11279b226656b8
	// for a detailed explanation.
	var isMobileFrontendActive = c( 'wgMFMode' ) !== null;

	var version = String( c( 'wgVersion' ) );

	var userGroups = c( 'wgUserGroups' );

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
			database: c( 'wgDBname' ),
			site_content_language: c( 'wgContentLanguage' )
		},
		performer: {
			is_logged_in: !mw.user.isAnon(),
			id: mw.user.getId(),
			name: mw.user.getName(),

			// NOTE: This method is expected to execute synchronously. mw.user.getGroups returns a
			// promise (jQuery.Promise) so get the information from the global config instead.
			groups: userGroups,

			// NOTE: As above, this method is expected to execute synchronously. We should test
			// whether the user has the "bot" right but mw.user.getRights() returns a promise
			// (jQuery.Promise). Fortunately, the "bot" group, which grants users the "bot" right,
			// is a default MediaWiki user group [0].
			//
			// [0] https://www.mediawiki.org/wiki/Help:User_rights_and_groups#User_rights_and_groups_on_your_wiki
			is_bot: userGroups.indexOf( 'bot' ) !== -1,

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

	contextAttributes = result;

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
