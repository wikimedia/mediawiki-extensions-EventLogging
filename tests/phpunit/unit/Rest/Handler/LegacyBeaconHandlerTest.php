<?php

namespace MediaWiki\Extension\EventLogging\Test\Rest\Handler;

use DateTime;
use MediaWiki\Extension\EventLogging\Rest\Handler\LegacyBeaconHandler;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @covers \MediaWiki\Extension\EventLogging\Rest\Handler\LegacyBeaconHandler
 */
class LegacyBeaconHandlerTest extends TestCase {
	/**
	 * Should convert legacy EventLogging event to event platform event
	 */
	public function testConvertEvent() {
		$input = [
			'meta' => [
				'stream' => 'eventlogging_MediaWikiPingback'
			],
			'schema' => 'MediaWikiPingback',
			'revision' => 15781718,
			'wiki' => 'dummy',
			'event' => [
				'database' => 'mysql',
				'MediaWiki' => '1.31.1',
				'PHP' => '7.4.33',
				'OS' => 'Linux 4.4.400-icpu-097',
				'arch' => 64,
				'machine' => 'x86_64',
				'serverSoftware' => 'Apache',
			],
		];

		$dtString = "2023-12-27T12:00:03.003";
		$recvFrom = 'host123.domain.net';
		$userAgent = 'test user agent';
		$uuid = 'fake_uuid';
		$expected = $input;
		$expected['$schema'] = '/analytics/legacy/mediawikipingback/1.0.0';
		$expected['client_dt'] = $dtString . 'Z';
		$expected['recvFrom'] = $recvFrom;
		$expected['http'] = [
			'request_headers' => [
				'user-agent' => $userAgent
			]
		];
		$expected['uuid'] = $uuid;

		$actual = LegacyBeaconHandler::convertEvent(
			$input,
			new DateTime( $dtString ),
			$recvFrom,
			$userAgent,
			$uuid
		);

		$this->assertEquals( $expected, $actual, 'converted event' );
	}

	/**
	 * Should convert DateTime to ISO-8601 string with zulu TZ
	 */
	public function testDateTimeString() {
		$dtString = "2023-12-27T12:00:03.003";
		$input = new DateTime( $dtString );
		$expected = $dtString . 'Z';
		$actual = LegacyBeaconHandler::dateTimeString( $input );
		$this->assertEquals( $expected, $actual, 'date time string' );
	}

	/**
	 * Should get legacy eventlogging stream name from schema name
	 */
	public function testGetStreamName() {
		$input = 'Test';
		$expected = 'eventlogging_Test';
		$actual = LegacyBeaconHandler::getStreamName( $input );
		$this->assertEquals( $expected, $actual, 'stream name' );
	}

	/**
	 * Should get legacy eventlogging schema URI from schema name
	 */
	public function testGetSchemaUri() {
		$input = 'Test';
		$expected = '/analytics/legacy/test/1.2.0';
		$actual = LegacyBeaconHandler::getSchemaUri( $input );
		$this->assertEquals( $expected, $actual, 'schema uri' );
	}

	public function testGetSchemaUriUnsupportedSchemaName() {
		$this->expectException( UnexpectedValueException::class );
		$input = 'NotSupportedSchemaNameTest';
		LegacyBeaconHandler::getSchemaUri( $input );
	}

	/**
	 * Should decode a JSON url encoded "qson" string to a PHP assoc array
	 */
	public function testDecodeQson() {
		$input = '?%7B%22schema%22%3A%22SchemaNameHere%22%2C%22revision%22%3A15781718%2C%' .
			'22wiki%22%3A%22dummy%22%2C%22event%22%3A%7B%22database%22%3A%22mysql%22%2C' .
			'%22MediaWiki%22%3A%221.31.1%22%2C%22PHP%22%3A%227.4.33%22%2C%22OS%22%3A' .
			'%22Linux%5Cu00204.4.400-icpu-097%22%2C%22arch%22%3A64%2C%22machine%22%3A%22x86_64' .
			'%22%2C%22serverSoftware%22%3A%22Apache%22%7D%7D;';
		$expected = [
			'schema' => 'SchemaNameHere',
			'revision' => 15781718,
			'wiki' => 'dummy',
			'event' => [
				'database' => 'mysql',
				'MediaWiki' => '1.31.1',
				'PHP' => '7.4.33',
				'OS' => 'Linux 4.4.400-icpu-097',
				'arch' => 64,
				'machine' => 'x86_64',
				'serverSoftware' => 'Apache',
			],
		];
		$actual = LegacyBeaconHandler::decodeQson( $input );
		$this->assertEquals( $expected, $actual, 'urldecoded and parsed json' );
	}
}
