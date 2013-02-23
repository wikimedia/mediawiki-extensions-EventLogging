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
	'jsonschema-invalid-missingfield' => 'Missing required field "$1"',
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
	'jsonschema-invalid-missingfield' => 'JSON Schema validation error, shown when a required field is missing.',
);

/** Belarusian (Taraškievica orthography) (беларуская (тарашкевіца)‎)
 * @author Wizardist
 */
$messages['be-tarask'] = array(
	'jsonschema-badidref' => 'Благі idref: «$1»',
	'jsonschema-idconvert' => 'Не атрымалася канвэртаваць var у id: «$1»',
	'jsonschema-invalidkey' => 'Няслушны ключ «$1» у «$2»',
	'jsonschema-invalidempty' => 'Пустая структура зьвестак паводле гэтай схемы недапушчальная',
	'jsonschema-invalidnode' => 'Няслушны вузел: чакалася «$1», атрымана «$2». Шлях: «$3»',
	'jsonschema-invalid-missingfield' => 'Ня знойдзенае абавязковае поле «$1»',
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
	'jsonschema-invalid-missingfield' => 'Es fehlt das erforderliche Feld „$1“.',
);

/** Lower Sorbian (dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'jsonschema-badidref' => 'idref njepłaśiwy: "$1"',
	'jsonschema-idconvert' => 'var njedajo se do id konwertěrowaś: "$1"',
	'jsonschema-invalidkey' => 'Njepłaśiwy kluc "$1" w "$2"',
	'jsonschema-invalidempty' => 'Prozna datowa struktura z toś tym šema njejo płaśiwa',
	'jsonschema-invalidnode' => 'Njepłaśiwy suk: "$1" jo se wótcakał, "$2" dostany.  Sćažka: "$3"',
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
	'jsonschema-invalid-missingfield' => 'Champ obligatoire "$1" absent',
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'jsonschema-badidref' => 'Crouyo idref : « $1 »',
	'jsonschema-idconvert' => 'Empossiblo de convèrtir var en id : « $1 »',
	'jsonschema-invalidkey' => 'Cllâf envalida « $1 » dedens « $2 »',
	'jsonschema-invalidnode' => 'Nuod envalido : « $1 » atendu, « $2 » avu. Chemin : « $3 »',
);

/** Galician (galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'jsonschema-badidref' => 'idref incorrecto: "$1"',
	'jsonschema-idconvert' => 'Non se pode converter var en id: "$1"',
	'jsonschema-invalidkey' => 'Clave "$1" non válida en "$2"',
	'jsonschema-invalidempty' => 'Estrutura de datos baleira non válida con este esquema',
	'jsonschema-invalidnode' => 'Nodo non válido: Agardábse "$1"; recibiuse "$2". Ruta: "$3"',
	'jsonschema-invalid-missingfield' => 'Falta o parámetro obrigatorio "$1"',
);

/** Hebrew (עברית)
 * @author Amire80
 */
$messages['he'] = array(
	'jsonschema-badidref' => 'ערף idref שגוי: "$1"',
	'jsonschema-idconvert' => 'לא ניתן להמיר var למזהה: "$1"',
	'jsonschema-invalidkey' => 'מפתח בלתי־תקין: "$1" ב־"$2"',
	'jsonschema-invalidempty' => 'מבנה נתונים ריק אינו תקין עם הסכֵמה הזאת',
	'jsonschema-invalidnode' => 'צומת בלתי־תקין: ציפיתי ל־"$1", קיבלתי "$2". נתיב: "$3"',
	'jsonschema-invalid-missingfield' => 'חסר השדה הנדרש "$1"',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'jsonschema-badidref' => 'idref njepłaćiwy: "$1"',
	'jsonschema-idconvert' => 'var njeda so do id konwertować: "$1"',
	'jsonschema-invalidkey' => 'Njepłaćiwy kluč "$1" w "$2"',
	'jsonschema-invalidempty' => 'Prózdna datowa struktura z tutym šema płaćiwa njeje',
	'jsonschema-invalidnode' => 'Njepłaćiwy suk: "$1" je so wočakował, "$2" dóstany.  Šćežka: "$3"',
	'jsonschema-invalid-missingfield' => 'Trěbne polo "$1" faluje',
);

