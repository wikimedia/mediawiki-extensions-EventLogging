<?php
/**
 * EventLogging Extension for MediaWiki
 *
 * @file
 *
 * @ingroup EventLogging
 * @ingroup Extensions
 *
 * @author  Ori Livneh <ori@wikimedia.org>
 * @license GPL v2 or later
 * @version 0.3
 */

// Credits

$wgExtensionCredits[ 'other' ][] = array(
	'path' => __FILE__,
	'name' => 'EventLogging',
	'author' => array(
		'Ori Livneh',
		'Timo Tijhof',
		'S Page',
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
 * @var bool|string: URI or false if not set.
 * URI of index.php on schema wiki.
 *
 * @example string: 'http://localhost/wiki/index.php'
 */
$wgEventLoggingSchemaIndexUri = false;

/**
 * @var bool|string: Value of $wgDBname for the MediaWiki instance
 * housing schemas; false if not set.
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
 * @param $schema string Schema name
 * @param $event array Map of event keys/vals.
 * @return bool Whether the event was logged.
 */
function wfLogServerSideEvent( $schema, $event ) {
	global $wgEventLoggingFile, $wgDBname;

	if ( !$wgEventLoggingFile ) {
		return false;
	}

	$queryString = http_build_query( array(
		'_db' => $wgDBname,
		'_id' => $schema
	) + $event ) . ';';

	wfErrorLog( '?' . $queryString . "\n", $wgEventLoggingFile );
	return true;
}


/**
 * Generate a memcached key containing the extension name
 * and a hash digest of the schema name and (optionally) any
 * other params.
 *
 * @param $schema string Schema title
 * @param $rev integer|null Revision number, or NULL for latest.
 * @param $rev,... string Additional values to hash.
 * @return string Memcached key (45 characters long).
 */
function wfSchemaKey( $schema, $rev = NULL /* , ... */ ) {
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

$wgAutoloadClasses[ 'EventLoggingHooks' ] = __DIR__ . '/EventLogging.hooks.php';
$wgAutoloadClasses[ 'SchemaModule' ] = __DIR__ . '/EventLogging.module.php';

$wgAutoloadClasses[ 'JsonSchemaContent' ] = __DIR__ . '/content/JsonSchemaContent.php';
$wgAutoloadClasses[ 'JsonSchemaContentHandler' ] = __DIR__ . '/content/JsonSchemaContentHandler.php';
$wgAutoloadClasses[ 'JsonSchemaHooks' ] = __DIR__ . '/content/JsonSchemaHooks.php';



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

$wgResourceModules[ 'ext.eventLogging.jsonSchema' ] = array(
	'styles'        => 'modules/ext.eventLogging.jsonSchema.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'position'      => 'top',
);


// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';

$wgHooks[ 'PageContentSaveComplete' ][] = 'EventLoggingHooks::onPageContentSaveComplete';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';

// Registers hook and content handlers for JSON schema content iff
// running on the MediaWiki instance housing the schemas.
$wgExtensionFunctions[] = 'JsonSchemaHooks::registerHandlers';
