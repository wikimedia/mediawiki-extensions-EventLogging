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
		foreach ( [
			'wgEventLoggingBaseUri',
			'wgEventLoggingSchemaApiUri',
		] as $configVar ) {
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
		$out->addModules( [ 'ext.eventLogging.subscriber' ] );

		if ( $out->getUser()->getIntOption( 'eventlogging-display-web' ) ) {
			$out->addModules( 'ext.eventLogging.debug' );
		}
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * Allows extensions to register schema modules client side. To log events for
	 * schemas that have been declared in this fashion, use mw#track.
	 *
	 * @since 1.32 the EventLoggingRegisterSchemas hook is deprecated. Register
	 * schemas in the extension.json file for your extension instead.
	 *
	 * @par Example using extension.json (manifest_version 2)
	 * @code
	 * {
	 *     "attributes": {
	 *         "EventLogging": {
	 *             "Schemas": {
	 *                 "MultimediaViewerNetworkPerformance": 7917896
	 *             }
	 *         }
	 *     }
	 * }
	 * @endcode
	 *
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader &$resourceLoader ) {
		global $wgEventLoggingSchemas;

		$extRegistry = ExtensionRegistry::getInstance();

		$schemas = $extRegistry->getAttribute( 'EventLoggingSchemas' ) + $wgEventLoggingSchemas;

		Hooks::run( 'EventLoggingRegisterSchemas', [ &$schemas ], '1.32' );

		$modules = [];
		foreach ( $schemas as $schemaName => $rev ) {
			$modules[ "schema.$schemaName" ] = [
				'class'    => ResourceLoaderSchemaModule::class,
				'schema'   => $schemaName,
				'revision' => $rev,
				'internal' => true,
			];
		}
		$resourceLoader->register( $modules );
	}

	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgEventLoggingBaseUri, $wgEventLoggingSchemaApiUri;

		$vars[ 'wgEventLoggingBaseUri' ] = $wgEventLoggingBaseUri;
		$vars[ 'wgEventLoggingSchemaApiUri' ] = $wgEventLoggingSchemaApiUri;
		return true;
	}

	/**
	 * @param array &$testModules
	 * @param ResourceLoader &$resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( &$testModules, &$resourceLoader ) {
		$testModules[ 'qunit' ][ 'ext.eventLogging.tests' ] = [
			'scripts'       => [ 'tests/ext.eventLogging.tests.js' ],
			'dependencies'  => [ 'ext.eventLogging' ],
			'localBasePath' => __DIR__ . '/..',
			'remoteExtPath' => 'EventLogging',
		];
		return true;
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		// See 'ext.eventLogging.debug' module.
		$preferences['eventlogging-display-web'] = [
			'type' => 'api',
		];
	}

	public static function onCanonicalNamespaces( &$namespaces ) {
		if ( JsonSchemaHooks::isSchemaNamespaceEnabled() ) {
			$namespaces[ NS_SCHEMA ] = 'Schema';
			$namespaces[ NS_SCHEMA_TALK ] = 'Schema_talk';
		}

		return true;
	}
}
