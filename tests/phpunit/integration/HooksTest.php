<?php

namespace MediaWiki\Extension\EventLogging\Tests\Integration;

use Generator;
use MediaWiki\Extension\EventLogging\Hooks;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\EventLogging\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'EventStreamConfig' );

		$this->setMwGlobals( [
			'wgEventStreams' => [
				'test.event' => [
					'schema_title' => 'test/event',
					'destination_event_service' => 'foo-event-service',
					'topic_prefixes' => [ 'foo.' ],
					'canary_events_enabled' => true,
					'producers' => [
						'foo_producer' => [
							'enabled' => true,
						],
					],
					'consumers' => [
						'foo_consumer' => [
							'enabled' => true,
						],
					],
					'sample' => [
						'unit' => 'pageview',
						'rate' => 0.01,
					],
				],
			],
		] );
	}

	public function provideEventLoggingConfigStreamConfigs(): Generator {
		yield [
			'eventLoggingStreamNames' => false,
			'expectedStreamConfigs' => false,
		];
		yield [
			'eventLoggingStreamNames' => [
				'test.event',
			],
			'expectedStreamConfigs' => [
				'test.event' => [
					'producers' => [
						'foo_producer' => [
							'enabled' => true,
						],
					],
					'sample' => [
						'unit' => 'pageview',
						'rate' => 0.01,
					],
				],
			]
		];
	}

	/**
	 * @dataProvider provideEventLoggingConfigStreamConfigs
	 */
	public function testGetEventLoggingConfigStreamConfigs( $eventLoggingStreamNames, $expectedStreamConfigs ): void {
		$this->setMwGlobals( 'wgEventLoggingStreamNames', $eventLoggingStreamNames );

		// We could inject a HashConfig instance here. However, the EventLogging and
		// EventStreamConfig extensions are not fully isolated from one another and they both
		// use the config variables that were overridden above.
		$config = $this->getServiceContainer()->getMainConfig();

		$result = Hooks::getEventLoggingConfig( $config );

		$this->assertEquals( $expectedStreamConfigs, $result['streamConfigs'] );
	}
}
