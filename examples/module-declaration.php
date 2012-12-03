<?php
/**
 * @example
 * If the schema is declared at meta.wikimedia.org/wiki/Schema:Person, it can be
 * packaged as a ResourceLoader module like this:
 */
$wgResourceModules[ 'schema.Person' ] = array(
	'class'  => 'SchemaModule',
	'schema' => 'Person'
);
