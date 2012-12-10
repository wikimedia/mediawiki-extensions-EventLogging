#!/usr/bin/env php
<?php
/**
 * EventLogging Dev Server Script.
 *
 * This command-line script will fire a crude HTTP server which will
 * listen to incoming events and log them to stdout. This server is
 * useful for debugging purposes ONLY and is NOT SUITABLE FOR USE IN
 * PRODUCTION.
 *
 *
 * TODO(ori-l, 26-Nov-2012): Validate events against schema.
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

$defaults = array(
	'iface' => '0.0.0.0',  // Listen on all interfaces
	'port'  => '8080'
);

define( 'USAGE', <<<EOT
EventLogging dev server

Usage: {$argv[0]} [OPTIONS]
  -h, --help      Show this message and exit
  --iface=IFACE   Bind to this interface (default: {$defaults['iface']})
  --port=PORT     Bind to this port (default: {$defaults['port']})


EOT
);

if ( PHP_SAPI !== 'cli' ) {
	die( 'Invalid entry point.' );
}

$IP = getenv( 'MW_INSTALL_PATH' ) ?: ( __DIR__ . '/../../..' );
require_once( "$IP/maintenance/commandLine.inc" );


// Helpers

/**
 * Logs message to terminal with timestamp.
 **/
function consoleLog( $msg ) {
	echo "[\033[1;34m" . wfTimestamp( TS_RFC2822 ) . "\033[0m] $msg\n";
}


/**
 * Reads an HTTP request from a socket.
 * Reads up to 5 kB all at once and returns the first line, containing
 * the request URI. If unable to read request, returns false.
 * @return string|false
 */
function readHttpReq( &$conn ) {
	// Read up to 5 kB in one go.
	$req = @socket_read( $conn, 5120 );
	return $req ? strstr( $req, "\n", true ) : false;
}


/**
 * Extracts the request URL from a raw GET request and parses it.
 * @return array|false: URL or false if no URL could be extracted.
 */
function parseGetReq( $req ) {
	preg_match( '/GET (?P<url>.*) HTTP/', $req, $matches );

	return array_key_exists( 'url', $matches )
		? parse_url( $matches[ 'url' ] )
		: false;
}


/**
 * Sends a blank HTTP response.
 * @param string $status
 * @param resource $socket
 */
function sendHttpResp( &$conn, $status ) {
	$resp = join( "\r\n", array( 'HTTP/1.1 ' . $status, 'Connection: close', '' ) );
	socket_write( $conn, $resp );
	socket_close( $conn );
}


/**
 * Reads and parses an incoming event via HTTP
 * @param resource &$socket: Socket to read from.
 * @return array: Event map (or empty array).
 */
function handleEvent( &$socket ) {
	$conn = socket_accept( $socket );
	$req = readHttpReq( $conn );
	$uri = parseGetReq( $req );
	$query = array();

	$status = ( $req && $uri )
		? '204 No Content'
		: '501 Not Implemented';

	if ( array_key_exists( 'query', $uri ) ) {
		parse_str( $uri[ 'query' ], $query );
	}

	sendHttpResp( $conn, $status );
	consoleLog( "$req [\033[1;33m$status\033[0m]" );

	return $query;
}


/**
 * Checks the return value of a socket call and emits an error message on fail.
 * @param bool $retval: Return value of a socket_* call.
 * @param string $msg
 */
function assertSocket( $retval, $msg ) {
	if ( $retval === false ) {
		$errno = socket_last_error();
		$err = socket_strerror( $errno );
		die( "$msg: ($errno) $err.\n" );
	}

	return $retval;
}

// Main

$opts = array_merge( $defaults, getopt( 'h', array( 'help', 'iface:', 'port:' ) ) );

if ( array_key_exists( 'help', $opts ) || array_key_exists( 'h', $opts ) ) {
	die( USAGE );
}

// Suppress socket_* warnings with '@' because they'll be re-raised as errors anyway.

$socket = assertSocket( @socket_create( AF_INET, SOCK_STREAM, 0 ), 'Failed to create socket' );
assertSocket( @socket_bind( $socket, $opts[ 'iface' ], $opts[ 'port' ] ), 'Failed to bind socket' );
assertSocket( @socket_listen( $socket ), 'socket_listen() failed' );


// On shutdown, close socket.
function shutdown() {
	socket_close( $socket );
}

register_shutdown_function( shutdown );

// If PHP was compiled with Process Control, exit cleanly on SIGTERM.
if ( function_exists ( 'pcntl_signal' ) ) {
	pcntl_signal( SIGTERM, shutdown );
}

consoleLog( "Serving HTTP on {$opts['iface']} port {$opts['port']} ..." );

while ( true ) {
	$event = handleEvent( $socket );
	if ( $event ) {
		echo FormatJson::encode( $event, true ) . "\n";
	}
}
