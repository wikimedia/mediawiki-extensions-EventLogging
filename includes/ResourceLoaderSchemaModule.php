<?php
/**
 * ResourceLoaderModule subclass for making remote schemas
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
 * Packages a remote schema as a JavaScript ResourceLoader module.
 */
class ResourceLoaderSchemaModule extends ResourceLoaderModule {

	/** @var RemoteSchema $schema **/
	public $schema;


	/**
	 * Constructor; invoked by ResourceLoader.
	 * Ensures that the 'schema' and 'revision' keys were set on the
	 * $wgResourceModules member array representing this module.
	 *
	 * Example:
	 * @code
	 *  $wgResourceModules[ 'schema.person' ] = array(
	 *      'class'    => 'ResourceLoaderSchemaModule',
	 *      'schema'   => 'Person',
	 *      'revision' => 4703006,
	 *  );
	 * @endcode
	 *
	 * @throws Exception if 'schema' or 'revision' keys are missing.
	 * @param array $args
	 */
	function __construct( $args ) {
		foreach( array( 'schema', 'revision' ) as $key ) {
			if ( !isset( $args[ $key ] ) ) {
				throw new Exception( "ResourceLoaderSchemaModule params must set '$key' key." );
			}
		}

		if ( !is_int( $args['revision'] ) ) {
			// Events will not validate on the Python server if this is defined
			// wrong.  Enforce it here as well, so it can be more easily caught
			// during local development.
			throw new Exception( "Revision for schema \"{$args['schema']}\" must be given as an integer" );
		}

		$this->schema = new RemoteSchema( $args['schema'], $args['revision'] );
		$this->targets = array( 'desktop', 'mobile' );
	}


	/**
	 * Part of the ResourceLoader module interface.
	 * Declares the core ext.eventLogging module as a dependency.
	 * @return array: Module names.
	 */
	function getDependencies() {
		return array( 'ext.eventLogging' );
	}


	/**
	 * Get the last modified timestamp of this module.
	 *
	 * The last modified timestamp controls caching. Because revisions are
	 * immutable, we don't need to fetch the RemoteSchema, nor get the revision's
	 * timestamp. We simply hash our module definition.
	 *
	 * @param ResourceLoaderContext $context
	 * @return integer: Unix timestamp.
	 */
	function getModifiedTime( ResourceLoaderContext $context ) {
		if ( !$this->schema->get() ) {
			// If we failed to fetch, hold off on rolling over definition timestamp
			return 1;
		}
		return $this->getDefinitionMtime( $context );
	}

	/**
	 * Get the definition summary for this module.
	 *
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = array(
			'revision' => $this->schema->revision,
		);
		return $summary;
	}


	/**
	 * Generates JavaScript module code from schema.
	 * Retrieves a schema and generates a JavaScript expression which,
	 * when run in the browser, adds it to mw.eventLog.schemas. Adds an
	 * empty schema if the schema could not be retrieved.
	 * @param ResourceLoaderContext $context
	 * @return string: JavaScript code.
	 */
	function getScript( ResourceLoaderContext $context ) {
		$schema = $this->schema->jsonSerialize();
		efStripKeyRecursive( $schema, 'description' );
		$params = array( $this->schema->title, $schema );
		return Xml::encodeJsCall( 'mediaWiki.eventLog.declareSchema', $params );
	}
}
