<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [

	'EventServiceClient' => function ( MediaWikiServices $services ): EventServiceClient {
		$config = $services->getMainConfig();
		$streamNames = $config->get( 'EventLoggingStreamNames' );

		if ( $streamNames === false ) {
			$streamConfigs = false;
		} else {
			$streamConfigs = $services->getService( 'EventStreamConfig.StreamConfigs' )
				->get( $streamNames, true );
		}

		$client = new EventServiceClient(
			$services->getHttpRequestFactory(),
			$streamConfigs,
			$serviceUri = $config->get( 'EventLoggingServiceUri' )
		);
		$client->setLogger( LoggerFactory::getInstance( 'EventLogging' ) );
		return $client;
	},

	'UserBucketProvider' => function ( MediaWikiServices $services ): UserBucketProvider {
		return new UserBucketProvider();
	},

];
