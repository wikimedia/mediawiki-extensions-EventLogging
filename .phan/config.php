<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'][] = 'EventLogging.namespaces.php';

// T223397 - Phan now includes dev dependencies
$cfg['suppress_issue_types'][] = 'PhanRedefinedInheritedInterface';

// T191666
$cfg['suppress_issue_types'][] = 'PhanParamTooMany';

return $cfg;
