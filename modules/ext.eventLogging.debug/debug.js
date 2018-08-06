/**
 * EventLogging client-side debug mode: Inspect events on page views.
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
( function ( mw, $ ) {
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

	mw.trackSubscribe( 'eventlogging.debug', function ( topic, event ) {
		mw.loader.using( [ 'mediawiki.notification', 'oojs-ui-windows' ] ).then( function () {
			displayLoggedEvent( event );
		} );
	} );

}( mediaWiki, jQuery ) );
