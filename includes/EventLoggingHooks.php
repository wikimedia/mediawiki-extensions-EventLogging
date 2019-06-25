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
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$out->addModules( [ 'ext.eventLogging' ] );

		if ( $out->getUser()->getIntOption( 'eventlogging-display-web' ) ) {
			$out->addModules( 'ext.eventLogging.debug' );
		}
	}

	/**
	 * Return all schemas with the requested revision id
	 * TODO: what happens when two extensions register the same schema with a different revision?
	 *
	 * @since 1.32 the EventLoggingRegisterSchemas hook is deprecated. Register
	 * schemas in the extension.json file for your extension instead.
	 *
	 * @return array
	 */
	private static function getSchemas() {
		global $wgEventLoggingSchemas;

		$extRegistry = ExtensionRegistry::getInstance();
		$schemas = $extRegistry->getAttribute( 'EventLoggingSchemas' ) + $wgEventLoggingSchemas;

		Hooks::run( 'EventLoggingRegisterSchemas', [ &$schemas ], '1.32' );

		return $schemas;
	}

	/**
	 * @param array &$vars
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgEventLoggingSchemaApiUri;

		$vars[ 'wgEventLoggingSchemaApiUri' ] = $wgEventLoggingSchemaApiUri;
		$vars[ 'wgEventLoggingSchemaRevision' ] = self::getSchemas();
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
	}
}
