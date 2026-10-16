<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventLogging\EventSubmitter\EventBusEventSubmitter;
use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use MediaWiki\Extension\EventLogging\EventSubmitter\NullEventSubmitter;
use MediaWiki\Extension\EventLogging\JsonSchemaHooksHelper;
use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService;
use MediaWiki\Extension\EventLogging\MetricsPlatform\ContextAttributesFactory;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;

/** @phpcs-require-sorted-array */
return [
	'EventLogging.ContextAttributesFactory' =>
		static function ( MediaWikiServices $services ): ContextAttributesFactory {
			return new ContextAttributesFactory(
				$services->getMainConfig(),
				ExtensionRegistry::getInstance(),
				$services->getNamespaceInfo(),
				$services->getRestrictionStore(),
				$services->getUserOptionsLookup(),
				$services->getContentLanguage(),
				$services->getUserGroupManager(),
				$services->getLanguageConverterFactory(),
				$services->get( 'EventLogging.UserBucketService' )
			);
		},
	// @deprecated since 1.48, use TestKitchen.InstrumentManager and Instrument::send() instead.
	// @see https://wikitech.wikimedia.org/wiki/Test_Kitchen/SDK/PHP_SDK
	'EventLogging.EventSubmitter' => static function ( MediaWikiServices $services ): EventSubmitter {
		$logger = $services->getService( 'EventLogging.Logger' );

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventBus' ) ) {
			$logger->warning( 'EventBus is not installed' );

			return new NullEventSubmitter();
		}

		return new EventBusEventSubmitter( $logger, $services->getMainConfig() );
	},
	'EventLogging.JsonSchemaHooksHelper' => static function ( MediaWikiServices $services ): JsonSchemaHooksHelper {
		return new JsonSchemaHooksHelper(
			new ServiceOptions( JsonSchemaHooksHelper::CONSTRUCTOR_OPTIONS, $services->getMainConfig() )
		);
	},
	'EventLogging.Logger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'EventLogging' );
	},
	// @deprecated since 1.48, use TestKitchen.InstrumentManager and Instrument::send() instead.
	// @see https://wikitech.wikimedia.org/wiki/Test_Kitchen/SDK/PHP_SDK
	'EventLogging.MetricsClientFactory' => static function ( MediaWikiServices $services ): MetricsClientFactory {
		return new MetricsClientFactory(
			$services->getService( 'EventLogging.ContextAttributesFactory' ),
			$services->getService( 'EventLogging.EventSubmitter' ),
			$services->getService( 'EventLogging.StreamConfigs' ),
			$services->getService( 'EventLogging.Logger' )
		);
	},
	'EventLogging.StreamConfigs' => static function ( MediaWikiServices $services ) {
		if ( !$services->hasService( 'EventStreamConfig.StreamConfigs' ) ) {
			return false;
		}

		$eventLoggingStreamNames = $services->getMainConfig()
			->get( 'EventLoggingStreamNames' );

		if ( $eventLoggingStreamNames === false ) {
			return false;
		}

		/** @var \MediaWiki\Extension\EventStreamConfig\StreamConfigs $streamConfigs */
		$streamConfigs = $services->getService( 'EventStreamConfig.StreamConfigs' );

		return $streamConfigs->get( $eventLoggingStreamNames );
	},
	'EventLogging.UserBucketService' => static function ( MediaWikiServices $services ): UserBucketService {
		return new UserBucketService( $services->getUserEditTracker() );
	}
];
