/**
 * Logs arbitrary events from client-side code to server. Each event
 * must validate against a predeclared data model, specified as JSON
 * Schema (version 3 of the draft).
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

( function ( mw, $, console ) {
	'use strict';

	/**
	 * @constructor
	 * @extends Error
	 **/
	function ValidationError( message ) {
		this.message = message;
	}
	ValidationError.prototype = new Error();

	if ( !mw.config.get( 'wgEventLoggingBaseUri' ) ) {
		mw.log( 'wgEventLoggingBaseUri is not set.' );
	}

	var self = mw.eventLog = {

		schemas: {},

		warn: console && $.isFunction( console.warn ) ?
			$.proxy( console.warn, console ) : mw.log,

		/**
		 * @param string schemaName
		 * @return {Object|null}
		 */
		getSchema: function ( schemaName ) {
			return self.schemas[ schemaName ] || null;
		},


		/**
		 * Declares event schema.
		 * @param {Object} schemas Schema specified as JSON Schema
		 * @param integer revision
		 * @return {Object}
		 */
		setSchema: function ( schemaName, meta ) {
			if ( self.schemas.hasOwnProperty( schemaName ) ) {
				self.warn( 'Clobbering existing "' + schemaName + '" schema' );
			}
			self.schemas[ schemaName ] = $.extend( true, {
				revision : 'UNKNOWN',
				schema   : { properties: {} },
				defaults : {}
			}, self.schemas[ schemaName ], meta );
			return self.schemas[ schemaName ];
		},


		/**
		 * @param {Object} instance Object to test.
		 * @param {string} type JSON Schema type specifier.
		 * @return {boolean}
		 */
		isInstance: function ( instance, type ) {
			// undefined and null are invalid values for any type.
			if ( instance === undefined || instance === null ) {
				return false;
			}
			switch ( type ) {
			case 'string':
				return typeof instance === 'string';
			case 'timestamp':
				return instance instanceof Date || (
						typeof instance === 'number' &&
						instance >= 0 &&
						instance % 1 === 0 );
			case 'boolean':
				return typeof instance === 'boolean';
			case 'integer':
				return typeof instance === 'number' && instance % 1 === 0;
			case 'number':
				return typeof instance === 'number' && isFinite( instance );
			default:
				return false;
			}
		},


		/**
		 * @param {Object} event Event to test for validity.
		 * @param {Object} schemaName Name of schema.
		 * @returns {boolean}
		 */
		isValid: function ( event, schemaName ) {
			try {
				this.validate( event, schemaName );
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
		 * @param {Object} event Event to validate.
		 * @param {Object} schemaName Name of schema.
		 * @throws {ValidationError} If event fails to validate.
		 */
		validate: function ( event, schemaName ) {
			var schema = self.getSchema( schemaName ),
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

				if ( !( self.isInstance( val, desc.type ) ) ) {
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
		 * to a schema. Note that setDefaults() does not validate, but the
		 * complete event object (including defaults) is validated prior to
		 * dispatch.
		 *
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object|null} schemaDefaults Defaults, or null to clear.
		 * @returns {Object} Updated defaults for schema.
		 */
		setDefaults: function ( schemaName, schemaDefaults ) {
			var schema = self.getSchema( schemaName );
			if ( schema === null ) {
				self.warn( 'Setting defaults on unknown schema "' + schemaName + '"' );
				schema = self.setSchema( schemaName );
			}
			return $.extend( true, schema.defaults, schemaDefaults );
		},


		/**
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object} event Event instance.
		 * @returns {Object} Encapsulated event.
		 */
		encapsulate: function ( schemaName, event ) {
			var schema = self.getSchema( schemaName );

			if ( schema === null ) {
				self.warn( 'Got event with unknown schema "' + schemaName + '"' );
				schema = self.setSchema( schemaName );
			}

			event = $.extend( true, {}, event, schema.defaults );

			return {
				event    : event,
				isValid  : self.isValid( event, schemaName ),
				revision : schema.revision,
				schema   : schemaName,
				webHost  : window.location.hostname,
				wiki     : mw.config.get( 'wgDBname' )
			};
		},


		/**
		 * Pushes data to server as URL-encoded JSON.
		 * @param {Object} data Payload to send.
		 * @returns {jQuery.Deferred} Promise object.
		 */
		dispatch: function ( data ) {
			var beacon = document.createElement( 'img' ),
				baseUri = mw.config.get( 'wgEventLoggingBaseUri' ),
				dfd = $.Deferred();

			if ( !baseUri ) {
				// We already logged the fact of wgEventLoggingBaseUri being
				// empty, so respect the caller's expectation and return a
				// rejected promise.
				dfd.rejectWith( data, [ data ] );
				return dfd.promise();
			}

			// Browsers uniformly fire the onerror event upon receiving HTTP
			// 204 ("No Content") responses to image requests. Thus, although
			// counterintuitive, resolving the promise on error is appropriate.
			$( beacon ).on( 'error', function () {
				dfd.resolveWith( data, [ data ] );
			} );

			beacon.src = baseUri + '?' + encodeURIComponent( $.toJSON( data ) ) + ';';
			return dfd.promise();
		},


		/**
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object} eventInstance Event instance.
		 * @returns {jQuery.Deferred} Promise object.
		 */
		logEvent: function ( schemaName, eventInstance ) {
			return self.dispatch( self.encapsulate( schemaName, eventInstance ) );
		}
	};

} ( mediaWiki, jQuery, window.console ) );
