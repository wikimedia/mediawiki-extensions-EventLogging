<?php
/**
 * PHP Unit tests for JsonSchemaContent.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

use MediaWiki\Extension\EventLogging\JsonSchemaContent;

/**
 * @group EventLogging
 * @covers \MediaWiki\Extension\EventLogging\JsonSchemaContent
 */
class JsonSchemaTest extends MediaWikiIntegrationTestCase {

	private const INVALID_JSON = '"Malformed, JSON }';
	private const INVALID_JSON_SCHEMA = '{"malformed":true}';  // Valid JSON, invalid JSON Schema.
	private const VALID_JSON_SCHEMA = '{"properties":{"valid":{"type":"boolean","required":true}}}';
	private const EVIL_JSON = '{"title":"<script>alert(document.cookie);</script>"}';

	/**
	 * Tests handling of invalid JSON.
	 * @covers \MediaWiki\Extension\EventLogging\JsonSchemaContent::isValid
	 */
	public function testInvalidJson() {
		$content = new JsonSchemaContent( self::INVALID_JSON );
		$this->assertFalse( $content->isValid(), 'Malformed JSON should be detected.' );
	}

	/**
	 * Tests handling of valid JSON that is not valid JSON Schema.
	 * @covers \MediaWiki\Extension\EventLogging\JsonSchemaContent::isValid
	 */
	public function testInvalidJsonSchema() {
		$content = new JsonSchemaContent( self::INVALID_JSON_SCHEMA );
		$this->assertFalse( $content->isValid(), 'Malformed JSON Schema should be detected.' );
	}

	/**
	 * Tests successful validation of well-formed JSON Schema.
	 * @covers \MediaWiki\Extension\EventLogging\JsonSchemaContent::isValid
	 */
	public function testValidJsonSchema() {
		$content = new JsonSchemaContent( self::VALID_JSON_SCHEMA );
		$this->assertTrue( $content->isValid(), 'Valid JSON Schema should be recognized as valid.' );
	}

	/**
	 * Tests JSON pretty-printing.
	 * Make sure that we can put a JsonSchemaContent
	 * into ContentTransformer.
	 */
	public function testPreSaveTransform() {
		$user = new User();
		$contentTransformer = $this->getServiceContainer()->getContentTransformer();
		$transformed = new JsonSchemaContent( self::VALID_JSON_SCHEMA );
		$prettyJson = $contentTransformer->preSaveTransform(
			$transformed,
			$this->createMock( Title::class ),
			$user,
			new ParserOptions( $user )
		)->getText();

		$this->assertStringContainsString( "\n", $prettyJson, 'Transformed JSON is beautified.' );
		$this->assertEquals(
			FormatJson::decode( $prettyJson ),
			FormatJson::decode( self::VALID_JSON_SCHEMA ),
			'Beautification does not alter JSON value.'
		);
	}

	/**
	 * Tests JSON->HTML representation.
	 * @covers \MediaWiki\Extension\EventLogging\JsonSchemaContent::getText
	 */
	public function testGetText() {
		$content = new JsonSchemaContent( self::EVIL_JSON );
		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput(
			$content,
			Title::newFromText( 'Test' )
		);
		$this->assertStringContainsString(
			'&lt;script>',
			$parserOutput->getText(),
			'HTML output should be escaped'
		);
	}
}
