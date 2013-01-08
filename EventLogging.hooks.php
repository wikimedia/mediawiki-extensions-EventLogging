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

class EventLoggingHooks {

	/**
	 * Emit a debug log message for each invalid or unset
	 * configuration variable (if any).
	 */
	public static function onSetup() {
		global $wgMemCachedServers;

		foreach( array(
			'wgEventLoggingBaseUri',
			'wgEventLoggingDBname',
			'wgEventLoggingFile',
			'wgEventLoggingSchemaIndexUri'
		) as $configVar ) {
			if ( $GLOBALS[ $configVar ] === false ) {
				wfDebugLog( 'EventLogging', "$configVar has not been configured." );
			}
		}

		if ( !count( $wgMemCachedServers ) ) {
			wfDebugLog( 'EventLogging', 'EventLogging requires memcached, '
				. 'and no memcached servers are defined.' );
		}
	}

	/**
	 * Log a server-side account_create event.
	 * @param $user object  the User object that was created.
	 * @param $byEmail boolean  the form has a [By e-mail] button
	 */
	public static function onAddNewAccount( $user, $byEmail ) {
		global $wgRequest;
		// $wgUser may be different from the $user param, e.g. it can be
		// a logged-in user creating this new account.
		global $wgUser;

		$userId = $user->getId();
		$creatorUserId = $wgUser->getId();

		// Detect when an anonymous user creates her own account (the common
		// operation). The implementation in SpecialUserlogin.php sets
		// $wgUser to the new $user.
		$selfMade = ( $userId > 0 && $userId === $creatorUserId );

		if ( class_exists( 'MobileContext' ) ) {
			$mobile = MobileContext::singleton()->shouldDisplayMobileView();
		}

		efLogServerSideEvent( 'account_create', array (
			'user_id'         => (int) $userId,
			'timestamp'       => (int) wfTimestamp( TS_UNIX, 0 ),
			'username'        => (string) $user->getName(),
			'self_made'       => (bool) $selfMade,
			'creator_user_id' => (int) $creatorUserId,
			'by_email'        => (bool) $byEmail,
			'userbuckets'     => (string) $wgRequest->getCookie( 'userbuckets', '' ),
			'mw_user_token'   => (string) $wgRequest->getCookie( 'mediaWiki.user.id', '' ),
			'host'            => (string) $_SERVER[ 'HTTP_HOST' ],
			'displayMobile'   => (boolean) $mobile,
			'version'         => 9	// matches VERSION in E3Experiments accountCreationUX.js
		) );
		// Note the above specifies a blank prefix to match the JavaScript cookie.

		return true;
	}


	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgEventLoggingBaseUri;

		$vars[ 'wgEventLoggingBaseUri' ] = $wgEventLoggingBaseUri;
		return true;
	}


	/**
	 * @param array &$testModules
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( &$testModules, &$resourceLoader ) {
		$testModules[ 'qunit' ][ 'ext.eventLogging.tests' ] = array(
			'scripts'       => array( 'tests/ext.eventLogging.tests.js' ),
			'dependencies'  => array( 'ext.eventLogging' ),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'EventLogging',
		);
		return true;
	}
}
