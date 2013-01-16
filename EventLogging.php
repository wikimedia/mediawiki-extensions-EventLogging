<?php
/**
 * EventLogging Extension for MediaWiki
 *
 * @file
 *
 * @ingroup EventLogging
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 * @license GPL v2 or later
 * @version 0.4
 */

// Credits

$wgExtensionCredits[ 'other' ][] = array(
	'path'   => __FILE__,
	'name'   => 'EventLogging',
	'author' => array(
		'Ori Livneh',
		'Timo Tijhof',
		'S Page',
	),
	'version' => '0.4',
	'url'     => 'https://www.mediawiki.org/wiki/Extension:EventLogging',
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
 * @see wfErrorLog
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
 * Writes an event to a file descriptor or socket.
 * Takes an event ID and an event, encodes it as query string,
 * and writes it to the UDP / TCP address or file specified by
 * $wgEventLoggingFile. If $wgEventLoggingFile is not set, returns
 * false without logging anything.
 *
 * @see wfErrorLog
 *
 * @param string $schema: Schema name.
 * @param integer $revId: revision ID of schema
 * @param array $event: Map of event keys/vals.
 * @return bool: Whether the event was logged.
 */
function efLogServerSideEvent( $schemaName, $revId, $event ) {
	global $wgEventLoggingFile, $wgDBname;

	if ( !$wgEventLoggingFile ) {
		return false;
	}

	$remoteSchema = new RemoteSchema( $schemaName, $revId );
	$schema = $remoteSchema->get();
	$isValid = is_array( $schema ) && efSchemaValidate( $event, $schema );

	$event[ '_meta' ] = array(
		'_site'      => $wgDBname,
		'_schema'    => $schemaName,
		'_revision'  => $revId,
		'_timestamp' => (int) wfTimestamp( TS_UNIX, 0 ),
		'_valid'     => $isValid,
	);

	wfErrorLog( FormatJson::encode( $event ) . "\n", $wgEventLoggingFile );
	return true;
}


/**
 * Takes a string of JSON data and formats it for readability.
 * @param string $json
 * @return string|null: Formatted JSON or null if input was invalid.
 */
function efBeautifyJson( $json ) {
	$decoded = FormatJson::decode( $json, true );
	if ( !is_array( $decoded ) ) {
		return NULL;
	}
	return FormatJson::encode( $decoded, true );
}


/**
 * Validates object against JSON Schema.
 * @throws JsonSchemaException: If the object fails to validate.
 * @param array $object: Object to be validated.
 * @param array $schema: Schema to validate against (default: JSON Schema).
 * @return bool: True if valid; false if invalid.
 */
function efSchemaValidate( $object, $schema = NULL ) {
	if ( $schema === NULL ) {
		// Default to JSON Schema
		$json = file_get_contents( __DIR__ . '/schemas/schemaschema.json' );
		$schema = FormatJson::decode( $json, true );
	}

	try {
		$root = new JsonTreeRef( $object );
		$root->attachSchema( $schema );
		return $root->validate();
	} catch ( JsonSchemaException $e ) {
		return false;
	}
}


// Classes

$wgAutoloadClasses += array(
	// Hooks
	'EventLoggingHooks' => __DIR__ . '/EventLogging.hooks.php',
	'JsonSchemaHooks'   => __DIR__ . '/includes/JsonSchemaHooks.php',

	// ContentHandler
	'JsonSchemaContent'        => __DIR__ . '/includes/JsonSchemaContent.php',
	'JsonSchemaContentHandler' => __DIR__ . '/includes/JsonSchemaContentHandler.php',

	// ResourceLoaderModule
	'RemoteSchema'               => __DIR__ . '/includes/RemoteSchema.php',
	'ResourceLoaderSchemaModule' => __DIR__ . '/includes/ResourceLoaderSchemaModule.php',

	// JsonSchema
	'JsonSchemaException' => __DIR__ . '/includes/JsonSchema.php',
	'JsonUtil'            => __DIR__ . '/includes/JsonSchema.php',
	'TreeRef'             => __DIR__ . '/includes/JsonSchema.php',
	'JsonTreeRef'         => __DIR__ . '/includes/JsonSchema.php',
	'JsonSchemaIndex'     => __DIR__ . '/includes/JsonSchema.php',
);



// Messages

$wgExtensionMessagesFiles += array(
	'EventLogging'           => __DIR__ . '/EventLogging.i18n.php',
	'EventLoggingNamespaces' => __DIR__ . '/EventLogging.namespaces.php',
	'JsonSchema'             => __DIR__ . '/includes/JsonSchema.i18n.php',
);



// Modules

$wgResourceModules[ 'ext.eventLogging' ] = array(
	'scripts'       => 'modules/ext.eventLogging.core.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'dependencies'  => array( 'jquery.json', 'mediawiki.util' ),
);


$wgResourceModules[ 'ext.eventLogging.jsonSchema' ] = array(
	'styles'        => 'modules/ext.eventLogging.jsonSchema.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'position'      => 'top',
);



// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';

$wgHooks[ 'AddNewAccount' ][] = 'EventLoggingHooks::onAddNewAccount';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';

// Registers hook and content handlers for JSON schema content iff
// running on the MediaWiki instance housing the schemas.
$wgExtensionFunctions[] = 'JsonSchemaHooks::registerHandlers';



// Unit Tests

$wgHooks[ 'UnitTestsList' ][] = function ( &$files ) {
	$files += glob( __DIR__ . '/tests/*Test.php' );
	return true;
};
