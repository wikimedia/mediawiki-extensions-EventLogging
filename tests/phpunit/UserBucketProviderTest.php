<?php

use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketProvider;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;

/**
 * @covers \MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketProvider
 */
class UserBucketProviderTest extends MediaWikiIntegrationTestCase {

	public function userProvider() {
		return [
			[ null, null ],
			[ 0, '0 edits' ],
			[ 4, '1-4 edits' ],
			[ 1000, '1000+ edits' ],
		];
	}

	/**
	 * @dataProvider userProvider
	 */
	public function testGetUserEditCountBucket( ?int $editCount, ?string $expectedBucket ) {
		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )
			->willReturn( $editCount );
		$this->setService( 'UserEditTracker', $userEditTracker );
		$user = $this->createMock( UserIdentity::class );

		$result = UserBucketProvider::getUserEditCountBucket( $user );

		$this->assertSame( $expectedBucket, $result );
	}

}
