<?php
/**
 * PHP API for logging events
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

use MediaWiki\MediaWikiServices;

class EventLogging {

	/** @var int flag indicating the user-agent should not be logged. */
	public const OMIT_USER_AGENT = 2;

	/** @var string used in Event Platform events if the user-agent is to be omitted */
	public const USER_AGENT_OMITTED = 'eventlogging_omit_user_agent';

	/**
	 * Submit an event according to the given stream's configuration.
	 * @param string $streamName
	 * @param array $eventData
	 */
	public static function submit( string $streamName, array $eventData ): void {
		EventLoggingServices::getInstance()->getEventServiceClient()
			->submit( $streamName, $eventData );
	}

	/**
	 * Transfer small data asynchronously using an HTTP POST.
	 * This is meant to match the Navigator.sendBeacon() API.
	 *
	 * @see https://w3c.github.io/beacon/#sec-sendBeacon-method
	 * @param string $url
	 * @param array $data
	 * @return bool
	 */
	public static function sendBeacon( $url, array $data = [] ) {
		$fname = __METHOD__;
		$url = wfExpandUrl( $url, PROTO_INTERNAL );
		DeferredUpdates::addCallableUpdate( function () use ( $url, $data, $fname ) {
			$options = $data ? [ 'postData' => $data ] : [];
			return MediaWikiServices::getInstance()->getHttpRequestFactory()
				->post( $url, $options, $fname );
		} );

		return true;
	}

	/**
	 * Legacy event logging entrypoint.
	 *
	 * NOTE: For forwards compatibility with Event Platform schemas,
	 * we hijack the wgEventLoggingSchemas revision to encode the
	 * $schema URI. If the value for a schema defined in
	 * EventLoggingSchemas is a string, it is assumed
	 * to be an Event Platform $schema URI, not a MW revision id.
	 * In this case, the event will be POSTed to EventGate.
	 *
	 * @param string $schemaName Schema name.
	 * @param int $revId revision ID of schema.
	 * @param array $event Map of event keys/vals.
	 * @param int $options Bitmask consisting of EventLogging::OMIT_USER_AGENT.
	 * @return bool Whether the event was logged.
	 */
	public static function logEvent( $schemaName, $revId, $event, $options = 0 ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$eventLoggingConfig = EventLoggingHooks::getEventLoggingConfig( $config );
		$schemaInfo = $eventLoggingConfig['schemasInfo'];
		$revisionOrSchemaUri = $schemaInfo[$schemaName] ?? -1;

		if ( is_string( $revisionOrSchemaUri ) ) {
			self::logEventServiceEvent(
				$config->get( 'ServerName' ),
				$schemaName,
				$revisionOrSchemaUri,
				!( $options & self::OMIT_USER_AGENT ),
				$event
			);
			return true;
		} else {
			return self::logEventLoggingEvent( $schemaName, $revId, $event, $options );
		}
	}

	/**
	 *
	 * Converts the encapsulated event from an object to a string.
	 *
	 * @param array $encapsulatedEvent Encapsulated event
	 * @return string $json
	 */
	public static function serializeEvent( $encapsulatedEvent ) {
		$event = $encapsulatedEvent['event'];

		if ( count( $event ) === 0 ) {
			// Ensure empty events are serialized as '{}' and not '[]'.
			$event = (object)$event;
		}

		$encapsulatedEvent['event'] = $event;

		// To make the resultant JSON easily extracted from a row of
		// space-separated values, we replace literal spaces with unicode
		// escapes. This is permitted by the JSON specs.
		return str_replace( ' ', '\u0020', FormatJson::encode( $encapsulatedEvent ) );
	}

	/**
	 * Validates object against JSON Schema.
	 *
	 * @throws JsonSchemaException If the object fails to validate.
	 * @param array $object Object to be validated.
	 * @param array|null $schema Schema to validate against (default: JSON Schema).
	 * @return bool True.
	 */
	public static function schemaValidate( $object, $schema = null ) {
		if ( $schema === null ) {
			// Default to JSON Schema
			$json = file_get_contents( dirname( __DIR__ ) . '/schemas/schemaschema.json' );
			$schema = FormatJson::decode( $json, true );
		}

		// We depart from the JSON Schema specification in disallowing by default
		// additional event fields not mentioned in the schema.
		// See <https://bugzilla.wikimedia.org/show_bug.cgi?id=44454> and
		// <https://tools.ietf.org/html/draft-zyp-json-schema-03#section-5.4>.
		if ( !array_key_exists( 'additionalProperties', $schema ) ) {
			$schema[ 'additionalProperties' ] = false;
		}

		$root = new JsonTreeRef( $object );
		$root->attachSchema( $schema );
		return $root->validate();
	}

	/**
	 * Randomise inclusion based on population size and a session ID.
	 * @param int $populationSize Return true one in this many times. This is 1/samplingRate.
	 * @param string $sessionId Hexadecimal value, only the first 8 characters are used
	 * @return bool True if the event should be included (sampled in), false if not (sampled out)
	 */
	public static function sessionInSample( $populationSize, $sessionId ) {
		$decimal = (int)base_convert( substr( $sessionId, 0, 8 ), 16, 10 );
		return $decimal % $populationSize === 0;
	}

	/**
	 * Emit a legacy schema event to EventGate.
	 *
	 * We arrive here from EventLogging::logEvent if wgEventLoggingSchemas contains a string
	 * value for $schemaName, on the assumption that the string value represents an event
	 * platform schema URI.
	 *
	 * Event validation is performed by the destination EventGate service.
	 *
	 * @param string $domain request domain as reported by $wgServerName
	 * @param string $schemaName target schema
	 * @param string $schemaUri target event platform schema URI
	 * @param bool $logUserAgent if the user agent should be logged
	 * @param array $event event data k-v object
	 */
	private static function logEventServiceEvent(
		string $domain,
		string $schemaName,
		string $schemaUri,
		bool $logUserAgent,
		array $event = []
	): void {
		self::submit(
			self::getLegacyStreamName( $schemaName ),
			self::prepareEventServiceEvent( $domain, $schemaUri, $logUserAgent, $event )
		);
	}

	/**
	 * Emit an event via a sendBeacon POST to the event beacon endpoint.
	 *
	 * @param string $schemaName Schema name.
	 * @param int $revId revision ID of schema.
	 * @param array $event Map of event keys/vals.
	 * @param int $options Bitmask consisting of EventLogging::OMIT_USER_AGENT.
	 * @return bool Whether the event was logged.
	 */
	private static function logEventLoggingEvent( $schemaName, $revId, $event, $options = 0 ) {
		global $wgDBname, $wgEventLoggingBaseUri;

		if ( !$wgEventLoggingBaseUri ) {
			return false;
		}

		$remoteSchema = new RemoteSchema( $schemaName, $revId );
		$schema = $remoteSchema->get();

		try {
			$isValid = is_array( $schema ) && self::schemaValidate( $event, $schema );
		} catch ( JsonSchemaException $e ) {
			$isValid = false;
		}

		$encapsulated = [
			'event'            => $event,
			'schema'           => $schemaName,
			'revision'         => $revId,
			'clientValidated'  => $isValid,
			'wiki'             => $wgDBname,
		];
		if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
			$encapsulated[ 'webHost' ] = $_SERVER[ 'HTTP_HOST' ];
		}
		if ( !( $options & self::OMIT_USER_AGENT ) && !empty( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
			$encapsulated[ 'userAgent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
		}

		$json = static::serializeEvent( $encapsulated );
		$url = $wgEventLoggingBaseUri . '?' . rawurlencode( $json ) . ';';
		return self::sendBeacon( $url );
	}

	/**
	 * Supplement the submitted event data with the following fields:
	 * - $schema: the schema name
	 * - client_dt: ISO8601 timestamp for the current time
	 * - meta.domain: the current hostname
	 * - http.request_headers.user-agent: the current User-Agent, or a placeholder constant if the
	 *   User-Agent should not be logged
	 *
	 * Example:
	 *
	 * Before:
	 * [
	 * 	'$schema' => '/test/event/1.0.0',
	 * ]
	 *
	 * After:
	 * [
	 *		'$schema' => '/test/event/1.0.0',
	 *		'client_dt' => '2020-12-11T19:39:06+0000',
	 *		'meta' => [
	 *			'domain' => 'en.wikipedia.org',
	 *		],
	 *		'http' => [
	 *			'request_headers' => [
	 *				'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML,
	 * like Gecko) Chrome/87.0.4280.88 Safari/537.36',
	 *			],
	 *		],
	 *	] );
	 *
	 * @param string $domain request domain as reported by $wgServerName
	 * @param string $schemaUri target schema URI
	 * @param bool $logUserAgent if the user agent should be logged
	 * @param array $event event data
	 * @return array updated event data
	 */
	private static function prepareEventServiceEvent(
		string $domain,
		string $schemaUri,
		bool $logUserAgent,
		array $event
	): array {
		$event['$schema'] = $schemaUri;
		$event['client_dt'] = date( DATE_ISO8601 );
		if ( !isset( $event['meta'] ) ) {
			$event['meta'] = [];
		}
		$event['meta']['domain'] = $domain;
		if ( !isset( $event['http'] ) ) {
			$event['http'] = [];
		}
		if ( !isset( $event['http']['request_headers'] ) ) {
			$event['http']['request_headers'] = [];
		}
		$ua = $logUserAgent ? $_SERVER[ 'HTTP_USER_AGENT' ] ?? '' : self::USER_AGENT_OMITTED;
		$event['http']['request_headers']['user-agent'] = $ua;
		return $event;
	}

	/**
	 * Prepend "eventlogging_" to the schema name to create a stream name for a migrated legacy
	 * schema.
	 *
	 * @param string $schemaName
	 * @return string
	 */
	private static function getLegacyStreamName( string $schemaName ): string {
		return "eventlogging_$schemaName";
	}

}
