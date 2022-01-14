/**
 * @param {ContextAttributes} from
 * @param {StreamProducerContextAttribute} name
 * @return {*}
 */
function getAttributeByName( from, name ) {
	var index = name.indexOf( '_' );
	var primaryKey = name.slice( 0, index );

	// @ts-ignore TS7053
	if ( !from[ primaryKey ] ) {
		return null;
	}

	var secondaryKey = name.slice( index + 1 );

	// @ts-ignore TS7053
	var value = from[ primaryKey ][ secondaryKey ];

	return ( value === undefined || value === null ) ? null : value;
}

/**
 * @param {ContextAttributes} from
 * @param {ContextAttributes} to
 * @param {StreamProducerContextAttribute} name
 */
function copyAttributeByName( from, to, name ) {
	var index = name.indexOf( '_' );
	var primaryKey = name.slice( 0, index );
	var secondaryKey = name.slice( index + 1 );

	// @ts-ignore TS7053
	var value = from[ primaryKey ] ? from[ primaryKey ][ secondaryKey ] : null;

	if ( value === undefined || value === null ) {
		return;
	}

	// @ts-ignore TS7053
	to[ primaryKey ] = to[ primaryKey ] || {};
	// @ts-ignore TS7053
	to[ primaryKey ][ secondaryKey ] = value;
}

module.exports = {
	copyAttributeByName: copyAttributeByName,
	getAttributeByName: getAttributeByName
};
