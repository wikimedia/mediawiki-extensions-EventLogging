<?php

/**
 * Download and validate the third-party resources configured in /modules/lib/foreign-resources.yaml.
 */

namespace MediaWiki\Extension\EventLogging;

use ForeignResourceManager;
use Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageForeignResources extends Maintenance {
	public function execute() {
		$frm = new ForeignResourceManager(
			__DIR__ . '/../modules/lib/foreign-resources.yaml',
			__DIR__ . '/../modules/lib'
		);
		return $frm->run( 'update', 'all' );
	}
}

$maintClass = ManageForeignResources::class;
require_once RUN_MAINTENANCE_IF_MAIN;
