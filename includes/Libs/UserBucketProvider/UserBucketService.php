<?php
namespace MediaWiki\Extension\EventLogging\Libs\UserBucketProvider;

use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;

/**
 * A service class for getting low-granularity segmentation of users. Currently, users can be
 * segmented by their edit count.
 */
class UserBucketService {

	/**
	 * The user edit count for an anonymous user.
	 *
	 * To maintain backwards compatibility with existing clients, we use `null` rather than, say,
	 * `"N/A"`.
	 */
	public const ANONYMOUS_USER_EDIT_COUNT_BUCKET = null;

	/**
	 * @var UserEditTracker
	 */
	private $userEditTracker;

	/**
	 * @param UserEditTracker $userEditTracker
	 */
	public function __construct( UserEditTracker $userEditTracker ) {
		$this->userEditTracker = $userEditTracker;
	}

	/**
	 * Find the coarse bucket corresponding to an edit count.
	 *
	 * The buckets are as follows:
	 *
	 * * 0 edits
	 * * 1-4 edits
	 * * 5-99 edits
	 * * 100-999 edits
	 * * 1000+ edits
	 *
	 * These bucket labels are the current standard, but are subject to change in the future. They are usually safe
	 * to keep in sanitized streams and should remain so even if they are changed.
	 *
	 * Sites may override this service and define their own metrics buckets.  If we rely on coarse bucketing to
	 * protect user identity, it's important to not mix different bucketing thresholds, since the intersections
	 * can reveal more detail than intended.
	 *
	 * @param int $userEditCount
	 * @return string Bucket identifier
	 */
	public function bucketEditCount( int $userEditCount ): string {
		if ( $userEditCount >= 1000 ) {
			return '1000+ edits';
		}
		if ( $userEditCount >= 100 ) {
			return '100-999 edits';
		}
		if ( $userEditCount >= 5 ) {
			return '5-99 edits';
		}
		if ( $userEditCount >= 1 ) {
			return '1-4 edits';
		}
		return '0 edits';
	}

	/**
	 * Find the coarse bucket corresponding to the edit count of the given user.
	 *
	 * @param UserIdentity $user provides raw edit count
	 * @return string|null Bucket identifier, or `null` for anonymous users.
	 *
	 * @see UserBucketService::bucketEditCount
	 * @see UserBucketService::ANONYMOUS_USER_EDIT_COUNT_BUCKET
	 */
	public function getUserEditCountBucket( UserIdentity $user ): ?string {
		$userEditCount = $this->userEditTracker->getUserEditCount( $user );

		if ( $userEditCount === null ) {
			return self::ANONYMOUS_USER_EDIT_COUNT_BUCKET;
		}

		return $this->bucketEditCount( $userEditCount );
	}
}
