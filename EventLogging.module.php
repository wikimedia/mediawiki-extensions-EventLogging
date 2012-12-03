<?php
/**
 * ResourceLoaderModule subclass for making event schemas
 * available as JavaScript submodules to client-side code.
 *
 * @file
 *
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * Packages a schema as a JavaScript ResourceLoader module.
 * The schemas are canonically stored as JSON Schema in the
 * Schema: namespace on Meta. This module attempts to retrieve
 * the required schema from memcached and default to an HTTP
 * request if the key is missing.
 *
 * To prevent a cache stampede, only one thread of execution is
 * permitted to attempt an HTTP request for a given schema.
 * Other threads simply generate an empty object.
 */
class SchemaModule extends ResourceLoaderModule {

	const LOCK_TIMEOUT = 30;

	protected $schema;
	protected $rev;


	/**
	 * Constructor; invoked by ResourceLoader. Ensures that the
	 * 'schema' key has been set on the $wgResourceModules member array
	 * representing this module.
	 *
	 * @example
	 *
	 *	$wgResourceModules[ 'schema.person' ] = array(
	 *		'class'    => 'SchemaModule',
	 *		'schema'   => 'Person',
	 *      'revision' => 4703006,
	 *	);
	 *
	 * @throws MWException if the schema key is missing.
	 * @param $options array
	 */
	public function __construct( $options ) {
		if ( !array_key_exists( 'schema', $options ) ) {
			throw new MWException( 'SchemaModule options must set a "schema" key.' );
		}
		$this->schema = $options[ 'schema' ];
		$this->rev = array_key_exists( 'revision', $options )
			? $options[ 'revision' ]
			: NULL;
	}

	/**
	 * Attempt to acquire exclusive update lock.
	 *
	 * @return bool Whether the lock was acquired.
	 */
	protected function acquireLock() {
		global $wgMemc;

		$lockKey = wfSchemaKey( $this->schema, $this->rev, 'lock' );
		return $wgMemc->add( $lockKey, 1, self::LOCK_TIMEOUT );
	}


	/**
	 * Part of the ResourceLoader module interface. Declares the core
	 * ext.eventLogging module as a dependency.
	 *
	 * @return array Module names.
	 */
	public function getDependencies() {
		return array( 'ext.eventLogging' );
	}


	/**
	 * Constructs a URI for fetching this schema.
	 */
	public function getUri() {
		global $wgEventLoggingSchemaIndexUri;

		$query = array( 'title' => $this->schema, 'action' => 'raw' );
		if ( $this->rev ) {
			$query[ 'oldid' ] = $this->rev;
		}
		return wfAppendQuery( $wgEventLoggingSchemaIndexUri, $query );
	}

	/**
	 * Attempts to retrieve a schema via HTTP.
	 *
	 * @return array|null Decoded JSON object or null on failure.
	 */
	private function httpGetSchema() {
		$uri = $this->getUri();

		// The HTTP request timeout is set to a fraction of the lock timeout to
		// prevent a pile-up of multiple lingering connections.
		$res = Http::get( $uri, self::LOCK_TIMEOUT * 0.8 );
		if ( $res === false ) {
			wfDebugLog( 'EventLogging', "Failed to fetch schema '{$this->schema}' from $uri" );
			return;
		}

		wfDebugLog( 'EventLogging', "Fetched schema '{$this->schema}' from $uri" );

		$schema = FormatJson::decode( $res, true );
		if ( !is_array( $schema ) ) {
			wfDebugLog( 'EventLogging', "Failed to decode schema '{$this->schema}' from $uri; got '$res'" );
			return;
		}

		return $schema;
	}


	/**
	 * Gets the last modified timestamp of this module.
	 *
	 * The last modified timestamp is set whenever a schema's page is
	 * saved (on PageContentSaveComplete).  If the key is missing, set
	 * it to now.
	 *
	 * @param $context ResourceLoaderContext
	 * @return integer Unix timestamp
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		global $wgMemc;

		$key = wfSchemaKey( $this->schema, $this->rev, 'mTime' );
		$mTime = $wgMemc->get( $key );

		if ( !$mTime ) {
			$mTime = wfTimestampNow();
			$wgMemc->add( $key, $mTime );
		}

		return $mTime;
	}


	/**
	 * Generates JavaScript module code from schema
	 *
	 * Retrieves a schema from cache or HTTP and generates a
	 * JavaScript expression which, when run in the browser, adds it
	 * to mediaWiki.eventLogging.schemas If unable to retrieve
	 * schema, sets the value to an empty object instead.
	 *
	 * @param $context ResourceLoaderContext
	 * @return string
	 */
	public function getScript( ResourceLoaderContext $context ) {
		global $wgMemc;

		$key = wfSchemaKey( $this->schema, $this->rev );
		$schema = $wgMemc->get( $key );

		if ( $schema === false ) {
			// Attempt to acquire exclusive update lock. If successful,
			// grab schema via HTTP and update the cache.
			if ( $this->acquireLock() ) {
				$schema = $this->httpGetSchema();
				if ( $schema ) {
					$wgMemc->add( $key, $schema );
				}
			}
		}

		if ( !$schema ) {
			$schema = new stdClass();  // Will be encoded to empty JS object.
		}

		// { key1: val1, key2: val2 } => { schema: { key1: val1, key2: val2 } }
		$schema = array( $this->schema => $schema );

		return Xml::encodeJsCall( 'mediaWiki.eventLog.setSchemas', array( $schema ) );
	}
}
