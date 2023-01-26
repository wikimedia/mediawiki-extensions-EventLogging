<?php

namespace MediaWiki\Extension\EventLogging\Test\EventSubmitter;

use ExtensionRegistry;
use Generator;
use HashConfig;
use MediaWiki\Extension\EventBus\EventBus;
use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Extension\EventLogging\EventSubmitter\EventBusEventSubmitter;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\EventLogging\EventSubmitter\EventBusEventSubmitter
 */
class EventBusEventSubmitterTest extends MediaWikiIntegrationTestCase {

	/** @var EventBus */
	private $mockEventBus;

	/** @var LoggerInterface */
	private $mockLogger;

	/** @var EventBusEventSubmitter */
	private $eventSubmitter;

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventBus' ) ) {
			$this->markTestSkipped( 'The EventBus extension is not loaded.' );
		}

		$this->mockEventBus = $this->createMock( EventBus::class );

		$mockEventBusFactory = $this->createMock( EventBusFactory::class );
		$mockEventBusFactory->method( 'getInstanceForStream' )
			->willReturn( $this->mockEventBus );

		$this->setService( 'EventBus.EventBusFactory', static function () use ( $mockEventBusFactory ) {
			return $mockEventBusFactory;
		} );

		$this->setService( 'EventLogging.StreamConfigs', static function () {
			return false;
		} );

		$this->mockLogger = $this->createMock( LoggerInterface::class );

		$config = new HashConfig( [
			'ServerName' => 'event_bus_event_submitter.test'
		] );

		$this->eventSubmitter = new EventBusEventSubmitter( $this->mockLogger, $config );
	}

	public function testSubmitWithoutSchemaField(): void {
		$this->mockLogger->expects( $this->once() )
			->method( 'warning' )
			->with( 'Event data is missing required field "$schema"', $this->anything() );

		$this->mockEventBus->expects( $this->never() )
			->method( 'send' );

		$this->eventSubmitter->submit( 'test.event', [] );
	}

	public function provideSubmissions(): Generator {
		yield [
			'streamName' => 'test.event',
			'event' => [
				'$schema' => '/test/event/1.0.0',
			],
		];

		yield [
			'streamName' => 'eventlogging_Test',
			'event' => [
				'$schema' => '/analytics/legacy/test/1.0.0',
				'client_dt' => wfTimestamp( TS_ISO_8601 ),
			],
		];

		// Test that EventBusEventSubmitter#prepareEvent() uses array_replace_recursive
		yield [
			'streamName' => 'test.event',
			'event' => [
				'$schema' => '/test/event/1.0.0',
				'dt' => wfTimestamp( TS_ISO_8601 )
			],
		];
	}

	/**
	 * @dataProvider provideSubmissions
	 */
	public function testSubmit( string $streamName, array $event ): void {
		$this->mockEventBus->expects( $this->once() )
			->method( 'send' )
			->with( $this->callback( function ( $events ) use ( $streamName ) {
				$this->assertEventCanBeIngested( $events[0], $streamName );

				return true;
			} ) );

		$this->eventSubmitter->submit( $streamName, $event );
	}

	public function testSubmitToUnknownStream(): void {
		$this->setService( 'EventLogging.StreamConfigs', static function () {
			return [];
		} );

		$this->mockLogger->expects( $this->once() )
			->method( 'warning' )
			->with(
				'Event submitted for unregistered stream name "{streamName}"',
				[
					'streamName' => 'test.event',
				]
			);

		$this->mockEventBus->expects( $this->never() )
			->method( 'send' );

		$this->eventSubmitter->submit(
			'test.event',
			[
				'$schema' => '/test/event/1.0.0',
			]
		);
	}

	private function assertIsTimestamp( string $timestamp ): void {
		// FIXME: Find a better way to assert "this is an ISO 8601 timestamp"
		$this->assertMatchesRegularExpression(
			TestingAccessWrapper::newFromClass( ConvertibleTimestamp::class )->regexes['TS_ISO_8601'],
			$timestamp
		);
		$this->assertStringEndsWith( 'Z', $timestamp );
	}

	private function assertEventCanBeIngested( $event, $streamName ): void {
		if ( array_key_exists( 'client_dt', $event ) ) {
			$this->assertArrayNotHasKey( 'dt', $event );
		} else {
			$this->assertArrayHasKey( 'dt', $event );
			$this->assertIsTimestamp( $event['dt'] );
		}

		$this->assertSame( $streamName, $event['meta']['stream'] );
		$this->assertSame( 'event_bus_event_submitter.test', $event['meta']['domain'] );

		$this->assertArrayHasKey( 'user-agent', $event['http']['request_headers'] );
	}
}
