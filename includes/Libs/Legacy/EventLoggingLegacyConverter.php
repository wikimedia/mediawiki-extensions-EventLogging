<?php

namespace MediaWiki\Extension\EventLogging\Libs\Legacy;

use DateTime;
use Exception;
use InvalidArgumentException;
use JsonException;
use MediaWiki\Extension\EventLogging\EventLogging;
use RuntimeException;
use UnexpectedValueException;

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
 * Methods to convert legacy EventLogging events into WMF Event Platform compatible ones.
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
 * '%q    %l    %n    %{%FT%T}t    %{X-Client-IP}o    "%{User-agent}i'
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
 *
 *
 */
class EventLoggingLegacyConverter {

	/**
	 * Maps legacy EventLogging schema names to the migrated WMF Event Platform
	 * schema version to be used.
	 *
	 * A schema must be declared here in order for it to be allowed to be produced,
	 * otherwise it will be rejected.
	 *
	 * @var array|string[]
	 */
	public static array $schemaVersions = [
		'MediaWikiPingback' => '1.0.0',
		'Test' => '1.2.0',
	];

	/**
	 * Parses and converts a legacy EventLogging 'qson' from the HTTP query params and headers
	 * to a WMF Event Platform compatible event.
	 *
	 * @param array|null $_server If not set, global $_SERVER will be used.
	 * @return array
	 * @throws Exception
	 */
	public static function fromHttpRequest( ?array $_server = null ): array {
		$_server ??= $_SERVER;

		$decodedEvent = self::decodeQson( $_server['QUERY_STRING'] );
		return self::convertEvent(
			$decodedEvent,
			new DateTime(),
			$_server['REMOTE_HOST'] ?? $_server['REMOTE_ADDR'] ?? null,
			$_server['HTTP_USER_AGENT'] ?? null
		);
	}

	/**
	 * Converts the legacy EventLogging event to a WMF Event Platform compatible one.
	 *
	 * @param array $event
	 * @param DateTime|null $dt
	 * @param string|null $recvFrom
	 * @param string|null $userAgent
	 * @return array
	 * @throws Exception
	 */
	public static function convertEvent(
		array $event,
		?DateTime $dt = null,
		?string $recvFrom = null,
		?string $userAgent = null
	): array {
		if ( !isset( $event['schema'] ) ) {
			throw new InvalidArgumentException(
				'Event is missing \'schema\' field. ' .
				'This is required to convert to WMF Event Platform event.'
			);
		}

		$event['$schema'] = self::getSchemaUri( $event['schema'] );
		$event['meta'] = [
			'stream' => self::getStreamName( $event['schema'] ),
		];

		// NOTE: We do not have a sequence num seqId, so we can't use a url based uuid5
		// eventlogging backend parse.py did.  Instead, use a random uuid4.
		$event['uuid'] ??= self::newUUIDv4();

		$dt ??= new DateTime();
		$event['dt'] = self::dateTimeString( $dt );
		// NOTE: `client_dt` is 'legacy' event time.  `dt` is the preferred event time field
		$event['client_dt'] = $event['dt'];

		if ( $recvFrom !== null ) {
			$event['recvFrom'] = $recvFrom;
		}

		if ( $userAgent !== null ) {
			$event['http'] = [
				'request_headers' => [
					'user-agent' => $userAgent
				]
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
		return $dt->format( 'Y-m-d\TH:i:s.' ) .
			substr( $dt->format( 'u' ), 0, 3 ) . 'Z';
	}

	/**
	 * 'qson' is a term found in the legacy eventlogging python codebase. It is URL encoded JSON.
	 * This parses URL encoded json data into a PHP assoc array.
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

	/**
	 * Return an RFC4122 compliant v4 UUID
	 *
	 * Taken from MediaWiki Wikimedia\UUID\GlobalIdGenerator.
	 *
	 * @return string
	 */
	public static function newUUIDv4(): string {
		$hex = bin2hex( random_bytes( 32 / 2 ) );

		return sprintf(
			'%s-%s-%s-%s-%s',
			// "time_low" (32 bits)
			substr( $hex, 0, 8 ),
			// "time_mid" (16 bits)
			substr( $hex, 8, 4 ),
			// "time_hi_and_version" (16 bits)
			'4' . substr( $hex, 12, 3 ),
			// "clk_seq_hi_res" (8 bits, variant is binary 10x) and "clk_seq_low" (8 bits)
			dechex( 0x8 | ( hexdec( $hex[15] ) & 0x3 ) ) . $hex[16] . substr( $hex, 17, 2 ),
			// "node" (48 bits)
			substr( $hex, 19, 12 )
		);
	}

	/**
	 * Extracts stream name from event in meta.stream field and calls EventLogging::submit
	 * @param array $event
	 * @return void
	 */
	public static function submitEvent( array $event ): void {
		$streamName = $event['meta']['stream'] ?? null;
		if ( !$streamName ) {
			throw new RuntimeException(
				'Cannot submit event: event must have stream name set in  meta.stream field.'
			);
		}
		EventLogging::submit( $streamName, $event );
	}

}
