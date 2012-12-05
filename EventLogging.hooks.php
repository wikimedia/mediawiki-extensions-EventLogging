<?php
/**
 * Hooks for EventLogging extension
 *
 * @file
 *
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class EventLoggingHooks {

	// Query strings are terminated with a semicolon to help identify
	// URIs that were truncated in transmit.
	const QS_TERMINATOR = ';';


	/**
	 * Emit a debug log message for each invalid or unset
	 * configuration variable (if any).
	 */
	public static function onSetup() {
		global $wgMemCachedServers;

		foreach( array(
			'wgEventLoggingBaseUri',
			'wgEventLoggingFile',
			'wgEventLoggingDBname',
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
	 * Generate and log an edit event on PageContentSaveComplete.
	 *
	 * @return bool
	 */
	public static function onPageContentSaveComplete( $article, $user,
		$content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status,
		$baseRevId ) {

		if ( $revision === NULL ) {
			// When an editor saves an article without having made any
			// changes, no revision is created, but ArticleSaveComplete
			// still gets called.
			return true;
		}

		$title = $article->getTitle();

		$event = array(
			'articleId' => $title->mArticleID,
			'api'       => defined( 'MW_API' ),
			'title'     => $title->mTextform,
			'namespace' => $title->getNamespace(),
			'created'   => is_null( $revision->getParentId() ),
			'summary'   => $summary,
			'timestamp' => $revision->getTimestamp(),
			'minor'     => $isMinor,
			'loggedIn'  => $user->isLoggedIn()
		);

		if ( $user->isLoggedIn() ) {
			$event += array(
				'userId'     => $user->getId(),
				'editCount'  => $user->getEditCount(),
				'registered' => wfTimestamp( TS_UNIX, $user->getRegistration() )
			);
		}

		wfLogServerSideEvent( 'edit', $event );
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
