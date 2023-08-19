<?php

use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketService;
use MediaWiki\Extension\EventLogging\MetricsPlatform\ContextAttributesFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\EventLogging\MetricsPlatform\ContextAttributesFactory
 * @group Database
 */
class ContextAttributesFactoryTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var array
	 */
	private $services;

	/**
	 * @var ContextAttributesFactory
	 */
	private $contextAttributesFactory;

	public function setUp(): void {
		parent::setUp();

		$services = $this->getServiceContainer();

		$this->services = [
			'mainConfig' => $services->getMainConfig(),
			'extensionRegistry' => $this->createMock( ExtensionRegistry::class ),
			'namespaceInfo' => $services->getNamespaceInfo(),
			'restrictionStore' => $services->getRestrictionStore(),
			'userOptionsLookup' => $services->getUserOptionsLookup(),
			'contentLanguage' => $services->getContentLanguage(),
			'userGroupManager' => $services->getUserGroupManager(),
			'languageConverterFactory' => $services->getLanguageConverterFactory(),
			'userBucketService' => new UserBucketService( $services->getUserEditTracker() ),
		];

		// Unit Under Test
		$this->contextAttributesFactory = $this->getMockBuilder( ContextAttributesFactory::class )
			->setConstructorArgs( $this->services )
			->onlyMethods( [ 'isUsingMobileDomain' ] )
			->getMock();
	}

	public static function provideAgentContextAttributes(): Generator {
		yield [
			'isUsingMobileDomain' => false,
			'expectedClientPlatformFamily' => 'desktop_browser',
		];

		yield [
			'isUsingMobileDomain' => true,
			'expectedClientPlatformFamily' => 'mobile_browser',
		];
	}

	/**
	 * @dataProvider provideAgentContextAttributes
	 */
	public function testAgentContextAttributes( $isUsingMobileDomain, $expectedClientPlatformFamily ): void {
		$user = $this->createMock( User::class );
		$user->method( 'getEditCount' )->willReturn( 42 );

		$title = Title::makeTitle( NS_SPECIAL, 'Blankpage' );
		$contextSource = RequestContext::newExtraneousContext( $title );
		$contextSource->setUser( $user );

		$this->contextAttributesFactory->method( 'isUsingMobileDomain' )
			->will( $this->returnValue( $isUsingMobileDomain ) );

		$contextAttributes = $this->contextAttributesFactory->newContextAttributes( $contextSource );

		$this->assertNull( $contextAttributes[ 'agent_app_install_id' ] );
		$this->assertSame( 'mediawiki_php', $contextAttributes[ 'agent_client_platform' ] );
		$this->assertSame(
			$expectedClientPlatformFamily,
			$contextAttributes[ 'agent_client_platform_family' ]
		);
	}

	public function testPageContextAttributes(): void {
		$title = Title::makeTitle( NS_SPECIAL, 'Blankpage' );
		$contextSource = RequestContext::newExtraneousContext( $title );

		$expectedNamespace = $title->getNamespace();
		$expectedNamespaceName = $this->services['namespaceInfo']->getCanonicalName( $expectedNamespace );

		$expectedWikidataItemId = 'QFooBarBaz';

		$output = $contextSource->getOutput();
		$output->setProperty( 'wikibase_item', $expectedWikidataItemId );

		$expectedPageContentLanguage = $title->getPageViewLanguage();

		$restrictionStore = $this->services['restrictionStore'];
		$expectedGroupsAllowedToMove = $restrictionStore->getRestrictions( $title, 'move' );
		$expectedGroupsAllowedToEdit = $restrictionStore->getRestrictions( $title, 'edit' );

		$contextAttributes = $this->contextAttributesFactory->newContextAttributes( $contextSource );

		$this->assertSame( $title->getArticleID(), $contextAttributes[ 'page_id' ] );
		$this->assertSame( $title->getDBkey(), $contextAttributes[ 'page_title' ] );
		$this->assertSame( $expectedNamespace, $contextAttributes[ 'page_namespace' ] );
		$this->assertSame( $expectedNamespaceName, $contextAttributes[ 'page_namespace_name' ] );
		$this->assertSame( $title->getLatestRevID(), $contextAttributes[ 'page_revision_id' ] );
		$this->assertSame( $expectedWikidataItemId, $contextAttributes[ 'page_wikidata_qid' ] );
		$this->assertSame(
			$expectedPageContentLanguage->getCode(),
			$contextAttributes[ 'page_content_language' ]
		);
		$this->assertSame( $title->isRedirect(), $contextAttributes[ 'page_is_redirect' ] );
		$this->assertSame(
			$expectedGroupsAllowedToMove,
			$contextAttributes[ 'page_groups_allowed_to_move' ]
		);
		$this->assertSame(
			$expectedGroupsAllowedToEdit,
			$contextAttributes[ 'page_groups_allowed_to_edit' ]
		);
	}

	public function testPageWikidataIdHandlesNull(): void {
		$title = Title::makeTitle( NS_SPECIAL, 'Blankpage' );
		$contextSource = RequestContext::newExtraneousContext( $title );

		$contextAttributes = $this->contextAttributesFactory->newContextAttributes( $contextSource );

		$this->assertNull( $contextAttributes[ 'page_wikidata_qid' ] );
	}

	public function testMediaWikiContextAttributes(): void {
		$title = Title::makeTitle( NS_SPECIAL, 'Blankpage' );
		$contextSource = RequestContext::newExtraneousContext( $title );

		$contextAttributes = $this->contextAttributesFactory->newContextAttributes( $contextSource );

		$mainConfig = $this->services['mainConfig'];

		$expectedMediaWikiIsProduction = strpos( MW_VERSION, 'wmf' ) !== false;

		$expectedMediaWikiDBName = $mainConfig->get( MainConfigNames::DBname );
		$expectedMediaWikiSiteContentLanguage = $this->services['contentLanguage']->getCode();

		$this->assertSame( MW_VERSION, $contextAttributes[ 'mediawiki_version' ] );
		$this->assertSame( $expectedMediaWikiIsProduction, $contextAttributes[ 'mediawiki_is_production' ] );
		$this->assertSame( $expectedMediaWikiDBName, $contextAttributes[ 'mediawiki_db_name' ] );
		$this->assertSame(
			$expectedMediaWikiSiteContentLanguage,
			$contextAttributes[ 'mediawiki_site_content_language' ]
		);
	}

	public static function provideMediaWikiIsDebugMode(): Generator {
		yield [
			'eventLoggingDisplayWeb' => 0,
			'eventLoggingDisplayConsole' => 0,
			'expectedMediaWikiIsDebugMode' => false,
		];
		yield [
			'eventLoggingDisplayWeb' => 1,
			'eventLoggingDisplayConsole' => 0,
			'expectedMediaWikiIsDebugMode' => true,
		];
		yield [
			'eventLoggingDisplayWeb' => 0,
			'eventLoggingDisplayConsole' => 1,
			'expectedMediaWikiIsDebugMode' => true,
		];
		yield [
			'eventLoggingDisplayWeb' => 1,
			'eventLoggingDisplayConsole' => 1,
			'expectedMediaWikiIsDebugMode' => true,
		];
	}

	/**
	 * @dataProvider provideMediaWikiIsDebugMode
	 */
	public function testBuildIsDebugMode(
		$eventLoggingDisplayWeb,
		$eventLoggingDisplayConsole,
		$expectedMediaWikiIsDebugMode
	) {
		$user = $this->getMutableTestUser()->getUser();

		$title = Title::makeTitle( NS_SPECIAL, 'Blankpage' );
		$contextSource = RequestContext::newExtraneousContext( $title );
		$contextSource->setUser( $user );

		$services = $this->getServiceContainer();
		$userOptionsManager = $services->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'eventlogging-display-web', $eventLoggingDisplayWeb );
		$userOptionsManager->setOption( $user, 'eventlogging-display-console', $eventLoggingDisplayConsole );
		$userOptionsManager->saveOptions( $user );

		$contextAttributes = $this->contextAttributesFactory->newContextAttributes( $contextSource );

		$this->assertSame( $expectedMediaWikiIsDebugMode, $contextAttributes[ 'mediawiki_is_debug_mode' ] );
	}

	public function testPerformerContextAttributes(): void {
		$groups = [ 'foo', 'bar' ];

		$user = $this->getTestUser( $groups )->getUser();

		$expectedGroups = $this->services['userGroupManager']->getUserEffectiveGroups( $user );

		$title = Title::makeTitle( NS_SPECIAL, 'Blankpage' );
		$contextSource = RequestContext::newExtraneousContext( $title );
		$contextSource->setUser( $user );

		$expectedUserLanguage = $contextSource->getLanguage();

		$languageConverter = $this->services['languageConverterFactory']->getLanguageConverter( $expectedUserLanguage );
		$expectedUserLanguageVariant =
			$languageConverter->hasVariants() ? $languageConverter->getPreferredVariant() : null;

		$expectedUserCanProbablyEditPage = $user->probablyCan( 'edit', $title );

		$expectedUserEditCountBucket = $this->services['userBucketService']->getUserEditCountBucket( $user );

		$expectedUserRegistrationTimestamp = $user->getRegistration();

		if ( $expectedUserRegistrationTimestamp ) {
			$expectedUserRegistrationTimestamp = wfTimestamp( TS_ISO_8601, $expectedUserRegistrationTimestamp );
		}

		$contextAttributes = $this->contextAttributesFactory->newContextAttributes( $contextSource );

		$this->assertSame( !$user->isAnon(), $contextAttributes[ 'performer_is_logged_in' ] );
		$this->assertSame(
			$user->getId(),
			$contextAttributes[ 'performer_id' ]
		);
		$this->assertSame( $user->getName(), $contextAttributes[ 'performer_name' ] );
		$this->assertSame( $expectedGroups, $contextAttributes[ 'performer_groups' ] );
		$this->assertSame( $user->isBot(), $contextAttributes[ 'performer_is_bot' ] );
		$this->assertSame( $expectedUserLanguage->getCode(), $contextAttributes[ 'performer_language' ] );
		$this->assertSame( $expectedUserLanguageVariant, $contextAttributes[ 'performer_language_variant' ] );
		$this->assertSame(
			$expectedUserCanProbablyEditPage,
			$contextAttributes[ 'performer_can_probably_edit_page' ]
		);
		$this->assertSame( $user->getEditCount(), $contextAttributes[ 'performer_edit_count' ] );
		$this->assertSame( $expectedUserEditCountBucket, $contextAttributes[ 'performer_edit_count_bucket' ] );
		$this->assertSame(
			$expectedUserRegistrationTimestamp,
			$contextAttributes[ 'performer_registration_dt' ]
		);
	}
}
