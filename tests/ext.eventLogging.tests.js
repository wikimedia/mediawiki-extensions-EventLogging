/*global QUnit:false */
( function ( mw, $ ) {
	'use strict';

	var earthquakeSchema = {
			description: 'Record of a history earthquake',
			properties: {
				epicenter: {
					type: 'string',
					'enum': [ 'Valdivia', 'Sumatra', 'Kamchatka' ],
					required: true
				},
				magnitude: {
					type: 'number',
					required: true
				},
				article: {
					type: 'string'
				}
			}
		},

		validationCases = [
			{
				args: {
					epicenter: 'Valdivia',
					article: '[[1960 Valdivia earthquake]]'
				},
				regex: /Missing/,
				msg: 'Required fields must be present.'
			},
			{
				args: {
					epicenter: 'Valdivia',
					magnitude: '9.5'
				},
				regex: /Wrong/,
				msg: 'Values must be instances of declared type'
			},
			{
				args: {
					epicenter: 'Valdivia',
					magnitude: 9.5,
					depth: 33
				},
				regex: /Unrecognized/,
				msg: 'Unrecognized fields fail validation'
			},
			{
				args: {
					epicenter: 'Tōhoku',
					magnitude: 9.0
				},
				regex: /enum/,
				msg: 'Enum fields constrain possible values'
			}
			// TODO (2013-01-07 spage) Add test where all three are missing,
			// and maybe another with two missing, article (which is optional)
			// and another (which would be required) (that would leave only one
			// required present).
		];

	QUnit.module( 'ext.eventLogging', QUnit.newMwEnvironment( {
		setup: function () {
			mw.eventLog.setSchema( 'earthquake', {
				schema: earthquakeSchema,
				revision: 'TEST'
			} );
			mw.config.set( 'wgEventLoggingBaseUri', '#' );
		},
		teardown: function () {
			mw.eventLog.schemas = {};
		}
	} ) );


	QUnit.test( 'Configuration', 1, function ( assert ) {
		assert.ok( mw.config.exists( 'wgEventLoggingBaseUri' ), 'Global config var "wgEventLoggingBaseUri" exists' );
	} );


	QUnit.test( 'getSchema', 2, function ( assert ) {
		assert.deepEqual( mw.eventLog.getSchema( 'earthquake' ).schema, earthquakeSchema, 'Retrieves schema if exists' );
		assert.deepEqual( mw.eventLog.getSchema( 'foo' ), null, 'Returns null for missing schemas' );
	} );

	QUnit.test( 'isInstance', 36, function ( assert ) {

		$.each( {
			boolean: {
				valid: [ true, false ],
				invalid: [ undefined, null, 0, -1, 1, 'false' ]
			},
			integer: {
				valid: [ -12, 42, 0, 4294967296 ],
				invalid: [ 42.1, NaN, Infinity, '42', [ 42 ] ]
			},
			number: {
				valid: [ 12, 42.1, 0, Math.PI ],
				invalid: [ '42.1', NaN, [ 42 ], undefined ]
			},
			string: {
				valid: [ 'Hello', '', '-1' ],
				invalid: [ [], 0, true ]
			},
			timestamp: {
				valid: [ new Date().getTime(), new Date() ],
				invalid: [ -1, 'yesterday', NaN ]
			}
		}, function ( type, cases ) {
			$.each( cases.valid, function () {
				assert.ok(
					mw.eventLog.isInstance( this, type ),
					[ $.toJSON( this ), type ].join( ' is a ' )
				);
			} );
			$.each( cases.invalid, function () {
				assert.ok(
					!mw.eventLog.isInstance( this, type ),
					[ $.toJSON( this ), type ].join( ' is not a ' )
				);
			} );
		} );

	} );

	QUnit.test( 'validate', 5, function ( assert ) {

		assert.ok( mw.eventLog.validate( {
			epicenter: 'Valdivia',
			magnitude: 9.5
		}, 'earthquake' ), 'Non-required fields may be omitted' );

		$.each( validationCases, function() {
			var thisCase = this;
			assert.throws( function () {
				mw.eventLog.validate( thisCase.args, 'earthquake' );
			}, thisCase.regex, thisCase.msg );
		} );

	} );

	QUnit.test( 'isValid', 5, function ( assert ) {

		assert.ok( mw.eventLog.isValid( {
			epicenter: 'Valdivia',
			magnitude: 9.5
		}, 'earthquake' ), 'Non-required fields may be omitted' );

		$.each( validationCases, function() {
			var thisCase = this;
			assert.assertFalse(
				mw.eventLog.isValid( thisCase.args, 'earthquake' ),
				thisCase.msg );
		} );
	} );

	QUnit.asyncTest( 'logEvent', 1, function ( assert ) {
		var e = {
			epicenter: 'Valdivia',
			magnitude: 9.5
		};

		mw.eventLog.logEvent( 'earthquake', e ).always( function () {
			QUnit.start();
			delete this.meta;
			assert.deepEqual( this, e, 'logEvent promise resolves with event' );
		} );
	} );

	QUnit.asyncTest( 'setDefaults', 2, function ( assert ) {

		assert.deepEqual( mw.eventLog.setDefaults( 'earthquake', {
			epicenter: 'Valdivia'
		} ), { epicenter: 'Valdivia' }, 'setDefaults returns defaults' );

		mw.eventLog.logEvent( 'earthquake', {
			magnitude: 9.5
		} ).always( function () {
			delete this.meta;
			assert.deepEqual( this, {
				epicenter: 'Valdivia',
				magnitude: 9.5
			}, 'Logged event is annotated with defaults' );
			QUnit.start();
		} );

	} );

} ( mediaWiki, jQuery ) );
