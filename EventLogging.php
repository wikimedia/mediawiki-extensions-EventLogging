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
 * @version 0.5
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
	'version' => '0.5',
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
 * @var bool|string: File name or UDP address; false if not set.
 * Server-side events will be logged to this location.
 *
 * The syntax for UDP addresses is `udp://host:port/prefix`. The prefix
 * (followed by a space) is included at the start of each message. By
 * convention it specifies which log bucket the message should be routed
 * to. It is best if the prefix is simply "EventLogging".
 *
 * @see wfErrorLog()
 *
 * @example string: 'udp://127.0.0.1:9000/EventLogging'
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

/**
 * @var bool: Whether to log SHA1 of Git HEAD of caller.
 */
$wgEventLoggingLogSHA1 = false;


// Helpers

/**
 * Gets the SHA1 of HEAD for the Git repository at a given path.
 * If path does not contain a Git repository, moves up the directory
 * tree searching for one, stopping at $IP. Returns false if no Git
 * repository found of if unable to determine SHA1 of HEAD.
 *
 * @param string $path: Starts search at this path. Must be a sub-path
 *                      of $IP (or equal to $IP).
 * @return string|bool: SHA1 of HEAD if repository discovery is
 *                      successful. False otherwise.
 */
function efPathGitHeadSHA1( $path ) {
	global $IP;

	if ( is_file( $path ) ) {
		$path = pathinfo( $path, PATHINFO_DIRNAME );
	}

	while ( strpos( $path, $IP ) === 0 ) {
		if ( file_exists( $path . '/.git' ) ) {
			$gitInfo = new GitInfo( $path );
			return $gitInfo->getHeadSHA1();
		}
		$path = dirname( $path );
	}
	return false;
}


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

	wfProfileIn( __FUNCTION__ );
	$remoteSchema = new RemoteSchema( $schemaName, $revId );
	$schema = $remoteSchema->get();
	$isValid = is_array( $schema ) && efSchemaValidate( $event, $schema );

	$encapsulated = array(
		'event'     => $event,
		'schema'    => $schemaName,
		'revision'  => $revId,
		'isValid'   => $isValid,
		'wiki'      => $wgDBname,
		'recvFrom'  => gethostname(),
		'timestamp' => $_SERVER[ 'REQUEST_TIME' ],
	);

	if ( $wgEventLoggingLogSHA1 ) {
		// Attempt to get the SHA1 of HEAD of caller.
		if ( version_compare( PHP_VERSION, '5.4.0', '>=' ) ) {
			// PHP 5.4.0 added a second parameter to debug_backtrace, 'limit',
			// which specifies the number of stack frames to return.
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
		} else {
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		}
		$caller = array_shift( $backtrace );
		$sha1 = substr( efPathGitHeadSHA1( $caller[ 'file' ] ), 0, 6 );
		$encapsulated[ 'HEAD' ] = $sha1 ?: NULL;
	}

	// To make the resultant JSON easily extracted from a row of
	// space-separated values, we replace literal spaces with unicode
	// escapes. This is permitted by the JSON specs.
	$json = str_replace( ' ', '\u0020', FormatJson::encode( $encapsulated ) );

	wfErrorLog( $json . "\n", $wgEventLoggingFile );
	wfProfileOut( __FUNCTION__ );
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
 *
 * @throws JsonSchemaException: If the object fails to validate.
 * @param array $object: Object to be validated.
 * @param array $schema: Schema to validate against (default: JSON Schema).
 * @return bool: True if valid; false if invalid.
 */
function efSchemaValidate( $object, $schema = NULL ) {
	wfProfileIn( __FUNCTION__ );
	if ( $schema === NULL ) {
		// Default to JSON Schema
		$json = file_get_contents( __DIR__ . '/schemas/schemaschema.json' );
		$schema = FormatJson::decode( $json, true );
	}

	// We depart from the JSON Schema specification in disallowing by default
	// additional event fields not mentioned in the schema.
	// See <https://bugzilla.wikimedia.org/show_bug.cgi?id=44454> and
	// <http://tools.ietf.org/html/draft-zyp-json-schema-03#section-5.4>.
	if ( !array_key_exists( 'additionalProperties', $schema ) ) {
		$schema[ 'additionalProperties' ] = false;
	}

	try {
		$root = new JsonTreeRef( $object );
		$root->attachSchema( $schema );
		$root->validate();
		wfProfileOut( __FUNCTION__ );
		return true;
	} catch ( JsonSchemaException $e ) {
		wfDebugLog( 'EventLogging', 'Object failed validation: '
			. FormatJson::encode( $object )
			. ' (exception: ' . $e->getMessage() . ')' );
		wfProfileOut( __FUNCTION__ );
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
	'targets'       => array( 'desktop', 'mobile' ),
	'mobileTargets' => array( 'alpha', 'beta', 'stable' ),
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
