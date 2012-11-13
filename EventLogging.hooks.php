<?php
/**
 * Hooks for EventLogging extension
 *
 * @file
 * @ingroup Extensions
 */

class EventLoggingHooks {

	public static function onSetup() {
		global $wgEventLoggingBaseUri;
		if ( !is_string( $wgEventLoggingBaseUri ) ) {
			$wgEventLoggingBaseUri = false;
			wfDebugLog( 'EventLogging', 'wgEventLoggingBaseUri is not correctly set.' );
		} elseif ( substr( $wgEventLoggingBaseUri, -1 ) === '?' ) {
			// Backwards compatibility: Base uri used to have to end with "?"
			// as the query string as appended directly.
			$wgEventLoggingBaseUri = substr( $wgEventLoggingBaseUri, 0, -1 );
		}
	}

	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgEventLoggingBaseUri;
		$vars[ 'wgEventLoggingBaseUri' ] = $wgEventLoggingBaseUri;
		return true;
	}

	/**
	 * @param array &$testModules
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( array &$testModules, ResourceLoader &$resourceLoader ) {
		$testModules[ 'qunit' ][ 'ext.EventLogging.tests' ] = array(
			'scripts'       => array( 'tests/ext.EventLogging.tests.js' ),
			'dependencies'  => array( 'ext.EventLogging' ),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'EventLogging',
		);
		return true;
	}

}
