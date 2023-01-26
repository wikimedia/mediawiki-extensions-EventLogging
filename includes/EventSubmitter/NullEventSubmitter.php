<?php

namespace MediaWiki\Extension\EventLogging\EventSubmitter;

/**
 * An event submitter that drops all events.
 */
class NullEventSubmitter implements EventSubmitter {

	/** @inheritDoc */
	public function submit( string $streamName, array $event ): void {
	}
}
