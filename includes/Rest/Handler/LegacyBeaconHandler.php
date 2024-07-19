<?php

namespace MediaWiki\Extension\EventLogging\Rest\Handler;

use DateTime;
use Exception;
use InvalidArgumentException;
use JsonException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Rest\Handler;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use WikiMap;
use Wikimedia\UUID\GlobalIdGenerator;

// NOTE: As of 2024-07, the only legacy EventLogging schema this needs to support is
// MediaWikiPingback.  Details about this can be found at https://phabricator.wikimedia.org/T323828.
// In summary, MediaWikiPingback does not use the EventLogging MediaWiki extension to produce events.
// MediaWikiPingback instrument collects data from 3rd party MediaWiki installs,
// and we cannot force those 3rd parties to upgrade to newer versions of MediaWiki
// that produce events directly to eventgate-analytics-external.
// (See https://gerrit.wikimedia.org/r/c/mediawiki/core/+/938271/ ).
//
// The MediaWikiPingback instrument is configured to send events directly to mediawiki.org, so
// we only need to handle legacy conversion of events from mediawiki.org.
//
// Once we are confident that there are sufficiently few remaining 3rd party MediaWiki installs
// out there that send events using this legacy endpoint, we can remove this endpoint and related
// code (EventLogging extension's EventLoggingLegacyConverter) entirely.

/**
 * GET /eventlogging/v0/beacon/?{qson_enconded_legacy_event}
 *
 * Converts legacy EventLogging events into WMF Event Platform compatible ones and submits
 * them using the provided EventSubmitter.
 *
 * Expects that the incoming HTTP query string is a 'qson' event (URL encoded JSON string).
 * This event will be parsed, converted and posted to EVENT_INTAKE_URL env var,
 * or the local eventgate-analytics-external service.
 *
 * This class mostly exists to aid in the final decommissioning of the eventlogging python backend
 * and associated components and data pipelines
 * (varnishkafka, Refine eventlogging_analytics job in analytics hadoop cluster, etc.)
 *
 * It attempts to replicate some of the logic in eventlogging/parse.py
 * https://gerrit.wikimedia.org/r/plugins/gitiles/eventlogging/+/refs/heads/master/eventlogging/parse.py
 * and the WMF configured varnishkafka logger.  However, because varnishkafka has
 * access to data that is not provided by the producer client (e.g. seqId, client IP, etc.),
 * This class does not support those kind of features.  It does its best to translate
 * the client produced legacy event into a WMF Event Platform compatible one.
 *
 * NOTE: The varnishkafka log format for eventlogging was:
 * '%q    %l    %n    %{%FT%T}t    %{X-Client-IP}o    "%{User-agent}i"'
 *
 * == Differences from original eventlogging/parse.py + format
 *
 * - seqId %n is not supported.
 *
 * - recvFrom is populated from REMOTE_HOST or REMOTE_ADDR, instead of the varnish cache hostname %l.
 *
 * - Receive timestamp is generated here, instead of the cache host request receive timestamp %t.
 *
 * - Client IP is not supported.
 *
 * - EventLogging Capsule id field will be set to a random uuid4,
 *   instead of a uuid5 built from event content.
 */
class LegacyBeaconHandler extends Handler {

	/**
	 * MediaWiki Config key EventLoggingLegacyBeaconAllowedWikiIds.
	 */
	private const ALLOWED_WIKI_IDS_CONFIG_KEY = 'EventLoggingLegacyBeaconAllowedWikiIds';

	public const CONSTRUCTOR_OPTIONS = [
		self::ALLOWED_WIKI_IDS_CONFIG_KEY
	];

	/**
	 * Maps legacy EventLogging schema names to the migrated WMF Event Platform
	 * schema version to be used.
	 *
	 * A schema must be declared here in order for it to be allowed to be produced,
	 * otherwise it will be rejected.
	 *
	 * NOTE: This is hardcoded here (instead of parameterized in config) because
	 * we do not intend to ever add entries to this.  Hopefully RestApiLegacyBeacon
	 * can be removed entirely in a few years.
	 *
	 * @var array|string[]
	 */
	public static array $schemaVersions = [
		'MediaWikiPingback' => '1.0.0',
		'Test' => '1.2.0',
	];

	/**
	 * @var array|mixed
	 */
	private array $allowedWikiIds;

	/**
	 * @var EventSubmitter
	 */
	private EventSubmitter $eventSubmitter;

	/**
	 * @var GlobalIdGenerator
	 */
	private GlobalIdGenerator $globalIdGenerator;

	/**
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * @param ServiceOptions $options
	 * @param EventSubmitter $eventSubmitter
	 * @param GlobalIdGenerator $globalIdGenerator
	 */
	public function __construct(
		ServiceOptions $options,
		EventSubmitter $eventSubmitter,
		GlobalIdGenerator $globalIdGenerator
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->eventSubmitter = $eventSubmitter;
		$this->globalIdGenerator = $globalIdGenerator;
		$this->allowedWikiIds = $options->get( self::ALLOWED_WIKI_IDS_CONFIG_KEY );
		$this->logger = LoggerFactory::getInstance( self::class );
	}

