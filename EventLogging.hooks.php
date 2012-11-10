<?php
/**
 * Hooks for EventLogging extension
 *
 * @file
 * @ingroup Extensions
 */

class EventLoggingHooks {

	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgEventLoggingBaseUri;
		if ( !is_string( $wgEventLoggingBaseUri ) ) {
			wfDebugLog( 'EventLogging', 'wgEventLoggingBaseUri is not set' ); 
			$vars[ 'wgEventLoggingBaseUri' ] = '';
			return true;
		}
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
