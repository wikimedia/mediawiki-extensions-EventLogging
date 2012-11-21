<?php
/** @example **/


// If the data model is declared at
// meta.wikimedia.org/wiki/Schema:User.json, it can be packaged
// as a ResourceLoader module like this:

$wgResourceModules[ 'dataModel.person' ] = array(
	'class' => 'DataModelModule',
	'model' => 'Person.json'
);
