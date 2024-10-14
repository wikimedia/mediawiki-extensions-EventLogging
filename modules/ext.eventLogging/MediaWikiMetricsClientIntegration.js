const c = mw.config.get.bind( mw.config );

// Module-local cache for the result of MediaWikiMetricsClientIntegration::getContextAttributes().
// Since the result of ::getContextAttributes() does not vary by instance, it is safe to cache the
// result at this level.
let contextAttributes = null;

/**
 * @classdesc Adapts the MediaWiki execution environment for the JavaScript Metrics Platform Client.
 *
 * See [Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform) on Wikitech.
 *
 * @class MediaWikiMetricsClientIntegration
 */
function MediaWikiMetricsClientIntegration() {}

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
	const isMobileFrontendActive = c( 'wgMFMode' ) !== null;

	const version = String( c( 'wgVersion' ) );

	const userIsLoggedIn = !mw.user.isAnon();
	const userGroups = c( 'wgUserGroups' );

	/* eslint-disable camelcase */
	const result = {
		agent: {
			client_platform: 'mediawiki_js',
			client_platform_family: isMobileFrontendActive ? 'mobile_browser' : 'desktop_browser'
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
			is_production: version.indexOf( 'wmf' ) !== -1,
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
			is_bot: userGroups.indexOf( 'bot' ) !== -1,

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
	/* eslint-enable camelcase */

	const self = this;

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

	Object.defineProperty( result.performer, 'active_browsing_session_token', {
		get: function () {
			return mw.eventLog.id.getSessionId();
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

MediaWikiMetricsClientIntegration.prototype.getCurrentUserExperiments = function () {
	const enrolled = [];
	const assigned = {};

	if ( !mw.user.isNamed() ) {
		return {
			experiments: {
				enrolled,
				assigned
			}
		};
	}

	const userExperiments = c( 'wgMetricsPlatformUserExperiments' );

	// Ensure userExperiments is defined and is an object
	if ( userExperiments && typeof userExperiments === 'object' ) {
		for ( const key in userExperiments ) {
			if ( Object.prototype.hasOwnProperty.call( userExperiments, key ) && userExperiments[ key ] !== 'unsampled' ) {
				// Only assign the value if it's not 'unsampled' and contains ':'
				const experimentData = userExperiments[ key ];

				if ( experimentData.indexOf( ':' ) !== -1 ) {
					enrolled.push( key );
					assigned[ key ] = experimentData.split( ':' )[ 1 ];
				}
			}
		}
	}

	return {
		experiments: {
			enrolled,
			assigned
		}
	};
};

MediaWikiMetricsClientIntegration.prototype.isCurrentUserEnrolled = function ( experimentName ) {
	// MetricsPlatform extension only works when the user is logged in
	// No enrollment to any experiment when the user is not
	if ( !mw.user.isNamed() ) {
		return false;
	}

	// Fetch the current user's experiments using the getCurrentUserExperiments method
	const currentUserExperiments = this.getCurrentUserExperiments();

	// Check if the user is enrolled in the experiment
	return currentUserExperiments.experiments.enrolled.indexOf( experimentName ) !== -1;
};
module.exports = MediaWikiMetricsClientIntegration;
