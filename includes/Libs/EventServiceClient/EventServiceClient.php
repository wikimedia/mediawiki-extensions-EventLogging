<?php

use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class EventServiceClient implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Array of active stream configurations keyed by stream name, or false if stream configs are
	 * disabled.
	 *
	 * @var array|bool
	 */
	private $streamConfigs;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $eventLoggingServiceUri;

	/**
	 * EventServiceClient constructor.
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param array|bool $streamConfigs Stream configurations (from $wgEventStreams), or false if
	 *  stream configurations are disabled
	 * @param string $eventLoggingServiceUri EventGate service URI
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		$streamConfigs,
		string $eventLoggingServiceUri
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->streamConfigs = $streamConfigs;
		$this->eventLoggingServiceUri = $eventLoggingServiceUri;

		$this->setLogger( new NullLogger() );
	}

	/**
	 * Submit an event to EventGate according to the given stream's configuration.
	 * @param string $streamName
	 * @param array $eventData
	 */
	public function submit( string $streamName, array $eventData ): void {
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
		if ( !$eventData || !$eventData['$schema'] ) {
			$this->logger->warning(
				__METHOD__ . ' called with event data missing required field "$schema".',
				[ 'eventData' => $eventData ]
			);
			return;
		}

		$eventData = self::addEventMetadata( $streamName, $eventData );

		$fname = __METHOD__;
		DeferredUpdates::addCallableUpdate( function () use ( $eventData, $fname ) {
			return $this->httpRequestFactory->post(
				$this->eventLoggingServiceUri,
				[ 'postData' => $eventData ],
				$fname
			);
		} );
	}

	/**
	 * Ensures that the event data object contains the following properties:
	 * - `meta.stream`: the stream name
	 * - `dt`: ISO 8601-formatted event timestamp
	 * If either or both of these are already set, leave them alone.
	 * @param string $streamName
	 * @param array $eventData
	 * @return array
	 */
	private static function addEventMetadata( string $streamName, array $eventData ): array {
		if ( !isset( $eventData['meta'] ) ) {
			$eventData['meta'] = [];
		}
		if ( !isset( $eventData['meta']['stream'] ) ) {
			$eventData['meta']['stream'] = $streamName;
		}

		// The 'dt' field is reserved for the internal use of this library,
		// and should not be set by any other caller. The 'meta.dt' field is
		// reserved for EventGate and will be set at ingestion to act as a record
		// of when the event was received.
		//
		// If 'dt' is provided, its value is not modified.
		// If 'dt' is not provided, a new value is computed.
		if ( !isset( $eventData['dt'] ) ) {
			$eventData['dt'] = date( DATE_ISO8601 );
		}

		return $eventData;
	}

}
