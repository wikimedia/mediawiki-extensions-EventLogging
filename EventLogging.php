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
 * @version 0.8.1
 */

// Credits

$wgExtensionCredits[ 'other' ][] = array(
	'path'   => __FILE__,
	'name'   => 'EventLogging',
	'author' => array(
		'Ori Livneh',
		'Timo Tijhof',
		'S Page',
		'Matthew Flaschen',
	),
	'version' => '0.8.0',
	'url'     => 'https://www.mediawiki.org/wiki/Extension:EventLogging',
	'descriptionmsg' => 'eventlogging-desc',
	'license-name' => 'GPL-2.0+'
);

// Namespaces
define( 'NS_SCHEMA', 470 );
define( 'NS_SCHEMA_TALK', 471 );

$wgHooks[ 'CanonicalNamespaces' ][] = function ( &$namespaces ) {
	global $wgDBname, $wgEventLoggingDBname;
	if ( $wgEventLoggingDBname === $wgDBname ) {
		$namespaces[ NS_SCHEMA ] = 'Schema';
		$namespaces[ NS_SCHEMA_TALK ] = 'Schema_talk';
	}

	return true;
};
$wgContentHandlers[ 'JsonSchema' ] = 'JsonSchemaContentHandler';
$wgNamespaceContentModels[ NS_SCHEMA ] = 'JsonSchema';
$wgNamespaceProtection[ NS_SCHEMA ] = array( 'autoconfirmed' );



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
 * @var bool|string: File name or UDP address; false if not set.
 * Server-side events will be logged to this location.
 *
 * The syntax for UDP addresses is `udp://host:port/prefix`. The prefix
 * (followed by a space) is included at the start of each message. By
 * convention it specifies which log bucket the message should be routed
 * to. It is best if the prefix is simply "EventLogging".
 *
 * @see MWLoggerLegacyLogger::emit()
 *
 * @example string: 'udp://127.0.0.1:9000/EventLogging'
 * @example string: '/var/log/mediawiki/events.log'
 */
$wgEventLoggingFile = false;

/**
 * @var bool|string: URI or false if not set.
 * URI of api.php on schema wiki.
 *
 * @example string: 'https://meta.wikimedia.org/w/api.php'
 */
$wgEventLoggingSchemaApiUri = 'https://meta.wikimedia.org/w/api.php';

/**
 * @var bool|string: Value of $wgDBname for the MediaWiki instance
 * housing schemas; false if not set.
 */
$wgEventLoggingDBname = 'metawiki';

/**
 * @var array: A map of event schema names to revision IDs.
 * @example array: array( 'MultimediaViewerNetworkPerformance' => 7917896 );
 */
$wgEventLoggingSchemas = isset( $wgEventLoggingSchemas ) ? $wgEventLoggingSchemas : array();

// Helpers

/**
 * Validates object against JSON Schema.
 *
 * @throws JsonSchemaException: If the object fails to validate.
 * @param array $object Object to be validated.
 * @param array $schema Schema to validate against (default: JSON Schema).
 * @return bool: True.
 */
function efSchemaValidate( $object, $schema = NULL ) {
	if ( $schema === NULL ) {
		// Default to JSON Schema
		$json = file_get_contents( __DIR__ . '/schemas/schemaschema.json' );
		$schema = FormatJson::decode( $json, true );
	}

	// We depart from the JSON Schema specification in disallowing by default
	// additional event fields not mentioned in the schema.
	// See <https://bugzilla.wikimedia.org/show_bug.cgi?id=44454> and
	// <https://tools.ietf.org/html/draft-zyp-json-schema-03#section-5.4>.
	if ( !array_key_exists( 'additionalProperties', $schema ) ) {
		$schema[ 'additionalProperties' ] = false;
	}

	$root = new JsonTreeRef( $object );
	$root->attachSchema( $schema );
	return $root->validate();
}

/**
 * Recursively remove a key from an array and all its subarray members.
 * Does not detect cycles.
 *
 * @param array &$array Array from which key should be stripped.
 * @param string $key Key to remove.
 */
function efStripKeyRecursive( &$array, $key ) {
	unset( $array[ $key ] );
	foreach( $array as $k => &$v ) {
		if ( is_array( $v ) ) {
			efStripKeyRecursive( $v, $key );
		}
	}
}

// Classes

$wgAutoloadClasses += array(
	'EventLogging' => __DIR__ . '/includes/EventLogging.php',

	// Hooks
	'EventLoggingHooks' => __DIR__ . '/includes/EventLoggingHooks.php',
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

	// API
	'ApiJsonSchema' => __DIR__ . '/includes/ApiJsonSchema.php',
);

// Messages

$wgMessagesDirs['EventLogging'] = __DIR__ . '/i18n/core';
$wgMessagesDirs['JsonSchema'] = __DIR__ . '/i18n/jsonschema';
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
	'dependencies'  => array( 'json', 'mediawiki.util' ),
	'targets'       => array( 'desktop', 'mobile' ),
);

$wgResourceModules[ 'ext.eventLogging.subscriber' ] = array(
	'scripts'       => 'modules/ext.eventLogging.subscriber.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'targets'       => array( 'desktop', 'mobile' ),
);

$wgResourceModules[ 'ext.eventLogging.jsonSchema' ] = array(
	'scripts'       => 'modules/ext.eventLogging.jsonSchema.js',
	'styles'        => 'modules/ext.eventLogging.jsonSchema.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'position'      => 'top',
);

// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';

$wgHooks[ 'BeforePageDisplay' ][] = 'EventLoggingHooks::onBeforePageDisplay';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';
$wgHooks[ 'ResourceLoaderRegisterModules' ][] = 'EventLoggingHooks::onResourceLoaderRegisterModules';

// Registers hook and content handlers for JSON schema content iff
// running on the MediaWiki instance housing the schemas.
$wgExtensionFunctions[] = 'JsonSchemaHooks::registerHandlers';

// Unit Tests

$wgHooks[ 'UnitTestsList' ][] = function ( &$files ) {
	$files = array_merge( $files, glob( __DIR__ . '/tests/*Test.php' ) );
	return true;
};
