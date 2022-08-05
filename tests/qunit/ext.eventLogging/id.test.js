'use strict';

QUnit.module( 'ext.eventLogging/id', {
	beforeEach: function () {
		var jar = {};

		this.originalCookie = mw.cookie;

		mw.cookie = {
			get: function ( key ) {
				// By default, mw.cookie.get returns null.
				return jar[ key ] || null;
			},
			set: function ( key, value ) {
				jar[ key ] = value;
			}
		};
	},
	afterEach: function () {
		mw.cookie = this.originalCookie;
	}
} );

QUnit.test( 'pageview', function ( assert ) {
	var id1 = mw.eventLog.id.getPageviewId();

	assert.strictEqual( id1, mw.eventLog.id.getPageviewId(), 'The first pageview ID has been memoized.' );

	mw.eventLog.id.resetPageviewId();

	var id2 = mw.eventLog.id.getPageviewId();

	assert.notStrictEqual( id1, id2, 'The first pageview ID has been reset and a second one generated.' );
	assert.strictEqual( id2, mw.eventLog.id.getPageviewId(), 'The second pageview ID has been memoized.' );
} );

QUnit.test( 'session', function ( assert ) {
	var id1 = mw.eventLog.id.getSessionId();

	assert.strictEqual( id1, mw.eventLog.id.getSessionId() );
	assert.strictEqual( id1, mw.eventLog.storage.get( 'sessionId' ), 'The first session ID has been stored.' );

	mw.eventLog.id.resetSessionId();

	assert.strictEqual(
		mw.eventLog.storage.get( 'sessionId' ),
		null,
		'The first session ID has been removed from the store.'
	);

	var id2 = mw.eventLog.id.getSessionId();

	assert.notStrictEqual( id1, id2, 'The first session ID has been reset and a second one generated.' );
	assert.strictEqual( id2, mw.eventLog.id.getSessionId() );
	assert.strictEqual( id2, mw.eventLog.storage.get( 'sessionId' ), 'The second session ID has been persisted.' );

	mw.track( 'sessionReset' );

	assert.strictEqual(
		mw.eventLog.storage.get( 'sessionId' ),
		null,
		'The second session ID has been removed from the store.'
	);

	var id3 = mw.eventLog.id.getSessionId();

	assert.notStrictEqual( id2, id3 );
	assert.strictEqual( id3, mw.eventLog.id.getSessionId() );
	assert.strictEqual( id3, mw.eventLog.storage.get( 'sessionId' ), 'The third session ID has been stored.' );
} );
