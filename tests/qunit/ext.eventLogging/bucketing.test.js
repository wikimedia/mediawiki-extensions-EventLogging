'use strict';

QUnit.module( 'ext.eventLogging/bucketing' );

QUnit.test.each( 'getUserEditCountBucket()', {
	anonymous: {
		editCount: null,
		expected: null
	},
	0: {
		editCount: 0,
		expected: '0 edits'
	},
	3: {
		editCount: 3,
		expected: '1-4 edits'
	},
	99999: {
		editCount: 99999,
		expected: '1000+ edits'
	}
}, ( assert, params ) => {
	assert.strictEqual(
		mw.eventLog.getUserEditCountBucket( params.editCount ),
		params.expected
	);
} );
