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

use MediaWiki\MediaWikiServices;

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
	 * Return all schemas registered in extension.json EventLoggingSchemas and
	 * PHP $wgEventLoggingSchemas.  The returned array will map from schema name
	 * to either MediaWiki (metawiki) revision id, or to a relative schema URI
	 * for forward compatibility with Event Platform.
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
		$schemas = $wgEventLoggingSchemas + $extRegistry->getAttribute( 'EventLoggingSchemas' );

		Hooks::run( 'EventLoggingRegisterSchemas', [ &$schemas ], '1.32' );

		return $schemas;
	}

	public static function getModuleData( ResourceLoaderContext $context, Config $config ) {
		return [
			'baseUrl' => $config->get( 'EventLoggingBaseUri' ),
			'schemasInfo' => self::getSchemas(),
			'serviceUri' => $config->get( 'EventLoggingServiceUri' ),
			'queueLingerSeconds' => $config->get( 'EventLoggingQueueLingerSeconds' ),
			'streamConfigs' => self::loadEventStreamConfigs( $config )
		];
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

	/**
	 * Uses the EventStreamConfig extension to return a stream configs map
	 * (stream name -> config).  The target stream configs to export are
	 * selected using the $wgEventLoggingStreamNames MW config variable.
	 * This is expected to be a list of stream names that are defined
	 * in $wgEventStreams.
	 *
	 * EventLogging uses this within the ./data.json data file
	 * from which it loads and configures all of the streams and stream
	 * configs to which it is allowed to submit events.
	 *
	 * This function returns an array mapping explicit stream names
	 * to their configurations.
	 *
	 * NOTE: We need a list of target streams to get configs for.
	 * $wgEventStreams may not explicitly define all stream names;
	 * it supports matching stream names by regexes.  We need to
	 * give the EventStreamConfig StreamConfigs->get function
	 * a list of streams to search for in $wgEventStreams.
	 * $wgEventLoggingStreamNames is that list.
	 *
	 * @param \Config $config
	 *
	 * @return array Selected stream name -> stream configs
	 */
	private static function loadEventStreamConfigs(
		\Config $config
	): array {
		$streamConfigs = MediaWikiServices::getInstance()->getService(
			'EventStreamConfig.StreamConfigs'
		);

		$targetStreams = $config->get( 'EventLoggingStreamNames' );

		if ( !is_array( $targetStreams ) ) {
			throw new RuntimeException(
				'Expected $wgEventLoggingStreamNames to be a list of stream names, got ' .
				$targetStreams
			);
		}

		return $streamConfigs->get( $targetStreams, false );
	}
}
