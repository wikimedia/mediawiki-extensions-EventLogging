<?php

use MediaWiki\Http\HttpRequestFactory;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/** @covers EventServiceClient */
class EventServiceClientTest extends MediaWikiIntegrationTestCase {

	use MockHttpTrait;

	/** @var EventServiceClient */
	private $client;

	/** @var MockObject */
	private $mockHttpRequestFactory;

	/** @var MockObject */
	private $mockLogger;

	private const TEST_HTTP_HOST = 'test.eventlogging.domain';

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgEventStreams' => [ [ 'stream' => 'test.event', 'schema_title' => 'test/event' ] ],
			'wgEventLoggingStreamNames' => [ 'test.event' ],
			'wgEventLoggingServiceUri' => 'http://foo.wikimedia.org/v1/events',
			'wgServerName' => self::TEST_HTTP_HOST,
		] );

		$this->mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$client = EventLoggingServices::getInstance()->getEventServiceClient();
		$this->client = TestingAccessWrapper::newFromObject( $client );
		$this->client->httpRequestFactory = $this->mockHttpRequestFactory;

		$this->mockLogger = $this->createMock( Logger::class );
		$this->client->setLogger( $this->mockLogger );
	}

	public function testPrepareEvent(): void {
		$class = TestingAccessWrapper::newFromClass( EventServiceClient::class );

		$preparedEvent = $class->prepareEvent(
			'test.event',
			[
				'$schema' => '/test/event/1.0.0',
				'field_a' => 'A'
			],
			[ 'extra_default' => 'B' ]
		);

		$this->assertArrayHasKey( 'meta', $preparedEvent );
		$this->assertSame( 'test.event', $preparedEvent['meta']['stream'] );
		$ts = TestingAccessWrapper::newFromClass( ConvertibleTimestamp::class );
		$this->assertRegExp( $ts->regexes['TS_ISO_8601'], $preparedEvent['dt'] );
		$this->assertSame( 'B', $preparedEvent['extra_default'] );
	}

	public function testSubmitEvent(): void {
		$this->mockLogger->expects( $this->never() )->method( 'warning' );

		$event = [
			'$schema' => '/test/event/1.0.0',
			// By setting this explicitly in the event,
			// EventServiceClient will not set it to current timestamp.
			'dt' => '2021-01-19T00:00:00Z',
			'field_a' => 'A'
		];

		$this->mockHttpRequestFactory->expects( $this->once() )
			->method( 'post' )
			->with(
				$this->equalTo( 'http://foo.wikimedia.org/v1/events' ),
				$this->callback( function ( $options ) use ( $event ) {
					$postedEvent = $options['postData'];
					$this->assertTrue( isset( $postedEvent['dt'] ) );

					// The eventDefaults used (if not provided to EventServiceClient)
					// should set all of the following.
					$expectedEvent = [
						'meta' => [
							'domain' => self::TEST_HTTP_HOST,
							'stream' => 'test.event',
						],
						'http' => [
							'request_headers' => [
								'user-agent' => ''
							]
						],
						'$schema' => $event['$schema'],
						'dt' => $event['dt'],
						'field_a' => $event['field_a'],
					];

					$this->assertSame( $expectedEvent, $postedEvent );

					// If we get here, the assertions all succeeded!
					return true;
				}
			)
		);
		$this->client->submit( 'test.event', $event );
	}

	public function testFailIfSchemaNotSpecified(): void {
		$this->mockLogger->expects( $this->once() )->method( 'warning' );
		$this->mockHttpRequestFactory->expects( $this->never() )->method( 'post' );
		$this->client->submit( 'test.event', [] );
	}

	public function testFailIfServiceUriNotConfigured(): void {
		$this->client->eventLoggingServiceUri = null;
		$this->mockLogger->expects( $this->once() )->method( 'warning' );
		$this->mockHttpRequestFactory->expects( $this->never() )->method( 'post' );
		$this->client->submit( 'test.event', [ '$schema' => '/test/event/1.0.0' ] );
	}

	public function testFailIfStreamNameNotConfigured(): void {
		$this->mockLogger->expects( $this->once() )->method( 'warning' );
		$this->mockHttpRequestFactory->expects( $this->never() )->method( 'post' );
		$this->client->submit( 'not.configured', [ '$schema' => '/test/event/1.0.0' ] );
	}

	public function testDisableStreamConfig(): void {
		$this->setMwGlobals( [ 'wgEventLoggingStreamNames' => false ] );

		$this->mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$client = EventLoggingServices::getInstance()->getEventServiceClient();
		$this->client = TestingAccessWrapper::newFromObject( $client );
		$this->client->httpRequestFactory = $this->mockHttpRequestFactory;

		$this->mockLogger->expects( $this->never() )->method( 'warning' );
		$this->mockHttpRequestFactory->expects( $this->once() )->method( 'post' );
		$this->client->submit( 'not.configured', [ '$schema' => '/test/event/1.0.0' ] );
	}

}
