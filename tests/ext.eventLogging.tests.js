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

	QUnit.test( 'warn when producing event without schema', function ( assert ) {
		var done = assert.async();
		this.sandbox.stub( mw.eventLog, 'enqueue', function () {
			assert.ok( false, 'enqueue should not be reached' );
		} );
		this.sandbox.stub( mw.log, 'warn', function () {
			done();
		} );
		assert.timeout( 100 );
		assert.expect( 0 );
		mw.eventLog.streamConfigs[ 'test.stream' ] = { some: 'config' };
		mw.eventLog.submit( 'test.stream', {} );
	} );

	QUnit.test( 'produce event correctly', function ( assert ) {
		var t0 = new Date().toISOString(),
			done = assert.async();
		this.sandbox.stub( mw.eventLog, 'enqueue', function ( callback ) {
			// Ensure callback is called right away, regardless of BackgroundQueue config.
			callback();
		} );
		this.sandbox.stub( mw.log, 'warn', function () {
			assert.ok( false, 'log warn should not be reached' );
		} );
		this.sandbox.stub( navigator, 'sendBeacon', function ( uri, jsonString ) {
			var t1 = new Date().toISOString(),
				data = JSON.parse( jsonString );
			assert.ok( data.meta.dt >= t0 && data.meta.dt <= t1, 'dt is valid' );
			assert.equal( data.meta.stream, 'test.stream', 'stream is valid' );
			done();
		} );
		mw.eventLog.setOptionsForTest( { serviceUri: 'testUri' } );
		mw.eventLog.streamConfigs[ 'test.stream' ] = { some: 'config' };
		mw.eventLog.submit( 'test.stream', { $schema: 'test/schema' } );
	} );

	QUnit.test( 'not allow to modify stream configs', function ( assert ) {
		mw.eventLog.streamConfigs[ 'test.stream' ] = { field: 'expectedValue' };
		mw.eventLog.streamConfig( 'test.stream' ).field = 'otherValue';
		assert.equal( mw.eventLog.streamConfigs[ 'test.stream' ].field, 'expectedValue' );
	} );

}() );
