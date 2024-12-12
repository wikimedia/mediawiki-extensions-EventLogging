/**
 * @type {StreamProducerContextAttribute[]}
 * @see https://wikitech.wikimedia.org/wiki/Metrics_Platform/Contextual_attributes
 */
const VALID_ATTRIBUTE_NAMES = [
	'agent_client_platform',
	'agent_client_platform_family',

	'page_id',
	'page_title',
	'page_namespace',
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
 * @param {ContextAttributes} from
 * @param {StreamProducerContextAttribute} name
 * @return {*}
 */
function getAttributeByName( from, name ) {
	const index = name.indexOf( '_' );
	const primaryKey = name.slice( 0, index );

	// @ts-ignore TS7053
	if ( !from[ primaryKey ] ) {
		return null;
	}

	const secondaryKey = name.slice( index + 1 );

	// @ts-ignore TS7053
	const value = from[ primaryKey ][ secondaryKey ];

	return ( value === undefined || value === null ) ? null : value;
}

/**
 * @param {ContextAttributes} from
 * @param {ContextAttributes} to
 * @param {StreamProducerContextAttribute} name
 */
function copyAttributeByName( from, to, name ) {
	const index = name.indexOf( '_' );
	const primaryKey = name.slice( 0, index );
	const secondaryKey = name.slice( index + 1 );

	// @ts-ignore TS7053
	const value = from[ primaryKey ] ? from[ primaryKey ][ secondaryKey ] : null;

	if ( value === undefined || value === null ) {
		return;
	}

	// @ts-ignore TS7053
	to[ primaryKey ] = to[ primaryKey ] || {};
	// @ts-ignore TS7053
	to[ primaryKey ][ secondaryKey ] = value;
}

/**
 * @param {ContextAttributes} from
 * @param {ContextAttributes} to
 */
function copyAttributes( from, to ) {
	VALID_ATTRIBUTE_NAMES.forEach( ( name ) => copyAttributeByName( from, to, name ) );
}

module.exports = {
	getAttributeByName: getAttributeByName,
	copyAttributeByName: copyAttributeByName,
	copyAttributes: copyAttributes
};
