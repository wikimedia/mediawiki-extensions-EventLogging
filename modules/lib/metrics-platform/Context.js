/**
 * @namespace MetricsPlatform.Context
 */

// Types
// =====

/**
 * @type {EventPlatform.StreamProducerContextAttribute[]}
 * @memberof MetricsPlatform.Context
 */
const VALID_ATTRIBUTE_NAMES = [
	'agent_client_platform',
	'agent_client_platform_family',
	'agent_ua_string',

	'page_id',
	'page_title',
	'page_namespace_id',
	'page_namespace_name',
	'page_revision_id',
	'page_wikidata_id',
	'page_wikidata_qid',
	'page_content_language',
	'page_is_redirect',
	'page_user_groups_allowed_to_move',
	'page_user_groups_allowed_to_edit',

	'mediawiki_skin',
	'mediawiki_version',
	'mediawiki_is_production',
	'mediawiki_is_debug_mode',
	'mediawiki_database',
	'mediawiki_site_content_language',
	'mediawiki_site_content_language_variant',

	'performer_is_logged_in',
	'performer_id',
	'performer_name',
	'performer_session_id',
	'performer_active_browsing_session_token',
	'performer_pageview_id',
	'performer_groups',
	'performer_is_bot',
	'performer_is_temp',
	'performer_language',
	'performer_language_variant',
	'performer_can_probably_edit_page',
	'performer_edit_count',
	'performer_edit_count_bucket',
	'performer_registration_dt'
];

/**
 * All the context attributes that can be provided by the JavaScript Metrics Platform Client.
 *
 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/Contextual_attributes
 *
 * @typedef {Object} ContextAttributes
 * @property {MetricsPlatform.Context.EventAgentData} agent
 * @property {MetricsPlatform.Context.EventPageData} [page]
 * @property {MetricsPlatform.Context.EventMediaWikiData} [mediawiki]
 * @property {MetricsPlatform.Context.EventPerformerData} [performer]
 * @property {EventPlatform.StreamSampleConfig} [sample]
 * @memberof MetricsPlatform.Context
 */

/**
 * @typedef {Object} EventAgentData
 * @property {string} [client_platform]
 * @property {string} [client_platform_family]
 * @memberof MetricsPlatform.Context
 */

/**
 * @typedef {Object} EventPageData
 * @property {number} [id]
 * @property {string} [title]
 * @property {number} [namespace_id]
 * @property {string} [namespace_name]
 * @property {number} [revision_id]
 * @property {number} [wikidata_id]
 * @property {string} [wikidata_qid]
 * @property {string} [content_language]
 * @property {boolean} [is_redirect]
 * @property {string[]} [user_groups_allowed_to_move]
 * @property {string[]} [user_groups_allowed_to_edit]
 * @memberof MetricsPlatform.Context
 */

/**
 * @typedef {Object} EventMediaWikiData
 * @property {string} [skin]
 * @property {string} [version]
 * @property {boolean} [is_production]
 * @property {boolean} [is_debug_mode]
 * @property {string} [database]
 * @property {string} [site_content_language]
 * @property {string} [site_content_language_variant]
 * @memberof MetricsPlatform.Context
 */

/**
 * @typedef {Object} EventPerformerData
 * @property {boolean} [is_logged_in]
 * @property {string} [id]
 * @property {string} [name]
 * @property {string} [session_id]
 * @property {string} [active_browsing_session_token]
 * @property {string} [pageview_id]
 * @property {string[]} [groups]
 * @property {boolean} [is_bot]
 * @property {boolean} [is_temp]
 * @property {string} [language]
 * @property {string} [language_variant]
 * @property {boolean} [can_probably_edit_page]
 * @property {number} [edit_count]
 * @property {string} [edit_count_bucket]
 * @property {string} [registration_dt]
 * @memberof MetricsPlatform.Context
 */

// Functions
// =========

/**
 * @param {MetricsPlatform.Context.ContextAttributes} from
 * @param {EventPlatform.StreamProducerContextAttribute} name
 * @return {any}
 * @memberof MetricsPlatform.Context
 */
function getAttributeByName( from, name ) {
	const index = name.indexOf( '_' );
	const primaryKey = name.slice( 0, index );

	if ( !from[ primaryKey ] ) {
		return null;
	}

	const secondaryKey = name.slice( index + 1 );
	const value = from[ primaryKey ][ secondaryKey ];

	return ( value === undefined || value === null ) ? null : value;
}

/**
 * @param {MetricsPlatform.Context.ContextAttributes} from
 * @param {MetricsPlatform.Context.ContextAttributes} to
 * @param {EventPlatform.StreamProducerContextAttribute} name
 * @memberof MetricsPlatform.Context
 */
function copyAttributeByName( from, to, name ) {
	const index = name.indexOf( '_' );
	const primaryKey = name.slice( 0, index );
	const secondaryKey = name.slice( index + 1 );

	const value = from[ primaryKey ] ? from[ primaryKey ][ secondaryKey ] : null;

	if ( value === undefined || value === null ) {
		return;
	}

	to[ primaryKey ] = to[ primaryKey ] || {};
	to[ primaryKey ][ secondaryKey ] = value;
}

/**
 * @param {MetricsPlatform.Context.ContextAttributes} from
 * @param {MetricsPlatform.Context.ContextAttributes} to
 * @memberof MetricsPlatform.Context
 */
function copyAttributes( from, to ) {
	VALID_ATTRIBUTE_NAMES.forEach( ( name ) => copyAttributeByName( from, to, name ) );
}

module.exports = {
	getAttributeByName: getAttributeByName,
	copyAttributeByName: copyAttributeByName,
	copyAttributes: copyAttributes
};
