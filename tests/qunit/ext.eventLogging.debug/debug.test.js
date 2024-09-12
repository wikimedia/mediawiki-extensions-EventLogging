QUnit.module( 'ext.eventLogging.debug', function () {
	'use strict';

	const eventLogDebug = require( 'ext.eventLogging.debug' );

	QUnit.test( 'validate()', function ( assert ) {
		const earthquakeSchema = {
			revision: 123,
			schema: {
				description: 'Record of a history earthquake',
				properties: {
					epicenter: {
						type: 'string',
						enum: [ 'Valdivia', 'Sumatra', 'Kamchatka' ],
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
		};
		const validationCases = [
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

		let errors = eventLogDebug.validate( {
			epicenter: 'Valdivia',
			magnitude: 9.5
		}, earthquakeSchema.schema );

		assert.propEqual( errors, [], 'Non-required fields may be omitted' );

		validationCases.forEach( function ( vCase ) {
			errors = eventLogDebug.validate( vCase.args, earthquakeSchema.schema );
			assert.notStrictEqual( errors.join( '' ).match( vCase.regex ), null, vCase.msg );
		} );
	} );

	QUnit.test.each( 'isInstanceOf()', {
		boolean: {
			type: 'boolean',
			valid: [ true, false ],
			invalid: [ undefined, null, 0, -1, 1, 'false' ]
		},
		integer: {
			type: 'integer',
			valid: [ -12, 42, 0, 4294967296 ],
			invalid: [ 42.1, NaN, Infinity, '42', [ 42 ] ]
		},
		number: {
			type: 'number',
			valid: [ 12, 42.1, 0, Math.PI ],
			invalid: [ '42.1', NaN, [ 42 ], undefined ]
		},
		string: {
			type: 'string',
			valid: [ 'Hello', '', '-1' ],
			invalid: [ [], 0, true ]
		},
		timestamp: {
			type: 'timestamp',
			valid: [ Date.now(), new Date() ],
			invalid: [ -1, 'yesterday', NaN ]
		},
		array: {
			type: 'array',
			valid: [ [], [ 42 ] ],
			invalid: [ -1, {}, undefined ]
		}
	}, function ( assert, data ) {
		data.valid.forEach( function ( value ) {
			assert.strictEqual( eventLogDebug.isInstanceOf( value, data.type ), true,
				JSON.stringify( value ) + ' is valid' );
		} );
		data.invalid.forEach( function ( value ) {
			assert.strictEqual( eventLogDebug.isInstanceOf( value, data.type ), false,
				JSON.stringify( value ) + ' is invalid' );
		} );
	} );

} );
