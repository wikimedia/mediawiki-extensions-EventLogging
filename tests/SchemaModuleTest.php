<?php
/**
 * PHP Unit tests for SchemaModule class.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 */
class SchemaModuleTest extends MediaWikiTestCase {

	const LOCK_KEY = 'eventLogging:303e1b51b0bd99d4f9b674d8271b1eee';
	const MTIME_KEY = 'eventLogging:f3b38737a6af118e2a4213dedbd4854e';
	const TEXT_KEY = 'eventLogging:e1b849f9631ffc1829b2e31402373e3c';

	private $cache;
	private $http;
	private $module;

	protected function setUp() {

		// Mock and inject dependencies.

		$this->context = new ResourceLoaderContext( new ResourceLoader(), new WebRequest() );

		$this->cache = $this
			->getMockBuilder( 'MemcachedPhpBagOStuff' )
			->disableOriginalConstructor()
			->getMock();

		$this->http = $this
			->getMock( 'stdClass', array( 'get' ) );

		$this->module = new SchemaModule( array(
			'schema'   => 'Test',
			'revision' => 1
		), $this->cache, $this->http );
	}

	public function testOptionsDefaultRevision() {
		$module = new SchemaModule( array( 'schema' => 'person' ) );
		$this->assertAttributeEquals( 'HEAD', 'revision', $module );
	}

	public function testOptionsSchemaRequired() {
		$this->setExpectedException( 'MWException' );
		$module = new SchemaModule( array( 'revision' => 100 ) );
	}

	public function testSchemaInCache() {
		$ok = array( 'ok' => true );

		// If the revision was in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( self::TEXT_KEY ) )
			->will( $this->returnValue( $ok ) );

		// ...no update lock will be acquired.
		$this->cache
			->expects( $this->never() )
			->method( 'add' );

		$this->assertEquals( $ok, $this->module->getSchema() );
	}

	public function testSchemaNotInCacheDoUpdate() {

		// If the revision was not in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( self::TEXT_KEY ) )
			->will( $this->returnValue( false ) );

		// ...we'll see two calls to cache->add:
		// 1) An attempt to acquire update lock. We'll allow it.
		// 2) An attempt to store the fetched revision in cache.
		$this->cache
			->expects( $this->exactly( 2 ) )
			->method( 'add' )
			->with( $this->stringContains( 'eventLogging:' ) )
			->will( $this->returnValue( true ) );

		// With the lock acquired, we'll see an HTTP request for the revision.
		$this->http
			->expects( $this->once() )
			->method( 'get' )
			->with(
				$this->stringContains( '?' ),
				$this->lessThan( SchemaModule::LOCK_TIMEOUT ) )
			->will( $this->returnValue( '{"ok":true}' ) );

		$this->assertEquals( array( 'ok' => true ), $this->module->getSchema() );
	}


	public function testSchemaNotInCacheNoUpdate() {

		// If the revision was not in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( self::TEXT_KEY ) )
			->will( $this->returnValue( false ) );

		// ...we'll see an attempt to acquire update lock. Deny it.
		$this->cache
			->expects( $this->once() )
			->method( 'add' )
			->with( $this->equalTo( self::LOCK_KEY ) )
			->will( $this->returnValue( false ) );

		// Without a lock, no HTTP requests.
		$this->http
			->expects( $this->never() )
			->method( 'get' );

		// When unable to retrieve from memcached or acquire an update
		// lock to retrieve via HTTP, getSchema() will return false.
		$this->assertFalse( $this->module->getSchema() );
	}

	public function testModifiedTimePresent() {

		$ts = wfTimestamp( 0, TS_UNIX );

		// If the mTime is in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( self::MTIME_KEY ) )
			->will( $this->returnValue( $ts ) );

		// ...no attempt will be made to set it.
		$this->cache
			->expects( $this->never() )
			->method( 'add' );

		$this->assertEquals( $ts, $this->module->getModifiedTime( $this->context ) );

	}
	public function testModifiedTimeMissing() {

		$ts = wfTimestamp( 0, TS_UNIX ) - 1;

		// If the mTime is not in memcached...
		$this->cache
			->expects( $this->once() )
			->method( 'get' )
			->with( $this->equalTo( self::MTIME_KEY ) )
			->will( $this->returnValue( false ) );

		// ...will set the current time in memcached
		$this->cache
			->expects( $this->once() )
			->method( 'add' )
			->with(
				$this->equalTo( self::MTIME_KEY ),
				$this->greaterThan( $ts ) )
			->will( $this->returnValue( false ) );

		$this->assertGreaterThan( $ts, $this->module->getModifiedTime( $this->context ) );

	}
}