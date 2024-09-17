'use strict';

QUnit.module( 'ext.eventLogging/MediaWikiMetricsClientIntegration', {
	beforeEach: function () {
		this.originalUserExperiments = mw.config.get( 'wgMetricsPlatformUserExperiments' );
		this.integration = new mw.eventLog.MediaWikiMetricsClientIntegration();

		this.sandbox.stub( mw.user, 'isNamed' ).returns( true );
	},
	afterEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', this.originalUserExperiments );
	}
} );

QUnit.test(
	'#getCurrentUserExperiments() - null $wgMetricsPlatformUserExperiments',
	function ( assert ) {
		mw.config.set( 'wgMetricsPlatformUserExperiments', null );

		assert.deepEqual(
			this.integration.getCurrentUserExperiments(),
			{
				experiments: {
					enrolled: [],
					assigned: {}
				}
			}
		);
	}
);

QUnit.test(
	'#getCurrentUserExperiments() - invalid $wgMetricsPlatformUserExperiments',
	function ( assert ) {
		mw.config.set( 'wgMetricsPlatformUserExperiments', 'Hello, World!' );

		assert.deepEqual(
			this.integration.getCurrentUserExperiments(),
			{
				experiments: {
					enrolled: [],
					assigned: {}
				}
			}
		);
	}
);

QUnit.test( '#getCurrentUserExperiments()', function ( assert ) {
	mw.config.set( 'wgMetricsPlatformUserExperiments', {
		foo: 'bar:baz',
		qux: 'unsampled',
		quux: 'invalid'
	} );

	assert.deepEqual(
		this.integration.getCurrentUserExperiments(),
		{
			experiments: {
				enrolled: [ 'foo' ],
				assigned: {
					foo: 'baz'
				}
			}
		}
	);
} );

QUnit.test( '#getCurrentUserExperiments() - logged-out and temporary users', function ( assert ) {
	mw.config.set( 'wgMetricsPlatformUserExperiments', {
		foo: 'bar:baz',
		qux: 'unsampled',
		quux: 'invalid'
	} );

	mw.user.isNamed.returns( false );

	assert.deepEqual(
		this.integration.getCurrentUserExperiments(),
		{
			experiments: {
				enrolled: [],
				assigned: {}
			}
		}
	);
} );
