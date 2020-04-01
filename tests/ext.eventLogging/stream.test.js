/* eslint-env qunit */
'use strict';

QUnit.module( 'ext.eventLogging/stream' );

QUnit.test( 'submit() - warn for event without schema', function ( assert ) {
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

QUnit.test( 'submit() - produce an event correctly', function ( assert ) {
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

QUnit.test( 'streamConfig() - disallow modification', function ( assert ) {
	mw.eventLog.streamConfigs[ 'test.stream' ] = { field: 'expectedValue' };
	mw.eventLog.streamConfig( 'test.stream' ).field = 'otherValue';
	assert.equal( mw.eventLog.streamConfigs[ 'test.stream' ].field, 'expectedValue' );
} );
