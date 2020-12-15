<?php

use MediaWiki\Http\HttpRequestFactory;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;

/** @covers EventServiceClient */
class EventServiceClientIntegrationTest extends MediaWikiIntegrationTestCase {

	use MockHttpTrait;

	/** @var EventServiceClient */
	private $client;

	/** @var MockObject */
	private $mockHttpRequestFactory;

	/** @var MockObject */
	private $mockLogger;

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgEventStreams' => [ [ 'stream' => 'test.event', 'schema_title' => 'test/event' ] ],
			'wgEventLoggingStreamNames' => [ 'test.event' ],
			'wgEventLoggingServiceUri' => 'http://foo.wikimedia.org/v1/events',
		] );

		$this->mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$client = EventLoggingServices::getInstance()->getEventServiceClient();
		$this->client = TestingAccessWrapper::newFromObject( $client );
		$this->client->httpRequestFactory = $this->mockHttpRequestFactory;

		$this->mockLogger = $this->createMock( Logger::class );
		$this->client->setLogger( $this->mockLogger );
	}

	public function testSubmitEvent(): void {
		$this->mockLogger->expects( $this->never() )->method( 'warning' );
		$this->mockHttpRequestFactory->expects( $this->once() )->method( 'post' );
		$this->client->submit( 'test.event', [ '$schema' => 'test/event/1.0.0' ] );
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
		$this->client->submit( 'test.event', [ '$schema' => 'test/event/1.0.0' ] );
	}

	public function testFailIfStreamNameNotConfigured(): void {
		$this->mockLogger->expects( $this->once() )->method( 'warning' );
		$this->mockHttpRequestFactory->expects( $this->never() )->method( 'post' );
		$this->client->submit( 'not.configured', [ '$schema' => 'test/event/1.0.0' ] );
	}

	public function testDisableStreamConfig(): void {
		$this->setMwGlobals( [ 'wgEventLoggingStreamNames' => false ] );

		$this->mockHttpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$client = EventLoggingServices::getInstance()->getEventServiceClient();
		$this->client = TestingAccessWrapper::newFromObject( $client );
		$this->client->httpRequestFactory = $this->mockHttpRequestFactory;

		$this->mockLogger->expects( $this->never() )->method( 'warning' );
		$this->mockHttpRequestFactory->expects( $this->once() )->method( 'post' );
		$this->client->submit( 'not.configured', [ '$schema' => 'test/event/1.0.0' ] );
	}

}