/** Indonesian (Bahasa Indonesia)
 * @author Farras
 */
$messages['id'] = array(
	'jsonschema-badidref' => 'Idref buruk: "$1"',
	'jsonschema-idconvert' => 'Gagal mengubah var ke id: "$1"',
	'jsonschema-invalidempty' => 'Struktur data kosong tidak sesuai dengan skema ini',
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
	'jsonschema-invalid-missingfield' => 'Campo obbligatorio mancante "$1"',
);

/** Japanese (日本語)
 * @author Shirayuki
 */
$messages['ja'] = array(
	'jsonschema-invalidempty' => 'このスキーマでは空のデータ構造は有効ではありません',
);

/** Korean (한국어)
 * @author 아라
 */
$messages['ko'] = array(
	'jsonschema-badidref' => '잘못된 idref: "$1"',
	'jsonschema-idconvert' => 'var를 id로 변환할 수 없습니다: "$1"',
	'jsonschema-invalidkey' => '"$2"에서 "$1" 키가 잘못되었습니다',
	'jsonschema-invalidempty' => '빈 데이터 구조는 이 스키마로는 올바르지 않습니다',
	'jsonschema-invalidnode' => '잘못된 노드: "$1"(을)를 기대했지만 "$2\'(을)을 얻었습니다. 경로: "$3"',
	'jsonschema-invalid-missingfield' => '"$1" 필수 필드가 없습니다',
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
	'jsonschema-invalid-missingfield' => 'Недостасува задолжителното поле „$1“',
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
	'jsonschema-invalid-missingfield' => 'Het verplichte veld "$1" ontbreekt',
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Dragonòt
 */
$messages['pms'] = array(
	'jsonschema-badidref' => 'Idref pa bon: "$1"',
	'jsonschema-idconvert' => 'As peul pa convertisse var a id: "$1"',
	'jsonschema-invalidkey' => 'Ciav pa bon-a "$1" an "$2"',
	'jsonschema-invalidempty' => 'Strutura ëd dat veuida nen bon-a con cost ëschema',
	'jsonschema-invalidnode' => 'Grop pa bon: spetà «$1», rivà «$2». Përcors: «$3»',
	'jsonschema-invalid-missingfield' => 'Camp obligatòri "$1" mancant',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'jsonschema-badidref' => 'Idref cattive: "$1"',
);

/** Russian (русский)
 * @author DCamer
 */
$messages['ru'] = array(
	'jsonschema-badidref' => 'Неверный idref: "$1"',
	'jsonschema-idconvert' => 'Не удается преобразовать var в id: "$1"',
	'jsonschema-invalidkey' => 'Недействительный ключ "$1" в "$2"',
	'jsonschema-invalid-missingfield' => 'Отсутствует обязательное поле "$1"',
);

/** Sinhala (සිංහල)
 * @author පසිඳු කාවින්ද
 */
$messages['si'] = array(
	'jsonschema-badidref' => 'අයහපත් idref: "$1"',
	'jsonschema-invalidkey' => '"$2" හී "$1" වලංගු නොවන යතුර',
);

/** Ukrainian (українська)
 * @author Ата
 */
$messages['uk'] = array(
	'jsonschema-badidref' => 'Неправильне посилання на id: "$1"',
	'jsonschema-idconvert' => 'Не вдається конвертувати var у id: "$1"',
	'jsonschema-invalidkey' => 'Неприпустимий ключ "$1" у "$2"',
	'jsonschema-invalidempty' => 'Порожня структура даних не припустима у цій схемі',
	'jsonschema-invalidnode' => 'Неприпустимий вузок: очікувано "$1", отримано "$2". Шлях: "$3"',
	'jsonschema-invalid-missingfield' => 'Відсутнє обов\'язкове поле "$1"',
);
