/**
 * Log arbitrary events from client-side code to server. Each event must
 * validate against a predeclared data model, specified as JSON Schema (version
 * 3 of the draft).
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

( function ( mw, $ ) {
	'use strict';

	var baseUri = mw.config.get( 'wgEventLoggingBaseUri' ),

		// According to the HTTP specs (RFC 2616, section 3.2.1), "[s]ervers ought
		// to be cautious about depending on URI lengths above 255 bytes, because
		// some older client or proxy implementations might not properly support
		// these lengths" (http://www.rfc-editor.org/rfc/rfc2616.txt). In practice,
		// URIs of up 2000 bytes are broadly supported, but it is hoped that
		// a stricter limit will promote thrift and simplicity.
		uriMaxBytes = 255;

	if ( typeof baseUri !== 'string' || !baseUri.length ) {
		mw.log( 'EventLogging: wgEventLoggingBaseUri is invalid.' );
		baseUri = '';
	}

	mw.eventLog = {};


	/**
	 * @param {string} modelName
	 * @return {Object|null}
	 */
	mw.eventLog.getModel = function ( modelName ) {
		var model = mw.eventLog.dataModels[ modelName ];
		if ( model === undefined ) {
			return null;
		}
		return model;
	};


	/**
	 * @param {string} modelName
	 * @param {Object} dataModel
	 * @param {boolean} clobber Whether existing model should be overwritten.
	 * @throws {Error} If model already exists and clobber is falsey.
	 * @return {Object} Data model.
	 */
	mw.eventLog.declareModel = function ( modelName, dataModel, clobber ) {
		if ( !clobber && mw.eventLog.dataModels[ modelName ] !== undefined ) {
			throw new Error( 'Cannot overwrite existing model "' + modelName + '"' );
		}
		mw.eventLog.dataModels[ modelName ] = dataModel;
		return dataModel;
	};


	/**
	 * @param {Object} instance Object to test.
	 * @param {string} type JSON Schema type specifier.
	 * @return {boolean}
	 */
	mw.eventLog.isInstance = function ( instance, type ) {
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
	};


	/**
	 * @param {Object} event Event to validate.
	 * @param {Object} modelName Name of data model.
	 * @throws {Error} If event fails to validate.
	 */
	mw.eventLog.assertValid = function ( event, modelName ) {
		var field, model = mw.eventLog.getModel( modelName );

		if ( model === null ) {
			throw new Error( 'Unknown event data model: ' + modelName );
		}

		for ( field in event ) {
			if ( model[ field ] === undefined ) {
				throw new Error( 'Unrecognized field ' + field );
			}
		}

		$.each( model, function ( field, desc ) {
			var val = event[ field ];

			if ( val === undefined ) {
				if ( desc.optional !== true ) {
					throw new Error( 'Missing field: ' + field );
				}
				return true;
			}
			if ( !( mw.eventLog.isInstance( val, desc.type ) ) ) {
				throw new Error( [ 'Wrong type for field:', field, val ].join(' ') );
			}
			// 'enum' is reserved for possible future use by the ECMAScript
			// specification, but it's legal to use it as an attribute name
			// (and it's part of the JSON Schema draft spec). Still, JSHint
			// complains unless the name is quoted. Currently (24-Oct-2012)
			// the only way to turn off the warning is to use "es5:true",
			// which would be too broad.
			if ( desc[ 'enum' ] && desc[ 'enum' ].indexOf( val ) === -1 ) {
				throw new Error( [ 'Value not in enum:', val, ',', $.toJSON( desc[ 'enum' ] ) ].join(' ') );
			}
		} );
		return true;
	};


	/**
	 * @param {string} eventName Canonical name of event.
	 * @param {Object} eventInstance Event instance.
	 * @returns {jQuery.Deferred} Promise object.
	 */
	mw.eventLog.logEvent = function ( modelName, eventInstance ) {
		mw.eventLog.assertValid( eventInstance, modelName );

		// Event instances are automatically annotated with '_db' and '_id' to
		// identify their origin and declared data model.
		eventInstance = $.extend( {}, eventInstance, {
			/*jshint nomen: false*/
			_db: mw.config.get( 'wgDBname' ),
			_id: modelName
			/*jshint nomen: true*/
		} );


		var uri = baseUri + $.param( eventInstance ),
			beacon = document.createElement( 'img' ),
			dfd = jQuery.Deferred();

		if ( uri.split( /%..|./ ).length - 1 > uriMaxBytes ) {
			throw new Error( 'Request URI is too long: ' + uri );
		}

		if ( !baseUri.length ) {
			// We already logged the fact of wgEventLoggingBaseUri being empty,
			// so respect the caller's expectation and return a rejected promise.
			dfd.reject();
			return dfd.promise();
		}

		// Browsers uniformly fire the onerror event upon receiving HTTP 204
		// ("No Content") responses to image requests. Thus, although
		// counterintuitive, resolving the promise on error is appropriate.
		// TODO(ori-l, 25-Oct-2012): resolve with event.
		$( beacon ).on( 'error', dfd.resolve );
		beacon.src = uri;
		return dfd.promise();
	};

} ( mediaWiki, jQuery ) );
