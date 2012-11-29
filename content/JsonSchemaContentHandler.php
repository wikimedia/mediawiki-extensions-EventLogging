<?php
/**
 * JSON Schema Content Handler
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class JsonSchemaContentHandler extends TextContentHandler {

	public function __construct( $modelId = 'JsonSchema' ) {
		parent::__construct( $modelId );
	}

	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		return new JsonSchemaContent( $text );
	}

	public function makeEmptyContent() {
		return new JsonSchemaContent( '' );
	}

	/** JSON Schema is English **/
	public function getPageLanguage( Title $title, Content $content = null ) {
		return wfGetLangObj( 'en' );
	}

	/** JSON Schema is English **/
	public function getPageViewLanguage( Title $title, Content $content = null ) {
		return wfGetLangObj( 'en' );
	}
}
