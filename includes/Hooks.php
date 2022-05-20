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

namespace MediaWiki\Extension\EventLogging;

use Config;
use ExtensionRegistry;
use Hooks as MWHooks;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;
use OutputPage;
use RuntimeException;
use Skin;
use User;

class Hooks {

	/**
	 * Emit a debug log message for each invalid or unset
	 * configuration variable (if any).
	 */
	public static function onSetup(): void {
		global $wgEventLoggingBaseUri;
		if ( $wgEventLoggingBaseUri === false ) {
			EventLogging::getLogger()->debug( 'wgEventLoggingBaseUri has not been configured.' );
		}
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		$out->addModules( [ 'ext.eventLogging' ] );

		$services = MediaWikiServices::getInstance();
		if ( method_exists( $services, 'getUserOptionsLookup' ) ) {
			// MW 1.35+
			$lookup = $services->getUserOptionsLookup();
			$eventloggingDebugMode = $lookup->getIntOption( $out->getUser(), 'eventlogging-display-web' )
				|| $lookup->getIntOption( $out->getUser(), 'eventlogging-display-console' );
		} else {
			// Avoid Phan warning about User::getIntOption, removed in MW 1.37
			$eventloggingDebugMode = (int)$out->getUser()->getOption( 'eventlogging-display-web' )
				|| (int)$out->getUser()->getOption( 'eventlogging-display-console' );
		}
		if ( $eventloggingDebugMode ) {
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

		MWHooks::run( 'EventLoggingRegisterSchemas', [ &$schemas ], '1.32' );

		return $schemas;
	}

	/**
	 * Returns an object with EventLogging specific configuration extracted from
	 * MW Config and from extension attributes.
	 *
	 * @param Config $config
	 * @return array
	 */
	public static function getEventLoggingConfig( Config $config ) {
		return [
			'baseUrl' => $config->get( 'EventLoggingBaseUri' ),
			'schemasInfo' => self::getSchemas(),
			'serviceUri' => $config->get( 'EventLoggingServiceUri' ),
			'queueLingerSeconds' => $config->get( 'EventLoggingQueueLingerSeconds' ),
			// If this is false, EventLogging will not use stream config.
			'streamConfigs' => self::loadEventStreamConfigs( $config )
		];
	}

	/**
	 * Wraps getEventLoggingConfig for use with ResourceLoader.
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function getModuleData( RL\Context $context, Config $config ) {
		return self::getEventLoggingConfig( $config );
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetPreferences( User $user, array &$preferences ): void {
		// See 'ext.eventLogging.debug' module.
		$preferences['eventlogging-display-web'] = [
			'type' => 'api',
		];
		$preferences['eventlogging-display-console'] = [
			'type' => 'api',
		];
	}

	public static function onCanonicalNamespaces( &$namespaces ): void {
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
	 * @param Config $config
	 * @return array|bool Selected stream name -> stream configs
	 */
	private static function loadEventStreamConfigs( Config $config ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventStreamConfig' ) ) {
			EventLogging::getLogger()->debug( 'EventStreamConfig is not installed' );
			return false;
		}

		$streamConfigs = MediaWikiServices::getInstance()->getService(
			'EventStreamConfig.StreamConfigs'
		);

		$targetStreams = $config->get( 'EventLoggingStreamNames' );
		if ( $targetStreams === false ) {
			return false;
		}
		if ( !is_array( $targetStreams ) ) {
			throw new RuntimeException(
				'Expected $wgEventLoggingStreamNames to be a list of stream names, got ' .
				$targetStreams
			);
		}

		return $streamConfigs->get( $targetStreams, false );
	}
}
