<?php

use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService;
use MediaWiki\MediaWikiServices;

return [
	'EventLogging.UserBucketService' => static function ( MediaWikiServices $services ): UserBucketService {
		return new UserBucketService( $services->getUserEditTracker() );
	},
];
