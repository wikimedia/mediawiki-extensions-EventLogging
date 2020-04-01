/* eslint-env qunit */
'use strict';

QUnit.module( 'ext.eventLogging/BackgroundQueue' );

QUnit.test( 'add()', function ( assert ) {
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
