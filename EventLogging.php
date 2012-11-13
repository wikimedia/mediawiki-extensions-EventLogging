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


// Files

$dir = __DIR__;

$wgAutoloadClasses[ 'EventLoggingHooks' ] = $dir . '/EventLogging.hooks.php';
$wgAutoloadClasses[ 'ResourceLoaderEventDataModels' ] = $dir . '/EventLogging.module.php';
$wgExtensionMessagesFiles[ 'EventLogging' ] = $dir . '/EventLogging.i18n.php';


// Configuration

/**
 * @var bool|string: Full URI or boolean false if not set.
 * Events are logged to this end point as key-value pairs in the
 * query string. Base must not contain any query string (no ? or &)
 * as key-value pairs can be anything.
 * @example string: '//log.example.org/event.gif'
 */
$wgEventLoggingBaseUri = false;

/**
 * @var bool|string: Full URI or boolean false if not set.
 * Canonical location of JSON data models. Will be fetched when the
 * models are not in memcached.
 */
$wgEventLoggingDataModelsUri = false;


// Modules

$wgResourceModules[ 'ext.EventLogging.dataModels' ] = array(
	'class' => 'ResourceLoaderEventDataModels',
);

$wgResourceModules[ 'ext.EventLogging' ] = array(
	'scripts'       => array(
		'modules/ext.EventLogging.js',
	),
	'localBasePath' => $dir,
	'remoteExtPath' => 'EventLogging',
	'dependencies'  => array(
		'ext.EventLogging.dataModels',
		'jquery.json',
		'mediawiki.util',
	),
);


// Hooks

$wgExtensionFunctions[] = 'EventLoggingHooks::onSetup';

$wgHooks[ 'APIEditBeforeSave'][] = 'EventLoggingHooks::onAPIEditBeforeSave';
$wgHooks[ 'ArticleSaveComplete' ][] = 'EventLoggingHooks::onArticleSaveComplete';
$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'EventLoggingHooks::onResourceLoaderGetConfigVars';
$wgHooks[ 'ResourceLoaderTestModules' ][] = 'EventLoggingHooks::onResourceLoaderTestModules';
