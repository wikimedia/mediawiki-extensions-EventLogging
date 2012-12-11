<?php
/**
 * Internationalisation file for JSON Schema validation errors.
 *
 * @file JsonSchema.i18n.php
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @license GNU General Public Licence 2.0 or later
 */

$messages = array();

/** English
 * @author Rob Lanphier
 */
$messages['en'] = array(
	'jsonschema-badidref' => 'Bad idref: "$1"',
	'jsonschema-idconvert' => 'Cannot convert var to id: "$1"',
	'jsonschema-invalidkey' => 'Invalid key "$1" in "$2"',
	'jsonschema-invalidempty' => 'Empty data structure not valid with this schema',
	'jsonschema-invalidnode' => 'Invalid node: expecting "$1", got "$2".  Path: "$3"',
);

/** Message documentation (Message documentation)
 * @author Ori Livneh
 */
$messages['qqq'] = array(
	'jsonschema-badidref' => 'JSON Schema validation error, shown when an id ref field is malformed.',
	'jsonschema-idconvert' => 'JSON Schema validation error, shown when no valid HTML id could be generated from input string.',
	'jsonschema-invalidkey' => 'JSON Schema validation error, shown object has a key not specified in schema.',
	'jsonschema-invalidempty' => 'JSON Schema validation error, shown when attempting to validate empty object against a schema that does not allow empty objects.',
	'jsonschema-invalidnode' => 'JSON Schema validation error, shown when object node does not match expected type.',
);

