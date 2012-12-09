<?php
/**
 * Hooks for managing JSON Schema namespace and content model.
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author  Ori Livneh <ori@wikimedia.org>
 */

class JsonSchemaHooks {

	/**
	 * Registers hook and content handlers if the JSON Schema
	 * namespace is enabled for this site.
	 *
	 * @return  bool  Whether hooks and handler were registered
	 */
	public static function registerHandlers() {
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
	 * Declare JSON as the code editor language for Schema: pages. This hook
	 * only runs if the CodeEditor extension is enabled.
	 *
	 * @param   $title  Title
	 * @param   &$lang  string  Page language
	 * @return  bool
	 */
	public static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		if ( $title->getNamespace() === NS_SCHEMA ) {
			$lang = 'json';
		}
		return true;
	}


	/**
	 * Register Schema namespaces and assign edit rights.
	 *
	 * @param   &$namespaces  array  Mapping of numbers to namespace names.
	 * @return  bool
	 */
	public static function onCanonicalNamespaces( array &$namespaces ) {
		global $wgGroupPermissions, $wgNamespaceContentModels, $wgNamespaceProtection;

		$namespaces[ NS_SCHEMA ] = 'Schema';
		$namespaces[ NS_SCHEMA_TALK ] = 'Schema_talk';

		$wgNamespaceProtection[ NS_SCHEMA ] = array( 'editinterface' );
		$wgNamespaceContentModels[ NS_SCHEMA ] = 'JsonSchema';

		return true;
	}


	/**
	 * On EditFilterMerged, validate that the revised contents are valid JSON,
	 * and reject the edit otherwise.
	 *
	 * @param  $editor   EditPage
	 * @param  $text     string    Content of the revised article
	 * @param  &$error   string    Error message to return
	 * @param  $summary  string    Edit summary provided for edit
	 */
	public static function onEditFilterMerged( $editor, $text, &$error, $summary ) {
		if ( $editor->getTitle()->getNamespace() !== NS_SCHEMA ) {
			return true;
		}

		$content = new JsonSchemaContent( $text );
		if ( !$content->isValid() ) {
			$error = wfMessage( 'eventlogging-invalid-json' )->parse();
			return true;
		}

		return true;
	}


	/**
	 * On BeforePageDisplay, in-line CSS for Schema objects.
	 *
	 * @param   &$out   OutputPage
	 * @param   &$skin  Skin
	 * @return  bool
	 */
	public static function onBeforePageDisplay( &$out, &$skin ) {
		if ( $out->getTitle()->getNamespace() === NS_SCHEMA ) {
			$out->addModuleStyles( 'ext.eventLogging.jsonSchema' );
		}
		return true;
	}
}
