'use strict';

var earthquakeModel = {
	epicenter: {
		type: 'string',
		enum: [ 'Valdivia', 'Sumatra', 'Kamchatka' ]
	},
	magnitude: {
		type: 'number'
	},
	article: {
		type: 'string',
		optional: true
	}
};


module( 'ext.EventLogging', QUnit.newMwEnvironment( {
	setup: function () {
		mw.eventLog.declareModel( 'earthquake', earthquakeModel, true );
	}
} ) );


test( 'Configuration', function () {
	ok( mw.config.exists( 'wgEventLoggingBaseUri' ), 'Global config var "wgEventLoggingBaseUri" exists' );
} );


test( 'getModel', function () {
	equal( mw.eventLog.getModel( 'earthquake' ), earthquakeModel, 'Retrieves model if exists' );
	equal( mw.eventLog.getModel( 'foo' ), null, 'Returns null for missing models' );
} );


test( 'declareModel', function () {
	var newModel = {
		richter: { type: 'number' }
	};
	raises( function () {
		mw.eventLog.declareModel( 'earthquake', newModel );
	}, /overwrite/, 'Does not clobber existing models' );
    equal( mw.eventLog.declareModel( 'earthquake', newModel, true ), newModel, 'Clobbers when explicitly asked' );
} );


test( 'isInstance', function () {

	// Numbers
	ok( mw.eventLog.isInstance( 42, 'number' ), '42 is a number' );
	ok( !mw.eventLog.isInstance( '42', 'number' ), '"42" is not a number' );

	// Booleans
	ok( mw.eventLog.isInstance( true, 'boolean' ), 'true is a boolean' );
	ok( !mw.eventLog.isInstance( 1, 'boolean' ), '1 is not a boolean' );

	// Strings
	ok( mw.eventLog.isInstance( 'hello', 'string' ), '"hello" is a string' );
	ok( !mw.eventLog.isInstance( true, 'string' ), 'true is not a string' );

	// Timestamps
	ok( mw.eventLog.isInstance( new Date(), 'timestamp' ), 'Date objects are timestamps' );
	ok( mw.eventLog.isInstance( 1351122187606, 'timestamp' ), '1351122187606 can be a timestamp' );
	ok( !mw.eventLog.isInstance( -1, 'timestamp' ), '-1 is not a timestamp' );

} );


test( 'assertValid', function () {
	ok( mw.eventLog.assertValid( {
		epicenter: 'Valdivia',
		magnitude: 9.5
	}, 'earthquake' ), 'Optional fields may be omitted' );

	raises( function () {
		mw.eventLog.assertValid( {
			epicenter: 'Valdivia',
			article: '[[1960 Valdivia earthquake]]'
		}, 'earthquake' );
	}, /Missing/, 'Required fields must be present.' );

	raises( function () {
		mw.eventLog.assertValid( {
			epicenter: 'Valdivia',
			magnitude: '9.5'
		}, 'earthquake' );
	}, /Wrong/, 'Values must be instances of declared type' );

	raises( function () {
		mw.eventLog.assertValid( {
			epicenter: 'Valdivia',
			magnitude: 9.5,
			depth: 33
		}, 'earthquake' );
	}, /Unrecognized/, 'Unrecognized fields fail validation' );

	raises( function () {
		mw.eventLog.assertValid( {
			epicenter: 'Tōhoku',
			magnitude: 9.0
		}, 'earthquake' );
	}, /enum/, 'Enum fields constrain possible values' );
} );


test( 'logEvent', function () {
	raises( function () {
		mw.eventLog.logEvent( 'earthquake', {
			epicenter: 'Sumatra',
			magnitude: 9.5,
			article: new Array( 256 ).join('*')
		} );
	}, /Request URI/, 'URIs over 255 bytes are rejected' );

	mw.config.set( 'wgEventLoggingBaseUri', '' );

	var promise = mw.eventLog.logEvent( 'earthquake', {
		epicenter: 'Valdivia',
		magnitude: 9.5
	} );

	ok( typeof promise.then === 'function', 'logEvent() returns promise object' );
} );
