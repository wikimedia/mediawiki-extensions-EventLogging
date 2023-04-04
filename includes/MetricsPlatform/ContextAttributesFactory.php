<?php

namespace MediaWiki\Extension\EventLogging\MetricsPlatform;

use Config;
use ExtensionRegistry;
use IContextSource;
use Language;
use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserOptionsLookup;
use MobileContext;
use NamespaceInfo;

/**
 * @internal
 */
class ContextAttributesFactory {

	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @var ExtensionRegistry
	 */
	private $extensionRegistry;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	/**
	 * @var RestrictionStore
	 */
	private $restrictionStore;

	/**
	 * @var UserOptionsLookup
	 */
	private $userOptionsLookup;

	/**
	 * @var Language
	 */
	private $contentLanguage;

	/**
	 * @var UserGroupManager
	 */
	private $userGroupManager;

	/**
	 * @var LanguageConverterFactory
	 */
	private $languageConverterFactory;

	/**
	 * @var UserBucketService
	 */
	private $userBucketService;

	/**
	 * @param Config $mainConfig
	 * @param ExtensionRegistry $extensionRegistry
	 * @param NamespaceInfo $namespaceInfo
	 * @param RestrictionStore $restrictionStore
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param Language $contentLanguage
	 * @param UserGroupManager $userGroupManager
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param UserBucketService $userBucketService
	 */
	public function __construct(
		Config $mainConfig,
		ExtensionRegistry $extensionRegistry,
		NamespaceInfo $namespaceInfo,
		RestrictionStore $restrictionStore,
		UserOptionsLookup $userOptionsLookup,
		Language $contentLanguage,
		UserGroupManager $userGroupManager,
		LanguageConverterFactory $languageConverterFactory,
		UserBucketService $userBucketService
	) {
		$this->mainConfig = $mainConfig;
		$this->extensionRegistry = $extensionRegistry;
		$this->namespaceInfo = $namespaceInfo;
		$this->restrictionStore = $restrictionStore;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->contentLanguage = $contentLanguage;
		$this->userGroupManager = $userGroupManager;
		$this->languageConverterFactory = $languageConverterFactory;
		$this->userBucketService = $userBucketService;
	}

	/**
	 * @param IContextSource $contextSource
	 * @return array
	 */
	public function newContextAttributes( IContextSource $contextSource ): array {
		$contextAttributes = [];
		$contextAttributes += $this->getAgentContextAttributes();
		$contextAttributes += $this->getPageContextAttributes( $contextSource );
		$contextAttributes += $this->getMediaWikiContextAttributes( $contextSource );
		$contextAttributes += $this->getPerformerContextAttributes( $contextSource );

		return $contextAttributes;
	}

	/**
	 * Gets whether the user is accessing the mobile
	 *
	 * @return bool
	 */
	protected function isUsingMobileDomain(): bool {
		if ( $this->extensionRegistry->isLoaded( 'MobileFrontend' ) ) {
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			return MobileContext::singleton()->usingMobileDomain();
		}

		return false;
	}

	/**
	 * @return array
	 */
	private function getAgentContextAttributes(): array {
		return [
			'agent_app_install_id' => null,
			'agent_client_platform' => 'mediawiki_php',
			'agent_client_platform_family' =>
				$this->isUsingMobileDomain() ? 'mobile_browser' : 'desktop_browser',
		];
	}

