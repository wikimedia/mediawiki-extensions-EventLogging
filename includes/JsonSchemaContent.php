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

/**
 * Represents the content of a JSON Schema article.
 */
class JsonSchemaContent extends TextContent {

	function __construct( $text ) {
		parent::__construct( $text, 'JsonSchema' );
	}


	/**
	 * @throws JsonSchemaException: If invalid.
	 * @return bool: True if valid.
	 */
	function validate() {
		$schema = FormatJson::decode( $this->getNativeData(), true );
		if ( !is_array( $schema ) ) {
			throw new JsonSchemaException( wfMessage( 'jsondata-invalidjson' )->parse() );
		}
		return efSchemaValidate( $schema );
	}


	/**
	 * @return bool: Whether content is valid JSON Schema.
	 */
	function isValid() {
		try {
			return $this->validate();
		} catch ( JsonSchemaException $e ) {
			return false;
		}
	}


	/**
	 * Beautifies JSON prior to save.
	 * @param Title $title Title
	 * @param User $user User
	 * @param ParserOptions $popts
	 * @return JsonSchemaContent
	 */
	function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		return new JsonSchemaContent( efBeautifyJson( $this->getNativeData() ) );
	}


	/**
	 * Constructs an HTML representation of a JSON object.
	 * @return string: HTML.
	 */
	static function objectTable( $mapping ) {
		$rows = array();

		foreach ( $mapping as $key => $val ) {
			$rows[] = self::objectRow( $key, $val );
		}
		return Xml::tags( 'table', array( 'class' => 'mw-json-schema' ),
			Xml::tags( 'tbody', array(), join( "\n", $rows ) )
		);
	}


	/**
	 * Constructs HTML representation of a single key-value pair.
	 * @return string: HTML.
	 */
	static function objectRow( $key, $val ) {
		$th = Xml::elementClean( 'th', array(), $key );
		if ( is_array( $val ) ) {
			$td = Xml::tags( 'td', array(), self::objectTable( $val ) );
		} else {
			if ( is_string( $val ) ) {
				$val = '"' . $val . '"';
			} else {
				$val = FormatJson::encode( $val );
			}

			$td = Xml::elementClean( 'td', array( 'class' => 'value' ), $val );
		}

		return Xml::tags( 'tr', array(), $th . $td );
	}


	/**
	 * Generates HTML representation of content.
	 * @return string: HTML representation.
	 */
	function getHighlightHtml() {
		$schema = FormatJson::decode( $this->getNativeData(), true );
		return is_array( $schema ) ? self::objectTable( $schema ) : '';
	}
}
