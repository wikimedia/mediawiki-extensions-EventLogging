const getAttributeByName = require( './ContextUtils.js' ).getAttributeByName;

/**
 * @constructor
 */
function CurationController() {}

/**
 * Whether the value is undefined or null.
 *
 * This provides a safe way to check for the existence of possibly-falsy values.
 *
 * @param {*} value
 * @return {boolean}
 */
CurationController.prototype.isEmpty = function ( value ) {
	return value === undefined || value === null;
};

/**
 * Apply filtering rules to a value.
 *
 * @param {*} value
 * @param {StreamProducerCurationConfig} rules
 * @return {boolean} true if the event passes filtering, false if not
 */
CurationController.prototype.applyRules = function ( value, rules ) {
	let operator;

	for ( operator in rules ) {
		let i;
		// @ts-ignore TS7053
		const operand = rules[ operator ];
		if ( operator === 'equals' && value !== operand ) {
			return false;
		} else if ( operator === 'not_equals' && value === operand ) {
			return false;
		} else if ( operator === 'greater_than' && value <= Number( operand ) ) {
			return false;
		} else if ( operator === 'less_than' && value >= Number( operand ) ) {
			return false;
		} else if ( operator === 'greater_than_or_equals' && value < Number( operand ) ) {
			return false;
		} else if ( operator === 'less_than_or_equals' && value > Number( operand ) ) {
			return false;
		} else if (
			operator === 'in' &&
			Array.isArray( operand ) &&
			operand.indexOf( value ) === -1
		) {
			return false;
		} else if (
			operator === 'not_in' &&
			Array.isArray( operand ) &&
			operand.indexOf( value ) > -1
		) {
			return false;
		} else if ( operator === 'contains' && value.indexOf( operand ) === -1 ) {
			return false;
		} else if ( operator === 'does_not_contain' && value.indexOf( operand ) > -1 ) {
			return false;
		} else if ( operator === 'contains_all' && Array.isArray( operand ) ) {
			for ( i = 0; i < operand.length; i++ ) {
				if ( value.indexOf( operand[ i ] ) === -1 ) {
					return false;
				}
			}
		} else if ( operator === 'contains_any' && Array.isArray( operand ) ) {
			let found;
			for ( i = 0; i < operand.length; i++ ) {
				if ( value.indexOf( operand[ i ] ) > -1 ) {
					found = true;
					break;
				}
			}
			if ( !found ) {
				return false;
			}
			found = false;
		}
	}
	return true;
};

/**
 * Apply any curation rules specified in the stream config to the event.
 *
 * Curation rules can be added to the 'metrics_platform_client.curation' property of any given
 * stream configuration. For example:
 *
 * ```
 * "very.cool.stream": {
 *   producers: {
 *     metrics_platform_client: {
 *       curation: {
 *         performer_is_logged_in: {
 *           equals: true
 *         },
 *         mediawiki_skin: {
 *           in: [ "Vector", "MinervaNeue" ]
 *         }
 *       }
 *     }
 *   }
 * }
 * ```
 *
 * The following rules are supported:
 *
 * ```
 * { equals: x }
 * { not_equals: x }
 * { less_than: x }
 * { greater_than: x }
 * { less_than_or_equals: x }
 * { greater_than_or_equals: x }
 * { in: [x, y, z] }
 * { not_in: [x, y, z] }
 * { contains: x }
 * { not_contains: x }
 * { contains_all: [x, y, z] }
 * { contains_any: [x, y, z] }
 * ```
 *
 * @param {MetricsPlatformEventData} eventData
 * @param {StreamConfig} streamConfig
 * @return {boolean} true if the event passes filtering, false if not
 * @throws {Error} If a malformed filter is found
 */
CurationController.prototype.shouldProduceEvent = function ( eventData, streamConfig ) {
	// eslint-disable camelcase
	const curationConfig = streamConfig &&
		streamConfig.producers &&
		streamConfig.producers.metrics_platform_client &&
		streamConfig.producers.metrics_platform_client.curation;
	// eslint-enable camelcase

	if ( !curationConfig || typeof curationConfig !== 'object' ) {
		return true;
	}

	/** @type {StreamProducerContextAttribute} */
	let property;

	for ( property in curationConfig ) {
		const value = getAttributeByName( eventData, property );
		const rules = curationConfig[ property ];

		if (
			this.isEmpty( value ) ||
			( rules && !this.applyRules( value, rules ) )
		) {
			return false;
		}
	}

	return true;
};

module.exports = CurationController;
