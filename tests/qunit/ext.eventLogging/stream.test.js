'use strict';

QUnit.module( 'ext.eventLogging/stream', {
	beforeEach: function () {
		this.clock = this.sandbox.useFakeTimers();
		this.originalOptions = mw.eventLog.setOptionsForTest( {
			baseUrl: '/dummy/',
			serviceUri: 'testUri',
			schemasInfo: {
				earthquake: 123,
				// eruption events will be prepared for POSTing to EventGate.
				eruption: '/analytics/legacy/eruption/1.0.0'
			},
			streamConfigs: {
				'test.stream': {},

				// eslint-disable-next-line camelcase
				eventlogging_eruption: {}
			}
		} );
	},
	afterEach: function () {
		mw.eventLog.getQueue().flush();
		mw.eventLog.setOptionsForTest( this.originalOptions );
	}
} );

QUnit.test( 'submit() - warn for event without schema', function ( assert ) {
	const seen = [];
	this.sandbox.stub( mw.eventLog, 'enqueue' );
	this.sandbox.stub( mw.log, 'warn', () => {
		seen.push( 'warn' );
	} );
	this.sandbox.stub( navigator, 'sendBeacon', () => {} );
	mw.eventLog.submit( 'test.stream', {} );
	assert.deepEqual( [ 'warn' ], seen );
	assert.strictEqual( mw.eventLog.enqueue.callCount, 0, 'enqueue() calls' );
} );

QUnit.test( 'submit() - produce an event correctly', function ( assert ) {
	this.sandbox.stub( mw.eventLog, 'enqueue', ( callback ) => {
		// Stub BackgroundQueue, regardless of intervalSecs config.
		callback();
	} );
	this.sandbox.stub( mw.log, 'warn' );
	this.sandbox.stub( navigator, 'sendBeacon' );

	this.clock.tick( 1000 );
	const t1 = new Date().toISOString();

	mw.eventLog.submit( 'test.stream', { $schema: 'test/schema' } );
	this.clock.tick( 1000 );

	assert.strictEqual( mw.log.warn.callCount, 0, 'warn() calls' );
	assert.strictEqual( navigator.sendBeacon.callCount, 1, 'sendBeacon() calls' );
	const jsonString = navigator.sendBeacon.args[ 0 ][ 1 ];
	const data = JSON.parse( jsonString );
	assert.strictEqual( data.dt, t1, 'client-side dt is valid' );
	assert.strictEqual( data.meta.stream, 'test.stream', 'stream is valid' );
} );
