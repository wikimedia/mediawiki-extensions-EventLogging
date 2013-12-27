<?php
/**
 * PHP Unit tests for required/non required files in the schema
 *
 * @file
 * @ingroup Extensions
 *
 * @author Nuria Ruiz <nuria@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers JsonSchema
 */
class ValidateSchemaTest extends MediaWikiTestCase {

	const VALID_JSON_SCHEMA = '{
		"description": "A wrapper around event objects that encodes generic metadata",
		"properties": {
			"event": {
				"type": "object",
				"description": "The encapsulated event object",
				"required": true
				},
				"other": {
					"type": "string",
					"description": "Some fake stuff",
					"required": true
				},
				"userAgent": {
					"type": "string",
					"description": "Some fake User Agent",
					"required": false
				}
			},
		"additionalProperties": false
	}';

	const VALID_EVENT = '{
		"clientIp": "e6636d0087dde9cc49142955607c17e0b5d3563a",
		"clientValidated": true,
		"event": {
			"action": "view",
			 "connectEnd": 393,
			 "connectStart": 393,
				},
		"userAgent": "some",
		"other": "some"
		}';

	const INVALID_EVENT_MISSING_REQUIRED_FIELD = '{
		"clientIp": "e6636d0087dde9cc49142955607c17e0b5d3563a",
		"clientValidated": true,
		"event": {
			"action": "view",
			"connectEnd": 393,
			"connectStart": 393
				},
		"userAgent": "some"
		}';

	/**
	 * Tests schema we are using for tests is, indeed, valid
	 * @covers JsonSchemaContent::isValid
	 */
	function testValidJson() {
		$content = new JsonSchemaContent( self::VALID_JSON_SCHEMA );
		$this->assertTrue( $content->isValid(), 'Well-formed JSON schema' );
	}

	/**
	* A valid event should, ahem, validate
	* @covers efSchemaValidate
	**/
	function testValidEvent() {
		$valid = efSchemaValidate(
			json_decode( self::VALID_EVENT, true ),
			json_decode( self::VALID_JSON_SCHEMA, true )
		);
		$this->assertTrue( $valid, 'Well-formed event should validate' );
	}

	/**
	* A valid event should, ahem, validate
	* @covers efSchemaValidate
	* @expectedException JsonSchemaException
	**/
	function testInvalidEvent() {
		$valid = efSchemaValidate(
			json_decode( self::INVALID_EVENT_MISSING_REQUIRED_FIELD, true ),
			json_decode( self::VALID_JSON_SCHEMA, true )
		);
		$this->assertFalse( $valid, 'Malformed event should not validate' );
	}

}
