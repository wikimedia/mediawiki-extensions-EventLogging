<?php
/**
 * Hooks for EventLogging extension
 *
 * @file
 * @ingroup Extensions
 */

class EventLoggingHooks {

	public static function onSetup() {
		global $wgEventLoggingBaseUri;
		if ( !is_string( $wgEventLoggingBaseUri ) ) {
			$wgEventLoggingBaseUri = false;
			wfDebugLog( 'EventLogging', 'wgEventLoggingBaseUri is not correctly set.' );
		} elseif ( substr( $wgEventLoggingBaseUri, -1 ) === '?' ) {
			// Backwards compatibility: Base uri used to have to end with "?"
			// as the query string as appended directly.
			$wgEventLoggingBaseUri = substr( $wgEventLoggingBaseUri, 0, -1 );
		}
	}

	private static $isAPI = false;

	const QUERYSTRING_TERMINATOR = '&_=_';

	/**
	 * Write an event to a file descriptor or socket.
	 *
	 * Takes an event ID and an event, encodes it as query string,
	 * and writes it to the UDP / TCP address or file specified by
	 * $wgEventLoggingFile. If $wgEventLoggingFile is not set, returns
	 * false without logging anything.
	 *
	 * @see wfErrorLog()
	 *
	 * @param $eventId string Event schema ID.
	 * @param $event array Map of event keys/vals.
	 * @return bool Whether the event was logged.
	 */
	private static function writeEvent( $eventId, $event ) {
		global $wgEventLoggingFile, $wgDBname;

		if ( !$wgEventLoggingFile ) {
			return false;
		}

		$queryString = http_build_query( array(
			'_db' => $wgDBname,
			'_id' => $eventId
		) + $event ) . self::QUERYSTRING_TERMINATOR;

		wfErrorLog( '?' . $queryString . "\n", $wgEventLoggingFile );
		return true;
	}


	/**
	 * APIEditBeforeSave hook. Set a boolean class property marking
	 * this edit as originating in the API.
	 *
	 * @param $editPage
	 * @param $text
	 * @param &$resultArr
	 * @return bool
	 */
	public static function onAPIEditBeforeSave( $editPage, $text, &$resultArr ) {
		self::$isAPI = true;
		return true;
	}


	/**
	 * Generate and log an edit event on ArticleSaveComplete.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleSaveComplete
	 * @return boolean
	 */
	public static function onArticleSaveComplete( WikiPage &$article, User &$user, $text, $summary,
		$minoredit, $watchthis, $sectionanchor, &$flags, $revision, &$status, $baseRevId ) {

		if ( $revision === NULL ) {
			// When an editor saves an article without having made any
			// changes, no revision is created, but ArticleSaveComplete
			// still gets called.
			return true;
		}

		$title = $article->getTitle();

		$event = array(
			'articleId' => $title->mArticleID,
			'api'       => self::$isAPI,
			'title'     => $title->mTextform,
			'namespace' => $title->getNamespace(),
			'created'   => is_null( $revision->getParentId() ),
			'summary'   => $summary,
			'timestamp' => $revision->getTimestamp(),
			'minor'     => $minoredit,
			'loggedIn'  => $user->isLoggedIn()
		);

		if ( $user->isLoggedIn() ) {
			$event += array(
				'userId'     => $user->getId(),
				'editCount'  => $user->getEditCount(),
				'registered' => wfTimestamp( TS_UNIX, $user->getRegistration() )
			);
		}

		self::writeEvent( 'edit', $event );
		return true;
	}


	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgEventLoggingBaseUri;

		$vars[ 'wgEventLoggingBaseUri' ] = $wgEventLoggingBaseUri;
		return true;
	}

	/**
	 * @param array &$testModules
	 * @param ResourceLoader $resourceLoader
	 * @return bool
	 */
	public static function onResourceLoaderTestModules( array &$testModules, ResourceLoader &$resourceLoader ) {
		$testModules[ 'qunit' ][ 'ext.EventLogging.tests' ] = array(
			'scripts'       => array( 'tests/ext.EventLogging.tests.js' ),
			'dependencies'  => array( 'ext.EventLogging' ),
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'EventLogging',
		);
		return true;
	}

}
