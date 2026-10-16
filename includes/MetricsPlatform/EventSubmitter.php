<?php

namespace MediaWiki\Extension\EventLogging\MetricsPlatform;

use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter as EventLoggingEventSubmitter;
use Wikimedia\MetricsPlatform\EventSubmitter as MetricsPlatformEventSubmitter;

/**
 * Adapts implementations of the EventLogging EventSubmitter interface to the equivalent Metrics
 * Platform interface, thereby allowing them to vary independently.
 *
 * @internal
 * @deprecated since 1.48, use TestKitchen.InstrumentManager and Instrument::send() instead.
 */
class EventSubmitter implements MetricsPlatformEventSubmitter {

	/** @var EventLoggingEventSubmitter */
	private $eventSubmitter;

	public function __construct( EventLoggingEventSubmitter $eventSubmitter ) {
		$this->eventSubmitter = $eventSubmitter;
	}

	/**
	 * @inheritDoc
	 * @deprecated since 1.48 Use the Test Kitchen PHP SDK instead, via the
	 * TestKitchen.InstrumentManager service and Instrument::send().
	 * @see https://wikitech.wikimedia.org/wiki/Test_Kitchen/SDK/PHP_SDK
	 */
	public function submit( string $streamName, array $event ): void {
		$this->eventSubmitter->submit( $streamName, $event );
	}
}
