<?php
/**
 * Hooks for EventLogging extension
 *
 * @file
 * @ingroup Extensions
 */

class EventLoggingHooks {

	/**
	 * ResourceLoaderGetConfigVars hook
	 * Sends down _static_ config vars to JavaScript
	 *
	 * @param $vars array
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgEventLoggingBaseUri;
		if ( is_string( $wgEventLoggingBaseUri ) ) {
			$vars[ 'wgEventLoggingBaseUri' ] = $wgEventLoggingBaseUri;
		} else {
			wfDebugLog( 'EventLogging', 'wgEventLoggingBaseUri is not set' ); 
			$vars[ 'wgEventLoggingBaseUri' ] = '';

		}
		return true;
	}

	/**
	 * ResourceLoaderTestModules hook handler.
	 * @param $testModules: array of javascript testing modules. 'qunit' is fed using tests/qunit/QUnitTestResources.php.
	 * @param $resourceLoader object
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( &$testModules, &$resourceLoader ) {
		$testModules[ 'qunit' ][ 'ext.EventLogging.tests' ] = array(
			'scripts'       => array( 'tests/ext.EventLogging.tests.js' ),
			'dependencies'  => array( 'ext.EventLogging' ),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'EventLogging',
		);
		return true;
	}

}
