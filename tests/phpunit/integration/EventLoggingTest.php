<?php

use MediaWiki\Http\HttpRequestFactory;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/** @covers EventLogging */
class EventLoggingTest extends MediaWikiIntegrationTestCase {

	private $mockEventServiceClient;
	private $mockHttpRequestFactory;
	private $timestamp;

	protected function setUp(): void {
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
			'wgEventLoggingBaseUri' => 'https://test.wikipedia.org/beacon/event'
		] );
	}

	public function testSendMigratedEventLoggingEvent(): void {
		$this->mockEventServiceClient->expects( $this->once() )
			->method( 'submit' )
			->with( 'eventlogging_Migrated', $this->callback( function ( $event ) {
				return (
					$event['$schema'] === '/test/event/1.0.0' &&
					preg_match( $this->timestamp->regexes['TS_ISO_8601'], $event['client_dt'] ) &&
					is_string( $event['meta']['domain'] ) &&
					is_string( $event['http']['request_headers']['user-agent'] )
				);
			} ) );
		$this->mockHttpRequestFactory->expects( $this->never() )->method( 'post' );
		EventLogging::logEvent( 'Migrated', 1337, [] );
	}

	public function testSendNonMigratedEventLoggingEvent(): void {
		$this->mockEventServiceClient->expects( $this->never() )->method( 'submit' );
		$this->mockHttpRequestFactory->expects( $this->once() )
			->method( 'post' )
			->with( $this->callback( function ( $url ) {
				return (
					is_string( $url ) &&
					str_starts_with( $url, 'https://test.wikipedia.org/beacon/event?' )
				);
			} ) );
		EventLogging::logEvent( 'NotMigrated', 1337, [] );
	}

	public function testPrepareEventServiceEvent(): void {
		$result = TestingAccessWrapper::newFromClass( EventLogging::class )
			->prepareEventServiceEvent( 'test.wikipedia.org', '/test/event/1.0.0', false, [] );
		$this->assertSame( $result['$schema'], '/test/event/1.0.0' );
		$this->assertRegExp( $this->timestamp->regexes['TS_ISO_8601'], $result['client_dt'] );
		$this->assertSame( 'test.wikipedia.org', $result['meta']['domain'] );
		$this->assertSame( EventLogging::USER_AGENT_OMITTED,
			$result['http']['request_headers']['user-agent'] );
	}

	public function testGetLegacyStreamName(): void {
		$result = TestingAccessWrapper::newFromClass( EventLogging::class )
			->getLegacyStreamName( 'Foo' );
		$this->assertSame( 'eventlogging_Foo', $result );
	}

}
