<?php

namespace MediaWiki\Extension\EventLogging\EventSubmitter;

interface EventSubmitter {

	/**
	 * Submit an event according to the configuration of the given stream.
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Event_Platform
	 * @see https://wikitech.wikimedia.org/wiki/Event_Platform/Instrumentation_How_To#In_PHP
	 *
	 * @param string $streamName
	 * @param array $event
	 */
	public function submit( string $streamName, array $event ): void;
}
