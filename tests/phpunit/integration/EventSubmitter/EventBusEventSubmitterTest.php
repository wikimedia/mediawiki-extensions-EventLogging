<?php

namespace MediaWiki\Extension\EventLogging\Test\EventSubmitter;

require_once __DIR__ . '/../../EventLoggingTestTrait.php';

use ExtensionRegistry;
use Generator;
use HashConfig;
use MediaWiki\Extension\EventBus\EventBus;
use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Extension\EventLogging\EventSubmitter\EventBusEventSubmitter;
use MediaWiki\Extension\EventLogging\Test\EventLoggingTestTrait;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\EventLogging\EventSubmitter\EventBusEventSubmitter
 */
class EventBusEventSubmitterTest extends MediaWikiIntegrationTestCase {
	use EventLoggingTestTrait;

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

	public static function provideSubmissions(): Generator {
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
				$event = $events[0];

				$this->assertEventCanBeIngested( $event, $event['$schema'], $streamName );

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
}
