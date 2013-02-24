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
	var $schema;


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
	 * @throws MWException if 'schema' or 'revision' keys are missing.
	 * @param array $args
	 */
	function __construct( $args ) {
		foreach( array( 'schema', 'revision' ) as $key ) {
			if ( !isset( $args[ $key ] ) ) {
				throw new MWException( "ResourceLoaderSchemaModule params must set '$key' key." );
			}
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
	 * Gets the last modified timestamp of this module.
	 * The last modified timestamp controls caching. Because revisions are
	 * immutable, we don't need to get the revision's timestamp. We
	 * simply return a timestamp of 1 (one second past epoch) if we were
	 * unable to retrieve the schema, or the revision id if successful.
	 * This ensures that clients will retrieve the schema when it becomes
	 * available.
	 * @param ResourceLoaderContext $context
	 * @return integer: Unix timestamp.
	 */
	function getModifiedTime( ResourceLoaderContext $context ) {
		return $this->schema->get() ? $this->schema->revision : 1;
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
		$params = array( $this->schema->title, $this->schema->jsonSerialize() );
		return Xml::encodeJsCall( 'mediaWiki.eventLog.declareSchema', $params );
	}
}
