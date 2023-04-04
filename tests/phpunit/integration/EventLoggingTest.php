<?php

require_once __DIR__ . '/../EventLoggingTestTrait.php';

use MediaWiki\Extension\EventBus\EventBus;
use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Extension\EventLogging\Test\EventLoggingTestTrait;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/** @covers EventLogging */
class EventLoggingTest extends MediaWikiIntegrationTestCase {
	use EventLoggingTestTrait;

	private $mockEventBus;
	private $mockEventBusFactory;
	private $mockHttpRequestFactory;
	private $timestamp;
	private $mockLogger;

	/**
	 * This is supposed to be what a user might provide to EventLogging::logEvent
	 * i.e. an un-encapsulated event.  Used in tests as input data.
	 * @var array
	 */
	private $legacyEvent = [
		'field_a' => 'hi',
	];

	/**
	 * Represents a Modern Event Platform event. Like $this->>legacyEvent, but includes a target schema.
	 * @var array
	 */
	private $newEvent = [
		"field_a" => "hi",
		'$schema' => '/test/event/1.0.0'
	];

	/*
	 * HTTP_HOST will be set to this value during the tests if it isn't already set.
	 * @var string
	 */
	private $testHttpHost = 'test.eventlogging.domain';

	/**
	 * If true, HTTP_HOST has been modified and should be reset during tearDown.
	 * @var bool
	 */
	private $modifiedHttpHost = false;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgEventLoggingSchemas' => [
				'Migrated' => '/test/event/1.0.0',
				'NotMigrated' => 1337,
			],
			'wgEventLoggingBaseUri' => 'https://test.wikipedia.org/beacon/event',
			'wgServerName' => $this->testHttpHost,
			'wgEventStreams' => [
				'test.event' => [
					'stream' => 'test.event',
					'schema_title' => 'test/event'
				],
				'eventlogging_Migrated' => [
					'stream' => 'eventlogging_Migrated',
					'schema_title' => 'analytics/legacy/test/migrated'
				],
				'test.event.mp1' => [
					'producers' => [
						'metrics_platform_client' => [
							'events' => [
								'foo',
								'bar',
							],
						],
					],
				],
				'test.event.mp2' => [
					'producers' => [
						'metrics_platform_client' => [
							'events' => [
								'bar',
							],
						],
					],
				],
				'test.event.mp3' => [
					'producers' => [
						'metrics_platform_client' => [
							'events' => [
								'foo',
							],
							'provide_values' => [
								'agent_client_platform',
								'agent_client_platform_family',
								'page_namespace',
								'page_title',
								'page_wikidata_qid',
							],
						],
					],
				],
			],
			'wgEventLoggingStreamNames' => [
				'test.event',
				'eventlogging_Migrated',
				'test.event.mp1',
				'test.event.mp2',
				'test.event.mp3',
			],
		] );

		// EventLogging uses HTTP_HOST.  If it isn't set for tests, set a dummy,
		// Otherwise use the actual value.
		if ( !isset( $_SERVER['HTTP_HOST'] ) ) {
			$this->modifiedHttpHost = true;
			$_SERVER['HTTP_HOST'] = $this->testHttpHost;
		} else {
			$this->testHttpHost = $_SERVER['HTTP_HOST'];
		}

		$multiClient = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->createMultiClient();
		$this->mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$this->mockHttpRequestFactory->method( 'createMultiClient' )
			->willReturn( $multiClient );
		$this->setService( 'HttpRequestFactory', function () {
			return $this->mockHttpRequestFactory;
		} );

		$this->mockEventBus = $this->createMock( EventBus::class );
		$this->mockEventBusFactory = $this->createMock( EventBusFactory::class );
		$this->mockEventBusFactory->method( 'getInstanceForStream' )->willReturn(
			$this->mockEventBus );
		$this->setService( 'EventBus.EventBusFactory', function () {
			return $this->mockEventBusFactory;
		} );

		$this->timestamp = TestingAccessWrapper::newFromClass( ConvertibleTimestamp::class );

		$this->mockLogger = $this->createMock( LoggerInterface::class );
		$this->setLogger( 'EventLogging', $this->mockLogger );

		EventLogging::resetMetricsPlatformClient();
	}

	protected function tearDown(): void {
		parent::tearDown();

		// IF setHttpHost in setUp, we should unset it in tearDown.
		if ( $this->modifiedHttpHost ) {
			unset( $_SERVER['HTTP_HOST'] );
		}
	}

	public function testSendNewSchemaEvent(): void {
		$this->mockEventBus->expects( $this->once() )
			->method( 'send' )
			->with( $this->callback( function ( $events ) {
				$this->assertEventCanBeIngested(
					$events[0],
					'/test/event/1.0.0',
					'test.event'
				);

				return true;
			} ) );

		EventLogging::submit( 'test.event', $this->newEvent );
	}

	public function testSendMigratedLegacyEvent(): void {
		$this->mockEventBus->expects( $this->once() )
			->method( 'send' )
			->with( $this->callback( function ( $events ) {
				$this->assertEventCanBeIngested(
					$events[0],
					'/test/event/1.0.0',
					'eventlogging_Migrated'
				);

				return true;
			} ) );

		EventLogging::logEvent( 'Migrated', 1337,  $this->legacyEvent );
	}

	public function testSendNonMigratedLegacyEvent(): void {
		$this->mockHttpRequestFactory->expects( $this->once() )
			->method( 'post' )
			->with( $this->callback( static function ( $url ) {
				return (
					is_string( $url ) &&
					str_starts_with( $url, 'https://test.wikipedia.org/beacon/event?' ) &&
					// Test that the base event is in the encoded event.
					// "field_a": "hi" url encodes to this.
					str_contains( $url, "%22field_a%22%3A%22hi%22" )
				);
			} ) );

		EventLogging::logEvent( 'NotMigrated', 1337,  $this->legacyEvent );
	}

	public function testEncapulateEventServiceEvent(): void {
		$result = TestingAccessWrapper::newFromClass( EventLogging::class )->encapsulate(
			'Migrated',
			'/test/event/1.0.0',
			$this->legacyEvent
		);

		$this->assertSame( '/test/event/1.0.0', $result['$schema'] );
		$this->assertMatchesRegularExpression( $this->timestamp->regexes['TS_ISO_8601'], $result['client_dt'] );
		$this->assertStringEndsWith( 'Z', $result['client_dt'] );
		$this->assertSame( $this->testHttpHost, $result['webHost'] );
		$this->assertSame( $result['event']['field_a'],  $this->legacyEvent['field_a'] );
	}

	public function testGetLegacyStreamName(): void {
		$result = TestingAccessWrapper::newFromClass( EventLogging::class )
			->getLegacyStreamName( 'Foo' );
		$this->assertSame( 'eventlogging_Foo', $result );
	}

	public function testFailIfSchemaNotSpecified(): void {
		$this->mockLogger->expects( $this->once() )->method( 'warning' );
		EventLogging::submit( 'test.event', [] );
	}

	public function testFailIfStreamNameNotConfigured(): void {
		$this->mockLogger->expects( $this->once() )->method( 'warning' );
		EventLogging::submit(
			'not.configured',
			[ '$schema' => '/test/event/1.0.0' ]
		);
	}

	public function testDisableStreamConfig(): void {
		$this->setMwGlobals( [ 'wgEventLoggingStreamNames' => false ] );
		$this->mockEventBus->expects( $this->once() )->method( 'send' );
		EventLogging::submit(
			'not.configured',
			[ '$schema' => '/test/event/1.0.0' ]
		);
	}

	public function testSubmitLoggerParameterDeprecated(): void {
		$this->expectDeprecationAndContinue( '/\$logger parameter is deprecated/' );

		EventLogging::submit( 'test.event', $this->newEvent, $this->mockLogger );
	}

	private function submitMetricsEvent( string $eventName, array $customData = [] ): array {
		$events = [];

		$this->mockEventBus->expects( $this->atLeastOnce() )
			->method( 'send' )
			->with( $this->callback( static function ( $es ) use ( &$events ) {
				$events[] = $es[0];

				return true;
			} ) );

		EventLogging::submitMetricsEvent( $eventName, $customData );

		return $events;
	}

	public function testDispatch(): void {
		$events = $this->submitMetricsEvent( 'bar', [ 'baz' => 'quux' ] );

		$this->assertCount( 2, $events );

		// First event…
		$event1 = $events[0];

		$this->assertEventCanBeIngested(
			$event1,
			// FIXME: This should be a public class constant on MetricsClient, i.e.
			//  MetricsClient::SCHEMA.
			'/analytics/mediawiki/client/metrics_event/1.2.0',
			'test.event.mp1'
		);

		// Assertions relating to the Metrics Platform:
		foreach ( [ 'agent', 'page', 'mediawiki', 'performer' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $event1 );
		}

		$this->assertArrayEquals(
			[
				'baz' => [
					'data_type' => 'string',
					'value' => 'quux',
				]
			],
			$event1['custom_data'],
		);

		// Second event…
		$event2 = $events[1];

		$this->assertSame( 'test.event.mp2', $event2['meta']['stream'] );

		unset( $event1['meta']['stream'], $event2['meta']['stream'] );

		$this->assertArrayEquals( $event1, $event2, 'The same event is submitted to both streams' );
	}

	public function testDispatchAddsContextAttributes(): void {
		$contextSource = RequestContext::getMain();

		$title = Title::makeTitle( NS_MAIN, 'Luke_Holland' );
		$contextSource->setTitle( $title );

		$output = $contextSource->getOutput();
		$output->setProperty( 'wikibase_item', 'Q22278223' );

		$events = $this->submitMetricsEvent( 'foo' );

		$this->assertCount( 2, $events );

		// We're only interested in event 2…
		$event2 = $events[1];

		$this->assertEventCanBeIngested(
			$event2,
			'/analytics/mediawiki/client/metrics_event/1.2.0',
			'test.event.mp3'
		);

		$this->assertArrayEquals(
			[
				'agent_client_platform' => 'mediawiki_php',
				'agent_client_platform_family' => 'desktop_browser',
			],
			$event2['agent']
		);

		$this->assertArrayEquals(
			[
				'page_namespace' => NS_MAIN,
				'page_title' => 'Luke_Holland',
				'page_wikidata_qid' => 'Q22278223',
			],
			$event2['page'],
			false,
			false,
			'Only the requested context attributes are added to the event'
		);

		foreach ( [ 'mediawiki', 'perfomer' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $event2 );
		}
	}
}
