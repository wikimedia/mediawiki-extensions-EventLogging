<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../SyntaxHighlight_GeSHi',
	]
);
$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../SyntaxHighlight_GeSHi',
	]
);

$cfg['file_list'][] = 'EventLogging.namespaces.php';

return $cfg;
