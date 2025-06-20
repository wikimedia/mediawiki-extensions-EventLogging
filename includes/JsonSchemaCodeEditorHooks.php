<?php

namespace MediaWiki\Extension\EventLogging;

use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Title\Title;

/**
 * EventLogging has a soft dependency on CodeEditor. Specifically, if CodeEditor is loaded, then EventLogging sets the
 * code language for pages in the Schema namespace to JSON by responding to the CodeEditorGetPageLanguage hook. We
 * maintain that soft dependency on CodeEditor by isolating CodeEditor-specific hook handlers to a class that will
 * only be autoloaded only when the hook fires.
 */
class JsonSchemaCodeEditorHooks implements CodeEditorGetPageLanguageHook {
	private JsonSchemaHooksHelper $jsonSchemaHooksHelper;

	public function __construct( JsonSchemaHooksHelper $jsonSchemaHooksHelper ) {
		$this->jsonSchemaHooksHelper = $jsonSchemaHooksHelper;
	}

	public function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, string $model, string $format ): void {
		if (
			$this->jsonSchemaHooksHelper->isSchemaNamespaceEnabled() &&
			$title->inNamespace( NS_SCHEMA )
		) {
			$lang = 'json';
		}
	}
}
