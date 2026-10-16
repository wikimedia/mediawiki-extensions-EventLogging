<?php

namespace MediaWiki\Extension\EventLogging\EventSubmitter;

/**
 * An event submitter that drops all events.
 *
 * @deprecated since 1.48, use TestKitchen.InstrumentManager and Instrument::send() instead.
 */
class NullEventSubmitter implements EventSubmitter {

	/** @inheritDoc */
	public function submit( string $streamName, array $event ): void {
	}
}
