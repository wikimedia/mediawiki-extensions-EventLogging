<?php
/**
 * EventLogging Extension for MediaWiki
 *
 * @file
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 * @license GPL v2 or later
 * @version 0.2
 */

$dir = dirname( __FILE__ );


// --------
// Credits
//

$wgExtensionCredits[ 'other' ][] = array(
	'path'           => __FILE__,
	'name'           => 'EventLogging',
	'version'        => '0.1',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:EventLogging',
	'author'         => 'Ori Livneh',
	'descriptionmsg' => 'eventlogging-desc'
);

$wgAutoloadClasses[ 'EventLoggingHooks' ] = $dir . '/EventLogging.hooks.php';
$wgExtensionMessagesFiles[ 'EventLogging' ] = $dir . '/EventLogging.i18n.php';



// ---------
// Modules
//

$wgResourceModules[ 'ext.EventLogging' ] = array(
	'scripts'       => array( 'modules/dataModels.js', 'modules/ext.EventLogging.js' ),
	'localBasePath' => $dir,
	'remoteExtPath' => 'EventLogging',
	'dependencies'  => array( 'mediawiki.util' ),
);



// -------
// Hooks
//

$wgHooks[ 'BeforePageDisplay' ][] = 'EventLoggingHooks::onBeforePageDisplay';
$wgHooks[ 'MakeGlobalVariablesScript' ][] = 'EventLoggingHooks::onMakeGlobalVariablesScript';
