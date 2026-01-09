const c = mw.config.get.bind( mw.config );

// Module-local cache for the result of MediaWikiMetricsClientIntegration::getContextAttributes().
// Since the result of ::getContextAttributes() does not vary by instance, it is safe to cache the
// result at this level.
let contextAttributes = null;

/**
 * @class
 * @classdesc Adapts the MediaWiki execution environment for the JavaScript Metrics Platform Client.
 * @constructor
 * See [Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform) on Wikitech.
 *
 * @memberof module:ext.eventLogging.metricsPlatform
 */
function MediaWikiMetricsClientIntegration() {
}

/**
 * Gets the hostname of the current document
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
	const isMobileFrontendActive = c( 'wgMFMode' ) !== null;

	const version = String( c( 'wgVersion' ) );

	const userIsLoggedIn = !mw.user.isAnon();
	const userGroups = c( 'wgUserGroups' );

	/* eslint-disable camelcase */
	const result = {
		agent: {
			client_platform: 'mediawiki_js',
			client_platform_family: isMobileFrontendActive ? 'mobile_browser' : 'desktop_browser',
			ua_string: navigator.userAgent
		},
		page: {
			id: c( 'wgArticleId' ),
			title: c( 'wgTitle' ),
			namespace_id: c( 'wgNamespaceNumber' ),
			namespace_name: c( 'wgCanonicalNamespace' ),
			revision_id: c( 'wgRevisionId' ),

			// The wikidata_id (int) context attribute is deprecated in favor of wikidata_qid
			// (string). See T330459 and T332673 for detail.
			wikidata_qid: c( 'wgWikibaseItemId' ),

			content_language: c( 'wgPageContentLanguage' ),
			is_redirect: c( 'wgIsRedirect' ),
			user_groups_allowed_to_move: c( 'wgRestrictionMove' ),
			user_groups_allowed_to_edit: c( 'wgRestrictionEdit' )
		},
		mediawiki: {
			skin: c( 'skin' ),
			version: version,
			is_production: version.includes( 'wmf' ),
			is_debug_mode: c( 'debug' ),
			database: c( 'wgDBname' ),
			site_content_language: c( 'wgContentLanguage' )
		},
		performer: {
			is_logged_in: userIsLoggedIn,
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
			is_bot: userGroups.includes( 'bot' ),

			is_temp: c( 'wgUserIsTemp' ),
			language: c( 'wgUserLanguage' ),
			language_variant: c( 'wgUserVariant' ),
			can_probably_edit_page: c( 'wgIsProbablyEditable' )
		}
	};

	if ( userIsLoggedIn ) {
		result.performer.edit_count = c( 'wgUserEditCount' );
		result.performer.edit_count_bucket = c( 'wgUserEditCountBucket' );
		result.performer.registration_dt = new Date( c( 'wgUserRegistration' ) ).toISOString();
	}

	Object.defineProperty( result.performer, 'session_id', {
		get: function () {
			return mw.user.sessionId();
		}
	} );

	Object.defineProperty( result.performer, 'pageview_id', {
		get: function () {
			return mw.user.getPageviewToken();
		}
	} );

	Object.defineProperty( result.performer, 'active_browsing_session_token', {
		get: function () {
			return mw.eventLog.id.getSessionId();
		}
	} );

	contextAttributes = result;

	return result;
};

module.exports = MediaWikiMetricsClientIntegration;
