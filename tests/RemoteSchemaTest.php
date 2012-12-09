<?php
/**
 * PHP Unit tests for RemoteSchema class.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers RemoteSchema
 */
class RemoteSchemaTest extends MediaWikiTestCase {

	var $cache;
	var $http;
	var $schema;

	var $statusSchema = array( 'status' => array( 'type' => 'string' ) );

	function setUp() {
		$this->cache = $this
			->getMockBuilder( 'MemcachedPhpBagOStuff' )
			->disableOriginalConstructor()
			->getMock();

		$this->http = $this->getMock( 'stdClass', array( 'get' ) );
		$this->schema = new RemoteSchema( 'Test', 99, $this->cache, $this->http );
	}

	function testSchemaInCache() {
		// If the revision was in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( 'schema:Test:99' ) )
			->will( $this->returnValue( $this->statusSchema ) );

		// ...no HTTP call will need to be made
		$this->http
			->expects( $this->never() )
			->method( 'get' );

		// ...so no lock will be acquired
		$this->cache
			->expects( $this->never() )
			->method( 'add' );

		$this->assertEquals( $this->statusSchema, $this->schema->get() );

		// Calling get() a second time won't trigger a fetch. If it did,
		// $this->cache->expects( $this->once() ) above would produce a test
		// failure.
		$this->assertEquals( $this->statusSchema, $this->schema->get() );
	}

	function testSchemaNotInCacheDoUpdate() {
		// If the revision was not in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( 'schema:Test:99' ) )
			->will( $this->returnValue( false ) );

		// ...RemoteSchema will attempt to acquire an update lock:
		$this->cache
			->expects( $this->any() )
			->method( 'add' )
			->with( $this->stringContains( 'schema:Test:99' ) )
			->will( $this->returnValue( true ) );

		// With the lock acquired, we'll see an HTTP request
		// for the revision:
		$this->http
			->expects( $this->once() )
			->method( 'get' )
			->with(
				$this->stringContains( '?' ),
				$this->lessThan( RemoteSchema::LOCK_TIMEOUT ) )
			->will( $this->returnValue( FormatJson::encode( $this->statusSchema ) ) );

		$this->assertEquals( $this->statusSchema, $this->schema->get() );
	}


	function testSchemaNotInCacheNoUpdate() {
		// If the revision was not in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( 'schema:Test:99' ) )
			->will( $this->returnValue( false ) );

		// ...we'll see an attempt to acquire update lock,
		// which we'll deny:
		$this->cache
			->expects( $this->once() )
			->method( 'add' )
			->with( 'schema:Test:99:lock' )
			->will( $this->returnValue( false ) );

		// Without a lock, no HTTP requests will be made:
		$this->http
			->expects( $this->never() )
			->method( 'get' );

		// When unable to retrieve from memcached or acquire an update
		// lock to retrieve via HTTP, getSchema() will return false.
		$this->assertFalse( $this->schema->get() );
	}

}
