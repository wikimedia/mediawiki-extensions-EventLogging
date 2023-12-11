<?php

namespace MediaWiki\Extension\EventLogging\EventSubmitter;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\EventBus\EventBus;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

/**
 * Submits events to an instance of EventGate via EventBus.
 *
 * @see https://wikitech.wikimedia.org/wiki/Event_Platform/EventGate
 * @see https://www.mediawiki.org/wiki/Extension:EventBus
 */
class EventBusEventSubmitter implements EventSubmitter {

	/** @var LoggerInterface */
	private $logger;

	/** @var string */
	private $domain;

	public function __construct( LoggerInterface $logger, Config $config ) {
		$this->logger = $logger;
		$this->domain = $config->get( 'ServerName' );
	}

	/**
	 * @inheritDoc
	 */
	public function submit( string $streamName, array $event ): void {
		if ( !isset( $event['$schema'] ) ) {
			$this->logger->warning(
				'Event data is missing required field "$schema"',
				[ 'event' => $event ]
			);

			return;
		}

		$event = $this->prepareEvent( $streamName, $event );
		$logger = $this->logger;

		DeferredUpdates::addCallableUpdate( static function () use ( $streamName, $event, $logger ) {
			$services = MediaWikiServices::getInstance();
			$streamConfigs = $services->getService( 'EventLogging.StreamConfigs' );

			if ( $streamConfigs !== false && !array_key_exists( $streamName, $streamConfigs ) ) {
				$logger->warning(
					'Event submitted for unregistered stream name "{streamName}"',
					[ 'streamName' => $streamName ]
				);
				return;
			}

			// @phan-suppress-next-line PhanUndeclaredClassMethod
			EventBus::getInstanceForStream( $streamName )->send( [ $event ] );
		} );
	}

	/**
	 * Prepares the event for submission by:
	 *
	 * 1. Setting the `meta.stream` required property;
	 * 2. Setting the `dt` required property, if it is not set; and
	 * 3. Setting the `http.request_headers.user_agent` and `meta.domain` properties, if they are
	 *    not set
	 *
	 * Note well that we test for the `meta.$schema` required property being set in
	 * {@link EventBusEventSubmitter::submit()} above.
	 *
	 * @see https://wikitech.wikimedia.org/wiki/Event_Platform/Schemas/Guidelines#Required_fields
	 *
	 * @param string $streamName
	 * @param array $event
	 * @return array The prepared event
	 */
	private function prepareEvent( string $streamName, array $event ): array {
		$defaults = [
			'http' => [
				'request_headers' => [
					'user-agent' => $_SERVER[ 'HTTP_USER_AGENT' ] ?? ''
				]
			],
			'meta' => [
				'domain' => $this->domain,
			],
			'dt' => wfTimestamp( TS_ISO_8601 ),
		];

		$requiredData = [
			'meta' => [
				'stream' => $streamName,
			],
		];

		$event = array_replace_recursive(
			$defaults,
			$event,
			$requiredData
		);

		//
		// If this is a migrated legacy event, client_dt will have been set already by
		// EventLogging::encapsulate, and the dt field should be left unset so that it can be set
		// to the intake time by EventGate. If dt was set by a caller, we unset it here.
		//
		// If client_dt is absent, this schema is native to the Event Platform, and dt represents
		// the client-side event time. We set it here, overwriting any caller-provided value to
		// ensure consistency.
		//
		// https://phabricator.wikimedia.org/T277253
		// https://phabricator.wikimedia.org/T277330
		//
		if ( isset( $event['client_dt'] ) ) {
			unset( $event['dt'] );
		}

		return $event;
	}
}
