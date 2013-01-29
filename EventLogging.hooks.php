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

		foreach ( array(
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
	 * @param $user object: The User object that was created.
	 * @param $byEmail boolean The form has a [By e-mail] button.
	 */
	public static function onAddNewAccount( $user, $byEmail ) {
		global $wgRequest, $wgUser;

		$userId = $user->getId();
		$creatorUserId = $wgUser->getId();

		// MediaWiki allows existing users to create accounts on behalf
		// of others. In such cases the ID of the newly-created user and
		// the ID of the user making this web request are different.
		$isSelfMade = ( $userId && $userId === $creatorUserId );

		$mobile = class_exists( 'MobileContext' ) &&
			MobileContext::singleton()->shouldDisplayMobileView();

		$event = array (
			'token'         => (string) $wgRequest->getCookie( 'mediaWiki.user.id', '' ),
			'userId'        => (int) $userId,
			'userName'      => (string) $user->getName(),
			'isSelfMade'    => (bool) $isSelfMade,
			'userBuckets'   => (string) $wgRequest->getCookie( 'userbuckets', '' ),
			'displayMobile' => (bool) $mobile,
		);
		$returnTo = $wgRequest->getVal( 'returnto' );
		if ( $returnTo !== null ) {
			$event['returnTo'] = $returnTo;
		}
		$returnToQuery = $wgRequest->getVal( 'returntoquery' );
		if ( $returnToQuery !== null ) {
			$event['returnToQuery'] = $returnToQuery;
		}

		efLogServerSideEvent( 'ServerSideAccountCreation', 5150394, $event );

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
