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

$wgExtensionCredits[ 'other' ][] = [
	'path'   => __FILE__,
	'name'   => 'EventLogging',
	'author' => [
		'Ori Livneh',
		'Timo Tijhof',
		'S Page',
		'Matthew Flaschen',
	],
	'version' => '0.8.0',
	'url'     => 'https://www.mediawiki.org/wiki/Extension:EventLogging',
	'descriptionmsg' => 'eventlogging-desc',
	'license-name' => 'GPL-2.0+'
];

// Namespaces
define( 'NS_SCHEMA', 470 );
define( 'NS_SCHEMA_TALK', 471 );

$wgHooks[ 'CanonicalNamespaces' ][] = 'EventLoggingHooks::onCanonicalNamespaces';

$wgContentHandlers[ 'JsonSchema' ] = 'JsonSchemaContentHandler';
$wgNamespaceContentModels[ NS_SCHEMA ] = 'JsonSchema';
$wgNamespaceProtection[ NS_SCHEMA ] = [ 'autoconfirmed' ];

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
$wgEventLoggingSchemas = isset( $wgEventLoggingSchemas ) ? $wgEventLoggingSchemas : [];

// Classes

$wgAutoloadClasses += [
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
];

// Messages

$wgMessagesDirs['EventLogging'] = __DIR__ . '/i18n/core';
$wgMessagesDirs['JsonSchema'] = __DIR__ . '/i18n/jsonschema';
$wgExtensionMessagesFiles['EventLoggingNamespaces'] = __DIR__ . '/EventLogging.namespaces.php';

// Modules

$wgResourceModules[ 'ext.eventLogging' ] = [
	'scripts'       => [
		'modules/ext.eventLogging.core.js',
		'modules/ext.eventLogging.debug.js',
	],
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'dependencies'  => [
		'json',
		'ext.eventLogging.subscriber',
		'user.options',
	],
	'targets'       => [ 'desktop', 'mobile' ],
];

$wgResourceModules[ 'ext.eventLogging.subscriber' ] = [
	'scripts'       => [
		'modules/ext.eventLogging.subscriber.js',
		'modules/ext.eventLogging.Schema.js',
	],
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'dependencies'  => [ 'mediawiki.user' ],
	'targets'       => [ 'desktop', 'mobile' ],
];

// Back-compatibility alias for subscriber
$wgResourceModules[ 'ext.eventLogging.Schema' ] = [
	'dependencies'  => [
		'ext.eventLogging.subscriber'
	],
	'targets'       => [ 'desktop', 'mobile' ],
];

$wgResourceModules[ 'ext.eventLogging.jsonSchema' ] = [
	'scripts'       => 'modules/ext.eventLogging.jsonSchema.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'position'      => 'top',
];

$wgResourceModules[ 'ext.eventLogging.jsonSchema.styles' ] = [
	'styles'        => 'modules/ext.eventLogging.jsonSchema.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'EventLogging',
	'position'      => 'top',
];

// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';

$wgHooks[ 'BeforePageDisplay' ][] = 'EventLoggingHooks::onBeforePageDisplay';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';
$wgHooks[ 'ResourceLoaderRegisterModules' ][] =
	'EventLoggingHooks::onResourceLoaderRegisterModules';
$wgHooks[ 'GetPreferences' ][] = 'EventLoggingHooks::onGetPreferences';

// Registers hook and content handlers for JSON schema content iff
// running on the MediaWiki instance housing the schemas.
$wgExtensionFunctions[] = 'JsonSchemaHooks::registerHandlers';

// Hidden option for users to see EventLogging as it happens
$wgDefaultUserOptions['eventlogging-display-web'] = 0;

// Unit Tests
$wgHooks[ 'UnitTestsList' ][] = 'EventLoggingHooks::onUnitTestsList';
