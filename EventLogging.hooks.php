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
		global $wgMemc;

		if ( get_class( $wgMemc ) === 'EmptyBagOStuff' ) {
			wfDebugLog( 'EventLogging', 'No suitable memcached driver found.' );
		}

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
	}

	/**
	 * @param $user object: The User object that was created.
	 * @param $byEmail boolean The form has a [By e-mail] button.
	 * @return bool True
	 */
	public static function onAddNewAccount( $user, $byEmail ) {
		global $wgRequest, $wgUser;

		$userId = $user->getId();
		$creatorUserId = $wgUser->getId();

		// MediaWiki allows existing users to create accounts on behalf
		// of others. In such cases the ID of the newly-created user and
		// the ID of the user making this web request are different.
		$isSelfMade = ( $userId && $userId === $creatorUserId );

		$displayMobile = class_exists( 'MobileContext' ) &&
			MobileContext::singleton()->shouldDisplayMobileView();

		$event = array(
			'token' => $wgRequest->getCookie( 'mediaWiki.user.id', '', '' ),
			'userId' => $userId,
			'userName' => $user->getName(),
			'isSelfMade' => $isSelfMade,
			'userBuckets' => $wgRequest->getCookie( 'userbuckets', '', '' ),
			'displayMobile' => $displayMobile,
		);

		$returnTo = $wgRequest->getVal( 'returnto' );
		if ( $returnTo !== null ) {
			$event[ 'returnTo' ] = $returnTo;
		}

		$returnToQuery = $wgRequest->getVal( 'returntoquery' );
		if ( $returnToQuery !== null ) {
			$event[ 'returnToQuery' ] = $returnToQuery;
		}

		efLogServerSideEvent( 'ServerSideAccountCreation', 5233795, $event );
		return true;
	}

	/**
	 * Log server-side event on successful page edit.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 * @see https://meta.wikimedia.org/wiki/Schema:PageContentSaveComplete
	 */
	public static function onPageContentSaveComplete( $article, $user, $content, $summary,
		$isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId ) {

		if ( $revision ) {
			$event = array( 'revisionId' => $revision->getId() );
			if ( isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
				$event[ 'userAgent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
			}
			efLogServerSideEvent( 'PageContentSaveComplete', 5303086, $event );
		}
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
