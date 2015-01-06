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


	var

	// `baseUrl` corresponds to $wgEventLoggingBaseUri, as declared
	// in EventLogging.php. If the default value of 'false' has not
	// been overridden, events will not be sent to the server.
	baseUrl = mw.config.get( 'wgEventLoggingBaseUri' ),

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
	self = mw.eventLog = {

		/**
		 * Schema registry. Schemas that have been declared explicitly via
		 * `eventLog.declareSchema` or implicitly by being referenced in an
		 * `eventLog.logEvent` call are stored in this object.
		 *
		 * @property schemas
		 * @type Object
		 */
		schemas: {},

		/**
		 * Load a schema from the schema registry.
		 * If the schema does not exist, it will be initialised.
		 *
		 * @method getSchema
		 * @param {String} schemaName Name of schema.
		 * @return {Object} Schema object.
		 */
		getSchema: function ( schemaName ) {
			if ( !self.schemas.hasOwnProperty( schemaName ) ) {
				self.schemas[ schemaName ] = { schema: { title: schemaName } };
			}
			return self.schemas[ schemaName ];
		},

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
			return $.extend( true, self.getSchema( schemaName ), meta );
		},


		/**
		 * Checks whether a JavaScript value conforms to a specified
		 * JSON Schema type.
		 *
		 * @method isInstanceOf
		 * @param {Object} value Object to test.
		 * @param {String} type JSON Schema type.
		 * @return {Boolean} Whether value is instance of type.
		 */
		isInstanceOf: function ( value, type ) {
			var jsType = $.type( value );
			switch ( type ) {
			case 'integer':
				return jsType === 'number' && value % 1 === 0;
			case 'number':
				return jsType === 'number' && isFinite( value );
			case 'timestamp':
				return jsType === 'date' || ( jsType === 'number' && value >= 0 && value % 1 === 0 );
			default:
				return jsType === type;
			}
		},


		/**
		 * Check whether a JavaScript object conforms to a JSON Schema.
		 *
		 * @method validate
		 * @param {Object} obj Object to validate.
		 * @param {Object} schema JSON Schema object.
		 * @return {Array} An array of validation errors (empty if valid).
		 */
		validate: function ( obj, schema ) {
			var errors = [], key, val, prop;

			if ( $.isEmptyObject( schema.properties ) ) {
				errors.push( 'Unknown schema' );
				return errors;
			}

			for ( key in obj ) {
				if ( !schema.properties.hasOwnProperty( key ) ) {
					errors.push( mw.format( 'Undeclared property "$1"', key ) );
				}
			}

			for ( key in schema.properties ) {
				prop = schema.properties[ key ];

				if ( !obj.hasOwnProperty( key ) ) {
					if ( prop.required ) {
						errors.push( mw.format( 'Missing property "$1"', key ) );
					}
					continue;
				}
				val = obj[ key ];

				if ( !( self.isInstanceOf( val, prop.type ) ) ) {
					errors.push( mw.format(
						'Value $1 is the wrong type for property "$2" ($3 expected)',
						JSON.stringify( val ), key, prop.type
					) );
					continue;
				}

				if ( prop[ 'enum' ] && $.inArray( val, prop[ 'enum' ] ) === -1 ) {
					errors.push( mw.format(
						'Value $1 for property "$2" is not one of $3',
						JSON.stringify( val ), key, JSON.stringify( prop['enum'] )
					) );
				}
			}

			return errors;
		},


		/**
		 * Sets default values to be applied to all subsequent events belonging
		 * to a schema. Note that `setDefaults` does not validate, but the
		 * complete event object (including defaults) is validated prior to
		 * dispatch.
		 *
		 * @method setDefaults
		 * @param {String} schemaName Canonical schema name.
		 * @param {Object} schemaDefaults Default values for schema's events.
		 * @return {Object} Updated defaults for schema.
		 */
		setDefaults: function ( schemaName, schemaDefaults ) {
			return self.declareSchema( schemaName, { defaults: schemaDefaults } );
		},


		/**
		 * Prepares an event for dispatch by filling defaults for any
		 * missing properties, and finally encapsulating the result in
		 * a generic object that contains metadata about the event.
		 *
		 * @method prepare
		 * @param {String} schemaName Canonical schema name.
		 * @param {Object} eventData Event instance.
		 * @return {Object} Encapsulated event.
		 */
		prepare: function ( schemaName, eventData ) {
			var schema = self.getSchema( schemaName ),
				event = $.extend( true, {}, schema.defaults, eventData ),
				errors = self.validate( event, schema.schema ),
				valid = !errors.length;

			while ( errors.length ) {
				mw.track( 'eventlogging.error', mw.format( '[$1] $2', schemaName, errors.pop() ) );
			}

			return {
				event            : event,
				clientValidated  : valid,
				revision         : schema.revision || -1,
				schema           : schemaName,
				webHost          : location.hostname,
				wiki             : mw.config.get( 'wgDBname' )
			};
		},

		/**
		 * Constructs the EventLogging URI based on the base URI and the
		 * encoded and stringified data.
		 *
		 * @method makeBeaconUrl
		 * @param {Object} data Payload to send
		 * @return {String|Boolean} The URI to log the event.
		 */
		makeBeaconUrl: function ( data ) {
			var queryString = encodeURIComponent( JSON.stringify( data ) );
			return baseUrl + '?' + queryString + ';';
		},

		/**
		 * Encodes a JavaScript object as percent-encoded JSON and
		 * pushes it to the server using a GET request. This request
		 * is sent by requesting an image with the source as the
		 * required EventLogging URI.
		 *
		 * @method dispatch
		 * @param {Object} data Payload to send.
		 * @return {jQuery.Promise} jQuery Promise object for the logging call
		 */
		dispatch: function ( data ) {
			var beacon = document.createElement( 'img' ),
				uri = self.makeBeaconUrl( data ),
				deferred = $.Deferred();

			if ( !uri ) {
				deferred.rejectWith( data, [ data ] );
				return deferred.promise();
			}

			// Browsers trigger `onerror` event on HTTP 204 replies to image
			// requests. Thus, confusingly, `onerror` indicates success.
			$( beacon ).on( 'error', function () {
				deferred.resolveWith( data, [ data ] );
			} );

			beacon.src = uri;
			return deferred.promise();
		},

		/**
		 * Asynchronously initiate logging for event data. The method returns a
		 * promise that indicates whether the method successfully queued the data.
		 * Even if it is queued for delivery, there is no guarantee it will ever be
		 * delivered. Currently, the only backend is sendBeacon, so if that is not
		 * available, the promise will be rejected.
		 * NOTE: This is an experimental method.
		 *
		 * @method logPersistentEvent
		 * @experimental
		 * @param {String} schemaName Canonical schema name.
		 * @param {Object} eventInstance Event instance.
		 * @return {jQuery.Promise} jQuery Promise object for the logging call. The promise would
		 *  have been resolved or rejected before the function returns. The first argument passed
		 *  to the callback is the data itself and the second one is a string with the status.
		 *  Even when the deferred is resolved it only means that the data has been queued to be sent.
		 */
		logPersistentEvent: function ( schemaName, eventInstance ) {
			var data = self.prepare( schemaName, eventInstance ),
				uri = self.makeBeaconUrl( data ),
				deferred = $.Deferred();

			if ( !uri ) {
				deferred.rejectWith( data, [ data, 'no-base-uri' ] );
				return deferred.promise();
			}

			if ( navigator.sendBeacon === undefined ) {
				deferred.rejectWith( data, [ data, 'no-browser-support' ] );
				return deferred.promise();
			}

			if ( navigator.sendBeacon( uri ) ) {
				deferred.resolveWith( data, [ data, 'queued' ] );
			} else {
				deferred.rejectWith( data, [ data, 'could-not-queue' ]  );
			}

			return deferred.promise();
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
		 * @param {Object} eventData Event object.
		 * @return {jQuery.Promise} jQuery Promise object for the logging call.
		 */
		logEvent: function ( schemaName, eventData ) {
			return self.dispatch( self.prepare( schemaName, eventData ) );
		}
	};

	// Output validation errors to the browser console, if available.
	if ( console && console.error ) {
		mw.trackSubscribe( 'eventlogging.error', function ( topic, error ) {
			console.error( error );
		} );
	}

} ( mediaWiki, jQuery, window.console ) );
