<?php
/**
 * PHP Unit tests for JsonSchemaContent.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers JsonSchema
 */
class JsonSchemaTest extends MediaWikiTestCase {

	const VALID_JSON = '{"properties":{"valid":{"type":"boolean","required":true}}}';
	const INVALID_JSON = '{"Malformed, JSON }';
	const EVIL_JSON = '{"value":"<script>alert(document.cookie);</script>"}';


	/**
	 * Tests JSON validation.
	 * @covers JsonSchemaContent::isValid
	 */
	function testIsValid() {
		// Invalid JSON
		$content = new JsonSchemaContent( self::INVALID_JSON );
		$this->assertFalse( $content->isValid(), 'Malformed JSON should be detected.' );

		// Valid JSON
		$content = new JsonSchemaContent( self::VALID_JSON );
		$this->assertTrue( $content->isValid(), 'Valid JSON should be recognized as valid.' );
	}


	/**
	 * Tests JSON pretty-printing.
	 * @covers JsonSchemaContent::preSaveTransform
	 */
	function testPreSaveTransform() {
		$transformed = new JsonSchemaContent( self::VALID_JSON );
		$prettyJson = $transformed->preSaveTransform(
			new Title(), new User(), new ParserOptions() )->getNativeData();

		$this->assertContains( "\n", $prettyJson, 'Transformed JSON is beautified.' );
		$this->assertEquals(
			FormatJson::decode( $prettyJson ),
			FormatJson::decode( self::VALID_JSON ),
			'Beautification does not alter JSON value.'
		);
	}


	/**
	 * Tests JSON->HTML representation.
	 * @covers JsonSchemaContent::getHighlightHtml
	 */
	public function testGetHighlightHtml() {
		$evil = new JsonSchemaContent( self::EVIL_JSON );
		$html = $evil->getHighlightHtml();
		$this->assertContains( '&lt;script&gt;', $html, 'HTML output should be escaped' );
	}
}