	/**
	 * @param IContextSource $contextSource
	 * @return array
	 */
	private function getPageContextAttributes( IContextSource $contextSource ): array {
		$output = $contextSource->getOutput();
		$wikidataItemId = $output->getProperty( 'wikibase_item' );
		$wikidataItemId = $wikidataItemId === null ? null : (string)$wikidataItemId;

		$result = [

			// The wikidata_id (int) context attribute is deprecated in favor of wikidata_qid
			// (string). See T330459 and T332673 for detail.
			'page_wikidata_qid' => $wikidataItemId,

		];

		$title = $contextSource->getTitle();

		// IContextSource::getTitle() can return null.
		//
		// TODO: Document under what circumstances this happens.
		if ( !$title ) {
			return $result;
		}

		$namespace = $title->getNamespace();

		// See the wgPageContentLanguage JavaScript config variable defined in
		// \OutputPage::getJSVars().
		$pageContentLanguage = $title->getPageViewLanguage();

		return $result + [
				'page_id' => $title->getArticleID(),
				'page_title' => $title->getDBkey(),
				'page_namespace' => $namespace,
				'page_namespace_name' => $this->namespaceInfo->getCanonicalName( $namespace ),
				'page_revision_id' => $title->getLatestRevID(),
				'page_content_language' => $pageContentLanguage->getCode(),
				'page_is_redirect' => $title->isRedirect(),
				'page_groups_allowed_to_move' => $this->restrictionStore->getRestrictions( $title, 'move' ),
				'page_groups_allowed_to_edit' => $this->restrictionStore->getRestrictions( $title, 'edit' ),
			];
	}

	/**
	 * @param IContextSource $contextSource
	 * @return array
	 */
	private function getMediaWikiContextAttributes( IContextSource $contextSource ): array {
		$skin = $contextSource->getSkin();

		$user = $contextSource->getUser();
		$isDebugMode =
			$this->userOptionsLookup->getIntOption( $user, 'eventlogging-display-web' ) === 1 ||
			$this->userOptionsLookup->getIntOption( $user, 'eventlogging-display-console' ) === 1;

		// TODO: Reevaluate whether the `mediawiki.is_production` contextual attribute is useful.
		//  We should be able to determine this from the database name of the wiki during analysis.
		$isProduction = strpos( MW_VERSION, 'wmf' ) !== false;

		return [
			'mediawiki_skin' => $skin->getSkinName(),
			'mediawiki_version' => MW_VERSION,
			'mediawiki_is_debug_mode' => $isDebugMode,
			'mediawiki_is_production' => $isProduction,
			'mediawiki_db_name' => $this->mainConfig->get( MainConfigNames::DBname ),
			'mediawiki_site_content_language' => $this->contentLanguage->getCode(),
		];
	}

	/**
	 * @param IContextSource $contextSource
	 * @return array
	 */
	private function getPerformerContextAttributes( IContextSource $contextSource ): array {
		$user = $contextSource->getUser();
		$userName = $user->isAnon() ? null : $user->getName();
		$userLanguage = $contextSource->getLanguage();

		$languageConverter = $this->languageConverterFactory->getLanguageConverter( $userLanguage );
		$userLanguageVariant = $languageConverter->hasVariants() ? $languageConverter->getPreferredVariant() : null;

		$userEditCount = $user->getEditCount();
		$userEditCountBucket = $user->isAnon() ? null : $this->userBucketService->bucketEditCount( $userEditCount );

		$registrationTimestamp = $user->getRegistration();

		if ( $registrationTimestamp ) {
			$registrationTimestamp = wfTimestamp( TS_ISO_8601, $registrationTimestamp );
		}

		$result = [
			'performer_is_logged_in' => !$user->isAnon(),
			'performer_id' => $user->getId(),
			'performer_name' => $userName,
			'performer_groups' => $this->userGroupManager->getUserEffectiveGroups( $user ),
			'performer_is_bot' => $user->isBot(),
			'performer_language' => $userLanguage->getCode(),
			'performer_language_variant' => $userLanguageVariant,
			'performer_edit_count' => $userEditCount,
			'performer_edit_count_bucket' => $userEditCountBucket,
			'performer_registration_dt' => $registrationTimestamp,
		];

		// IContextSource::getTitle() can return null.
		//
		// TODO: Document under what circumstances this happens.
		$title = $contextSource->getTitle();

		if ( $title ) {
			$result['performer_can_probably_edit_page'] = $user->probablyCan( 'edit', $title );
		}

		return $result;
	}
}
