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
	 * @return bool: Whether hooks and handler were registered.
	 */
	static function registerHandlers() {
		global $wgHooks, $wgContentHandlers, $wgEventLoggingDBname, $wgDBname;

		if ( $wgEventLoggingDBname === $wgDBname ) {
			$wgContentHandlers[ 'JsonSchema' ] = 'JsonSchemaContentHandler';

			$wgHooks[ 'BeforePageDisplay' ][] = 'JsonSchemaHooks::onBeforePageDisplay';
			$wgHooks[ 'CanonicalNamespaces' ][] = 'JsonSchemaHooks::onCanonicalNamespaces';
			$wgHooks[ 'EditFilterMerged' ][] = 'JsonSchemaHooks::onEditFilterMerged';
			$wgHooks[ 'CodeEditorGetPageLanguage' ][] = 'JsonSchemaHooks::onCodeEditorGetPageLanguage';

			return true;
		}

		return false;
	}

	/**
	 * Declares JSON as the code editor language for Schema: pages.
	 * This hook only runs if the CodeEditor extension is enabled.
	 * @param Title $title
	 * @param string &$lang: Page language.
	 * @return bool
	 */
	static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		if ( $title->getNamespace() === NS_SCHEMA ) {
			$lang = 'json';
		}
		return true;
	}

	/**
	 * Registers Schema namespaces and assign edit rights.
	 * @param array &$namespaces: Mapping of numbers to namespace names.
	 * @return bool
	 */
	static function onCanonicalNamespaces( array &$namespaces ) {
		global $wgNamespaceContentModels, $wgNamespaceProtection;

		$namespaces[ NS_SCHEMA ] = 'Schema';
		$namespaces[ NS_SCHEMA_TALK ] = 'Schema_talk';

		$wgNamespaceProtection[ NS_SCHEMA ] = array( 'autoconfirmed' );
		$wgNamespaceContentModels[ NS_SCHEMA ] = 'JsonSchema';

		return true;
	}

	/**
	 * Validates that the revised contents are valid JSON.
	 * If not valid, rejects edit with error message.
	 * @param EditPage $editor
	 * @param string $text: Content of the revised article.
	 * @param string &$error: Error message to return.
	 * @param string $summary: Edit summary provided for edit.
	 * @return True
	 */
	static function onEditFilterMerged( $editor, $text, &$error, $summary ) {
		if ( $editor->getTitle()->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		$content = new JsonSchemaContent( $text );

		try {
			$content->validate();
		} catch ( JsonSchemaException $e ) {
			$error = $e->getMessage();
		}

		return true;
	}

	/**
	 * Adds CSS for pretty-printing schema on NS_SCHEMA pages.
	 * @param &$out OutputPage
	 * @param &$skin Skin
	 * @return bool
	 */
	static function onBeforePageDisplay( &$out, &$skin ) {
		$title = $out->getTitle();
		$revId = $out->getRevisionId();

		if ( $title->inNamespace( NS_SCHEMA ) ) {
			$out->addModules( 'ext.eventLogging.jsonSchema' );

			if ( $revId !== null ) {
				$out->addSubtitle( $out->msg( 'eventlogging-revision-id' )
					// We use 'rawParams' rather than 'numParams' to make it
					// easy to copy/paste the value into code.
					->rawParams( $revId )
					->escaped() );
			}
		}
		return true;
	}
}
