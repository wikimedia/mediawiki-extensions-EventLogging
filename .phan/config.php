<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'][] = 'EventLogging.namespaces.php';

// TODO Remove on new version of phan-config
$cfg['directory_list'][] = '.phan/stubs/';
$cfg['exclude_analysis_directory_list'][] = '.phan/stubs/';

// Phan now includes dev dependencies - hopefully will be removed
$cfg['suppress_issue_types'][] = 'PhanRedefinedInheritedInterface';

// T191666
$cfg['suppress_issue_types'][] = 'PhanParamTooMany';

return $cfg;