	public function execute() {
		// we will always return 204, no matter what.
		$response = $this->getResponseFactory()->createNoContent();

		// Restrict this API endpoint to allowedWikiIds.
		$wiki = WikiMap::getCurrentWikiId();
		if ( !in_array( $wiki, $this->allowedWikiIds ) ) {
			$this->logger->error( "Cannot forward legacy event: LegacyEventBeacon is disabled on $wiki." );
			return $response;
		}

		// Decode the 'event' out of the qson encoded query string
		$queryString = $this->getRequest()->getUri()->getQuery();
		try {
			$decodedEvent = self::decodeQson( $queryString );
		} catch ( Exception $e ) {
			$this->logger->error(
				"Failed decoding query string as 'qson' event: " . $e->getMessage(),
				[ 'exception' => $e ]
			);
			return $response;
		}

		// Convert the event to WMF Event Platform compatible
		try {
			$event = self::convertEvent(
				$decodedEvent,
				new DateTime(),
				$this->getRequest()->getServerParams()['REMOTE_HOST'] ?? null,
				$this->getRequest()->getHeader( 'user-agent' )[0] ?? null,
				$this->globalIdGenerator->newUUIDv4(),
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed converting event from legacy EventLogging to WMF Event Platform compatible: ' .
				$e->getMessage(),
				[ 'exception' => $e ]
			);
			return $response;
		}

		// submit event (likely in a DeferredUpdate via EventBusEventSubmitter).
		$this->eventSubmitter->submit( $event['meta']['stream'], $event );

		// 204 HTTP response
		return $response;
	}

	public function needsReadAccess(): bool {
		return false;
	}

	public function needsWriteAccess(): bool {
		return false;
	}

	/**
	 * Converts the legacy EventLogging event to a WMF Event Platform compatible one.
	 *
	 * @param array $event
	 * @param DateTime|null $dt
	 * @param string|null $recvFrom
	 * @param string|null $userAgent
	 * @param string|null $uuid
	 * @return array
	 * @throws InvalidArgumentException
	 * @throws UnexpectedValueException
	 */
	public static function convertEvent(
		array $event,
		?DateTime $dt = null,
		?string $recvFrom = null,
		?string $userAgent = null,
		?string $uuid = null
	): array {
		if ( !isset( $event['schema'] ) ) {
			throw new InvalidArgumentException(
				'Event is missing \'schema\' field. This is required to convert to WMF Event Platform event.'
			);
		}

		$event['$schema'] = self::getSchemaUri( $event['schema'] );
		$event['meta'] = [
			'stream' => self::getStreamName( $event['schema'] ),
		];

		if ( $uuid != null ) {
			$event['uuid'] = $uuid;
		}

		$dt ??= new DateTime();
		// NOTE: `client_dt` is 'legacy' event time.
		$event['client_dt'] = self::dateTimeString( $dt );

		if ( $recvFrom !== null ) {
			$event['recvFrom'] = $recvFrom;
		}

		if ( $userAgent !== null ) {
			$event['http'] = [
				'request_headers' => [ 'user-agent' => $userAgent ],
			];
		}

		return $event;
	}

	/**
	 * Returns an ISO-8601 UTC datetime string with 'zulu' timezone notation.
	 * If $dt is not given, returns for current timestamp.
	 *
	 * @param DateTime|null $dt
	 * @return string
	 */
	public static function dateTimeString( ?DateTime $dt ): string {
		return $dt->format( 'Y-m-d\TH:i:s.' ) . substr( $dt->format( 'u' ), 0, 3 ) . 'Z';
	}

	/**
	 * 'qson' is a term found in the legacy eventlogging python codebase. It is URL encoded JSON.
	 * This parses URL encoded json data into a PHP assoc array.
	 *
	 * @param string $data
	 * @return array
	 * @throws JsonException
	 */
	public static function decodeQson( string $data ): array {
		$decoded = rawurldecode( trim( $data, '?&;' ) );
		return json_decode(
			$decoded,
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
	}

	/**
	 * Converts legacy EventLogging schema name to migrated Event Platform stream name.
	 *
	 * @param string $schemaName
	 * @return string
	 */
	public static function getStreamName( string $schemaName ): string {
		return 'eventlogging_' . $schemaName;
	}

	public static function isSchemaAllowed( string $schemaName ): bool {
		return array_key_exists( $schemaName, self::$schemaVersions );
	}

	/**
	 * Converts the EventLogging legacy $schemaName to the migrated WMF
	 * Event Platform schema URI. This expects that the migrated schema URI is at
	 * /analytics/legacy/<schemaName>/<version>
	 *
	 * @param string $schemaName
	 * @return string
	 */
	public static function getSchemaUri( string $schemaName ): string {
		if ( !self::isSchemaAllowed( $schemaName ) ) {
			throw new UnexpectedValueException(
				"$schemaName is not in the list of allowed legacy schemas."
			);
		}

		$version = self::$schemaVersions[$schemaName];
		return '/analytics/legacy/' . strtolower( $schemaName ) . '/' . $version;
	}
}
