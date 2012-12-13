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
	'jsonschema-invalidnode' => 'Invalid node: expecting "$1", got "$2". Path: "$3"',
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

/** German (Deutsch)
 * @author Metalhead64
 */
$messages['de'] = array(
	'jsonschema-badidref' => 'idref ungültig: „$1“',
	'jsonschema-idconvert' => 'var konnte nicht zu id konvertiert werden: „$1“',
	'jsonschema-invalidkey' => 'Ungültiger Schlüssel „$1“ in „$2“',
	'jsonschema-invalidempty' => 'Leere Datenstruktur ist mit diesem Schema nicht gültig',
	'jsonschema-invalidnode' => 'Ungültiger Knoten: Erwartet „$1“, erhalten „$2“. Pfad: „$3“',
);

/** Italian (italiano)
 * @author Beta16
 */
$messages['it'] = array(
	'jsonschema-badidref' => 'IDref malformato: "$1"',
	'jsonschema-idconvert' => 'Non è possibile convertire var in ID: "$1"',
	'jsonschema-invalidkey' => 'Chiave non valida "$1" in "$2"',
	'jsonschema-invalidempty' => 'Struttura dati vuota non valida con questo schema',
	'jsonschema-invalidnode' => 'Nodo non valido: si aspettava "$1", ma ricevuto "$2". Percorso: "$3"',
);

/** Japanese (日本語)
 * @author Shirayuki
 */
$messages['ja'] = array(
	'jsonschema-invalidempty' => 'このスキーマでは空のデータ構造は有効ではありません',
);
