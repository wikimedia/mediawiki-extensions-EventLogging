<?php
/**
 * Hooks for managing JSON Schema namespace and content model.
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class JsonSchemaHooks {

	/**
	 * Registers hook and content handlers if the JSON Schema
	 * namespace is enabled for this site.
	 *
	 * @return bool Whether hooks and handler were registered
	 */
	public static function registerHandlers() {
		global $wgHooks, $wgContentHandlers, $wgEventLoggingDBname, $wgDBname;

		if ( $wgEventLoggingDBname === $wgDBname ) {
			$wgContentHandlers[ 'JsonSchema' ] = 'JsonSchemaContentHandler';

			$wgHooks[ 'CanonicalNamespaces' ][] = 'JsonSchemaHooks::onCanonicalNamespaces';
			$wgHooks[ 'ContentHandlerDefaultModelFor' ][] = 'JsonSchemaHooks::onContentHandlerDefaultModelFor';
			$wgHooks[ 'EditFilterMerged' ][] = 'JsonSchemaHooks::onEditFilterMerged';
			$wgHooks[ 'PageContentSaveComplete' ][] = 'JsonSchemaHooks::onPageContentSaveComplete';

			return true;
		}

		return false;
	}


	/**
	 * Register Schema namespaces and assign edit rights.
	 *
	 * @param &$namespaces array Mapping of numbers to namespace names.
	 * @return bool
	 */
	public static function onCanonicalNamespaces( array &$namespaces ) {
		global $wgGroupPermissions, $wgNamespaceProtection;

		$namespaces[ NS_SCHEMA ] = 'Schema';
		$namespaces[ NS_SCHEMA_TALK ] = 'Schema_talk';

		$wgNamespaceProtection[ NS_SCHEMA ] = array( 'editinterface' );

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

		$title = $article->getTitle();

		if ( $revision === NULL || $title->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		$schema = FormatJson::decode( $content->getNativeData(), true );
		if ( !is_array( $schema ) ) {
			wfDebugLog( 'EventLogging', 'New schema revision fails to parse.' );
			return true;
		}

		$wgMemc->set( wfSchemaKey( $title->getDBkey() ), $schema );
		$wgMemc->set( wfSchemaKey( $title->getDBkey(), 'mTime' ),
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
		global $wgOut;

		if ( $title->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		$model = 'JsonSchema';
		$wgOut->addModules( 'ext.eventLogging.jsonSchema' );

		return false;
	}
}
