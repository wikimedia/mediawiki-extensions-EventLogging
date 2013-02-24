/**
 * This module implements EventLogging's API for logging events from
 * client-side JavaScript code. Instances of `ResourceLoaderSchemaModule`
 * indicate a dependency on this module and declare themselves via its
 * 'declareSchema' method.
 *
 * Developers should not load this module directly, but work with schema
 * modules instead. Schema modules will load this module as a
 * dependency.
 *
 * @module ext.eventLogging.core.js
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function ( mw, $, console ) {
	'use strict';

	/**
	 * Represents a failure to validate an object against its schema.
	 *
	 * @class ValidationError
	 * @constructor
	 * @extends Error
	 * @private
	 **/
	function ValidationError( message ) {
		this.message = message;
	}
	ValidationError.prototype = new Error();


	/**
	 * Client-side EventLogging API.
	 *
	 * The public API consists of a single function, `mw.eventLog.logEvent`.
	 * Other methods represent internal functionality, which is exposed only
	 * to ease debugging code and writing tests.
	 *
	 *
	 * @class eventLog
	 * @namespace mediaWiki
	 * @static
	 */
	var self = mw.eventLog = {

		/**
		 * Schema registry. Schemas that have been declared explicitly via
		 * `eventLog.declareSchema` or implicitly by being referenced in an
		 * `eventLog.logEvent` call are stored in this object.
		 *
		 * @property schemas
		 * @type Object
		 */
		schemas: {},

		warn: console && $.isFunction( console.warn ) ?
			$.proxy( console.warn, console ) : mw.log,

		/**
		 * Register a schema so that it can be used to validate events.
		 * `ResourceLoaderSchemaModule` instances generate JavaScript code that
		 * invokes this method.
		 *
		 * @method declareSchema
		 * @param {String} schemaName Name of schema.
		 * @param {Object} [meta] An object describing a schema:
		 *   @param {Number} meta.revision Revision ID.
		 *   @param {Object} meta.schema The schema itself.
		 * @return {Object} The registered schema.
		 */
		declareSchema: function ( schemaName, meta ) {
			if ( self.schemas.hasOwnProperty( schemaName ) ) {
				self.warn( 'Clobbering existing "' + schemaName + '" schema' );
			}
			self.schemas[ schemaName ] = $.extend( true, {
				revision : -1,
				schema   : { properties: {} },
				defaults : {}
			}, self.schemas[ schemaName ], meta );
			return self.schemas[ schemaName ];
		},


		/**
		 * Checks whether a JavaScript value conforms to a specified JSON
		 * Schema type. Supports string, timestamp, boolean, integer and
		 * number types. Arrays are not currently supported.
		 *
		 * @method isInstanceOf
		 * @param {Object} instance Object to test.
		 * @param {String} type JSON Schema type.
		 * @return {Boolean} Whether value is instance of type.
		 */
		isInstanceOf: function ( value, type ) {
			// undefined and null are invalid values for any type.
			if ( value === undefined || value === null ) {
				return false;
			}
			switch ( type ) {
			case 'string':
				return typeof value === 'string';
			case 'timestamp':
				return value instanceof Date || (
						typeof value === 'number' &&
						value >= 0 &&
						value % 1 === 0 );
			case 'boolean':
				return typeof value === 'boolean';
			case 'integer':
				return typeof value === 'number' && value % 1 === 0;
			case 'number':
				return typeof value === 'number' && isFinite( value );
			default:
				return false;
			}
		},


		/**
		 * Checks whether an event object conforms to a JSON Schema.
		 *
		 * @method isValid
		 * @param {Object} event Event to test for validity.
		 * @param {String} schemaName Name of schema.
		 * @return {Boolean} Whether event conforms to the schema.
		 */
		isValid: function ( event, schemaName ) {
			try {
				self.assertValid( event, schemaName );
				return true;
			} catch ( e ) {
				if ( !( e instanceof ValidationError ) ) {
					throw e;
				}
				self.warn( e.message );
				return false;
			}
		},

		/**
		 * Asserts that an event validates against a JSON Schema. If the event
		 * does not validate, throws a `ValidationError`.
		 *
		 * @method assertValid
		 * @param {Object} event Event to validate.
		 * @param {Object} schemaName Name of schema.
		 * @throws {ValidationError} If event fails to validate.
		 */
		assertValid: function ( event, schemaName ) {
			var schema = self.schemas[ schemaName ] || null,
				props = schema.schema.properties,
				prop;

			if ( $.isEmpty( props ) ) {
				throw new ValidationError( 'Unknown schema: ' + schemaName );
			}

			for ( prop in event ) {
				if ( props[ prop ] === undefined ) {
					throw new ValidationError( 'Unrecognized property: ' + prop );
				}
			}

			$.each( props, function ( prop, desc ) {
				var val = event[ prop ];

				if ( val === undefined ) {
					if ( desc.required ) {
						throw new ValidationError( 'Missing property: ' + prop );
					}
					return true;
				}

				if ( !( self.isInstanceOf( val, desc.type ) ) ) {
					throw new ValidationError( 'Wrong type for property: ' + prop + ' ' +  val );
				}

				if ( desc[ 'enum' ] && $.inArray( val, desc[ 'enum' ] ) === -1 ) {
					throw new ValidationError( 'Value "' + val + '" not in enum ' + $.toJSON( desc[ 'enum' ] ) );
				}
			} );

			return true;
		},


		/**
		 * Sets default values to be applied to all subsequent events belonging
		 * to a schema. Note that `setDefaults` does not validate, but the
		 * complete event object (including defaults) is validated prior to
		 * dispatch.
		 *
		 * @method setDefaults
		 * @param {String} schemaName Canonical schema name.
		 * @param {Object|null} schemaDefaults Defaults, or null to clear.
		 * @return {Object} Updated defaults for schema.
		 */
		setDefaults: function ( schemaName, schemaDefaults ) {
			var schema = self.schemas[ schemaName ];
			if ( schema === undefined ) {
				self.warn( 'Setting defaults on unknown schema "' + schemaName + '"' );
				schema = self.declareSchema( schemaName );
			}
			return $.extend( true, schema.defaults, schemaDefaults );
		},


		/**
		 * Takes an event object and puts it inside a generic wrapper
		 * object that contains generic metadata about the event.
		 *
		 * @method encapsulate
		 * @param {String} schemaName Canonical schema name.
		 * @param {Object} event Event instance.
		 * @return {Object} Encapsulated event.
		 */
		encapsulate: function ( schemaName, event ) {
			var schema = self.schemas[ schemaName ];

			if ( schema === undefined ) {
				self.warn( 'Got event with unknown schema "' + schemaName + '"' );
				schema = self.declareSchema( schemaName );
			}

			event = $.extend( true, {}, event, schema.defaults );

			return {
				event            : event,
				clientValidated  : self.isValid( event, schemaName ),
				revision         : schema.revision,
				schema           : schemaName,
				webHost          : window.location.hostname,
				wiki             : mw.config.get( 'wgDBname' )
			};
		},


		/**
		 * Encodes a JavaScript object as percent-encoded JSON and
		 * pushes it to the server using a GET request.
		 *
		 * @method dispatch
		 * @param {Object} data Payload to send.
		 * @return {jQuery.Deferred} Promise object.
		 */
		dispatch: function ( data ) {
			var beacon = document.createElement( 'img' ),
				baseUri = mw.config.get( 'wgEventLoggingBaseUri' ),
				dfd = $.Deferred();

			if ( !baseUri ) {
				dfd.rejectWith( data, [ data ] );
				return dfd.promise();
			}

			// Browsers trigger `onerror` event on HTTP 204 replies to image
			// requests. Thus, confusingly, `onerror` indicates success.
			$( beacon ).on( 'error', function () {
				dfd.resolveWith( data, [ data ] );
			} );

			beacon.src = baseUri + '?' + encodeURIComponent( $.toJSON( data ) ) + ';';
			return dfd.promise();
		},


		/**
		 * Construct and transmit to a remote server a record of some event
		 * having occurred. Events are represented as JavaScript objects that
		 * conform to a JSON Schema. The schema describes the properties the
		 * event object may (or must) contain and their type. This method
		 * represents the public client-side API of EventLogging.
		 *
		 * @method logEvent
		 * @param {String} schemaName Canonical schema name.
		 * @param {Object} eventInstance Event instance.
		 * @return {jQuery.Deferred} Promise object.
		 */
		logEvent: function ( schemaName, eventInstance ) {
			return self.dispatch( self.encapsulate( schemaName, eventInstance ) );
		}
	};

	// For backward-compatibility; may be removed after 28-Feb-2012 deployment.
	self.setSchema = self.declareSchema;

	if ( !mw.config.get( 'wgEventLoggingBaseUri' ) ) {
		self.warn( '"$wgEventLoggingBaseUri" is not set.' );
	}

} ( mediaWiki, jQuery, window.console ) );
