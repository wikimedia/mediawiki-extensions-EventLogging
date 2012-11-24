<?php
/**
 * Hooks for managing JSON Schema namespace and content model.
 *
 * @file
 * @ingroup Extensions
 */

class JsonSchemaHooks {

	/**
	 * Check if this is the home wiki for data models.
	 *
	 * @return bool
	 */
	public static function isHomeWiki() {
		global $wgEventLoggingDBname, $wgDBname;
		return ( $wgEventLoggingDBname === $wgDBname );
	}

	/**
	 * Register Schema namespaces.
	 *
	 * @param &$namespaces array Mapping of numbers to namespace names.
	 * @return bool
	 */
	public static function onCanonicalNamespaces( array &$namespaces ) {
		if ( self::isHomeWiki() ) {
			$namespaces[ NS_SCHEMA ] = 'Schema';
			$namespaces[ NS_SCHEMA_TALK ] = 'Schema_talk';
		}

		return true;
	}


	/**
	 * On EditFilterMerged, validate that the revised contents are valid JSON,
	 * and reject the edit otherwise.
	 *
	 * @param $editor EditPage
	 * @param $text string Content of the revised article
	 * @param &$error string Error message to return
	 * @param $summary string Edit summary provided for edit
	 */
	public static function onEditFilterMerged( $editor, $text, &$error, $summary ) {
		if ( !self::isHomeWiki() ) {
			return true;
		}

		if ( $editor->getTitle()->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		$content = new JsonSchemaContent( $text );
		if ( !$content->isValid() ) {
			$error = '{{MediaWiki:InvalidJsonError}}'; // XXX(ori-l, 17-Nov-2012): i18n!
			return true;
		}

		return true;
	}


	/**
	 * On PageContentSaveComplete, check if the page we're saving is in the
	 * NS_SCHEMA namespace. If so, cache its content and mtime.
	 *
	 * @return bool
	 */
	public static function onPageContentSaveComplete( $article, $user,
		$content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status,
		$baseRevId ) {

		global $wgMemc, $wgResourceModules;

		if ( !self::isHomeWiki() ) {
			return true;
		}

		$title = $article->getTitle();

		if ( $revision === NULL || $title->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		$model = FormatJson::decode( $content->getNativeData(), true );
		if ( !is_array( $model ) ) {
			wfDebugLog( 'EventLogging', 'New data model revision fails to parse.' );
			return true;
		}

		$wgMemc->set( wfModelKey( $title->getDBkey() ), $model );
		$wgMemc->set( wfModelKey( $title->getDBkey(), 'mTime' ),
			wfTimestamp( TS_UNIX, $revision->getTimestamp() ) );
		return true;
	}


	/**
	 * On ContentHandlerDefaultModelFor, specify JsonSchema as the
	 * content model for articles in the NS_SCHEMA namespace.
	 *
	 * @param $title Title Specify model for this title.
	 * @param &$model string The desired model.
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( !self::isHomeWiki() ) {
			return true;
		}

		global $wgOut;

		if ( $title->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		$model = 'JsonSchema';
		$wgOut->addModules( 'ext.eventLogging.jsonSchema' );

		return false;
	}
}
