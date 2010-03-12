#!/usr/bin/php5 -q
<?php

// Init scripts, library locations, and binaries
$locum_lib_dir = '/usr/local/lib/locum';
$insurge_lib_dir = '/usr/local/lib/insurge';
$sphinx_indexer = '/usr/local/sphinx/bin/indexer';

// Include the libraries
require_once($locum_lib_dir . '/locum-server.php');
require_once($insurge_lib_dir . '/insurge-server.php');

$locum = new locum_server;
echo "Rebuilding holds cache... ";
$locum->rebuild_holds_cache();
echo "Done.\n";
$insurge = new insurge_server;
echo "Rebuilding Insurge index table... ";
$insurge->rebuild_index_table();
echo "Done.\n";
echo "Rebuilding Facet Heap... ";
$locum->rebuild_facet_heap();
echo "Done.\n";
echo "Rebuilding the Sphinx index... ";
shell_exec($sphinx_indexer . '  --all --rotate');
echo "Done.\n";
echo "Finished with maintenance tasks.\n";