/* eslint-env qunit */
( function () {
	'use strict';

	var earthquakeSchema = {
			revision: 123,
			schema: {
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
			}
		},

		validationCases = [
			{
				args: {},
				regex: /^Missing property/,
				msg: 'Empty, omitting all optional and required fields.'
			},
			{
				args: {
					epicenter: 'Valdivia'
				},
				regex: /^Missing property/,
				msg: 'Empty, omitting one optional and one required field.'
			},
			{
				args: {
					epicenter: 'Valdivia',
					article: '[[1960 Valdivia earthquake]]'
				},
				regex: /^Missing property/,
				msg: 'Required fields must be present.'
			},
			{
				args: {
					epicenter: 'Valdivia',
					magnitude: '9.5'
				},
				regex: /wrong type for property/,
				msg: 'Values must be instances of declared type'
			},
			{
				args: {
					epicenter: 'Valdivia',
					magnitude: 9.5,
					depth: 33
				},
				regex: /^Undeclared property/,
				msg: 'Unrecognized fields fail validation'
			},
			{
				args: {
					epicenter: 'Tōhoku',
					magnitude: 9.0
				},
				regex: /is not one of/,
				msg: 'Enum fields constrain possible values'
			}
		];

	QUnit.module( 'ext.eventLogging.debug', QUnit.newMwEnvironment( {
		setup: function () {
			this.suppressWarnings();
			mw.eventLog.declareSchema( 'earthquake', earthquakeSchema );
			mw.config.set( 'wgEventLoggingBaseUri', '#' );
		},
		teardown: function () {
			mw.eventLog.schemas = {};
			this.restoreWarnings();
		}
	} ) );

	QUnit.test( 'validate', function ( assert ) {
		var meta, errors;
		assert.expect( validationCases.length + 1 );

		meta = mw.eventLog.getSchema( 'earthquake' );
		errors = mw.eventLog.validate( {
			epicenter: 'Valdivia',
			magnitude: 9.5
		}, meta.schema );

		assert.propEqual( errors, [], 'Non-required fields may be omitted' );

		$.each( validationCases, function ( _, vCase ) {
			errors = mw.eventLog.validate( vCase.args, meta.schema );
			assert.ok( errors.join( '' ).match( vCase.regex ), vCase.msg );
		} );
	} );

	QUnit.module( 'ext.eventLogging: isInstanceOf()' );

	$.each( {
		'boolean': {
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
			valid: [ +new Date(), new Date() ],
			invalid: [ -1, 'yesterday', NaN ]
		},
		array: {
			valid: [ [], [ 42 ] ],
			invalid: [ -1, {}, undefined ]
		}
	}, function ( type, cases ) {
		QUnit.test( type, function ( assert ) {
			$.each( cases.valid, function ( index, value ) {
				assert.strictEqual( mw.eventLog.isInstanceOf( value, type ), true,
					JSON.stringify( value ) + ' is a ' + type );
			} );
			$.each( cases.invalid, function ( index, value ) {
				assert.strictEqual( mw.eventLog.isInstanceOf( value, type ), false,
					JSON.stringify( value ) + ' is not a ' + type );
			} );
		} );
	} );

}() );
