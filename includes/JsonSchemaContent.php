<?php
/**
 * JSON Schema Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

namespace MediaWiki\Extension\EventLogging;

use MediaWiki\Content\JsonContent;
use MediaWiki\Extension\EventLogging\Libs\JsonSchemaValidation\JsonSchemaException;
use MediaWiki\Json\FormatJson;

/**
 * Represents the content of a JSON Schema article.
 */
class JsonSchemaContent extends JsonContent {

	private const DEFAULT_RECURSION_LIMIT = 3;

	/** @inheritDoc */
	public function __construct( $text, $modelId = 'JsonSchema' ) {
		parent::__construct( $text, $modelId );
	}

	/**
	 * Resolve a JSON reference to a schema.
	 * @param string $ref Schema reference with format 'Title/Revision'
	 * @return array|bool
	 */
	public static function resolve( $ref ) {
		[ $title, $revId ] = explode( '/', $ref );
		$rs = new RemoteSchema( $title, (int)$revId );
		return $rs->get();
	}

	/**
	 * Recursively resolve references in a schema.
	 * @param array $schema Schema object to expand
	 * @param int $recursionLimit Maximum recursion limit
	 * @return array Expanded schema object
	 */
	public static function expand( $schema,
			$recursionLimit = self::DEFAULT_RECURSION_LIMIT ) {
		return array_map( static function ( $value ) use( $recursionLimit ) {
			if ( is_array( $value ) && $recursionLimit > 0 ) {
				if ( isset( $value['$ref'] ) ) {
					$value = JsonSchemaContent::resolve( $value['$ref'] );
				}
				return JsonSchemaContent::expand( $value, $recursionLimit - 1 );
			}
			return $value;
		}, $schema );
	}

	/**
	 * Decodes the JSON schema into a PHP associative array.
	 * @return array Schema array
	 */
	public function getJsonData() {
		return FormatJson::decode( $this->getText(), true );
	}

	/**
	 * @throws JsonSchemaException If content is invalid
	 * @return bool True if valid
	 */
	public function validate() {
		$schema = $this->getJsonData();
		if ( !is_array( $schema ) ) {
			throw new JsonSchemaException( 'eventlogging-invalid-json' );
		}
		return EventLogging::schemaValidate( $schema );
	}

	/**
	 * @return bool Whether content is valid JSON Schema.
	 */
	public function isValid() {
		try {
			return parent::isValid() && $this->validate();
		} catch ( JsonSchemaException ) {
			return false;
		}
	}

	/**
	 * Generate generic PHP and JavaScript code strings showing how to
	 * use a schema.
	 * @param string $dbKey DB key of schema article
	 * @param int $revId Revision ID of schema article
	 * @return array[] Nested array with each sub-array having a language, header
	 *  (message key), and code
	 */
	public function getCodeSamples( $dbKey, $revId ) {
		return [
			[
				'language' => 'php',
				'header' => 'eventlogging-code-sample-logging-on-server-side',
				'code' => "EventLogging::logEvent( '$dbKey', $revId, \$event );",
			], [
				'language' => 'json',
				'header' => 'eventlogging-code-sample-module-setup-json',
				'code' => FormatJson::encode( [
					'attributes' => [ 'EventLogging' => [
						'Schemas' => [ $dbKey => $revId, ] ]
					] ], "\t" ),
			], [
				'language' => 'javascript',
				'header' => 'eventlogging-code-sample-logging-on-client-side',
				'code' => "mw.track( 'event.{$dbKey}', { /* ... */ } );",
			],
		];
	}
}
