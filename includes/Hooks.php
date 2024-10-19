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

use MediaWiki\Config\Config;
use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use Skin;

class Hooks implements
	CanonicalNamespacesHook,
	BeforePageDisplayHook,
	GetPreferencesHook
{

	/**
	 * The list of stream config settings that should be sent to the client as part of the
	 * ext.eventLogging RL module.
	 *
	 * @var string[]
	 */
	private const STREAM_CONFIG_SETTINGS_ALLOWLIST = [
		'sample',
		'producers',
	];

	private UserOptionsLookup $userOptionsLookup;

	public function __construct(
		UserOptionsLookup $userOptionsLookup
	) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Emit a debug log message for each invalid or unset
	 * configuration variable (if any).
	 */
	public static function onSetup(): void {
		global $wgEventLoggingBaseUri, $wgEventLoggingStreamNames;

		if ( $wgEventLoggingBaseUri === false ) {
			EventLogging::getLogger()->debug( 'wgEventLoggingBaseUri has not been configured.' );
		}

		if ( $wgEventLoggingStreamNames !== false && !is_array( $wgEventLoggingStreamNames ) ) {
			EventLogging::getLogger()->debug(
				'wgEventLoggingStreamNames is configured but is not a list of stream names'
			);

			$wgEventLoggingStreamNames = [];
		}
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.eventLogging' ] );

		if ( $this->userOptionsLookup->getIntOption( $out->getUser(), 'eventlogging-display-web' )
			|| $this->userOptionsLookup->getIntOption( $out->getUser(), 'eventlogging-display-console' )
		) {
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
	 * @return array
	 */
	private static function getSchemas() {
		global $wgEventLoggingSchemas;

		$extRegistry = ExtensionRegistry::getInstance();
		$schemas = $wgEventLoggingSchemas + $extRegistry->getAttribute( 'EventLoggingSchemas' );

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
			'streamConfigs' => self::loadEventStreamConfigs()
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
	public function onGetPreferences( $user, &$preferences ): void {
		// See 'ext.eventLogging.debug' module.
		$preferences['eventlogging-display-web'] = [
			'type' => 'api',
		];
		$preferences['eventlogging-display-console'] = [
			'type' => 'api',
		];
	}

	public function onCanonicalNamespaces( &$namespaces ): void {
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
	 * @return array|bool Selected stream name -> stream configs
	 */
	private static function loadEventStreamConfigs() {
		// FIXME: Does the following need to be logged?
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventStreamConfig' ) ) {
			EventLogging::getLogger()->debug( 'EventStreamConfig is not installed' );
			return false;
		}

		$streamConfigs = MediaWikiServices::getInstance()->getService( 'EventLogging.StreamConfigs' );

		if ( $streamConfigs === false ) {
			return false;
		}

		// Only send stream config settings that should be sent to the client as part of the
		// ext.eventLogging RL module.
		$settingsAllowList = array_flip( self::STREAM_CONFIG_SETTINGS_ALLOWLIST );

		return array_map(
			static function ( $streamConfig ) use ( $settingsAllowList ) {
				return array_intersect_key( $streamConfig, $settingsAllowList );
			},
			$streamConfigs
		);
	}
}
