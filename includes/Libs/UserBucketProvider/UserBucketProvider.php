<?php

namespace MediaWiki\Extension\EventLogging\Libs\UserBucketProvider;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * Utilities for getting low-granularity segmentation of users.
 */
class UserBucketProvider {

	/**
	 * Find the coarse bucket corresponding to a user's edit count.
	 *
	 * @see UserBucketService::bucketEditCount()
	 * @see UserBucketService::getUserEditCountBucket()
	 *
	 * @param UserIdentity $user provides raw edit count
	 * @return string|null Bucket identifier, or null for anonymous users.
	 *
	 * @deprecated since 1.40. Use an injected instance of `UserBucketService` or the
	 *  `EventLogging.UserBucketService` service instead
	 */
	public static function getUserEditCountBucket( UserIdentity $user ): ?string {
		return MediaWikiServices::getInstance()
			->getService( 'EventLogging.UserBucketService' )
			->getUserEditCountBucket( $user );
	}
}

class_alias( UserBucketProvider::class, 'UserBucketProvider' );
