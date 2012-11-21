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
	),
	'version' => '0.2',
	'url' => 'https://www.mediawiki.org/wiki/Extension:EventLogging',
	'descriptionmsg' => 'eventlogging-desc'
);



// Namespaces

define( 'NS_SCHEMA', 470 );
define( 'NS_SCHEMA_TALK', 471 );



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
 * @var bool|string: Format string or false if not set.
 * Using sprintf() syntax, this string should format an article
 * name into a retrieval URI.
 */
$wgEventLoggingModelsUriFormat = false;

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


/**
 * Generate a memcached key containing the extension name
 * and a hash digest of the model name and (optionally) any
 * other params.
 *
 * @param $model string
 * @param $model,... string Additional values to hash.
 * @return string Memcached key (45 characters long).
 */
function wfModelKey( $model /* , ... */ ) {
	$digest = md5( join( func_get_args() ) );
	return 'eventLogging:' . $digest;
}


/**
 * Takes a string of JSON data and formats it for readability.
 *
 * @param $json string
 * @return string|null Formatted JSON or null if input was invalid.
 */
function wfBeautifyJson( $json ) {
	$decoded = FormatJson::decode( $json, true );
	if ( !is_array( $decoded ) ) {
		return NULL;
	}
	return FormatJson::encode( $decoded, true );
}

// Classes

$wgAutoloadClasses[ 'DataModelModule' ] = __DIR__ . '/EventLogging.module.php';
$wgAutoloadClasses[ 'EventLoggingHomeHooks' ] = __DIR__ . '/EventLogging.home.php';
$wgAutoloadClasses[ 'EventLoggingHooks' ] = __DIR__ . '/EventLogging.hooks.php';

$wgAutoloadClasses[ 'JsonSchemaContent' ] = __DIR__ . '/content/JsonSchemaContent.php';
$wgAutoloadClasses[ 'JsonSchemaContentHandler' ] = __DIR__ . '/content/JsonSchemaContentHandler.php';



// Messages

$wgExtensionMessagesFiles[ 'EventLogging' ] = __DIR__ . '/EventLogging.i18n.php';
$wgExtensionMessagesFiles[ 'EventLoggingNamespaces' ] = __DIR__ . '/EventLogging.namespaces.php';



// Modules

$wgResourceModules[ 'ext.eventLogging' ] = array(
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


$wgContentHandlers[ 'JsonSchema' ] = 'JsonSchemaContentHandler';


// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';

$wgHooks[ 'PageContentSaveComplete' ][] = 'EventLoggingHooks::onPageContentSaveComplete';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';


// Home Wiki Hooks

$wgHooks[ 'CanonicalNamespaces' ][] = 'EventLoggingHomeHooks::onCanonicalNamespaces';
$wgHooks[ 'ContentHandlerDefaultModelFor' ][] = 'EventLoggingHomeHooks::onContentHandlerDefaultModelFor';
$wgHooks[ 'EditFilterMerged' ][] = 'EventLoggingHomeHooks::onEditFilterMerged';
$wgHooks[ 'PageContentSaveComplete' ][] = 'EventLoggingHomeHooks::onPageContentSaveComplete';
