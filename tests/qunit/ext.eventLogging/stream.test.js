'use strict';

QUnit.module( 'ext.eventLogging/stream', {
	beforeEach: function () {
		this.clock = this.sandbox.useFakeTimers();
		this.originalOptions = mw.eventLog.setOptionsForTest( {} );
	},
	afterEach: function () {
		mw.eventLog.setOptionsForTest( this.originalOptions );
	}
} );

QUnit.test( 'submit() - warn for event without schema', function ( assert ) {
	var seen = [];
	this.sandbox.stub( mw.eventLog, 'enqueue' );
	this.sandbox.stub( mw.log, 'warn', function () {
		seen.push( 'warn' );
	} );

	mw.eventLog.setOptionsForTest( {
		streamConfigs: {
			'test.stream': { some: 'config' }
		}
	} );
	mw.eventLog.submit( 'test.stream', {} );
	assert.deepEqual( [ 'warn' ], seen );
	assert.strictEqual( mw.eventLog.enqueue.callCount, 0, 'enqueue() calls' );
} );

QUnit.test( 'submit() - produce an event correctly', function ( assert ) {
	var t1, jsonString, data;
	this.sandbox.stub( mw.eventLog, 'enqueue', function ( callback ) {
		// Stub BackgroundQueue, regardless of intervalSecs config.
		callback();
	} );
	this.sandbox.stub( mw.log, 'warn' );
	this.sandbox.stub( navigator, 'sendBeacon' );

	this.clock.tick( 1000 );
	t1 = new Date().toISOString();
	mw.eventLog.setOptionsForTest( {
		serviceUri: 'testUri',
		streamConfigs: {
			'test.stream': { some: 'config' }
		}
	} );
	mw.eventLog.submit( 'test.stream', { $schema: 'test/schema' } );
	this.clock.tick( 1000 );

	assert.strictEqual( mw.log.warn.callCount, 0, 'warn() calls' );
	assert.strictEqual( navigator.sendBeacon.callCount, 1, 'sendBeacon() calls' );
	jsonString = navigator.sendBeacon.args[ 0 ][ 1 ];
	data = JSON.parse( jsonString );
	assert.strictEqual( data.dt, t1, 'client-side dt is valid' );
	assert.strictEqual( data.meta.stream, 'test.stream', 'stream is valid' );
} );
