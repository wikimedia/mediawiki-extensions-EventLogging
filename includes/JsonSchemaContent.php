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
			throw new JsonSchemaException( wfMessage( 'eventlogging-invalid-json' )->parse() );
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
	 * Generate generic PHP and JavaScript code strings showing how to
	 * use a schema.
	 * @param $dbKey string DB key of schema article
	 * @param $revId int Revision ID of schema article
	 * @return array: Array mapping language names to source code
	 */
	public function getCodeSamples( $dbKey, $revId ) {
		return array(
			'PHP' =>
				"\$wgResourceModules[ 'schema.{$dbKey}' ] = array(\n" .
				"	'class'  => 'ResourceLoaderSchemaModule',\n" .
				"	'schema' => '{$dbKey}',\n" .
				"	'revision' => {$revId},\n" .
				");",
			'JavaScript' =>
				"mw.eventLog.logEvent( '{$dbKey}', { /* ... */ } );"
		);
	}

	/**
	 * Wraps HTML representation of content.
	 *
	 * If the schema already exists and if the SyntaxHiglight GeSHi
	 * extension is installed, use it to render code snippets
	 * showing how to use schema.
	 *
	 * @see https://mediawiki.org/wiki/Extension:SyntaxHighlight_GeSHi
	 *
	 * @param $title Title
	 * @param $revId int|null Revision ID
	 * @param $options ParserOptions|null
	 * @param $generateHtml bool Whether or not to generate HTML
	 * @return ParserOutput
	 */
	public function getParserOutput( Title $title, $revId = null,
		ParserOptions $options = null, $generateHtml = true ) {
		$out = parent::getParserOutput( $title, $revId, $options, $generateHtml );

		if ( $revId !== null && class_exists( 'SyntaxHighlight_GeSHi' ) ) {
			$html = '';
			$highlighter = new SyntaxHighlight_GeSHi();
			foreach( self::getCodeSamples( $title->getDBkey(), $revId ) as $lang => $code ) {
				$geshi = $highlighter->prepare( $code, $lang );
				$out->addHeadItem( $highlighter::buildHeadItem( $geshi ), "source-$lang" );
				$html .= Xml::tags( 'h2', array(), $lang ) . $geshi->parse_code();
			}
			// The glyph is '< >' from the icon font 'Entypo' (see ../modules).
			$html = Xml::tags( 'div', array( 'class' => 'mw-json-schema-code-glyph' ), '&#xe714;' ) .
				Xml::tags( 'div', array( 'class' => 'mw-json-schema-code-samples' ), $html );
			$out->setText( $html . $out->mText );
		}

		return $out;
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
