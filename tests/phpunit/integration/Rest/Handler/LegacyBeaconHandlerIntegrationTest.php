<?php

namespace MediaWiki\Extension\EventLogging\Test\Rest\Handler;

use GuzzleHttp\Psr7\Uri;
use HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventLogging\EventSubmitter\NullEventSubmitter;
use MediaWiki\Extension\EventLogging\Rest\Handler\LegacyBeaconHandler;
use MediaWiki\MainConfigNames;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WikiMap;

/**
 * @covers \MediaWiki\Extension\EventLogging\Rest\Handler\LegacyBeaconHandler
 */
class LegacyBeaconHandlerIntegrationTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private const MOCK_SERVER_NAME = 'my_wiki';
	private const MOCK_USER_AGENT = 'test_user_agent';

	private MockObject $mockEventSubmitter;
	private HashConfig $config;

	protected function setUp(): void {
		parent::setUp();

		$this->config = new HashConfig( [
			MainConfigNames::ServerName => self::MOCK_SERVER_NAME,
			'EventLoggingLegacyBeaconAllowedWikiIds' => [ WikiMap::getCurrentWikiId() ],
		] );

		$this->mockEventSubmitter = $this->createMock( NullEventSubmitter::class );
	}

	public static function provideTestData() {
		yield 'well formed and allowed qson encoded event' => [
			[
				'recvFrom' => self::MOCK_SERVER_NAME,
				'meta' => [
					'stream' => 'eventlogging_MediaWikiPingback'
				],
				'$schema' => '/analytics/legacy/mediawikipingback/1.0.0',
				'http' => [
					'request_headers' => [
						'user-agent' => self::MOCK_USER_AGENT,
					]
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
			],
			'?%7B%22schema%22%3A%22MediaWikiPingback%22%2C%22revision%22%3A15781718%2C' .
			'%22wiki%22%3A%22dummy%22%2C%22event%22%3A%7B%22database%22%3A%22mysql%22%2C%22MediaWiki%22' .
			'%3A%221.31.1%22%2C%22PHP%22%3A%227.4.33%22%2C%22OS%22%3A%22Linux%5Cu00204.4.400-icpu-097%22%2C' .
			'%22arch%22%3A64%2C%22machine%22%3A%22x86_64%22%2C%22serverSoftware%22%3A%22Apache%22%7D%7D;'
		];
	}

	/**
	 * @dataProvider provideTestData
	 */
	public function testExecute( $expectedEvent, $queryString ) {
		$services = $this->getServiceContainer();

		$handler = new LegacyBeaconHandler(
			new ServiceOptions(
				LegacyBeaconHandler::CONSTRUCTOR_OPTIONS,
				$this->config,
			),
			$this->mockEventSubmitter,
			$services->getGlobalIdGenerator()
		);

		$request = new RequestData( [
			'uri' => new Uri( "http://localhost:8080/w/rest.php/eventlogging/v0/beacon/$queryString" ),
			'headers' => [ 'User-Agent' => self::MOCK_USER_AGENT ],
			'serverParams' => [ 'REMOTE_HOST' => self::MOCK_SERVER_NAME ],
		] );

		$this->mockEventSubmitter
			->expects( $this->once() )
			->method( 'submit' )
			->willReturnCallback( function ( $streamName, $event ) use ( $expectedEvent ): bool {
				$this->assertEquals( $expectedEvent['meta']['stream'], $streamName );

				// Assert only that non-deterministic values are set, and then unset them.
				$this->assertIsString( $event['uuid'] );
				$this->assertIsString( $event['client_dt'] );
				unset( $event['uuid'] );
				unset( $event['client_dt'] );

				$this->assertEquals( $expectedEvent, $event );

				return true;
			} );

		$this->executeHandler( $handler, $request );
	}
}
