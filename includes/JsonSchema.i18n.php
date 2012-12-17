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

/** Spanish (español)
 * @author Armando-Martin
 */
$messages['es'] = array(
	'jsonschema-badidref' => 'Idref erróneo: "$1"',
	'jsonschema-idconvert' => 'No se puede convertir var a id: "$1"',
	'jsonschema-invalidkey' => 'Clave no válida "$1" en "$2"',
	'jsonschema-invalidempty' => 'No es válida la estructura de datos vacía con este esquema',
	'jsonschema-invalidnode' => 'Nodo no válido: se esperaba "$1", se obtuvo "$2". Ruta: "$3"',
);

/** French (français)
 * @author Gomoko
 */
$messages['fr'] = array(
	'jsonschema-badidref' => 'Mauvais idref: "$1"',
	'jsonschema-idconvert' => 'Impossible de convertir var en id: "$1"',
	'jsonschema-invalidkey' => 'Clé "$1" non valide dans "$2"',
	'jsonschema-invalidempty' => 'Structure de donnée vide non valide avec ce schéma',
	'jsonschema-invalidnode' => 'Nœud non valide: "$1" attendu, "$2" obtenu. Chemin: "$3"',
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

/** Macedonian (македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'jsonschema-badidref' => 'Погрешен idref: „$1“',
	'jsonschema-idconvert' => 'Не можам да го претворам var во id: „$1“',
	'jsonschema-invalidkey' => 'Неважечки клуч „$1“ во „$2“',
	'jsonschema-invalidempty' => 'Празната податочна структура не важи за оваа шема',
	'jsonschema-invalidnode' => 'Неважечки јазол: очекував „$1“, а добив „$2“. Патека: „$3“',
);

/** Dutch (Nederlands)
 * @author Siebrand
 */
$messages['nl'] = array(
	'jsonschema-badidref' => 'Onjuiste idref: "$1"',
	'jsonschema-idconvert' => 'Het is niet mogelijk var naar id te converteren: "$1"',
	'jsonschema-invalidkey' => 'Ongeldige sleutel "$1" in "$2"',
	'jsonschema-invalidempty' => 'Een lege gegevensstructuur is niet geldig voor dit schema',
	'jsonschema-invalidnode' => 'Ongeldige node: "$1" werd verwacht, "$2" is waargenomen. Pad: "$3"',
);
