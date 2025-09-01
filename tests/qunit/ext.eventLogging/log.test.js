'use strict';

QUnit.module( 'ext.eventLogging/log', QUnit.newMwEnvironment( {
	config: {
		// Used by MediaWikiMetricsClientIntegration#getHostname()
		wgServerName: 'example.test'
	},
	beforeEach: function () {
		// Used by MetricsClient#addRequiredMetadata()
		this.clock = this.sandbox.useFakeTimers( {
			now: 1301648400000,
			toFake: [ 'Date' ]
		} );
		this.sandbox.stub( navigator, 'sendBeacon', () => {} );
		this.sandbox.stub( mw.log, 'warn', () => {} );
		this.sandbox.stub( mw.log, 'error', () => {} );
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
} ) );

QUnit.test( 'logEvent()', ( assert ) => {
	const eventData = {
		epicenter: 'Valdivia',
		magnitude: 9.5
	};

	return mw.eventLog.logEvent( 'earthquake', eventData ).then( ( e ) => {
		assert.deepEqual( e.event, eventData, 'logEvent promise resolves with event' );
		assert.strictEqual( e.revision, 123, 'logEvent gets the revision id from config' );
	} );
} );

QUnit.test( 'logEvent() via submit()', ( assert ) => {
	const eventData = {
		volcano: 'Nyiragongo',
		Explosivity: 1
	};

	return mw.eventLog.logEvent( 'eruption', eventData ).then( ( e ) => {
		const expectedEventData = {
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

		assert.propEqual( e.meta, { domain: 'example.test', stream: 'eventlogging_eruption' }, 'meta' );
		assert.strictEqual( e.webHost, 'example.test', 'webHost' );
		assert.strictEqual( e.client_dt, '2011-04-01T09:00:00.000Z', 'client_dt' );
		assert.strictEqual( e.dt, undefined, 'no dt' );
		assert.strictEqual( e.revision, undefined, 'no revision' );
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
}, ( assert, data ) => {
	const url = new Array( data.size + 1 ).join( 'x' );
	const result = mw.eventLog.checkUrlSize( 'earthquake', url );
	assert.deepEqual( result, data.expected );
} );

QUnit.test( 'logEvent() - reject large event data', ( assert ) => {
	const event = {
		epicenter: 'Valdivia',
		magnitude: 9.5,
		article: new Array( mw.eventLog.maxUrlSize + 1 ).join( 'x' )
	};

	mw.eventLog.logEvent( 'earthquake', event )
		.then(
			() => {
				assert.true( false, 'Expected an error' );
			},
			( e, error ) => {
				assert.deepEqual( error, 'Url exceeds maximum length',
					'logEvent promise resolves with error' );
			}
		)
		.then( assert.async() );
} );
