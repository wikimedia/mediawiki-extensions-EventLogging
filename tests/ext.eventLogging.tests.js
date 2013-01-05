( function ( mw, $ ) {
	'use strict';

	var warn = mw.eventLog.warn,

		earthquakeSchema = {
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
		};

	QUnit.module( 'ext.eventLogging', QUnit.newMwEnvironment( {
		setup: function () {
			mw.eventLog.warn = function( msg ) {
				throw new Error( msg );
			};
			mw.eventLog.setSchema( 'earthquake', {
				schema: earthquakeSchema,
				revision: 'TEST'
			} );
			mw.config.set( 'wgEventLoggingBaseUri', '#' );
		},
		teardown: function () {
			mw.eventLog.schemas = {};
			mw.eventLog.warn = warn;
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

		assert.throws( function () {
			mw.eventLog.validate( {
				epicenter: 'Valdivia',
				article: '[[1960 Valdivia earthquake]]'
			}, 'earthquake' );
		}, /Missing/, 'Required fields must be present.' );

		assert.throws( function () {
			mw.eventLog.validate( {
				epicenter: 'Valdivia',
				magnitude: '9.5'
			}, 'earthquake' );
		}, /Wrong/, 'Values must be instances of declared type' );

		assert.throws( function () {
			mw.eventLog.validate( {
				epicenter: 'Valdivia',
				magnitude: 9.5,
				depth: 33
			}, 'earthquake' );
		}, /Unrecognized/, 'Unrecognized fields fail validation' );

		assert.throws( function () {
			mw.eventLog.validate( {
				epicenter: 'Tōhoku',
				magnitude: 9.0
			}, 'earthquake' );
		}, /enum/, 'Enum fields constrain possible values' );
	} );

	QUnit.asyncTest( 'logEvent', 1, function ( assert ) {
		var e = {
			epicenter: 'Valdivia',
			magnitude: 9.5
		};

		mw.eventLog.logEvent( 'earthquake', e ).always( function () {
			QUnit.start();
			delete this[ 'meta' ];
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
			delete this[ 'meta' ];
			assert.deepEqual( this, {
				epicenter: 'Valdivia',
				magnitude: 9.5
			}, 'Logged event is annotated with defaults' );
			QUnit.start();
		} );

	} );

} ( mediaWiki, jQuery ) );
