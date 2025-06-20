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

namespace MediaWiki\Extension\EventLogging;

use MediaWiki\Api\ApiModuleManager;
use MediaWiki\Api\Hook\ApiMain__moduleManagerHook;
use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\EventLogging\Libs\JsonSchemaValidation\JsonSchemaException;
use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\MovePageIsValidMoveHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class JsonSchemaHooks implements
	BeforePageDisplayHook,
	EditFilterMergedContentHook,
	MovePageIsValidMoveHook,
	ApiMain__moduleManagerHook,
	CanonicalNamespacesHook
{
	private JsonSchemaHooksHelper $jsonSchemaHooksHelper;

	public function __construct( JsonSchemaHooksHelper $jsonSchemaHooksHelper ) {
		$this->jsonSchemaHooksHelper = $jsonSchemaHooksHelper;
	}

	/**
	 * ApiMain::moduleManager hook to register jsonschema
	 * API module only if the Schema namespace is enabled
	 *
	 * @param ApiModuleManager $moduleManager
	 */
	public function onApiMain__moduleManager( $moduleManager ): void {
		if ( $this->jsonSchemaHooksHelper->isSchemaNamespaceEnabled() ) {
			$moduleManager->addModule(
				'jsonschema',
				'action',
				ApiJsonSchema::class
			);
		}
	}

	/**
	 * Validates that the revised contents are valid JSON.
	 * If not valid, rejects edit with error message.
	 *
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 * @return bool
	 */
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	): bool {
		$title = $context->getTitle();

		if ( $this->jsonSchemaHooksHelper->isSchemaNamespaceEnabled()
			|| !$title->inNamespace( NS_SCHEMA )
		) {
			return true;
		}

		if ( !preg_match( '/^[a-zA-Z0-9_-]{1,63}$/', $title->getText() ) ) {
			$status->fatal( 'badtitle' );
			// @todo Remove this line after this extension do not support mediawiki version 1.36 and before
			$status->value = EditPage::AS_HOOK_ERROR_EXPECTED;
			return false;
		}

		if ( !$content instanceof JsonSchemaContent ) {
			return true;
		}

		try {
			$content->validate();
			return true;
		} catch ( JsonSchemaException $e ) {
			$status->fatal( $context->msg( $e->getCode(), $e->args ) );
			// @todo Remove this line after this extension do not support mediawiki version 1.36 and before
			$status->value = EditPage::AS_HOOK_ERROR_EXPECTED;
			return false;
		}
	}

	/**
	 * Add the revision id as the subtitle on NS_SCHEMA pages.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$title = $out->getTitle();
		$revId = $out->getRevisionId();

		if ( $this->jsonSchemaHooksHelper->isSchemaNamespaceEnabled()
			&& $title->inNamespace( NS_SCHEMA )
			&& $revId !== null
		) {
			$out->addSubtitle( $out->msg( 'eventlogging-revision-id' )
				// We use 'rawParams' rather than 'numParams' to make it
				// easy to copy/paste the value into code.
				->rawParams( $revId )
				->escaped() );
		}
	}

	/**
	 * Prohibit moving (renaming) Schema pages, as doing so violates
	 * immutability guarantees.
	 *
	 * @param Title $currentTitle
	 * @param Title $newTitle
	 * @param Status $status
	 * @return bool
	 */
	public function onMovePageIsValidMove(
		$currentTitle, $newTitle, $status
	) {
		if ( !$this->jsonSchemaHooksHelper->isSchemaNamespaceEnabled() ) {
			// Namespace isn't even enabled
			return true;
		} elseif ( $currentTitle->inNamespace( NS_SCHEMA ) ) {
			$status->fatal( 'eventlogging-error-move-source' );
			return false;
		} elseif ( $newTitle->inNamespace( NS_SCHEMA ) ) {
			$status->fatal( 'eventlogging-error-move-destination' );
			return false;
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onCanonicalNamespaces( &$namespaces ): void {
		if ( $this->jsonSchemaHooksHelper->isSchemaNamespaceEnabled() ) {
			$namespaces[ NS_SCHEMA ] = 'Schema';
			$namespaces[ NS_SCHEMA_TALK ] = 'Schema_talk';
		}
	}
}
