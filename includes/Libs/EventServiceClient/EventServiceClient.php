<?php

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class EventServiceClient implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/**
	 * Array of active stream configurations keyed by stream name, or false if stream configs are
	 * disabled.
	 *
	 * @var array|bool
	 */
	private $streamConfigs;

	/**
	 * Event intake service URI from the extension configuration (or false if disabled)
	 *
	 * @var string|bool
	 */
	private $eventLoggingServiceUri;

	/**
	 * Extra fields that will be used to augment outgoing events
	 * if the events don't have these set already.  This
	 * data will be used as defaults for the $event provided to submit.
	 * This will be set to the value returned from self::getEventDefaults() by the constructor.
	 *
	 * @var array
	 */
	private $eventDefaults;

	/**
	 * EventServiceClient constructor.
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param array|bool $streamConfigs
	 * @param string|bool $eventLoggingServiceUri
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		$streamConfigs,
		$eventLoggingServiceUri
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->streamConfigs = $streamConfigs;
		$this->eventLoggingServiceUri = $eventLoggingServiceUri;
		$this->eventDefaults = self::getEventDefaults();

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Submit an event to EventGate according to the given stream's configuration.
	 * @param string $streamName
	 * @param array $event
	 */
	public function submit( string $streamName, array $event ): void {
		if ( !$this->eventLoggingServiceUri ) {
			$this->logger->warning( 'EventLoggingServiceUri not configured.' );
			return;
		}
		if ( $this->streamConfigs !== false &&
			!array_key_exists( $streamName, $this->streamConfigs )
		) {
			$this->logger->warning(
				__METHOD__ . ' called with unregistered stream name "{streamName}".',
				[ 'streamName' => $streamName ]
			);
			return;
		}
		if ( !$event || !$event['$schema'] ) {
			$this->logger->warning(
				__METHOD__ . ' called with event data missing required field "$schema".',
				[ 'event' => $event ]
			);
			return;
		}

		$event = self::prepareEvent( $streamName, $event, $this->eventDefaults );

		$fname = __METHOD__;
		DeferredUpdates::addCallableUpdate( function () use ( $event, $fname ) {
			return $this->httpRequestFactory->post(
				$this->eventLoggingServiceUri,
				[ 'postData' => $event ],
				$fname
			);
		} );
	}

	/**
	 * Prepares the event with extra data for submit.
	 * This will always set
	 * - `meta.stream` to $streamName
	 *
	 * This will optionally set (if not already in the event):
	 * - `dt` to the current time to reperesent the 'event time'
	 * - $this->eventDefaults
	 *
	 * @param string $streamName
	 * @param array $event
	 * @param array $eventDefaults
	 * @return array
	 */
	private static function prepareEvent(
		string $streamName,
		array $event,
		array $eventDefaults = []
	): array {
		$requiredData = [
			// meta.stream should always be set to $streamName
			'meta' => [
				'stream' => $streamName
			]
		];

		$preparedEvent = array_merge_recursive(
			$eventDefaults,
			$event,
			$requiredData
		);

		// The 'dt' field is reserved for the internal use of this library,
		// and should not be set by any other caller. The 'meta.dt' field is
		// reserved for EventGate and will be set at by EventGate at receive time
		// to act as a record of when the event was received.
		//
		// If 'dt' is provided, its value is not modified.
		// If 'dt' is not provided, a new value is computed.
		$preparedEvent['dt'] = $preparedEvent['dt'] ?? date( DATE_ISO8601 );

		return $preparedEvent;
	}

	/**
	 * Returns values we always want set in events based on common
	 * schemas for all EventLoggging events.  This sets:
	 *
	 * - meta.domain to the value of $config->get( 'ServerName' )
	 * - http.request_headers['user-agent'] to the value of $_SERVER( 'HTTP_USER_AGENT' ) ?? ''
	 *
	 * The returned object will be used as default values for the $event params passed
	 * to submit().
	 * @return array
	 */
	private static function getEventDefaults() {
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
