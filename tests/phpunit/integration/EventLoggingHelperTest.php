<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/** @covers EventLoggingHelper */
class EventLoggingHelperTest extends MediaWikiIntegrationTestCase {

	public function testPrepareEvent(): void {
		$preparedEvent = EventLoggingHelper::prepareEvent(
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

	public function testGetEventDefaults(): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$defaults = EventLoggingHelper::getEventDefaults( $config );
		$this->assertSame( $config->get( 'ServerName' ), $defaults['meta']['domain'] );
		$this->assertTrue( isset( $defaults['http']['request_headers']['user-agent'] ) );
	}
}
