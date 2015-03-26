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
