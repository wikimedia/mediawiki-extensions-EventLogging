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

	var $context;
	var $module;
	var $schema;


	function setUp() {
		$this->schema = $this
			->getMockBuilder( 'RemoteSchema' )
			->setConstructorArgs( array( 'TestSchema', 99 ) )
			->getMock();

		$this->context = new ResourceLoaderContext(
			new ResourceLoader(), new WebRequest() );

		$this->module = new ResourceLoaderSchemaModule( array(
			'schema'   => 'TestSchema',
			'revision' => 99
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
		$this->assertGreaterThan( 1, $mtime );
	}
}
