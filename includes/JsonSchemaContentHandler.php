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

namespace MediaWiki\Extension\EventLogging;

use Content;
use ExtensionRegistry;
use JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Html\Html;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\SyntaxHighlight\SyntaxHighlight;
use MediaWiki\Title\Title;
use Xml;

class JsonSchemaContentHandler extends JsonContentHandler {

	public function __construct( $modelId = 'JsonSchema' ) {
		parent::__construct( $modelId );
	}

	public function canBeUsedOn( Title $title ) {
		return $title->inNamespace( NS_SCHEMA );
	}

	/**
	 * Wraps HTML representation of content.
	 *
	 * If the schema already exists and if the SyntaxHighlight
	 * extension is installed, use it to render code snippets
	 * showing how to use schema.
	 *
	 * @see https://mediawiki.org/wiki/Extension:SyntaxHighlight_GeSHi
	 *
	 * @param Content $content
	 * @param ContentParseParams $cpoParams
	 * @param ParserOutput &$parserOutput The output object to fill (reference).
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	) {
		'@phan-var JsonSchemaContent $content';
		$page = $cpoParams->getPage();
		$revId = $cpoParams->getRevId();
		parent::fillParserOutput( $content, $cpoParams, $parserOutput );
		if ( $revId !== null && ExtensionRegistry::getInstance()->isLoaded( 'SyntaxHighlight' ) ) {
			$html = '';
			foreach ( $content->getCodeSamples( $page->getDBkey(), $revId ) as $sample ) {
				$lang = $sample['language'];
				$code = $sample['code'];
				$highlighted = SyntaxHighlight::highlight( $code, $lang )->getValue();
				$html .= Html::element( 'h2',
					[],
					wfMessage( $sample['header'] )->text()
				) . $highlighted;
			}
			// The glyph is '< >' from the icon font 'Entypo' (see ../modules).
			$html = Xml::tags( 'div', [ 'class' => 'mw-json-schema-code-glyph' ], '&#xe714;' ) .
				Xml::tags( 'div', [ 'class' => 'mw-json-schema-code-samples' ], $html );
			$parserOutput->setIndicator( 'schema-code-samples', $html );
			$parserOutput->addModules( [ 'ext.eventLogging.jsonSchema', 'ext.pygments' ] );
			$parserOutput->addModuleStyles( [ 'ext.eventLogging.jsonSchema.styles' ] );
		}
	}

	protected function getContentClass() {
		return JsonSchemaContent::class;
	}
}
