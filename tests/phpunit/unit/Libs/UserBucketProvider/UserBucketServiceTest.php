<?php

namespace MediaWiki\Extension\EventLogging\Test\Libs\UserBucketProvider;

use Generator;
use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService
 */
class UserBucketServiceTest extends TestCase {

	public function provideBucketEditCount(): Generator {
		yield [ 0, '0 edits' ];
		yield [ 1, '1-4 edits' ];
		yield [ 4, '1-4 edits' ];
		yield [ 5, '5-99 edits' ];
		yield [ 99, '5-99 edits' ];
		yield [ 100, '100-999 edits' ];
		yield [ 999, '100-999 edits' ];
		yield [ 1000, '1000+ edits' ];
	}

	/**
	 * @dataProvider provideBucketEditCount
	 */
	public function testBucketEditCount( int $userEditCount, string $expectedUserEditCountBucket ) {
		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userBucketService = new UserBucketService( $userEditTracker );

		$this->assertEquals(
			$expectedUserEditCountBucket,
			$userBucketService->bucketEditCount( $userEditCount )
		);
	}

	public function provideGetUserEditCountBucket(): Generator {
		yield [
			UserIdentityValue::newRegistered( 1234567890, 'Phuedx' ),
			20,
			'5-99 edits',
		];
		yield [
			UserIdentityValue::newAnonymous( 'Anon. Y. Mouse' ),
			null,
			null
		];
	}

	/**
	 * @dataProvider provideGetUserEditCountBucket
	 */
	public function testGetUserEditCountBucket(
		UserIdentityValue $user,
		?int $userEditCount,
		?string $expectedUserEditCountBucket
	) {
		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->expects( $this->once() )
			->method( 'getUserEditCount' )
			->with( $user )
			->will( $this->returnValue( $userEditCount ) );

		$userBucketService = new UserBucketService( $userEditTracker );

		$this->assertEquals( $expectedUserEditCountBucket, $userBucketService->getUserEditCountBucket( $user ) );
	}
}
