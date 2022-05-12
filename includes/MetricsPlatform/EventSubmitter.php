<?php

namespace MediaWiki\Extension\EventLogging\MetricsPlatform;

use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter as EventLoggingEventSubmitter;
use Wikimedia\MetricsPlatform\EventSubmitter as MetricsPlatformEventSubmitter;

/**
 * Adapts implementations of the EventLogging EventSubmitter interface to the equivalent Metrics
 * Platform interface, thereby allowing them to vary independently.
 */
class EventSubmitter implements MetricsPlatformEventSubmitter {

	/** @var EventLoggingEventSubmitter */
	private $eventSubmitter;

	public function __construct( EventLoggingEventSubmitter $eventSubmitter ) {
		$this->eventSubmitter = $eventSubmitter;
	}

	/** @inheritDoc */
	public function submit( string $streamName, array $event ): void {
		$this->eventSubmitter->submit( $streamName, $event );
	}
}
