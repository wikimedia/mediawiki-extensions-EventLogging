/**
 * EventLogging client-side debug mode: Inspect events and validation errors on
 * calls to mw.eventLog.logEvent
 *
 * To enable, run the following from the browser console:
 *
 *     mw.loader.using( 'mediawiki.api' ).then( function () {
 *         new mw.Api().saveOption( 'eventlogging-display-web', '1' );
 *     } );
 *
 * To disable:
 *
 *     mw.loader.using( 'mediawiki.api' ).then( function () {
 *         new mw.Api().saveOption( 'eventlogging-display-web', '0' );
 *     } );
 *
 * See EventLoggingHooks.php for the module loading, and user option registation.
 *
 * @private
 * @class mw.eventLog.Debug
 * @singleton
 */
( function () {
	'use stict';

	var dialogPromise;

	/**
	 * @private
	 * @return {jQuery.Promise} Yields a function to open an OOUI Window
	 */
	function makeDialogPromise() {
		return mw.loader.using( 'oojs-ui-windows' ).then( function () {
			/* global OO */
			var manager = new OO.ui.WindowManager(),
				dialog = new OO.ui.MessageDialog();
			$( 'body' ).append( manager.$element );
			manager.addWindows( [ dialog ] );

			return function openDialog( args ) {
				manager.openWindow( dialog, $.extend( {
					verbose: true,
					size: 'large',
					actions: [
						{
							action: 'accept',
							label: mw.msg( 'ooui-dialog-message-accept' ),
							flags: 'primary'
						}
					]
				}, args ) );
			};
		} );
	}

	/**
	 * @private
	 * @param {Object} event As formatted by mw.eventLog.prepare()
	 */
	function displayLoggedEvent( event ) {
		var baseUrl = mw.config.get( 'wgEventLoggingSchemaApiUri' ).replace( 'api.php', 'index.php' ),
			json = JSON.stringify( event, null, 2 ),
			formatted = mw.format(
				mw.html.escape( 'Log event ($1): $2' ),
				mw.html.element( 'a',
					{ href: baseUrl + '?oldid=' + event.revision },
					'Schema: ' + event.schema
				),
				mw.html.element( 'tt', {},
					JSON.stringify( event.event, null, 1 ).slice( 0, 100 ) + '...'
				)
			),
			content = $( '<p>' ).html( formatted );

		content.on( 'click', function () {
			dialogPromise = dialogPromise || makeDialogPromise();
			dialogPromise.then( function ( openDialog ) {
				openDialog( {
					title: 'Schema: ' + event.schema,
					message: $( '<pre>' ).text( json )
				} );
			} );
		} );

		/* eslint-disable no-console */
		if ( window.console && console.info ) {
			console.info( event.schema, event );
		}
		mw.notification.notify( content, { autoHide: true, autoHideSeconds: 'long' } );
	}

	function validateAndDisplay( topic, event ) {
		// TODO: put validation errors directly in the dialog box
		var schema = mw.eventLog.getSchema( event.schema ),
			errors = mw.eventLog.validate( event.event, schema.schema );

		while ( errors.length ) {
			mw.track( 'eventlogging.error', mw.format( '[$1] $2', event.schema, errors.pop() ) );
		}

		mw.loader.using( [ 'mediawiki.notification', 'oojs-ui-windows' ] ).then( function () {
			displayLoggedEvent( event );
		} );
	}

	mw.trackSubscribe( 'eventlogging.debug', function ( topic, event ) {
		// TODO: load this directly from meta in the next change
		mw.loader.using( mw.format( 'schema.$1', event.schema ) ).then(
			function () {
				validateAndDisplay( topic, event );
			},
			function () {
				mw.track( 'eventlogging.error', mw.format( 'Could not load schema: $1', event.schema ) );
			}
		);
	} );

	// Output validation errors to the browser console, if available.
	mw.trackSubscribe( 'eventlogging.error', function ( topic, error ) {
		mw.log.error( mw.format( '$1: $2', 'EventLogging Validation', error ) );
	} );

}() );
