<?php

use MediaWiki\Http\HttpRequestFactory;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/** @covers EventLogging */
class EventLoggingTest extends MediaWikiIntegrationTestCase {

	private $mockEventServiceClient;
	private $mockHttpRequestFactory;
	private $timestamp;

	/**
	 * This is supposed to be what a user might provide to EventLogging::logEvent
	 * i.e. an un-encapsulated event.  Used in tests as input data.
	 * @var array
	 */
	private $legacyEvent = [
		"field_a" => "hi"
	];

	/*
	 * HTTP_HOST will be set to this value during the tests if it isn't already set.
	 * @var string
	 */
	private $testHttpHost = 'test.eventlogging.domain';

	/**
	 * If true, HTTP_HOST has been modified and should be reset during tearDown.
	 * @var boolean
	 */
	private $modifiedHttpHost = false;

	protected function setUp(): void {
		parent::setUp();

		// EventLoggging uses HTTP_HOST.  If it isn't set for tests, set a dummy,
		// Otherwise use the actual value.
		if ( !isset( $_SERVER['HTTP_HOST'] ) ) {
			$this->modifiedHttpHost = true;
			$_SERVER['HTTP_HOST'] = $this->testHttpHost;
		} else {
			$this->testHttpHost = $_SERVER['HTTP_HOST'];
		}

		$this->mockEventServiceClient = $this->createMock( EventServiceClient::class );
		$this->setService( 'EventServiceClient', function () {
			return $this->mockEventServiceClient;
		} );

		$this->mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$this->setService( 'HttpRequestFactory', function () {
			return $this->mockHttpRequestFactory;
		} );

		$this->timestamp = TestingAccessWrapper::newFromClass( ConvertibleTimestamp::class );

		$this->setMwGlobals( [
			'wgEventLoggingSchemas' => [
				'Migrated' => '/test/event/1.0.0',
				'NotMigrated' => 1337,
			],
			'wgEventLoggingBaseUri' => 'https://test.wikipedia.org/beacon/event',
			'wgServerName' => $this->testHttpHost,
		] );
	}

	protected function tearDown(): void {
		parent::tearDown();

		// IF setHttpHost in setUp, we should unset it in tearDown.
		if ( $this->modifiedHttpHost ) {
			unset( $_SERVER['HTTP_HOST'] );
		}
	}

	public function testSendMigratedEventLoggingEvent(): void {
		$this->mockEventServiceClient->expects( $this->once() )
			->method( 'submit' )
			->with( 'eventlogging_Migrated', $this->callback( function ( $event ) {
				return (
					$event['$schema'] === '/test/event/1.0.0' &&
					preg_match( $this->timestamp->regexes['TS_ISO_8601'], $event['client_dt'] ) &&
					// $eventData['event'] should equal $legacyEvent
					$event['event']['field_a'] === $this->legacyEvent['field_a']
				);
			} ) );
		$this->mockHttpRequestFactory->expects( $this->never() )->method( 'post' );

		EventLogging::logEvent( 'Migrated', 1337,  $this->legacyEvent );
	}

	public function testSendNonMigratedEventLoggingEvent(): void {
		$this->mockEventServiceClient->expects( $this->never() )->method( 'submit' );
		$this->mockHttpRequestFactory->expects( $this->once() )
			->method( 'post' )
			->with( $this->callback( function ( $url ) {
				return (
					is_string( $url ) &&
					str_starts_with( $url, 'https://test.wikipedia.org/beacon/event?' ) &&
					// Test that the $legacyEvent is in the encoded event.
					// "field_a": "hi" url encodes to this.
					str_contains( $url, "%22field_a%22%3A%22hi%22" )
				);
			} ) );

		EventLogging::logEvent( 'NotMigrated', 1337,  $this->legacyEvent );
	}

	public function testEncapulateEventServiceEvent(): void {
		$result = TestingAccessWrapper::newFromClass( EventLogging::class )->encapsulate(
			'Migrated',
			'/test/event/1.0.0',
			$this->legacyEvent
		);

		$this->assertSame( $result['$schema'], '/test/event/1.0.0' );
		$this->assertRegExp( $this->timestamp->regexes['TS_ISO_8601'], $result['client_dt'] );
		$this->assertSame( $this->testHttpHost, $result['webHost'] );
		$this->assertSame( $result['event']['field_a'],  $this->legacyEvent['field_a'] );
	}

	public function testGetLegacyStreamName(): void {
		$result = TestingAccessWrapper::newFromClass( EventLogging::class )
			->getLegacyStreamName( 'Foo' );
		$this->assertSame( 'eventlogging_Foo', $result );
	}

}
