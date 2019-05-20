<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'][] = 'EventLogging.namespaces.php';

// T191666
$cfg['suppress_issue_types'][] = 'PhanParamTooMany';

return $cfg;
