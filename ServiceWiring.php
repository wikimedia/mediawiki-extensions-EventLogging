<?php

use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService;
use MediaWiki\MediaWikiServices;

return [
	'EventLogging.UserBucketService' => static function ( MediaWikiServices $services ): UserBucketService {
		return new UserBucketService( $services->getUserEditTracker() );
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

		return $streamConfigs->get( $eventLoggingStreamNames, true );
	},
];
