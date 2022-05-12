<?php

namespace MediaWiki\Extension\EventLogging\Test;

use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * A handful of helpful assertions that can be made about events in unit and integration tests.
 */
trait EventLoggingTestTrait {
	public function assertIsTimestamp( string $timestamp ): void {
		// FIXME: Find a better way to assert "this is an ISO 8601 timestamp"
		$this->assertMatchesRegularExpression(
			TestingAccessWrapper::newFromClass( ConvertibleTimestamp::class )->regexes['TS_ISO_8601'],
			$timestamp
		);
		$this->assertStringEndsWith( 'Z', $timestamp );
	}

	public function assertEventCanBeIngested( $event, $schema, $streamName ): void {
		$this->assertSame( $schema, $event['$schema'] );

		$this->assertArrayHasKey( 'meta', $event );
		$this->assertSame( $streamName, $event['meta']['stream'] );
		$this->assertArrayHasKey( 'domain', $event['meta'] );

		if ( array_key_exists( 'client_dt', $event ) ) {
			$this->assertArrayNotHasKey( 'dt', $event );
		} else {
			$this->assertArrayHasKey( 'dt', $event );
			$this->assertIsTimestamp( $event['dt'] );
		}

		$this->assertArrayHasKey( 'user-agent', $event['http']['request_headers'] );
	}
}
