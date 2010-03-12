#!/usr/bin/php5 -q
<?php

// Init scripts, library locations, and binaries
$locum_lib_dir = '/usr/local/lib/locum';
$mysql_init_script = '/etc/init.d/mysql';
$sphinx_init_script = '/etc/init.d/sphinx';
$sphinx_indexer = '/usr/local/sphinx/bin/indexer';

// Include Locum libraries
require_once($locum_lib_dir . '/locum-server.php');

$locum = new locum_server;
$locum->rebuild_holds_cache();

// Restart services, reindex, etc.
$locum->index();