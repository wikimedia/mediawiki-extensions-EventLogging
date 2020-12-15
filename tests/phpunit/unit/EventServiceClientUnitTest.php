<?php

use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/** @covers EventServiceClient */
class EventServiceClientUnitTest extends MediaWikiUnitTestCase {

	public function testAddEventMetadata(): void {
		$client = TestingAccessWrapper::newFromClass( EventServiceClient::class );
		$eventData = $client->addEventMetadata( 'test.event', [] );
		$this->assertArrayHasKey( 'meta', $eventData );
		$this->assertSame( 'test.event', $eventData['meta']['stream'] );
		$ts = TestingAccessWrapper::newFromClass( ConvertibleTimestamp::class );
		$this->assertRegExp( $ts->regexes['TS_ISO_8601'], $eventData['dt'] );
	}

}
