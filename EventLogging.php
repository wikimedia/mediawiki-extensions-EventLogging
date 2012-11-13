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


// Credits

$wgExtensionCredits[ 'other' ][] = array(
	'path' => __FILE__,
	'name' => 'EventLogging',
	'author' => array(
		'Ori Livneh',
	),
	'version' => '0.1',
	'url' => 'https://www.mediawiki.org/wiki/Extension:EventLogging',
	'descriptionmsg' => 'eventlogging-desc'
);


$dir = __DIR__;

$wgAutoloadClasses[ 'EventLoggingHooks' ] = $dir . '/EventLogging.hooks.php';
$wgExtensionMessagesFiles[ 'EventLogging' ] = $dir . '/EventLogging.i18n.php';

if ( !isset( $wgEventLoggingBaseUri ) ) {
	$wgEventLoggingBaseUri = false;
}


// Modules

$wgResourceModules[ 'ext.EventLogging' ] = array(
	'scripts'       => array(
		'modules/ext.EventLogging.js',
		'modules/ext.EventLogging.dataModels.js',
	),
	'localBasePath' => $dir,
	'remoteExtPath' => 'EventLogging',
	'dependencies'  => array(
		'mediawiki.util',
		'jquery.json'
	),
);


// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
