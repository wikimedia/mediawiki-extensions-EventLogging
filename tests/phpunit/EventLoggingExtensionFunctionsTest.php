<?php
/**
 * PHP Unit tests for top-level ('ef-*') functions in EventLogging
 * extension.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Extension\EventLogging\Libs\JsonSchemaValidation\JsonSchemaException;

/**
 * @group EventLogging
 * @covers \MediaWiki\Extension\EventLogging\EventLogging::schemaValidate
 */
class EventLoggingExtensionFunctionsTest extends MediaWikiIntegrationTestCase {

	/** @var array a basic JSON schema, decoded to associative array. */
	private static $validSchema = [
		'properties' => [
			'valid' => [
				'type' => 'boolean',
				'required' => true,
			],
			'action' => [
				'type' => 'string',
				'enum' => [
					'delete',
					'edit',
					'history',
					'protect',
					'purge',
					'submit',
					'view',
				],
			],
		]
	];

	/** @var array conforms to $validSchema. */
	private static $validObject = [ 'valid' => true, 'action' => 'history' ];

	/** @var array does not conform to $validSchema. */
	private static $invalidObject = [ 'valid' => true, 'action' => 'cache' ];

	/**
	 * Tests validation of objects against schema.
	 * EventLogging uses Rob Lanphier's JSON Schema Validation Library,
	 * which comes with a set of unit tests for verifying the handling
	 * of various edge cases. Accordingly, this test is designed to
	 * perform only a basic, high-level sanity-check on object and
	 * schema validation.
	 *
	 * @covers \MediaWiki\Extension\EventLogging\EventLogging::schemaValidate
	 */
	public function testSchemaValidate() {
		$this->assertTrue( EventLogging::schemaValidate( self::$validObject, self::$validSchema ),
			'EventLogging::schemaValidate() returns true when object validates successfully.' );
		$this->assertTrue( EventLogging::schemaValidate( self::$validSchema ),
			'EventLogging::schemaValidate() defaults to validating against the schema schema.' );
	}

	/**
	 * Tests invalidation of objects that deviate from schema.
	 * @covers \MediaWiki\Extension\EventLogging\EventLogging::schemaValidate
	 */
	public function testSchemaInvalidate() {
		$this->expectException( JsonSchemaException::class );
		EventLogging::schemaValidate( self::$invalidObject, self::$validSchema );
	}
}
