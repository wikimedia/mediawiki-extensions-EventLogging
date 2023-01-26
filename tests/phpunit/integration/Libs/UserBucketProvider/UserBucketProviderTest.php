<?php

namespace phpunit\integration\Libs\UserBucketProvider;

use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketProvider;
use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketProvider
 */
class UserBucketProviderTest extends MediaWikiIntegrationTestCase {
	public function testGetUserEditCountBucket(): void {
		$user = new UserIdentityValue( 1, 'Admin' );

		$userBucketService = $this->createMock( UserBucketService::class );
		$userBucketService->expects( $this->once() )
			->method( 'getUserEditCountBucket' )
			->with( $user );

		$this->setService( 'EventLogging.UserBucketService', $userBucketService );

		UserBucketProvider::getUserEditCountBucket( $user );
	}
}
