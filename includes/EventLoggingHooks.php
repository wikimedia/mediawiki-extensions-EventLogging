<?php
/**
 * Hooks for EventLogging extension.
 *
 * @file
 *
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class EventLoggingHooks {

	/**
	 * Emit a debug log message for each invalid or unset
	 * configuration variable (if any).
	 */
	public static function onSetup() {
		global $wgMemc;

		if ( get_class( $wgMemc ) === 'EmptyBagOStuff' ) {
			wfDebugLog( 'EventLogging', 'No suitable memcached driver found.' );
		}

		foreach ( array(
			'wgEventLoggingBaseUri',
			'wgEventLoggingDBname',
			'wgEventLoggingFile',
			'wgEventLoggingSchemaApiUri'
		) as $configVar ) {
			if ( $GLOBALS[ $configVar ] === false ) {
				wfDebugLog( 'EventLogging', "$configVar has not been configured." );
			}
		}
	}

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$out->addModules( array( 'ext.eventLogging.subscriber' ) );
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * Allows extensions to register schema modules by adding keys to an
	 * associative array which is passed by reference to each handler. The
	 * array maps schema names to numeric revision IDs. By using this hook
	 * handler rather than registering modules directly, extensions can have
	 * a soft dependency on EventLogging. If EventLogging is not present, the
	 * hook simply never fires. To log events for schemas that have been
	 * declared in this fashion, use mw#track.
	 *
	 * @example
	 * <code>
	 * $wgHooks[ 'EventLoggingRegisterSchemas' ][] = function ( &$schemas ) {
	 *     $schemas[ 'MultimediaViewerNetworkPerformance' ] = 7917896;
	 * };
	 * </code>
	 *
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader &$resourceLoader ) {
		global $wgEventLoggingSchemas;

		$schemas = array();
		wfRunHooks( 'EventLoggingRegisterSchemas', array( &$schemas ) );
		$schemas = array_merge( $wgEventLoggingSchemas, $schemas );

		$modules = array();
		foreach ( $schemas as $schemaName => $rev ) {
			$modules[ "schema.$schemaName" ] = array(
				'class'    => 'ResourceLoaderSchemaModule',
				'schema'   => $schemaName,
				'revision' => $rev,
			);
		}
		$resourceLoader->register( $modules );
	}

	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgEventLoggingBaseUri;

		$vars[ 'wgEventLoggingBaseUri' ] = $wgEventLoggingBaseUri;
		return true;
	}

	/**
	 * @param array &$testModules
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( &$testModules, &$resourceLoader ) {
		$testModules[ 'qunit' ][ 'ext.eventLogging.tests' ] = array(
			'scripts'       => array( 'tests/ext.eventLogging.tests.js' ),
			'dependencies'  => array( 'ext.eventLogging' ),
			'localBasePath' => __DIR__ . '/..',
			'remoteExtPath' => 'EventLogging',
		);
		return true;
	}
}
