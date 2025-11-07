// Types
// =====

/**
 * @namespace EventPlatform
 */

/**
 * @typedef {Object} StreamSampleConfig
 * @property {string} unit
 * @property {number} rate
 * @memberof EventPlatform
 */

/* eslint-disable camelcase,no-unused-vars */
/**
 * @enum {string}
 * @readonly
 * @memberof EventPlatform
 */
const StreamProducerContextAttribute = {

	// Agent
	agent_client_platform: 'agent_client_platform',
	agent_client_platform_family: 'agent_client_platform_family',
	agent_ua_string: 'agent_ua_string',

	// Page
	page_id: 'page_id',
	page_title: 'page_title',
	page_namespace_id: 'page_namespace_id',
	page_namespace_name: 'page_namespace_name',
	page_revision_id: 'page_revision_id',
	page_wikidata_id: 'page_wikidata_id',
	page_wikidata_qid: 'page_wikidata_id',
	page_content_language: 'page_content_language',
	page_is_redirect: 'page_is_redirect',
	page_user_groups_allowed_to_move: 'page_user_groups_allowed_to_move',
	page_user_groups_allowed_to_edit: 'page_user_groups_allowed_to_edit',

	// MediaWiki
	mediawiki_skin: 'mediawiki_skin',
	mediawiki_version: 'mediawiki_version',
	mediawiki_is_production: 'mediawiki_is_production',
	mediawiki_is_debug_mode: 'mediawiki_is_debug_mode',
	mediawiki_database: 'mediawiki_database',
	mediawiki_site_content_language: 'mediawiki_site_content_language',
	mediawiki_site_content_language_variant: 'mediawiki_site_content_language_variant',

	// Performer
	performer_is_logged_in: 'performer_is_logged_in',
	performer_id: 'performer_id',
	performer_name: 'performer_name',
	performer_session_id: 'performer_session_id',
	performer_active_browsing_session_token: 'performer_active_browsing_session_token',
	performer_pageview_id: 'performer_pageview_id',
	performer_groups: 'performer_groups',
	performer_is_bot: 'performer_is_bot',
	performer_is_temp: 'performer_is_temp',
	performer_language: 'performer_language',
	performer_language_variant: 'performer_language_variant',
	performer_can_probably_edit_page: 'performer_can_probably_edit_page',
	performer_edit_count: 'performer_edit_count',
	performer_edit_count_bucket: 'performer_edit_count_bucket',
	performer_registration_dt: 'performer_registration_dt'
};

/**
 * @typedef {Object} StreamProducerConfig
 * @property {string[]} [events]
 * @property {EventPlatform.StreamSampleConfig} [sampling]
 * @property {EventPlatform.StreamProducerContextAttribute[]} [provide_values]
 * @memberof EventPlatform
 */

/**
 * @typedef {Object} StreamConfig
 * @property {string} [schema_title]
 * @property {Map<string,EventPlatform.StreamProducerConfig>} [producers]
 * @property {EventPlatform.StreamSampleConfig} [sample]
 * @memberof EventPlatform
 */

/**
 * @typedef {Map<string,EventPlatform.StreamConfig>} StreamConfigs
 * @memberof EventPlatform
 */

// Functions
// =========

/**
 * @param {?EventPlatform.StreamSampleConfig} sample
 * @return {boolean}
 * @memberof EventPlatform
 */
function isValidSample( sample ) {
	return !!(
		sample &&
		sample.unit && sample.rate &&
		sample.rate >= 0 && sample.rate <= 1
	);
}

module.exports = {
	isValidSample: isValidSample
};
