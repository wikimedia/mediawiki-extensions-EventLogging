/* eslint-env qunit */
'use strict';

QUnit.module( 'ext.eventLogging/stream', {
	beforeEach: function () {
		this.clock = this.sandbox.useFakeTimers();
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

QUnit.test( 'streamConfig() - disallow modification', function ( assert ) {
	mw.eventLog.setOptionsForTest( {
		streamConfigs: {
			'test.stream': { field: 'expectedValue' }
		}
	} );
	mw.eventLog.streamConfig( 'test.stream' ).field = 'otherValue';
	assert.equal( mw.eventLog.streamConfig( 'test.stream' ).field, 'expectedValue' );
} );

QUnit.test( 'streamInSample() - perform correct determination', function ( assert ) {
	mw.eventLog.setOptionsForTest( {
		streamConfigs: {
			'test.stream': {
				some: 'config'
			},
			'test.virtually_disabled': {
				sampling: {
					rate: 0.0
				}
			},
			'test.mobile_app': {
				sampling: {
					identifier: 'device'
				}
			},
			'test.mobile_app.alt': {
				sampling: {
					identifier: 'device',
					rate: 1.0
				}
			}
		}
	} );
	assert.equal( mw.eventLog.streamInSample( 'test.stream' ), true );
	assert.equal( mw.eventLog.streamInSample( 'test.virtually_disabled' ), false );
	assert.equal( mw.eventLog.streamInSample( 'test.mobile_app' ), false );
	assert.equal( mw.eventLog.streamInSample( 'test.mobile_app.alt' ), false );
	assert.equal( mw.eventLog.streamInSample( 'test.nonexistent' ), false );
} );

QUnit.test( 'streamInSample() - determination is cached', function ( assert ) {
	var det1, det2, det3, det4;

	mw.eventLog.setOptionsForTest( {
		streamConfigs: {
			'test.stream1': {
				some: 'config'
			},
			'test.stream2': {
				sampling: {
					rate: 0.5,
					identifier: 'session'
				}
			},
			'test.stream3': {
				sampling: {
					rate: 0.5,
					identifier: 'pageview'
				}
			},
			'test.stream4': {
				sampling: {
					rate: 1.0
				}
			}
		}
	} );

	// Each stream's determination is lazy, cached first time it's requested.
	// Subsequent calls should be much faster since the cached determination is
	// supposed to be used.
	this.clock.tick( 1000 );
	det1 = mw.eventLog.streamInSample( 'test.stream1' );
	this.clock.tick( 1000 );
	det2 = mw.eventLog.streamInSample( 'test.stream2' );
	this.clock.tick( 1000 );
	det3 = mw.eventLog.streamInSample( 'test.stream3' );
	this.clock.tick( 1000 );
	det4 = mw.eventLog.streamInSample( 'test.stream4' );
	this.clock.tick( 1000 );
	assert.equal( mw.eventLog.streamInSample( 'test.stream1' ), det1 );
	this.clock.tick( 1000 );
	assert.equal( mw.eventLog.streamInSample( 'test.stream2' ), det2 );
	this.clock.tick( 1000 );
	assert.equal( mw.eventLog.streamInSample( 'test.stream3' ), det3 );
	this.clock.tick( 1000 );
	assert.equal( mw.eventLog.streamInSample( 'test.stream4' ), det4 );
	this.clock.tick( 1000 );
	assert.equal( det1, det4 );
} );
