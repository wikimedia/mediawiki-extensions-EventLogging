/**
 * Sync arbitrary events from client-side code to server. Each event must
 * validate against a predeclared data model. Data models are specified in
 * JSON. The following example illustrates the data model format:
 *
 *
 * 	{
 * 	  "experiment": {
 * 		"type": "string"
 * 	  },
 * 	  "timestamp": {
 * 		"type": "timestamp",
 * 		"optional": true
 * 	  },
 * 	  "version": {
 * 		"type": "int"
 * 	  },
 * 	  "bucket": {
 * 		"type": "enum",
 * 		"values": [
 * 		  "control",
 * 		  "experimental-1",
 * 		  "experimental-2"
 * 		]
 * 	  }
 * 	}
 *
 *
 * The following types are recognized:
 *
 *   +-----------+-----------------------+
 *   | Type      | Restrictions          |
 *   +-----------+-----------------------+
 *   | number    | JavaScript Number     |
 *   | string    | nonempty              |
 *   | timestamp | JavaScript Date       |
 *   | article   | unsigned 32-bit int   |
 *   | revision  |          "            |
 *   | user      |          "            |
 *   | enum      | TBD                   |
 *   +-----------+-----------------------+
 *
 * Article, revision and users are typed to facilitate automatic tagging.
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/*jslint white:true, browser:true, forin:true, bitwise:true, vars:true */
/*globals mediaWiki, models */

( function ( window, document, mw, encode ) {
	'use strict';

	//--- TODO(ori): Use a config var.
	var BASE_URI = '//bits.wikimedia.org/event.gif?';

	// URIs that are longer than 2,000 characters are unsafe:
	// - http://www.boutell.com/newfaq/misc/urllength.html
	// - http://stackoverflow.com/questions/417142
	var URI_MAXLEN = 2000;

	/**
	 * Test if value is valid instance of field type.
	 *
	 * @param {Object} instance Object to test.
	 * @param {Object} field
	 * @return {boolean}
	 */
	function isInstance( instance, field ) {
		switch( field.type ) {
		case 'article':
		case 'revision':
		case 'user':
			// Primary key; Should be non-zero uint32.
			return typeof instance === "number" &&
				instance >>> 0 === instance &&
				instance !== 0;
		case 'string':
			return typeof instance === "string" && instance.length;
		case 'timestamp':
			//--- TODO(ori-l): accept dbdates and int timestamps.
			return instance instanceof Date;
		case 'boolean':
			return typeof instance === "boolean";
		case 'number':
			return typeof instance === "number" && !isNaN( instance );
			//--- TODO(ori-l): add "enum" type.
		default:
			return false;
		}
	}


	/**
	 * Throw a TypeError if event fails to validate against data model.
	 *
	 * @throws {TypeError}
	 * @param {Object} event Event to validate.
	 * @param {Object} model Data model.
	 */
	function assertValid( event, model ) {
		var field;
		for ( field in model ) {
			if ( event.field === undefined && !!model[ field ].optional ) {
				throw new TypeError( 'Missing "' + field + '" field' );
			}
			if ( !( isInstance( event[ field ], model[ field ] ) ) ) {
				throw new TypeError( 'Invalid value for field "' + field +
									 '": "' + event[ field ] + '"' );
			}
		}
	}


	/**
	 * Serialize an event object to query string.
	 *
	 * @param {Object} event Event to serialize.
	 * @returns {string} Query string.
	 */
	function serialize( event ) {
		var keyvals = [], key, val;
		for ( key in event ) {
			val = event[ key ];
			keyvals.push( encode( key ) + '=' + encode( val ) );
		}
		return keyvals.join( '&' );
	}


	/**
	 * Push an event to the server.
	 *
	 * @param {Object} event Event to log.
	 */
	function dispatchEvent( event ) {
	}

	mw.eventLog = mw.eventLog || {};

	mw.eventLog.logEvent = function ( eventName, eventInstance ) {
		var dataModel = models.get( eventName );
		assertValid( eventInstance, dataModel );

		var uri = BASE_URI + serialize( eventInstance );
		if ( uri.length > URI_MAXLEN ) {
			throw new RangeError( 'track(): request URI is too long' );
		}
		document.createElement( 'img' ).src = uri;
	};

} ( window, document, mediaWiki, window.encodeURIComponent ) );
