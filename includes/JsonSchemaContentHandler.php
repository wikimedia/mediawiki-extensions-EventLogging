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

class JsonSchemaContentHandler extends JsonContentHandler {

	public function __construct( $modelId = 'JsonSchema' ) {
		parent::__construct( $modelId, array( CONTENT_FORMAT_JSON ) );
	}

	protected function getContentClass() {
		return 'JsonSchemaContent';
	}
}
