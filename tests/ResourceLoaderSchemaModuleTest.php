<?php
/**
 * PHP Unit tests for ResourceLoaderSchemaModule class.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * @group EventLogging
 * @covers ResourceLoaderSchemaModule
 */
class ResourceLoaderSchemaModuleMemcachedTest extends MediaWikiTestCase {

	const TITLE = 'TestSchema';
	const REV = 99;
	const ERROR_VERSION = 1;

	/** @var ResourceLoaderContext */
	private $context;
	/** @var ResourceLoaderSchemaModule */
	private $module;

	function setUp() {
		parent::setUp();

		$this->context = new ResourceLoaderContext(
			new ResourceLoader(), new WebRequest() );

		$this->module = self::getMockSchemaModule( self::TITLE, self::REV );
	}

	function getMockSchemaModule( $title, $revid ) {
		$schema = $this
			->getMockBuilder( 'RemoteSchema' )
			->setConstructorArgs( array( $title, $revid ) )
			->getMock();

		$module = new ResourceLoaderSchemaModule( array(
			'schema'   => $title,
			'revision' => $revid
		) );

		// Inject mock
		$module->schema = $schema;
		return $module;
	}


	/**
	 * When the RemoteSchema dependency cannot be loaded, the modified
	 * time should be set to 1.
	 * @covers ResourceLoaderSchemaModule::getModifiedTime
	 */
	function testFetchFailureModifiedTime() {
		$this->module->schema
			->expects( $this->once() )
			->method( 'get' )
			->will( $this->returnValue( false ) );

		$mtime = $this->module->getModifiedTime( $this->context );
		$this->assertEquals( self::ERROR_VERSION, $mtime );
	}


	/**
	 * When the RemoteSchema dependency can be loaded, the modified time
	 * should be set to sum of $wgCacheEpoch (in UNIX time) and the revision number.
	 * @covers ResourceLoaderSchemaModule::getModifiedTime
	 */
	function testFetchOkModifiedTime() {
		global $wgCacheEpoch;

		$unixTimeCacheEpoch = wfTimestamp( TS_UNIX, $wgCacheEpoch );

		$schema = array( 'status' => array( 'type' => 'string' ) );

		$this->module->schema
			->expects( $this->once() )
			->method( 'get' )
			->will( $this->returnValue( $schema ) );

		$mtime = $this->module->getModifiedTime( $this->context );

		// Should be true regardless of epoch
		$this->assertGreaterThan(
			self::ERROR_VERSION,
			$mtime,
			'1 signifies an error, and <1 should not be possible'
		);
		$this->assertGreaterThan(
			$unixTimeCacheEpoch,
			$mtime,
			'Should be greater than cache epoch, so epoch does not mask updates'
		);
	}

	/*
	   Gets the effective modification time, as calculated by
	   ResourceLoaderStartupModule.  It calculates this as the maximum of
	   the module's self-reported modification time and $wgCacheEpoch.

	   This is not broken into a method in core, so we reproduce it here.
	*/
	function getEffectiveModifiedTime( $module) {
		global $wgCacheEpoch;

		$moduleMtime = wfTimestamp( TS_UNIX, $module->getModifiedTime( $this->context ) );
		$mtime = max( $moduleMtime, wfTimestamp( TS_UNIX, $wgCacheEpoch ) );
		return $mtime;
	}

	/**
	 * When a schema is updated, the effective ModifiedTime as calculated by ResourceLoader
	 * should increase.
	 */
	function testEffectiveModifiedTimeIncreases() {
		$oldSchemaContent = array( 'numberOfGrains' => array( 'type' => 'integer' ) );
		$newSchemaContent = array( 'grainCount' => array( 'type' => 'integer' ) );

		$oldModule = $this->getMockSchemaModule( self::TITLE, 123 );
		$oldModule->schema
			->expects( $this->once() )
			->method( 'get' )
			->will( $this->returnValue( $oldSchemaContent ) );
		$oldEffectiveModifiedTime = $this->getEffectiveModifiedTime( $oldModule );

		$newModule = $this->getMockSchemaModule( self::TITLE, 124 );
		$newModule->schema
			->expects( $this->once() )
			->method( 'get' )
			->will( $this->returnValue( $newSchemaContent ) );
		$newEffectiveModifiedTime = $this->getEffectiveModifiedTime( $newModule );

		$this->assertGreaterThan(
			$oldEffectiveModifiedTime,
			$newEffectiveModifiedTime,
			'A schema with a newer revid should have a greater effective modified time.'
		);
	}

	/**
	 * getTargets() should return an array including both 'desktop' and
	 * 'mobile'. This is essentially verifying that the base class
	 * implementation correctly delegates to the 'targets' property on
	 * ResourceLoaderSchemaModule.
	 */
	function testGetTargets() {
		$targets = $this->module->getTargets();
		$this->assertContains( 'mobile', $targets );
		$this->assertContains( 'desktop', $targets );
	}
}
