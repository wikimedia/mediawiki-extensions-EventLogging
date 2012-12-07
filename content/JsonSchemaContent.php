<?php
/**
 * JSON Schema Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author  Ori Livneh <ori@wikimedia.org>
 */

class JsonSchemaContent extends TextContent {

	public function __construct( $text ) {
		parent::__construct( $text, 'JsonSchema' );
	}

	/**
	 * Returns whether content is valid JSON Schema
	 *
	 * @return  boolean
	 */
	public function isValid() {
		return is_array( FormatJson::decode( $this->getNativeData(), true ) );
	}

	/**
	 * Beautifies JSON prior to save.
	 *
	 * @param   $title   Title
	 * @param   $user    User
	 * @param   $popts   ParserOptions
	 * @return  Content
	 */
	public function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		return new JsonSchemaContent( wfBeautifyJson( $this->getNativeData() ) );
	}


	/**
	 * Construct an HTML representation of a JSON object.
	 *
	 * @return string
	 */
	protected static function objectTable( $mapping ) {
		$rows = array();
		$count = 0;

		foreach ( $mapping as $key => $val ) {
			$rows[] = self::objectRow( $key, $val );
			$count++;
		}

		$caption = wfMessage( 'eventlogging-json' )->numParams( $count )->escaped();

		return Xml::tags( 'table', array( 'class' => 'mw-json-schema' ),
			Xml::tags( 'caption', array(), $caption ) . "\n" .
			Xml::tags( 'tbody', array(), join( "\n", $rows ) )
		);
	}

	/**
	 * Construct HTML representation of a single key-value pair.
	 *
	 * @return  string
	 */
	protected static function objectRow( $key, $val ) {
		$th = Xml::elementClean( 'th', array(), $key );
		$td = is_array( $val ) ?
			Xml::tags( 'td', array(), self::objectTable( $val ) ) :
			Xml::elementClean( 'td', array( 'class' => 'value' ), FormatJson::encode( $val ) );
		return Xml::tags( 'tr', array(), $th . $td );
	}


	/**
	 * Generate HTML representation of content
	 *
	 * @return  string  HTML representation
	 */
	public function getHighlightHtml() {
		$schema = FormatJson::decode( $this->getNativeData(), true );
		return is_array( $schema ) ? self::objectTable( $schema ) : '';
	}
}
