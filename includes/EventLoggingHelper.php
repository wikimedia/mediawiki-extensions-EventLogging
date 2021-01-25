<?php

use MediaWiki\MediaWikiServices;

class EventLoggingHelper {

	/**
	 * Prepares the event with extra data for submission.
	 * This will always set
	 * - `meta.stream` to $streamName
	 *
	 * This will optionally set (if not already in the event):
	 * - `dt` to the current time to represent the 'event time'
	 * - $eventDefaults
	 *
	 * @param string $streamName
	 * @param array $event
	 * @param array|null $eventDefaults
	 * @return array
	 */
	public static function prepareEvent(
		string $streamName,
		array $event,
		array $eventDefaults = null
	): array {
		$requiredData = [
			// meta.stream should always be set to $streamName
			'meta' => [
				'stream' => $streamName
			]
		];

		$preparedEvent = array_merge_recursive(
			$eventDefaults ?? self::getEventDefaults(),
			$event,
			$requiredData
		);

		$preparedEvent['dt'] = $preparedEvent['dt'] ?? date( DATE_ISO8601 );

		return $preparedEvent;
	}

	/**
	 * Returns values we always want set in events based on common
	 * schemas for all EventLogging events.  This sets:
	 *
	 * - meta.domain to the value of $config->get( 'ServerName' )
	 * - http.request_headers['user-agent'] to the value of $_SERVER( 'HTTP_USER_AGENT' ) ?? ''
	 *
	 * The returned object will be used as default values for the $event params passed
	 * to submit().
	 * @return array
	 */
	public static function getEventDefaults(): array {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		return [
			'meta' => [
				'domain' => $config->get( 'ServerName' )
			],
			'http' => [
				'request_headers' => [
					'user-agent' => $_SERVER[ 'HTTP_USER_AGENT' ] ?? ''
				]
			]
		];
	}

}
