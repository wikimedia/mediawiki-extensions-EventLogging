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

	var $context;
	var $module;
	var $schema;


	function setUp() {
		parent::setUp();

		$this->schema = $this
			->getMockBuilder( 'RemoteSchema' )
			->setConstructorArgs( array( self::TITLE, self::REV ) )
			->getMock();

		$this->context = new ResourceLoaderContext(
			new ResourceLoader(), new WebRequest() );

		$this->module = new ResourceLoaderSchemaModule( array(
			'schema'   => self::TITLE,
			'revision' => self::REV
		) );

		// Inject mock
		$this->module->schema = $this->schema;
	}


	/**
	 * When the RemoteSchema dependency cannot be loaded, the modified
	 * time should be set to 1.
	 * @covers ResourceLoaderSchemaModule::getModifiedTime
	 */
	function testFetchFailureModifiedTime() {
		$this->schema
			->expects( $this->once() )
			->method( 'get' )
			->will( $this->returnValue( false ) );

		$mtime = $this->module->getModifiedTime( $this->context );
		$this->assertEquals( 1, $mtime );
	}


	/**
	 * When the RemoteSchema dependency can be loaded, the modified time
	 * should be set to the revision number.
	 * @covers ResourceLoaderSchemaModule::getModifiedTime
	 */
	function testFetchOkModifiedTime() {
		$schema = array( 'status' => array( 'type' => 'string' ) );

		$this->schema
			->expects( $this->once() )
			->method( 'get' )
			->will( $this->returnValue( $schema ) );

		$mtime = $this->module->getModifiedTime( $this->context );
		$this->assertEquals( self::REV, $mtime );
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
