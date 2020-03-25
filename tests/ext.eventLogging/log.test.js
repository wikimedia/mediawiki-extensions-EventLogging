/* eslint-env qunit */
'use strict';

QUnit.module( 'ext.eventLogging/log', QUnit.newMwEnvironment( {
	setup: function () {
		this.suppressWarnings();
		mw.eventLog.setOptionsForTest( {
			baseUrl: '#',
			schemaRevision: { earthquake: 123 }
		} );
	},
	teardown: function () {
		this.restoreWarnings();
	}
} ) );

QUnit.test( 'logEvent()', function ( assert ) {
	var event = {
		epicenter: 'Valdivia',
		magnitude: 9.5
	};

	return mw.eventLog.logEvent( 'earthquake', event ).then( function ( e ) {
		assert.deepEqual( e.event, event, 'logEvent promise resolves with event' );
		assert.equal( e.revision, 123, 'logEvent gets the revision id from config' );
	} );
} );

// eslint-disable-next-line no-jquery/no-each-util
$.each( {
	'checkUrlSize() - URL size is ok': {
		size: mw.eventLog.maxUrlSize,
		expected: undefined
	},
	'checkUrlSize() - URL size is not ok': {
		size: mw.eventLog.maxUrlSize + 1,
		expected: 'Url exceeds maximum length'
	}
}, function ( name, params ) {
	QUnit.test( name, function ( assert ) {
		var url = new Array( params.size + 1 ).join( 'x' ),
			result = mw.eventLog.checkUrlSize( 'earthquake', url );
		assert.deepEqual( result, params.expected, name );
	} );
} );

QUnit.test( 'logEvent() - reject large event data', function ( assert ) {
	var event = {
		epicenter: 'Valdivia',
		magnitude: 9.5,
		article: new Array( mw.eventLog.maxUrlSize + 1 ).join( 'x' )
	};

	mw.eventLog.logEvent( 'earthquake', event )
		.done( function () {
			assert.ok( false, 'Expected an error' );
		} )
		.fail( function ( e, error ) {
			assert.deepEqual( error, 'Url exceeds maximum length',
				'logEvent promise resolves with error' );
		} )
		.always( assert.async() );
} );
