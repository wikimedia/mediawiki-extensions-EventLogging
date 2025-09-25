<?php

namespace MediaWiki\Extension\EventLogging\Test;

use DateTimeImmutable;

/**
 * A handful of helpful assertions that can be made about events in unit and integration tests.
 */
trait EventLoggingTestTrait {
	public function assertIsTimestamp( string $timestamp ): void {
		$this->assertStringEndsWith( 'Z', $timestamp );
		$this->assertInstanceOf(
			DateTimeImmutable::class,
			DateTimeImmutable::createFromFormat( DateTimeImmutable::ATOM, $timestamp )
		);
	}

	public function assertEventCanBeIngested( array $event, string $schema, string $streamName ): void {
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
