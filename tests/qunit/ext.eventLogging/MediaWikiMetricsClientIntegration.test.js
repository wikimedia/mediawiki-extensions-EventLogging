'use strict';

QUnit.module( 'ext.eventLogging/MediaWikiMetricsClientIntegration - getCurrentUserExperiments', {
	beforeEach: function () {
		this.originalUserExperiments = mw.config.get( 'wgMetricsPlatformUserExperiments' );
		this.integration = new mw.eventLog.MediaWikiMetricsClientIntegration();

		this.sandbox.stub( mw.user, 'isNamed' ).returns( true );
	},
	afterEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', this.originalUserExperiments );
	}
} );

// Test cases for getCurrentUserExperiments
QUnit.test( '#getCurrentUserExperiments() - null $wgMetricsPlatformUserExperiments', function ( assert ) {
	mw.config.set( 'wgMetricsPlatformUserExperiments', null );

	assert.deepEqual(
		this.integration.getCurrentUserExperiments(),
		{
			experiments: {
				enrolled: [],
				assigned: {}
			}
		},
		'Returns an empty enrolled and assigned object when wgMetricsPlatformUserExperiments is null'
	);
} );

QUnit.test( '#getCurrentUserExperiments() - invalid $wgMetricsPlatformUserExperiments', function ( assert ) {
	mw.config.set( 'wgMetricsPlatformUserExperiments', 'Hello, World!' );

	assert.deepEqual(
		this.integration.getCurrentUserExperiments(),
		{
			experiments: {
				enrolled: [],
				assigned: {}
			}
		},
		'Returns an empty enrolled and assigned object when wgMetricsPlatformUserExperiments is invalid'
	);
} );

QUnit.test( '#getCurrentUserExperiments()', function ( assert ) {
	mw.config.set( 'wgMetricsPlatformUserExperiments', {
		assigned: { bar: 'baz', oof: 'boo' },
		enrolled: [ 'foo' ]
	} );

	assert.deepEqual(
		this.integration.getCurrentUserExperiments(),
		{
			experiments: {
				enrolled: [ 'foo' ],
				assigned: {
					bar: 'baz',
					oof: 'boo'
				}
			}
		},
		'Returns correct enrollment and assignment for valid experiments'
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
		},
		'Returns an empty enrolled and assigned object when the user is not logged in'
	);
} );

// Test cases for isCurrentUserEnrolled
QUnit.module( 'ext.eventLogging/MediaWikiMetricsClientIntegration - isCurrentUserEnrolled', {
	beforeEach: function () {
		this.originalUserExperiments = mw.config.get( 'wgMetricsPlatformUserExperiments' );
		this.integration = new mw.eventLog.MediaWikiMetricsClientIntegration();

		this.sandbox.stub( mw.user, 'isNamed' ).returns( true );
		this.sandbox.stub( this.integration, 'getCurrentUserExperiments' );
	},
	afterEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', this.originalUserExperiments );
	}
} );

QUnit.test( 'isCurrentUserEnrolled returns false when user is not logged in', function ( assert ) {
	mw.user.isNamed.returns( false );

	const result = this.integration.isCurrentUserEnrolled( 'foo' );

	assert.strictEqual( result, false, 'Returns false when the user is not logged in' );
} );

QUnit.test( 'isCurrentUserEnrolled returns false when user is not enrolled in the experiment', function ( assert ) {
	mw.user.isNamed.returns( true );

	this.integration.getCurrentUserExperiments.returns( {
		experiments: {
			enrolled: [],
			assigned: {}
		}
	} );

	const result = this.integration.isCurrentUserEnrolled( 'foo' );

	assert.strictEqual( result, false, 'Returns false when the user is not enrolled in the experiment' );
} );

QUnit.test( 'isCurrentUserEnrolled returns true when user is enrolled in the experiment', function ( assert ) {
	mw.user.isNamed.returns( true );

	this.integration.getCurrentUserExperiments.returns( {
		experiments: {
			enrolled: [ 'foo' ],
			assigned: {
				foo: true
			}
		}
	} );

	const result = this.integration.isCurrentUserEnrolled( 'foo' );

	assert.strictEqual( result, true, 'Returns true when the user is enrolled in the experiment' );

	this.integration.getCurrentUserExperiments.restore();
} );
