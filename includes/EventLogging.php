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

class EventLogging {

	/** @var const Flag indicating the user-agent should not be logged. **/
	const OMIT_USER_AGENT = 2;

	/**
	 * Writes an event to a file descriptor or socket.
	 * Takes an event ID and an event, encodes it as query string,
	 * and writes it to the UDP / TCP address or file specified by
	 * $wgEventLoggingFile. If $wgEventLoggingFile is not set, returns
	 * false without logging anything.
	 *
	 * @see wfErrorLog
	 *
	 * @param string $schemaName Schema name.
	 * @param int $revId revision ID of schema.
	 * @param array $event Map of event keys/vals.
	 * @param int $options Bitmask consisting of EventLogging::OMIT_USER_AGENT.
	 * @return bool: Whether the event was logged.
	 */
	static function logEvent( $schemaName, $revId, $event, $options = 0 ) {
		global $wgDBname, $wgEventLoggingFile;

		if ( !$wgEventLoggingFile ) {
			return false;
		}

		wfProfileIn( __METHOD__ );
		$remoteSchema = new RemoteSchema( $schemaName, $revId );
		$schema = $remoteSchema->get();

		try {
			$isValid = is_array( $schema ) && efSchemaValidate( $event, $schema );
		} catch ( JsonSchemaException $e ) {
			$isValid = false;
		}

		if ( count( $event ) === 0 ) {
			// Ensure empty events are serialized as '{}' and not '[]'.
			$event = (object)$event;
		}

		$encapsulated = array(
			'event'            => $event,
			'schema'           => $schemaName,
			'revision'         => $revId,
			'clientValidated'  => $isValid,
			'wiki'             => $wgDBname,
			'recvFrom'         => gethostname(),
			'timestamp'        => $_SERVER[ 'REQUEST_TIME' ],
		);
		if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
			$encapsulated[ 'webHost' ] = $_SERVER[ 'HTTP_HOST' ];
		}

		if ( !( $options & self::OMIT_USER_AGENT ) && !empty( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
			$encapsulated[ 'userAgent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
		}

		// To make the resultant JSON easily extracted from a row of
		// space-separated values, we replace literal spaces with unicode
		// escapes. This is permitted by the JSON specs.
		$json = str_replace( ' ', '\u0020', FormatJson::encode( $encapsulated ) );

		wfErrorLog( $json . "\n", $wgEventLoggingFile );
		wfProfileOut( __METHOD__ );
		return true;
	}
}
