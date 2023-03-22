'use strict';

QUnit.module( 'ext.eventLogging/log', {
	beforeEach: function () {
		this.sandbox.stub( mw.log, 'warn', function () {} );
		this.sandbox.stub( mw.log, 'error', function () {} );

		this.originalOptions = mw.eventLog.setOptionsForTest( {
			baseUrl: '/dummy/',
			schemasInfo: {
				earthquake: 123,
				// eruption events will be prepared for POSTing to EventGate.
				eruption: '/analytics/legacy/eruption/1.0.0'
			},
			streamConfigs: false
		} );
	},
	afterEach: function () {
		mw.eventLog.setOptionsForTest( this.originalOptions );
	}
} );

QUnit.test( 'logEvent()', function ( assert ) {
	var eventData = {
		epicenter: 'Valdivia',
		magnitude: 9.5
	};

	return mw.eventLog.logEvent( 'earthquake', eventData ).then( function ( e ) {
		assert.deepEqual( e.event, eventData, 'logEvent promise resolves with event' );
		assert.strictEqual( e.revision, 123, 'logEvent gets the revision id from config' );
	} );
} );

QUnit.test( 'logEvent() via submit()', function ( assert ) {
	var eventData = {
		volcano: 'Nyiragongo',
		Explosivity: 1
	};

	return mw.eventLog.logEvent( 'eruption', eventData ).then( function ( e ) {
		var expectedEventData = {
			volcano: 'Nyiragongo',
			Explosivity: 1
		};

		assert.deepEqual(
			e.event,
			expectedEventData,
			'logEvent promise resolves with event prepared for EventGate'
		);

		assert.strictEqual(
			e.$schema,
			'/analytics/legacy/eruption/1.0.0',
			'logEvent builds the $schema url from revision in config'
		);

		assert.notStrictEqual( e.meta, undefined, 'meta field should be set' );
		assert.strictEqual( e.dt, undefined, 'dt should be unset' );
		assert.notStrictEqual( e.client_dt, undefined, 'client_dt should be set' );
		assert.strictEqual( e.meta.domain, e.webHost, 'meta.domain should match webHost field' );
		assert.strictEqual( e.revision, undefined, 'revision field should be unset' );
	} );
} );

QUnit.test.each( 'checkUrlSize()', {
	'URL size is ok': {
		size: mw.eventLog.maxUrlSize,
		expected: undefined
	},
	'URL size is not ok': {
		size: mw.eventLog.maxUrlSize + 1,
		expected: 'Url exceeds maximum length'
	}
}, function ( assert, data ) {
	var url = new Array( data.size + 1 ).join( 'x' );
	var result = mw.eventLog.checkUrlSize( 'earthquake', url );
	assert.deepEqual( result, data.expected );
} );

QUnit.test( 'logEvent() - reject large event data', function ( assert ) {
	var event = {
		epicenter: 'Valdivia',
		magnitude: 9.5,
		article: new Array( mw.eventLog.maxUrlSize + 1 ).join( 'x' )
	};

	mw.eventLog.logEvent( 'earthquake', event )
		.done( function () {
			assert.true( false, 'Expected an error' );
		} )
		.fail( function ( e, error ) {
			assert.deepEqual( error, 'Url exceeds maximum length',
				'logEvent promise resolves with error' );
		} )
		.always( assert.async() );
} );
