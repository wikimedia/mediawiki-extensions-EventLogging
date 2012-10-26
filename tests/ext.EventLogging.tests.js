/*global assert*/

( function ( mw ) {
	'use strict';

	var earthquakeModel = {
		epicenter: {
			type: 'string',
			'enum': [ 'Valdivia', 'Sumatra', 'Kamchatka' ]
		},
		magnitude: {
			type: 'number'
		},
		article: {
			type: 'string',
			optional: true
		}
	};


	QUnit.module( 'ext.EventLogging', QUnit.newMwEnvironment( {
		setup: function () {
			mw.eventLog.declareModel( 'earthquake', earthquakeModel, true );
		}
	} ) );


	QUnit.test( 'Configuration', function () {
		assert.ok( mw.config.exists( 'wgEventLoggingBaseUri' ), 'Global config var "wgEventLoggingBaseUri" exists' );
	} );


	QUnit.test( 'getModel', function () {
		assert.equal( mw.eventLog.getModel( 'earthquake' ), earthquakeModel, 'Retrieves model if exists' );
		assert.equal( mw.eventLog.getModel( 'foo' ), null, 'Returns null for missing models' );
	} );


	QUnit.test( 'declareModel', function () {
		var newModel = {
			richter: { type: 'number' }
		};
		assert.throws( function () {
			mw.eventLog.declareModel( 'earthquake', newModel );
		}, /overwrite/, 'Does not clobber existing models' );
		assert.equal( mw.eventLog.declareModel( 'earthquake', newModel, true ), newModel, 'Clobbers when explicitly asked' );
	} );


	QUnit.test( 'isInstance', function () {

		// Numbers
		assert.ok( mw.eventLog.isInstance( 42, 'number' ), '42 is a number' );
		assert.ok( !mw.eventLog.isInstance( '42', 'number' ), '"42" is not a number' );

		// Booleans
		assert.ok( mw.eventLog.isInstance( true, 'boolean' ), 'true is a boolean' );
		assert.ok( !mw.eventLog.isInstance( 1, 'boolean' ), '1 is not a boolean' );

		// Strings
		assert.ok( mw.eventLog.isInstance( 'hello', 'string' ), '"hello" is a string' );
		assert.ok( !mw.eventLog.isInstance( true, 'string' ), 'true is not a string' );

		// Timestamps
		assert.ok( mw.eventLog.isInstance( new Date(), 'timestamp' ), 'Date objects are timestamps' );
		assert.ok( mw.eventLog.isInstance( 1351122187606, 'timestamp' ), '1351122187606 can be a timestamp' );
		assert.ok( !mw.eventLog.isInstance( -1, 'timestamp' ), '-1 is not a timestamp' );

	} );


	QUnit.test( 'assertValid', function () {
		assert.ok( mw.eventLog.assertValid( {
			epicenter: 'Valdivia',
			magnitude: 9.5
		}, 'earthquake' ), 'Optional fields may be omitted' );

		assert.throws( function () {
			mw.eventLog.assertValid( {
				epicenter: 'Valdivia',
				article: '[[1960 Valdivia earthquake]]'
			}, 'earthquake' );
		}, /Missing/, 'Required fields must be present.' );

		assert.throws( function () {
			mw.eventLog.assertValid( {
				epicenter: 'Valdivia',
				magnitude: '9.5'
			}, 'earthquake' );
		}, /Wrong/, 'Values must be instances of declared type' );

		assert.throws( function () {
			mw.eventLog.assertValid( {
				epicenter: 'Valdivia',
				magnitude: 9.5,
				depth: 33
			}, 'earthquake' );
		}, /Unrecognized/, 'Unrecognized fields fail validation' );

		assert.throws( function () {
			mw.eventLog.assertValid( {
				epicenter: 'Tōhoku',
				magnitude: 9.0
			}, 'earthquake' );
		}, /enum/, 'Enum fields constrain possible values' );
	} );


	QUnit.test( 'logEvent', function () {
		assert.throws( function () {
			mw.eventLog.logEvent( 'earthquake', {
				epicenter: 'Sumatra',
				magnitude: 9.5,
				article: new Array( 256 ).join('*')
			} );
		}, /Request URI/, 'URIs over 255 bytes are rejected' );

		var promise = mw.eventLog.logEvent( 'earthquake', {
			epicenter: 'Valdivia',
			magnitude: 9.5
		} );

		assert.ok( promise && typeof promise.then === 'function', 'logEvent() returns promise object' );
	} );

} ( mediaWiki ) );
