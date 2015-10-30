( function ( mw, $ ) {
	'use strict';

	// 2^32 - 1
	var MAX_INT32_UNSIGNED = 4294967295;

	/**
	 * The class allows inheriting classes to log events based on a sampling
	 * rate if sampling is enabled.
	 *
	 * How to use:
	 *
	 *     var mySchema = new mw.eventLog.Schema( 'Name', 0.01, { skin: 'minerva' } );
	 *     // Log the following event at the default sampling rate of 0.01.
	 *     mySchema.log( { 'action': 'viewed' } );
	 *     // Log the following event at the sampling rate of 0.2.
	 *     mySchema.log( { 'action': 'clicked' }, 0.2 );
	 *
	 * @class mw.eventLog.Schema
	 * @constructor
	 * @param {string} name Schema name to log to.
	 * @param {number} samplingRate The rate at which sampling is performed.
	 *  The values are between 0 and 1 inclusive.
	 * @param {Object} [defaults] A set of defaults to log to the schema. Once
	 *  these defaults are set the values will be logged along with any additional
	 *  fields that are passed to the log method.
	 */
	function Schema( name, samplingRate, defaults ) {
		var randomNumber;

		if ( !name ) {
			throw new Error( 'name is required' );
		}
		// Theoretically samplingRate can be 0
		if ( samplingRate === undefined ) {
			throw new Error( 'samplingRate is required' );
		}

		this.name = name;
		this.samplingRate = samplingRate;
		this.defaults = defaults || {};

		// Get the first 32-bit integer from the random session ID and
		// scale it down to [0, 1] range.
		randomNumber = parseInt(
				mw.user.generateRandomSessionId().slice( 0, 8 ),
				16
			) / MAX_INT32_UNSIGNED;

		/**
		 * Random number that is used for determining whether the user is in the sample
		 *
		 * @return {number} number Between 0 and 1 inclusive
		 */
		this.getRandomNumber = function () {
			return randomNumber;
		};
	}

	/**
	 * Whether the user is bucketed. Returns true the randomly generated number
	 * during initialization is smaller than the samplingRate.
	 *
	 * @param {number} samplingRate Number between 0 and 1 inclusive
	 * @return {boolean}
	 */
	Schema.prototype.isUserInBucket = function ( samplingRate ) {
		return this.getRandomNumber() <= samplingRate;
	};

	/**
	 * Log an event via the EventLogging subscriber. If the schema uses different
	 * sampling rates for different events, samplingRate argument can also be
	 * passed which will determine whether to log the event. Otherwise,
	 * the prototype samplingRate will be used.
	 *
	 * @param {Object} data Data to log
	 * @param {number} [samplingRate] number between 0 and 1.
	 *  If not passed this.samplingRate will be used.
	 */
	Schema.prototype.log = function ( data, samplingRate ) {
		var self = this;

		samplingRate = ( samplingRate !== undefined ) ? samplingRate : this.samplingRate;

		if ( this.isUserInBucket( samplingRate ) ) {
			mw.loader.using( [ 'ext.eventLogging', 'schema.' + this.name ], function () {
				mw.eventLog.logEvent( self.name, $.extend( {}, self.defaults, data ) );
			} );
		}
	};

	mw.eventLog.Schema = Schema;

}( mediaWiki, jQuery ) );
