<?php
/**
 * EventLogging Extension for MediaWiki
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 * @license GPL v2 or later
 * @version 0.2
 */

// Credits

$wgExtensionCredits[ 'other' ][] = array(
	'path' => __FILE__,
	'name' => 'EventLogging',
	'author' => array(
		'Ori Livneh',
		'Timo Tijhof',
	),
	'version' => '0.2',
	'url' => 'https://www.mediawiki.org/wiki/Extension:EventLogging',
	'descriptionmsg' => 'eventlogging-desc'
);



// Configuration

/**
 * @var bool|string: Full URI or false if not set.
 * Events are logged to this end point as key-value pairs in the query
 * string. Must not contain a query string.
 *
 * @example string: '//log.example.org/event.gif'
 */
$wgEventLoggingBaseUri = false;

/**
 * @var bool|string: Filename, or TCP / UDP address; false if not set.
 * Server-side events will be logged to this location.
 *
 * @see wfErrorLog()
 *
 * @example string: 'udp://127.0.0.1:9000'
 * @example string: '/var/log/mediawiki/events.log'
 */
$wgEventLoggingFile = false;

/**
 * @var bool|string: Full URI or false if not set.
 * Canonical location of JSON data models. Will be fetched when the
 * models are not in memcached.
 */
$wgEventLoggingModelsUri = false;

/**
 * @var bool|string: Value of $wgDBname for the MediaWiki instance
 * housing data models; false if not set.
 */
$wgEventLoggingDBname = false;


// Helpers

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
 * @param $model string Event data model name.
 * @param $event array Map of event keys/vals.
 * @return bool Whether the event was logged.
 */
function wfLogServerSideEvent( $model, $event ) {
	global $wgEventLoggingFile, $wgDBname;

	if ( !$wgEventLoggingFile ) {
		return false;
	}

	$queryString = http_build_query( array(
		'_db' => $wgDBname,
		'_id' => $model
	) + $event ) . ';';

	wfErrorLog( '?' . $queryString . "\n", $wgEventLoggingFile );
	return true;
}


// Classes

$wgAutoloadClasses[ 'EventLoggingHooks' ] = __DIR__ . '/EventLogging.hooks.php';
$wgAutoloadClasses[ 'EventLoggingHomeHooks' ] = __DIR__ . '/EventLogging.home.php';
$wgAutoloadClasses[ 'ResourceLoaderEventDataModels' ] = __DIR__ . '/EventLogging.module.php';
$wgExtensionMessagesFiles[ 'EventLogging' ] = __DIR__ . '/EventLogging.i18n.php';



// Modules

$wgResourceModules[ 'ext.eventLogging.core' ] = array(
	'scripts'       => array(
		'modules/ext.eventLogging.core.js',
	),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'dependencies'  => array(
		'jquery.json',
		'mediawiki.util',
	),
);

$wgResourceModules[ 'ext.eventLogging' ] = array(
	'class' => 'ResourceLoaderEventDataModels',
	'dependencies'  => array(
		'ext.eventLogging.core'
	),
);



// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';

$wgHooks[ 'PageContentSaveComplete' ][] = 'EventLoggingHooks::onPageContentSaveComplete';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';


// Home Wiki Hooks

$wgHooks[ 'ContentHandlerDefaultModelFor' ][] = 'EventLoggingHomeHooks::onContentHandlerDefaultModelFor';
$wgHooks[ 'EditFilterMerged' ][] = 'EventLoggingHomeHooks::onEditFilterMerged';
$wgHooks[ 'PageContentSaveComplete' ][] = 'EventLoggingHomeHooks::onPageContentSaveComplete';
