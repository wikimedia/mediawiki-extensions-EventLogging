/* eslint-env qunit */
( function () {
	'use strict';

	QUnit.module( 'ext.eventLogging', QUnit.newMwEnvironment( {
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

	QUnit.test( 'eventInSample', function ( assert ) {
		assert.strictEqual( mw.eventLog.eventInSample( 0 ), false );
		assert.strictEqual( mw.eventLog.eventInSample( 1 ), true );

		// Test the rest using randomTokenMatch() since we don't
		// want consistency in this case
	} );

	QUnit.test( 'sessionInSample', function ( assert ) {
		var mockRandomSession = function () {
			var n;
			// we know this is a multiple of 10
			n = 1000000000;
			return n.toString( 16 );
		};
		this.sandbox.stub( mw.user, 'sessionId', mockRandomSession );

		assert.strictEqual( mw.eventLog.sessionInSample( 1 ), true );
		assert.strictEqual( mw.eventLog.sessionInSample( 7 ), false );
	} );

	QUnit.test( 'randomTokenMatch', function ( assert ) {
		var n = 1000000, m = 1000001;

		assert.strictEqual( mw.eventLog.randomTokenMatch( 10, n.toString( 16 ) ), true );
		assert.strictEqual( mw.eventLog.randomTokenMatch( 10, m.toString( 16 ) ), false );
	} );

	QUnit.test( 'logEvent', function ( assert ) {
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
		'URL size is ok': {
			size: mw.eventLog.maxUrlSize,
			expected: undefined
		},
		'URL size is not ok': {
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

	QUnit.test( 'logTooLongEvent', function ( assert ) {
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

	QUnit.test( 'BackgroundQueue', function ( assert ) {
		var q = new mw.eventLog.BackgroundQueue( 1 / 1000 ),
			done = assert.async();
		q.add( function () {
			assert.strictEqual( q.getTimer(), null );
			assert.strictEqual( q.getCallbacks().length, 0 );
			done();
		} );
		assert.strictEqual( typeof q.getTimer(), 'number' );
		assert.strictEqual( q.getCallbacks().length, 1 );
	} );

}() );
