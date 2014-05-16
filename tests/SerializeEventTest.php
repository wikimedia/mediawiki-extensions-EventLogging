<?php
/**
 * PHP Unit tests for serializeEvent function
 *
 * @file
 * @ingroup Extensions
 *
 * @author Nuria Ruiz <nuria@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers JsonSchema
 */
class SerializeEventTest extends MediaWikiTestCase {

	/**
	*
	* Empty event should be returned as an object.
	**/
	function testSerializeEventEmptyEvent() {
		$encapsulatedEvent = array(
			'event'            => array(),
			'other'            => 'some',
		);
		$expectedJson = "{\"event\":{},\"other\":\"some\"}";
		$json = EventLogging::serializeEvent( $encapsulatedEvent );
		$this->assertEquals( $expectedJson, $json,
			'Empty event should be returned as an object' );
	}

	/**
	*
	* Event should be returned without modifications
	**/
	function testSerializeEventHappyCase() {
		$event = array();
		$event['prop1'] = 'blah';
		$encapsulatedEvent = array(
			'event'            => $event,
			'other'            => 'some',
		);
		$expectedJson = "{\"event\":{\"prop1\":\"blah\"},\"other\":\"some\"}";
		$json = EventLogging::serializeEvent( $encapsulatedEvent );
		$this->assertEquals( $expectedJson, $json,
			'Event should be a simple json string' );
	}
}
