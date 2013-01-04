<?php
/**
 * @example
 * If the schema is declared at meta.wikimedia.org/wiki/Schema:SignUp,
 * it can be packaged as a ResourceLoader module like this:
 */
$wgResourceModules[ 'schema.SignUp' ] = array(
	'class'  => 'ResourceLoaderSchemaModule',
	'schema' => 'SignUp',
	'revision' => 4868070
);
